#!/bin/bash

# ==============================================================================
# SiGeRU - Sistema de Gestión de Residuos Urbanos
# Grupo Antares - Consola de Administración (Docker Compose)
# ==============================================================================

set -o pipefail

COLOR_RESET="\e[0m"
COLOR_VERDE="\e[1;32m"
COLOR_ROJO="\e[1;31m"
COLOR_AZUL="\e[1;34m"
COLOR_AMARILLO="\e[1;33m"
COLOR_CYAN="\e[1;36m"

LOG_ADMIN="/var/log/sigeru-admin.log"
LOG_BACKUP_APP="/var/log/sigeru-backup.log"
LOG_BACKUP_DB="/var/log/sigeru-backup-mysql.log"

# Directorio del proyecto (donde está el docker-compose.yml)
DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
COMPOSE_FILE="$DIR/docker-compose.yml"

if [ "$EUID" -ne 0 ]; then
    echo -e "${COLOR_ROJO}ERROR: Este script debe ejecutarse con privilegios de root (sudo).${COLOR_RESET}" >&2
    exit 1
fi

RegistrarAccion() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - [ORQUESTADOR] $1" >> "$LOG_ADMIN"
}

Pausar() {
    echo
    echo -e "${COLOR_AMARILLO}Presione [ENTER] para continuar...${COLOR_RESET}"
    read -r
}

LlamarModulo() {
    local script="$DIR/$1"
    if [ -x "$script" ]; then
        "$script"
    else
        echo -e "${COLOR_ROJO}No se encontró o no es ejecutable: $script${COLOR_RESET}"
        echo -e "${COLOR_AMARILLO}Ejecute: chmod +x $script${COLOR_RESET}"
        Pausar
    fi
}

# ------------------------------------------------------------------------------
# Submenú: Estado de Contenedores Docker
# ------------------------------------------------------------------------------
SubmenuDocker() {
    local opc=-1
    while [ "$opc" -ne 0 ]; do
        clear
        echo -e "${COLOR_CYAN}=====================================================${COLOR_RESET}"
        echo -e "${COLOR_CYAN}         SiGeRU - ESTADO DE CONTENEDORES            ${COLOR_RESET}"
        echo -e "${COLOR_CYAN}=====================================================${COLOR_RESET}"
        echo "1. Ver estado de todos los contenedores"
        echo "2. Levantar todos los servicios (docker compose up)"
        echo "3. Detener todos los servicios (docker compose down)"
        echo "4. Reiniciar todos los servicios"
        echo "5. Ver uso de recursos (CPU, Memoria)"
        echo "6. Ver red Docker (sigeru_network)"
        echo "7. Listar volúmenes de respaldo"
        echo "0. Volver al menú principal"
        echo -e "${COLOR_CYAN}-----------------------------------------------------${COLOR_RESET}"
        read -p "Seleccione una opción: " opc

        case $opc in
            1)
                echo -e "\n${COLOR_AZUL}--- Contenedores SiGeRU ---${COLOR_RESET}"
                docker ps -a --filter "name=sigeru" \
                    --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}"
                Pausar
                ;;
            2)
                echo -e "${COLOR_AZUL}Levantando servicios...${COLOR_RESET}"
                docker compose -f "$COMPOSE_FILE" up -d \
                    && echo -e "${COLOR_VERDE}Todos los servicios iniciados.${COLOR_RESET}" \
                    || echo -e "${COLOR_ROJO}Error al iniciar los servicios.${COLOR_RESET}"
                RegistrarAccion "docker compose up -d ejecutado"
                Pausar
                ;;
            3)
                echo -e "${COLOR_AMARILLO}Deteniendo servicios...${COLOR_RESET}"
                docker compose -f "$COMPOSE_FILE" down \
                    && echo -e "${COLOR_VERDE}Servicios detenidos correctamente.${COLOR_RESET}" \
                    || echo -e "${COLOR_ROJO}Error al detener los servicios.${COLOR_RESET}"
                RegistrarAccion "docker compose down ejecutado"
                Pausar
                ;;
            4)
                echo -e "${COLOR_AZUL}Reiniciando servicios...${COLOR_RESET}"
                docker compose -f "$COMPOSE_FILE" restart \
                    && echo -e "${COLOR_VERDE}Servicios reiniciados.${COLOR_RESET}" \
                    || echo -e "${COLOR_ROJO}Error al reiniciar.${COLOR_RESET}"
                RegistrarAccion "docker compose restart ejecutado"
                Pausar
                ;;
            5)
                echo -e "\n${COLOR_AZUL}--- Uso de recursos (Ctrl+C para salir) ---${COLOR_RESET}"
                docker stats --no-stream sigeru_web sigeru_db 2>/dev/null \
                    || echo -e "${COLOR_ROJO}No se pudieron obtener métricas. ¿Contenedores corriendo?${COLOR_RESET}"
                Pausar
                ;;
            6)
                echo -e "\n${COLOR_AZUL}--- Red Docker: sigeru_network ---${COLOR_RESET}"
                docker network inspect sigeru_network 2>/dev/null \
                    || echo -e "${COLOR_ROJO}Red 'sigeru_network' no encontrada.${COLOR_RESET}"
                Pausar
                ;;
            7)
                echo -e "\n${COLOR_AZUL}--- Contenido del volumen sigeru_backups ---${COLOR_RESET}"
                docker run --rm -v sigeru_backups:/backups alpine \
                    sh -c "find /backups -type f | sort" 2>/dev/null \
                    || echo "Volumen vacío o no existe."
                Pausar
                ;;
            0) break ;;
            *) echo -e "${COLOR_ROJO}Opción inválida.${COLOR_RESET}"; Pausar ;;
        esac
    done
}

