#!/bin/bash

# ==============================================================================
# SiGeRU - Gestión de respaldos del servidor web (contenedor + código fuente)
# Contenedor: sigeru_web (Apache + PHP 8)
# Esquema: Completo (Mensual) + Diferencial (Semanal) + Incremental (Diario)
# Los respaldos se guardan en el volumen Docker: sigeru_backups -> /backups
# ==============================================================================

set -o pipefail

# Configuración
CONTENEDOR_WEB="sigeru_web"
DIR_PROYECTO="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Rutas de control (.snar) en el HOST
DIR_SNAR="/var/lib/sigeru-backup"
SNAR_BASE="$DIR_SNAR/aplicacion_base.snar"
SNAR_INC="$DIR_SNAR/aplicacion_inc.snar"
ARCHIVO_LOG="/var/log/sigeru-backup.log"
RUTA_TEMP="/tmp/sigeru-backup-app"
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
    mkdir -p "$DIR_SNAR"
    mkdir -p "$(dirname "$ARCHIVO_LOG")"
}


GuardarEnVolumen() {
    local archivo_host="$1"
    local tipo="$2"
    local nombre_archivo
    nombre_archivo=$(basename "$archivo_host")

    RegistrarLog "Copiando respaldo al volumen Docker (sigeru_backups/$tipo/)..."

    docker run --rm \
        -v sigeru_backups:/backups \
        -v "$archivo_host":/tmp/entrada/"$nombre_archivo":ro \
        alpine sh -c "mkdir -p /backups/aplicaciones/$tipo && cp /tmp/entrada/$nombre_archivo /backups/aplicaciones/$tipo/"

    if [ $? -eq 0 ]; then
        RegistrarLog "Respaldo guardado en volumen: /backups/aplicaciones/$tipo/$nombre_archivo"
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
        alpine sh -c "find /backups/aplicaciones/$tipo/ -name '*.tar.gz' -type f -mtime +$dias_retencion -delete"

    if [ $? -eq 0 ]; then
        RegistrarLog "Rotación de respaldos de aplicación $tipo completada."
    else
        RegistrarLog "ADVERTENCIA: No se pudo ejecutar la rotación."
    fi
}


# ------------------------------------------------------------------------------
# 1. Respaldo Completo Mensual (Nivel 0)
# ------------------------------------------------------------------------------
BackupCompleto() {
    local archivo="$RUTA_TEMP/aplicacion-completo-$FECHA.tar.gz"
    RegistrarLog "Iniciando RESPALDO COMPLETO MENSUAL (Nivel 0)..."

    # Borrar snapshots anteriores para crear nuevo estado base mensual
    rm -f "$SNAR_BASE" "$SNAR_INC"

    # Respaldar el directorio del proyecto en el host (el código fuente)
    tar --listed-incremental="$SNAR_BASE" \
        -czf "$archivo" \
        --exclude="$DIR_PROYECTO/.git" \
        --exclude="$DIR_PROYECTO/docker" \
        "$DIR_PROYECTO" 2>>"$ARCHIVO_LOG"

    local tar_status=$?
    if [ $tar_status -eq 0 ] || [ $tar_status -eq 1 ]; then
        cp "$SNAR_BASE" "$SNAR_INC"
        RegistrarLog "Respaldo completo mensual generado ($archivo)."
        GuardarEnVolumen "$archivo" "mensuales"
    else
        RegistrarLog "ERROR al generar respaldo completo (Código tar: $tar_status)."
        rm -f "$archivo"
    fi
}


# ------------------------------------------------------------------------------
# 2. Respaldo Diferencial Semanal (Nivel 1 - cambios desde el Completo)
# ------------------------------------------------------------------------------
BackupDiferencial() {
    local archivo="$RUTA_TEMP/aplicacion-diferencial-$FECHA.tar.gz"
    RegistrarLog "Iniciando RESPALDO DIFERENCIAL SEMANAL (cambios respecto al mensual)..."

    if [ ! -f "$SNAR_BASE" ]; then
        RegistrarLog "ADVERTENCIA: No existe snapshot base mensual. Ejecutando completo primero."
        BackupCompleto
        return
    fi

    local snar_dif_temp="$DIR_SNAR/temp_dif.snar"
    cp "$SNAR_BASE" "$snar_dif_temp"

    tar --listed-incremental="$snar_dif_temp" \
        -czf "$archivo" \
        --exclude="$DIR_PROYECTO/.git" \
        --exclude="$DIR_PROYECTO/docker" \
        "$DIR_PROYECTO" 2>>"$ARCHIVO_LOG"

    local tar_status=$?
    if [ $tar_status -eq 0 ] || [ $tar_status -eq 1 ]; then
        cp "$snar_dif_temp" "$SNAR_INC"
        rm -f "$snar_dif_temp"
        RegistrarLog "Respaldo diferencial generado ($archivo)."
        GuardarEnVolumen "$archivo" "semanales"
    else
        rm -f "$snar_dif_temp"
        RegistrarLog "ERROR al generar respaldo diferencial (Código tar: $tar_status)."
        rm -f "$archivo"
    fi
}


# ------------------------------------------------------------------------------
# 3. Respaldo Incremental Diario (Nivel 2 - cambios respecto al día anterior)
# ------------------------------------------------------------------------------
BackupIncremental() {
    local archivo="$RUTA_TEMP/aplicacion-incremental-$FECHA.tar.gz"
    RegistrarLog "Iniciando RESPALDO INCREMENTAL DIARIO (cambios respecto a última copia)..."

    if [ ! -f "$SNAR_INC" ]; then
        RegistrarLog "ADVERTENCIA: No existe snapshot incremental previo. Generando completo base."
        BackupCompleto
        return
    fi

    tar --listed-incremental="$SNAR_INC" \
        -czf "$archivo" \
        --exclude="$DIR_PROYECTO/.git" \
        --exclude="$DIR_PROYECTO/docker" \
        "$DIR_PROYECTO" 2>>"$ARCHIVO_LOG"

    local tar_status=$?
    if [ $tar_status -eq 0 ] || [ $tar_status -eq 1 ]; then
        RegistrarLog "Respaldo incremental diario generado ($archivo)."
        GuardarEnVolumen "$archivo" "diarios"
    else
        RegistrarLog "ERROR al generar respaldo incremental (Código tar: $tar_status)."
        rm -f "$archivo"
    fi
}


# ==============================================================================
# Control de Flujo Principal
# ==============================================================================

ValidarPermisos
PrepararDirectorios

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
        echo "  SiGeRU - Gestión de Respaldos de Aplicación (Docker) "
        echo "======================================================="
        echo "Uso: $0 {incremental|diferencial|completo}"
        echo
        echo "Opciones:"
        echo "  incremental  : Respaldo diario de cambios  (Retención: 7 días)"
        echo "  diferencial  : Respaldo semanal vs. base mensual (Retención: 4 semanas)"
        echo "  completo     : Respaldo mensual base de referencia (Retención: 12 meses)"
        echo
        echo "Los respaldos se almacenan en el volumen Docker 'sigeru_backups'."
        exit 1
        ;;
esac
