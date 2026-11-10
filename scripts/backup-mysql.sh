#!/bin/bash

# ==============================================================================
# SiGeRU - Gestión de respaldos de Base de Datos
# Contenedor: sigeru_db (MySQL 8)
# Esquema: Completo (Mensual) + Diferencial (Semanal) + Incremental (Diario)
# Los respaldos se guardan en el volumen Docker: sigeru_backups -> /backups
# ==============================================================================

set -o pipefail

# Configuración del contenedor
CONTENEDOR_DB="sigeru_db"
NOMBRE_BD="sigeru"
DB_USER="sigeru_user"
DB_PASS="sigeru_pass"

# Rutas de control en el HOST (para los marcadores de binlog)
DIR_CONTROL="/var/lib/sigeru-backup"
MARKER_BASE="$DIR_CONTROL/mysql_base_coords.txt"
MARKER_LAST="$DIR_CONTROL/mysql_last_coords.txt"
ARCHIVO_LOG="/var/log/sigeru-backup-mysql.log"

# Directorio temporal en el host para armar el tar
RUTA_TEMP="/tmp/sigeru-backup-mysql"
FECHA=$(date +"%Y-%m-%d_%H-%M-%S")


RegistrarLog() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$ARCHIVO_LOG"
}


ValidarPermisos() {
    if [ "$EUID" -ne 0 ]; then
        echo "ERROR: Este script debe ejecutarse con privilegios de root." >&2
        exit 1
    fi
}


PrepararDirectorios() {
    mkdir -p "$RUTA_TEMP"
    mkdir -p "$DIR_CONTROL"
    mkdir -p "$(dirname "$ARCHIVO_LOG")"
}


VerificarContenedor() {
    if ! docker inspect --format '{{.State.Running}}' "$CONTENEDOR_DB" 2>/dev/null | grep -q "true"; then
        RegistrarLog "ERROR: El contenedor '$CONTENEDOR_DB' no está corriendo. Abortando."
        exit 1
    fi
}


# Ejecutar mysqladmin o mysql dentro del contenedor
EjecutarMySQL() {
    docker exec "$CONTENEDOR_DB" bash -c "$1"
}


ObtenerCoordenadasBinlog() {
    local status
    status=$(EjecutarMySQL "mysql -u$DB_USER -p$DB_PASS -N -e 'SHOW BINARY LOG STATUS;' 2>/dev/null \
             || mysql -u$DB_USER -p$DB_PASS -N -e 'SHOW MASTER STATUS;' 2>/dev/null")
    local arch pos
    arch=$(echo "$status" | awk '{print $1}')
    pos=$(echo "$status"  | awk '{print $2}')
    echo "$arch $pos"
}


GuardarEnVolumen() {
    local archivo_host="$1"
    local tipo="$2"
    local nombre_archivo
    nombre_archivo=$(basename "$archivo_host")

    RegistrarLog "Copiando respaldo al volumen Docker (sigeru_backups/$tipo/)..."

    # Copiar el archivo dentro del volumen a través del contenedor backup
    docker run --rm \
        -v sigeru_backups:/backups \
        -v "$archivo_host":/tmp/entrada/"$nombre_archivo":ro \
        alpine sh -c "mkdir -p /backups/mysql/$tipo && cp /tmp/entrada/$nombre_archivo /backups/mysql/$tipo/"

    if [ $? -eq 0 ]; then
        RegistrarLog "Respaldo guardado en volumen: /backups/mysql/$tipo/$nombre_archivo"
        rm -f "$archivo_host"
        EjecutarRotacion "$tipo"
    else
        RegistrarLog "ERROR: Falló al copiar el respaldo al volumen Docker."
        return 1
    fi
}


EjecutarRotacion() {
    local tipo="$1"
    local dias_retencion

    case "$tipo" in
        diarios)   dias_retencion=7 ;;
        semanales) dias_retencion=28 ;;
        mensuales) dias_retencion=365 ;;
        *) return ;;
    esac

    RegistrarLog "Aplicando retención en volumen ($tipo: $dias_retencion días)..."

    docker run --rm \
        -v sigeru_backups:/backups \
        alpine sh -c "find /backups/mysql/$tipo/ -name 'mysql-*' -type f -mtime +$dias_retencion -delete"

    if [ $? -eq 0 ]; then
        RegistrarLog "Rotación de respaldos MySQL $tipo completada."
    else
        RegistrarLog "ADVERTENCIA: No se pudo ejecutar la rotación."
    fi
}