# ------------------------------------------------------------------------------
# Submenú: Respaldos GFS
# ------------------------------------------------------------------------------
SubmenuRespaldos() {
    local opc=-1
    while [ "$opc" -ne 0 ]; do
        clear
        echo -e "${COLOR_CYAN}=====================================================${COLOR_RESET}"
        echo -e "${COLOR_CYAN}       SiGeRU - GESTIÓN DE RESPALDOS (GFS)          ${COLOR_RESET}"
        echo -e "${COLOR_CYAN}=====================================================${COLOR_RESET}"
        echo -e "${COLOR_AZUL}--- Aplicación Web (código fuente) ---${COLOR_RESET}"
        echo "1. Respaldo Incremental Diario (App)"
        echo "2. Respaldo Diferencial Semanal (App)"
        echo "3. Respaldo Completo Mensual (App)"
        echo
        echo -e "${COLOR_AZUL}--- Base de Datos MySQL (contenedor sigeru_db) ---${COLOR_RESET}"
        echo "4. Respaldo Incremental Diario (MySQL)"
        echo "5. Respaldo Diferencial Semanal (MySQL)"
        echo "6. Respaldo Completo Mensual (MySQL)"
        echo
        echo -e "${COLOR_AZUL}--- Registros ---${COLOR_RESET}"
        echo "7. Ver logs de respaldos"
        echo "8. Listar respaldos guardados en volumen"
        echo "0. Volver al menú principal"
        echo -e "${COLOR_CYAN}-----------------------------------------------------${COLOR_RESET}"
        read -p "Seleccione una opción: " opc

        case $opc in
            1) "$DIR/backup-sigeru.sh" incremental; Pausar ;;
            2) "$DIR/backup-sigeru.sh" diferencial; Pausar ;;
            3) "$DIR/backup-sigeru.sh" completo;    Pausar ;;
            4) "$DIR/backup-mysql.sh"  incremental; Pausar ;;
            5) "$DIR/backup-mysql.sh"  diferencial; Pausar ;;
            6) "$DIR/backup-mysql.sh"  completo;    Pausar ;;
            7)
                echo -e "${COLOR_AZUL}--- Logs de Respaldo de Aplicación ---${COLOR_RESET}"
                if [ -f "$LOG_BACKUP_APP" ]; then tail -n 10 "$LOG_BACKUP_APP"; else echo "Sin registros."; fi
                echo -e "\n${COLOR_AZUL}--- Logs de Respaldo de MySQL ---${COLOR_RESET}"
                if [ -f "$LOG_BACKUP_DB" ]; then tail -n 10 "$LOG_BACKUP_DB"; else echo "Sin registros."; fi
                Pausar
                ;;
            8)
                echo -e "\n${COLOR_AZUL}--- Respaldos en volumen sigeru_backups ---${COLOR_RESET}"
                docker run --rm -v sigeru_backups:/backups alpine \
                    sh -c "find /backups -type f -name '*.tar.gz' | sort" 2>/dev/null \
                    || echo "Volumen vacío o no existe."
                Pausar
                ;;
            0) break ;;
            *) echo -e "${COLOR_ROJO}Opción inválida.${COLOR_RESET}"; Pausar ;;
        esac
    done
}

