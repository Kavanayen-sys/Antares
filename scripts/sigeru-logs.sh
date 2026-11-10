#!/bin/bash

# ==============================================================================
# SiGeRU - Módulo: Gestión de Logs (contenedores Docker + sistema host)
# ==============================================================================

set -o pipefail

COLOR_RESET="\e[0m"
COLOR_VERDE="\e[1;32m"
COLOR_ROJO="\e[1;31m"
COLOR_AZUL="\e[1;34m"
COLOR_AMARILLO="\e[1;33m"
COLOR_CYAN="\e[1;36m"

CONTENEDOR_WEB="sigeru_web"
CONTENEDOR_DB="sigeru_db"
LOG_ADMIN="/var/log/sigeru-admin.log"
LOG_BACKUP_APP="/var/log/sigeru-backup.log"
LOG_BACKUP_DB="/var/log/sigeru-backup-mysql.log"

if [ "$EUID" -ne 0 ]; then
    echo -e "${COLOR_ROJO}ERROR: Debe ejecutarse como root.${COLOR_RESET}" >&2
    exit 1
fi

Pausar() {
    echo
    echo -e "${COLOR_AMARILLO}Presione [ENTER] para continuar...${COLOR_RESET}"
    read -r
}

opc=-1
while [ "$opc" -ne 0 ]; do
    clear
    echo -e "${COLOR_CYAN}=====================================================${COLOR_RESET}"
    echo -e "${COLOR_CYAN}     SiGeRU - GESTIÓN DE LOGS (Docker + Host)       ${COLOR_RESET}"
    echo -e "${COLOR_CYAN}=====================================================${COLOR_RESET}"
    echo "1. Ver log de administración general (host)"
    echo "2. Ver logs del contenedor Web (Apache + PHP)"
    echo "3. Ver logs del contenedor MySQL"
    echo "4. Ver logs de respaldos de aplicación"
    echo "5. Ver logs de respaldos de MySQL"
    echo "6. Ver eventos de autenticación SSH (host)"
    echo "7. Ver errores críticos del sistema (journalctl)"
    echo "0. Volver al menú principal"
    echo -e "${COLOR_CYAN}-----------------------------------------------------${COLOR_RESET}"
    read -p "Seleccione una opción: " opc

    case $opc in
        1)
            echo -e "\n${COLOR_AZUL}--- Log de administración SiGeRU ---${COLOR_RESET}"
            if [ -f "$LOG_ADMIN" ]; then
                tail -n 30 "$LOG_ADMIN"
            else
                echo "Sin registros en $LOG_ADMIN."
            fi
            Pausar
            ;;
        2)
            echo -e "\n${COLOR_AZUL}--- Logs del contenedor $CONTENEDOR_WEB (últimas 50 líneas) ---${COLOR_RESET}"
            docker logs --tail=50 "$CONTENEDOR_WEB" 2>&1 \
                || echo -e "${COLOR_ROJO}El contenedor '$CONTENEDOR_WEB' no está corriendo.${COLOR_RESET}"
            echo
            echo -e "${COLOR_AMARILLO}¿Ver logs en tiempo real? (s/n):${COLOR_RESET}"
            read -r resp
            if [[ "$resp" =~ ^[sS]$ ]]; then
                echo -e "${COLOR_AMARILLO}(Ctrl+C para salir)${COLOR_RESET}"
                docker logs -f "$CONTENEDOR_WEB" 2>&1
            fi
            Pausar
            ;;
        3)
            echo -e "\n${COLOR_AZUL}--- Logs del contenedor $CONTENEDOR_DB (últimas 50 líneas) ---${COLOR_RESET}"
            docker logs --tail=50 "$CONTENEDOR_DB" 2>&1 \
                || echo -e "${COLOR_ROJO}El contenedor '$CONTENEDOR_DB' no está corriendo.${COLOR_RESET}"
            echo
            echo -e "${COLOR_AMARILLO}¿Ver logs en tiempo real? (s/n):${COLOR_RESET}"
            read -r resp
            if [[ "$resp" =~ ^[sS]$ ]]; then
                echo -e "${COLOR_AMARILLO}(Ctrl+C para salir)${COLOR_RESET}"
                docker logs -f "$CONTENEDOR_DB" 2>&1
            fi
            Pausar
            ;;
        4)
            echo -e "\n${COLOR_AZUL}--- Logs de Respaldo de Aplicación ---${COLOR_RESET}"
            if [ -f "$LOG_BACKUP_APP" ]; then
                tail -n 20 "$LOG_BACKUP_APP"
            else
                echo "Sin registros en $LOG_BACKUP_APP."
            fi
            Pausar
            ;;
        5)
            echo -e "\n${COLOR_AZUL}--- Logs de Respaldo de MySQL ---${COLOR_RESET}"
            if [ -f "$LOG_BACKUP_DB" ]; then
                tail -n 20 "$LOG_BACKUP_DB"
            else
                echo "Sin registros en $LOG_BACKUP_DB."
            fi
            Pausar
            ;;
        6)
            echo -e "\n${COLOR_AZUL}--- Últimos eventos de SSH (host) ---${COLOR_RESET}"
            journalctl -u sshd -n 30 --no-pager 2>/dev/null \
                || echo "journalctl no disponible en este sistema."
            Pausar
            ;;
        7)
            echo -e "\n${COLOR_ROJO}--- Errores Críticos del Sistema (host) ---${COLOR_RESET}"
            journalctl -p err..emerg -n 25 --no-pager 2>/dev/null \
                || echo "journalctl no disponible en este sistema."
            Pausar
            ;;
        0) break ;;
        *) echo -e "${COLOR_ROJO}Opción inválida.${COLOR_RESET}"; Pausar ;;
    esac
done
