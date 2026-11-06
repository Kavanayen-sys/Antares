FROM alpine:3.19

# Instalar utilidades requeridas: bash, tar, gzip, mariadb-client (mysqldump / mysqladmin) y tzdata
RUN apk add --no-cache \
    bash \
    tar \
    gzip \
    mariadb-client \
    coreutils \
    tzdata

# Configurar zona horaria (Montevideo / UTC-3)
ENV TZ=America/Montevideo
RUN cp /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

# Crear directorios de trabajo, respaldos y logs
RUN mkdir -p /scripts /backups/mysql /backups/aplicaciones /var/lib/sigeru-backup /var/log

# Copiar scripts de respaldo y entrypoint
COPY scripts/ /scripts/
RUN chmod +x /scripts/*.sh

WORKDIR /scripts

ENTRYPOINT ["/scripts/entrypoint.sh"]
CMD ["crond", "-f", "-l", "2"]