# ==============================================================================
# Bucle del Menú Principal
# ==============================================================================

opcion=-1
RegistrarAccion "Sesión administrativa iniciada"

while [ "$opcion" -ne 0 ]; do
    clear
    echo -e "${COLOR_CYAN}======================================================================${COLOR_RESET}"
    echo -e "${COLOR_CYAN}       SiGeRU - CONSOLA DE ADMINISTRACIÓN                             ${COLOR_RESET}"
    echo -e "${COLOR_CYAN}       Grupo Antares | Infraestructura Docker Compose                 ${COLOR_RESET}"
    echo -e "${COLOR_CYAN}======================================================================${COLOR_RESET}"
    echo -e " 1. ${COLOR_VERDE}[Usuarios/Grupos]${COLOR_RESET}   Gestión de Usuarios y Grupos del host"
    echo -e " 2. ${COLOR_VERDE}[Respaldos]${COLOR_RESET}         Rutinas de Backup SiGeRU (GFS: Diario/Semanal/Mensual)"
    echo -e " 3. ${COLOR_VERDE}[Redes]${COLOR_RESET}             Configuración de Red del host (nmcli)"
    echo -e " 4. ${COLOR_VERDE}[Base de Datos]${COLOR_RESET}     Administración del contenedor MySQL (sigeru_db)"
    echo -e " 5. ${COLOR_VERDE}[Firewall]${COLOR_RESET}          Control de Puertos y Reglas del host (Firewalld)"
    echo -e " 6. ${COLOR_VERDE}[Logs]${COLOR_RESET}              Logs de contenedores Docker y sistema"
    echo -e " 7. ${COLOR_VERDE}[Docker]${COLOR_RESET}            Estado, control y recursos de contenedores"
    echo -e " 0. ${COLOR_ROJO}[Salir]${COLOR_RESET}             Cerrar la consola de administración"
    echo -e "${COLOR_CYAN}======================================================================${COLOR_RESET}"
    read -p "Seleccione el módulo a administrar: " opcion

    case $opcion in
        1) LlamarModulo "sigeru-usuarios.sh" ;;
        2) SubmenuRespaldos ;;
        3) LlamarModulo "sigeru-redes.sh" ;;
        4) LlamarModulo "sigeru-db.sh" ;;
        5) LlamarModulo "sigeru-firewall.sh" ;;
        6) LlamarModulo "sigeru-logs.sh" ;;
        7) SubmenuDocker ;;
        0)
            echo -e "\n${COLOR_VERDE}Cerrando consola de administración SiGeRU. ¡Hasta luego!${COLOR_RESET}\n"
            RegistrarAccion "Sesión administrativa finalizada"
            exit 0
            ;;
        *)
            echo -e "${COLOR_ROJO}Opción inválida.${COLOR_RESET}"
            Pausar
            ;;
    esac
done