# ------------------------------------------------------------------------------
# 1. Respaldo Completo Mensual
# ------------------------------------------------------------------------------
BackupCompleto() {
    local sql_file="$RUTA_TEMP/mysql-$NOMBRE_BD-completo-$FECHA.sql"
    local archivo="$RUTA_TEMP/mysql-$NOMBRE_BD-completo-$FECHA.tar.gz"
    RegistrarLog "Iniciando RESPALDO COMPLETO MENSUAL de MySQL ($NOMBRE_BD)..."

    docker exec "$CONTENEDOR_DB" \
        mysqldump \
            -u"$DB_USER" -p"$DB_PASS" \
            --single-transaction \
            --quick \
            --routines \
            --triggers \
            "$NOMBRE_BD" > "$sql_file" 2>>"$ARCHIVO_LOG"

    local dump_status=$?
    if [ $dump_status -eq 0 ] && [ -s "$sql_file" ]; then
        EjecutarMySQL "mysqladmin -u$DB_USER -p$DB_PASS flush-logs 2>/dev/null"
        local coords
        coords=$(ObtenerCoordenadasBinlog)
        echo "$coords" > "$MARKER_BASE"
        echo "$coords" > "$MARKER_LAST"

        tar -czf "$archivo" -C "$RUTA_TEMP" "$(basename "$sql_file")"
        rm -f "$sql_file"

        RegistrarLog "Respaldo completo generado ($archivo). Coordenadas: $coords"
        GuardarEnVolumen "$archivo" "mensuales"
    else
        RegistrarLog "ERROR: Falló mysqldump completo (código: $dump_status)."
        rm -f "$sql_file"
    fi
}


# ------------------------------------------------------------------------------
# 2. Respaldo Diferencial Semanal (binlogs desde el Completo Mensual)
# ------------------------------------------------------------------------------
BackupDiferencial() {
    RegistrarLog "Iniciando RESPALDO DIFERENCIAL SEMANAL de MySQL ($NOMBRE_BD)..."

    if [ ! -f "$MARKER_BASE" ]; then
        RegistrarLog "ADVERTENCIA: No existe punto base mensual. Ejecutando completo primero."
        BackupCompleto
        return
    fi

    local coords_inicio
    coords_inicio=$(cat "$MARKER_BASE")
    local binlog_inicio
    binlog_inicio=$(echo "$coords_inicio" | awk '{print $1}')

    EjecutarMySQL "mysqladmin -u$DB_USER -p$DB_PASS flush-logs 2>/dev/null"
    local coords_actuales
    coords_actuales=$(ObtenerCoordenadasBinlog)
    local binlog_nuevo
    binlog_nuevo=$(echo "$coords_actuales" | awk '{print $1}')

    # Extraer binlogs del contenedor al host
    local binlogs_dir="$RUTA_TEMP/binlogs_dif_$FECHA"
    mkdir -p "$binlogs_dir"

    docker exec "$CONTENEDOR_DB" bash -c \
        "cat /var/lib/mysql/binlog.index 2>/dev/null || ls /var/lib/mysql/mysql-bin.index 2>/dev/null || echo ''" \
        | while IFS= read -r log_line; do
            log_name=$(basename "$log_line")
            if [ "$log_name" == "$binlog_nuevo" ]; then break; fi
            if [ "$log_name" == "$binlog_inicio" ]; then
                docker cp "$CONTENEDOR_DB:/var/lib/mysql/$log_name" "$binlogs_dir/" 2>/dev/null
            fi
        done

    local archivo="$RUTA_TEMP/mysql-$NOMBRE_BD-diferencial-$FECHA.tar.gz"
    local meta_file="$binlogs_dir/metadata.txt"
    echo "TIPO=DIFERENCIAL"       > "$meta_file"
    echo "BASE_DESDE=$coords_inicio" >> "$meta_file"
    echo "HASTA=$coords_actuales" >> "$meta_file"

    tar -czf "$archivo" -C "$binlogs_dir" . 2>>"$ARCHIVO_LOG"
    local tar_status=$?
    rm -rf "$binlogs_dir"

    if [ $tar_status -eq 0 ]; then
        echo "$coords_actuales" > "$MARKER_LAST"
        RegistrarLog "Respaldo diferencial generado ($archivo)."
        GuardarEnVolumen "$archivo" "semanales"
    else
        RegistrarLog "ERROR al empaquetar diferencial."
        rm -f "$archivo"
    fi
}


