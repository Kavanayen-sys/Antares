#!/bin/bash

# ==============================================================================
# SiGeRU - Módulo: Gestión de Base de Datos MySQL (contenedor sigeru_db)
# ==============================================================================

set -o pipefail

COLOR_RESET="\e[0m"
COLOR_VERDE="\e[1;32m"
COLOR_ROJO="\e[1;31m"
COLOR_AZUL="\e[1;34m"
COLOR_AMARILLO="\e[1;33m"
COLOR_CYAN="\e[1;36m"

CONTENEDOR_DB="sigeru_db"
LOG_ADMIN="/var/log/sigeru-admin.log"

if [ "$EUID" -ne 0 ]; then
    echo -e "${COLOR_ROJO}ERROR: Debe ejecutarse como root.${COLOR_RESET}" >&2
    exit 1
fi

RegistrarAccion() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - [BD] $1" >> "$LOG_ADMIN"
}

Pausar() {
    echo
    echo -e "${COLOR_AMARILLO}Presione [ENTER] para continuar...${COLOR_RESET}"
    read -r
}

ContenedorCorriendo() {
    docker inspect --format '{{.State.Running}}' "$CONTENEDOR_DB" 2>/dev/null | grep -q "true"
}

opc=-1
while [ "$opc" -ne 0 ]; do
    clear
    echo -e "${COLOR_CYAN}=====================================================${COLOR_RESET}"
    echo -e "${COLOR_CYAN}     SiGeRU - GESTIÓN DE BASE DE DATOS (Docker)     ${COLOR_RESET}"
    echo -e "${COLOR_CYAN}     Contenedor: $CONTENEDOR_DB                     ${COLOR_RESET}"
    echo -e "${COLOR_CYAN}=====================================================${COLOR_RESET}"
    echo "1. Ver estado del contenedor MySQL"
    echo "2. Iniciar / Detener / Reiniciar contenedor"
    echo "3. Ver bases de datos existentes"
    echo "4. Ejecutar optimización de tablas (mysqlcheck)"
    echo "5. Acceder a consola MySQL interactiva"
    echo "6. Ver variables de configuración de MySQL"
    echo "0. Volver al menú principal"
    echo -e "${COLOR_CYAN}-----------------------------------------------------${COLOR_RESET}"
    read -p "Seleccione una opción: " opc

    case $opc in
        1)
            echo -e "\n${COLOR_AZUL}--- Estado del contenedor $CONTENEDOR_DB ---${COLOR_RESET}"
            docker ps --filter "name=$CONTENEDOR_DB" --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}"
            echo
            if ContenedorCorriendo; then
                echo -e "${COLOR_VERDE}[OK] El contenedor está corriendo.${COLOR_RESET}"
            else
                echo -e "${COLOR_ROJO}[!!] El contenedor está detenido.${COLOR_RESET}"
            fi
            Pausar
            ;;
        2)
            echo "a. Iniciar | b. Detener | c. Reiniciar"
            read -p "Acción: " subopc
            case $subopc in
                a)
                    docker compose -f "$(dirname "$0")/docker-compose.yml" start db \
                        && echo -e "${COLOR_VERDE}Contenedor MySQL iniciado.${COLOR_RESET}" \
                        || echo -e "${COLOR_ROJO}Error al iniciar el contenedor.${COLOR_RESET}"
                    RegistrarAccion "Contenedor MySQL iniciado"
                    ;;
                b)
                    docker compose -f "$(dirname "$0")/docker-compose.yml" stop db \
                        && echo -e "${COLOR_AMARILLO}Contenedor MySQL detenido.${COLOR_RESET}" \
                        || echo -e "${COLOR_ROJO}Error al detener el contenedor.${COLOR_RESET}"
                    RegistrarAccion "Contenedor MySQL detenido"
                    ;;
                c)
                    docker compose -f "$(dirname "$0")/docker-compose.yml" restart db \
                        && echo -e "${COLOR_VERDE}Contenedor MySQL reiniciado.${COLOR_RESET}" \
                        || echo -e "${COLOR_ROJO}Error al reiniciar el contenedor.${COLOR_RESET}"
                    RegistrarAccion "Contenedor MySQL reiniciado"
                    ;;
                *) echo -e "${COLOR_ROJO}Opción inválida.${COLOR_RESET}" ;;
            esac
            Pausar
            ;;
        3)
            echo -e "\n${COLOR_AZUL}--- Bases de Datos en el contenedor ---${COLOR_RESET}"
            docker exec "$CONTENEDOR_DB" mysql -usigeru_user -psigeru_pass -e "SHOW DATABASES;" 2>/dev/null \
                || echo -e "${COLOR_ROJO}Error al conectar. Verifique que el contenedor esté corriendo.${COLOR_RESET}"
            Pausar
            ;;
        4)
            echo -e "\n${COLOR_AZUL}Ejecutando mysqlcheck sobre la base 'sigeru'...${COLOR_RESET}"
            docker exec "$CONTENEDOR_DB" mysqlcheck --optimize -usigeru_user -psigeru_pass sigeru 2>/dev/null \
                || echo -e "${COLOR_ROJO}Error al ejecutar mysqlcheck.${COLOR_RESET}"
            RegistrarAccion "mysqlcheck ejecutado sobre sigeru"
            Pausar
            ;;
        5)
            echo -e "\n${COLOR_AMARILLO}Abriendo consola MySQL interactiva en el contenedor...${COLOR_RESET}"
            echo -e "${COLOR_AMARILLO}(escriba 'exit' para volver)${COLOR_RESET}\n"
            docker exec -it "$CONTENEDOR_DB" mysql -usigeru_user -psigeru_pass sigeru
            Pausar
            ;;
        6)
            echo -e "\n${COLOR_AZUL}--- Variables de configuración MySQL ---${COLOR_RESET}"
            docker exec "$CONTENEDOR_DB" mysql -usigeru_user -psigeru_pass \
                -e "SHOW VARIABLES LIKE 'max_connections'; SHOW VARIABLES LIKE 'innodb_buffer%'; SHOW VARIABLES LIKE 'log_%';" \
                2>/dev/null
            Pausar
            ;;
        0) break ;;
        *) echo -e "${COLOR_ROJO}Opción inválida.${COLOR_RESET}"; Pausar ;;
    esac
done
