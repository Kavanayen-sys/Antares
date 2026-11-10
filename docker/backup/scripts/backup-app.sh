#!/bin/bash
# ==============================================================================
# SiGeRU - Respaldo del código y aplicación web desde el Contenedor de Respaldos
# Genera archivos comprimidos .tar.gz en el volumen /backups/aplicaciones
# ==============================================================================

set -o pipefail

DIR_APP="/app_data"
DIR_BACKUP="/backups/aplicaciones"
DIR_SNAR="/var/lib/sigeru-backup"
ARCHIVO_LOG="/var/log/sigeru-backup-app.log"
FECHA=$(date +"%Y-%m-%d_%H-%M-%S")

RegistrarLog() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$ARCHIVO_LOG"
}

mkdir -p "$DIR_BACKUP/mensuales" "$DIR_BACKUP/semanales" "$DIR_BACKUP/diarios" "$DIR_SNAR"

TIPO="${1:-completo}"
SNAR_BASE="$DIR_SNAR/app_base.snar"
SNAR_INC="$DIR_SNAR/app_inc.snar"

case "$TIPO" in
    completo|mensual)
        SUBDIR="mensuales"
        RETENCION=365
        TAR_FILE="$DIR_BACKUP/$SUBDIR/app-completo-$FECHA.tar.gz"
        rm -f "$SNAR_BASE" "$SNAR_INC"
        tar --listed-incremental="$SNAR_BASE" \
            -czf "$TAR_FILE" \
            --exclude="./.git" \
            --exclude="./docker" \
            -C "$DIR_APP" . 2>>"$ARCHIVO_LOG"
        TAR_STATUS=$?
        if [ $TAR_STATUS -eq 0 ] || [ $TAR_STATUS -eq 1 ]; then
            cp "$SNAR_BASE" "$SNAR_INC"
            RegistrarLog "¡Respaldo completo de la aplicación generado!: $TAR_FILE"
        else
            RegistrarLog "ERROR al generar respaldo completo (código: $TAR_STATUS)."
            rm -f "$TAR_FILE"
            exit 1
        fi
        ;;
    diferencial|semanal)
        SUBDIR="semanales"
        RETENCION=28
        TAR_FILE="$DIR_BACKUP/$SUBDIR/app-diferencial-$FECHA.tar.gz"
        if [ ! -f "$SNAR_BASE" ]; then
            RegistrarLog "No existe base mensual. Ejecutando completo primero..."
            exec "$0" completo
        fi
        SNAR_DIF="$DIR_SNAR/temp_dif.snar"
        cp "$SNAR_BASE" "$SNAR_DIF"
        tar --listed-incremental="$SNAR_DIF" \
            -czf "$TAR_FILE" \
            --exclude="./.git" \
            --exclude="./docker" \
            -C "$DIR_APP" . 2>>"$ARCHIVO_LOG"
        TAR_STATUS=$?
        if [ $TAR_STATUS -eq 0 ] || [ $TAR_STATUS -eq 1 ]; then
            cp "$SNAR_DIF" "$SNAR_INC"
            rm -f "$SNAR_DIF"
            RegistrarLog "¡Respaldo diferencial de la aplicación generado!: $TAR_FILE"
        else
            RegistrarLog "ERROR al generar respaldo diferencial (código: $TAR_STATUS)."
            rm -f "$SNAR_DIF" "$TAR_FILE"
            exit 1
        fi
        ;;
    incremental|diario)
        SUBDIR="diarios"
        RETENCION=7
        TAR_FILE="$DIR_BACKUP/$SUBDIR/app-incremental-$FECHA.tar.gz"
        if [ ! -f "$SNAR_INC" ]; then
            RegistrarLog "No existe snapshot incremental previo. Ejecutando completo primero..."
            exec "$0" completo
        fi
        tar --listed-incremental="$SNAR_INC" \
            -czf "$TAR_FILE" \
            --exclude="./.git" \
            --exclude="./docker" \
            -C "$DIR_APP" . 2>>"$ARCHIVO_LOG"
        TAR_STATUS=$?
        if [ $TAR_STATUS -eq 0 ] || [ $TAR_STATUS -eq 1 ]; then
            RegistrarLog "¡Respaldo incremental de la aplicación generado!: $TAR_FILE"
        else
            RegistrarLog "ERROR al generar respaldo incremental (código: $TAR_STATUS)."
            rm -f "$TAR_FILE"
            exit 1
        fi
        ;;
esac

# Rotación
find "$DIR_BACKUP/$SUBDIR/" -name "app-*.tar.gz" -type f -mtime +$RETENCION -delete 2>/dev/null
RegistrarLog "Rotación completada para $SUBDIR de aplicación (retención: $RETENCION días)."
