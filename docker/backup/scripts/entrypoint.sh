#!/bin/bash
set -e

# Configurar tareas cron automáticas en Alpine
cat << 'CRON' > /etc/crontabs/root
0 2 * * * /scripts/backup-db.sh completo >> /var/log/cron-backup.log 2>&1
0 3 * * * /scripts/backup-app.sh completo >> /var/log/cron-backup.log 2>&1
CRON

# Ejecutar un respaldo inicial al arrancar si el volumen está vacío
if [ -z "$(ls -A /backups/mysql/mensuales 2>/dev/null)" ]; then
    echo "[SiGeRU Backup] Generando primer respaldo de base de datos..."
    /scripts/backup-db.sh completo || true
fi

if [ -z "$(ls -A /backups/aplicaciones/mensuales 2>/dev/null)" ]; then
    echo "[SiGeRU Backup] Generando primer respaldo de aplicación..."
    /scripts/backup-app.sh completo || true
fi

echo "[SiGeRU Backup] Servicio de respaldo activo y programado."

exec "$@"