# ------------------------------------------------------------------------------
# 3. Respaldo Incremental Diario (binlogs desde el último marcador)
# ------------------------------------------------------------------------------
BackupIncremental() {
    RegistrarLog "Iniciando RESPALDO INCREMENTAL DIARIO de MySQL ($NOMBRE_BD)..."

    if [ ! -f "$MARKER_LAST" ]; then
        RegistrarLog "ADVERTENCIA: No existe marcador previo. Ejecutando completo base."
        BackupCompleto
        return
    fi

    local coords_inicio
    coords_inicio=$(cat "$MARKER_LAST")
    local binlog_inicio
    binlog_inicio=$(echo "$coords_inicio" | awk '{print $1}')

    EjecutarMySQL "mysqladmin -u$DB_USER -p$DB_PASS flush-logs 2>/dev/null"
    local coords_actuales
    coords_actuales=$(ObtenerCoordenadasBinlog)
    local binlog_nuevo
    binlog_nuevo=$(echo "$coords_actuales" | awk '{print $1}')

    local binlogs_dir="$RUTA_TEMP/binlogs_inc_$FECHA"
    mkdir -p "$binlogs_dir"

    docker exec "$CONTENEDOR_DB" bash -c \
        "cat /var/lib/mysql/binlog.index 2>/dev/null || echo ''" \
        | while IFS= read -r log_line; do
            log_name=$(basename "$log_line")
            if [ "$log_name" == "$binlog_nuevo" ]; then break; fi
            if [ "$log_name" == "$binlog_inicio" ]; then
                docker cp "$CONTENEDOR_DB:/var/lib/mysql/$log_name" "$binlogs_dir/" 2>/dev/null
            fi
        done

    local archivo="$RUTA_TEMP/mysql-$NOMBRE_BD-incremental-$FECHA.tar.gz"
    local meta_file="$binlogs_dir/metadata.txt"
    echo "TIPO=INCREMENTAL"      > "$meta_file"
    echo "DESDE=$coords_inicio"  >> "$meta_file"
    echo "HASTA=$coords_actuales" >> "$meta_file"

    tar -czf "$archivo" -C "$binlogs_dir" . 2>>"$ARCHIVO_LOG"
    local tar_status=$?
    rm -rf "$binlogs_dir"

    if [ $tar_status -eq 0 ]; then
        echo "$coords_actuales" > "$MARKER_LAST"
        RegistrarLog "Respaldo incremental generado ($archivo)."
        GuardarEnVolumen "$archivo" "diarios"
    else
        RegistrarLog "ERROR al empaquetar incremental."
        rm -f "$archivo"
    fi
}


# ==============================================================================
# Control de Flujo Principal
# ==============================================================================

ValidarPermisos
PrepararDirectorios
VerificarContenedor

case "$1" in
    incremental|diario)
        BackupIncremental
        ;;
    diferencial|semanal)
        BackupDiferencial
        ;;
    completo|mensual)
        BackupCompleto
        ;;
    *)
        echo "======================================================="
        echo "    SiGeRU - Gestión de Respaldos de MySQL (Docker)    "
        echo "======================================================="
        echo "Uso: $0 {incremental|diferencial|completo}"
        echo
        echo "Opciones:"
        echo "  incremental  : Respaldo diario de binlogs  (Retención: 7 días)"
        echo "  diferencial  : Respaldo semanal vs. base mensual (Retención: 4 semanas)"
        echo "  completo     : Respaldo mensual mysqldump  (Retención: 12 meses)"
        echo
        echo "Los respaldos se almacenan en el volumen Docker 'sigeru_backups'."
        exit 1
        ;;
esac
