#!/bin/bash
# ==============================================================================
# SiGeRU - Respaldo de Base de Datos MySQL desde el Contenedor de Respaldos
# Genera archivos comprimidos .tar.gz en el volumen /backups/mysql
# ==============================================================================

set -o pipefail

DB_HOST="${SIGERU_DB_HOST:-db}"
DB_PORT="${SIGERU_DB_PORT:-3306}"
DB_USER="${SIGERU_DB_USER:-sigeru}"
DB_PASS="${SIGERU_DB_PASSWORD:-sigeru123}"
DB_NAME="${SIGERU_DB_NAME:-sigeru}"

DIR_BACKUP="/backups/mysql"
ARCHIVO_LOG="/var/log/sigeru-backup-mysql.log"
RUTA_TEMP="/tmp/backup-db"
FECHA=$(date +"%Y-%m-%d_%H-%M-%S")

RegistrarLog() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$ARCHIVO_LOG"
}

mkdir -p "$DIR_BACKUP/mensuales" "$DIR_BACKUP/semanales" "$DIR_BACKUP/diarios" "$RUTA_TEMP"

TIPO="${1:-completo}"
case "$TIPO" in
    completo|mensual)
        SUBDIR="mensuales"
        RETENCION=365
        ;;
    diferencial|semanal)
        SUBDIR="semanales"
        RETENCION=28
        ;;
    incremental|diario)
        SUBDIR="diarios"
        RETENCION=7
        ;;
    *)
        SUBDIR="mensuales"
        RETENCION=365
        ;;
esac

SQL_FILE="$RUTA_TEMP/mysql-$DB_NAME-$TIPO-$FECHA.sql"
TAR_FILE="$DIR_BACKUP/$SUBDIR/mysql-$DB_NAME-$TIPO-$FECHA.tar.gz"

RegistrarLog "Iniciando respaldo ($TIPO) de la base de datos '$DB_NAME' desde host '$DB_HOST'..."

# Ejecutar mysqldump sobre la red interna de Docker
mysqldump \
    -h "$DB_HOST" \
    -P "$DB_PORT" \
    -u "$DB_USER" \
    -p"$DB_PASS" \
    --single-transaction \
    --quick \
    --routines \
    --triggers \
    "$DB_NAME" > "$SQL_FILE" 2>>"$ARCHIVO_LOG"

DUMP_STATUS=$?

if [ $DUMP_STATUS -eq 0 ] && [ -s "$SQL_FILE" ]; then
    tar -czf "$TAR_FILE" -C "$RUTA_TEMP" "$(basename "$SQL_FILE")"
    rm -f "$SQL_FILE"
    RegistrarLog "¡Respaldo de BD generado exitosamente!: $TAR_FILE"
    
    # Rotación
    find "$DIR_BACKUP/$SUBDIR/" -name "mysql-*.tar.gz" -type f -mtime +$RETENCION -delete 2>/dev/null
    RegistrarLog "Rotación completada para $SUBDIR (retención: $RETENCION días)."
else
    RegistrarLog "ERROR: Falló mysqldump (código: $DUMP_STATUS)."
    rm -f "$SQL_FILE"
    exit 1
fi
