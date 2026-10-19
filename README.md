# SiGeRU - Segunda Entrega Full Stack

Versión simple realizada con HTML, CSS, JavaScript, PHP 8 y MySQL 8.

## Funciones incluidas

- landing page ciudadana orientada a vecinos (noticias, cuidado comunitario, reporte rápido);
- registro e inicio de sesión;
- formulario público de incidencias con mapa interactivo (Leaflet / OpenStreetMap);
- selector de contenedores enlazado al mapa interactivo;
- panel con listados y ABM de usuarios, contenedores y camiones;
- mapa general de contenedores en el panel de administración con marcadores interactivos;
- mapa interactivo para seleccionar y guardar coordenadas (`latitud` y `longitud`) al dar de alta o editar un contenedor;
- autocompletado inteligente de calle, esquina y municipio al hacer clic en el mapa de contenedores (geocodificación inversa OpenStreetMap);
- persistencia de coordenadas geográficas en la tabla `contenedorcomunitario`;
- gestión de rutas, cuadrillas y asignaciones de servicio;
- validación de integrantes únicos en cuadrillas (no permite asignar choferes ni peones que ya integran otra cuadrilla);
- control de maquinaria y centros de acopio con CRUD completo (alta, baja, modificación y listado);
- listado, cambio de estado y baja de incidencias;
- vistas y permisos del panel adaptados según el rol (administrador, municipal, cuadrilla, operario);
- sección Mi Ruta para la cuadrilla con hoja de paradas, mapa interactivo del recorrido e incidencias asignadas;
- mapa interactivo general de incidencias en la ciudad con colores por nivel de prioridad y actualización rápida de estado;
- sistema de alertas y notificaciones para trabajadores (campana con contador en cabecera, lista desplegable de avisos críticos y banner destacado para cuadrillas);
- estadísticas y gráficos interactivos con Chart.js en el resumen del panel (estado de incidencias, top 5 de contenedores con desbordes o fallas, distribución por tipo de problema y disponibilidad de la flota de vehículos);
- intercambio de datos en JSON;
- arquitectura de microservicios con 4 APIs REST independientes (usuarios, logística, contenedores y centros/vertederos);
- modelo físico, DDL, dump y datos de prueba.

## Seguridad incluida

- todas las consultas con datos del usuario usan sentencias preparadas;
- contraseñas almacenadas con `password_hash`;
- validación de tipos, largos, correos y opciones permitidas;
- sesión PHP con cookie `HttpOnly` y `SameSite=Lax`;
- cambio del identificador de sesión al iniciar sesión;
- token CSRF para altas, modificaciones y bajas;
- límite básico de intentos de login;
- acceso al panel y endpoints limitado según el rol del usuario;
- acceso desde navegador limitado a `localhost:8090` y `127.0.0.1:8090`;
- salida HTML escapada para evitar inyección de código.

## 1. Preparar la base de datos

Se necesita MySQL 8 iniciado. Desde una terminal, dentro de `primera-entrega`:

```powershell
mysql -u root -p -e "source database/ddl.sql"
mysql -u root -p sigeru -e "source database/datos-prueba.sql"
```

Si el usuario `root` no tiene contraseña, presionar Enter cuando MySQL la solicite.
El archivo `dump-estructura.sql` contiene la misma estructura que el DDL, sin datos.

Las APIs usan estos valores por defecto:

| Dato | Valor |
|---|---|
| Servidor | `localhost` |
| Puerto | `3306` |
| Base | `sigeru` |
| Usuario | `root` |
| Contraseña | vacía |

Se pueden cambiar con las variables `SIGERU_DB_HOST`, `SIGERU_DB_PORT`,
`SIGERU_DB_NAME`, `SIGERU_DB_USER` y `SIGERU_DB_PASSWORD`.

## 2. Iniciar la aplicación

Se necesita PHP 8 con la extensión `mysqli`.

### Inicio automático

- **En Windows:** Hacer doble clic en `iniciar-windows.bat` (o ejecutarlo desde CMD/PowerShell). Abrirá automáticamente 5 terminales con cada API y el frontend, y abrirá el navegador en `http://localhost:8090`. Para detener todo podés usar `detener-windows.bat`.
- **En Linux:** Dar permisos y ejecutar `iniciar-linux.sh`:
  ```bash
  chmod +x iniciar-linux.sh
  ./iniciar-linux.sh
  ```
  Abrirá las terminales según el entorno gráfico disponible (GNOME, XFCE, KDE, etc.) o en segundo plano si estás en una sesión de servidor/SSH. Para detener todo podés usar `./detener-linux.sh`.

### Inicio manual

Abrir terminales dentro de la carpeta del proyecto:

```powershell
C:\xampp\php\php.exe -S localhost:8091 api-usuarios/index.php
C:\xampp\php\php.exe -S localhost:8092 api-logistica/index.php
C:\xampp\php\php.exe -S localhost:8093 api-contenedores/index.php
C:\xampp\php\php.exe -S localhost:8094 api-centros/index.php
C:\xampp\php\php.exe -S localhost:8090 -t frontend
```

Abrir <http://localhost:8090>.

Cuenta de demostración:

- correo: `admin@sigeru.local`
- contraseña: `admin123`

## Endpoints principales

### API de usuarios - puerto 8091

- `POST /registro`
- `POST /login`
- `POST /logout`
- `GET /sesion`
- `GET|POST /usuarios`
- `PUT|DELETE /usuarios/{id}`

### API de logística - puerto 8092

- `GET|POST /camiones`
- `PUT|DELETE /camiones/{id}`
- `GET|POST /rutas`
- `GET|PUT|DELETE /rutas/{id}`
- `GET|POST /cuadrillas`
- `DELETE /cuadrillas/{id}`
- `GET /cuadrilleros`
- `GET|POST /asignaciones`
- `GET /mi-ruta`

### API de contenedores - puerto 8093

- `GET /contenedores-publicos`
- `GET|POST /contenedores`
- `PUT|DELETE /contenedores/{id}`
- `GET|POST /incidencias`
- `PUT|DELETE /incidencias/{id}`

### API de centros - puerto 8094

- `GET|POST /maquinaria`
- `PUT|DELETE /maquinaria/{id}`
- `GET|POST /centros`
- `PUT|DELETE /centros/{id}`
- `GET|POST /vertederos`
