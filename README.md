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
- sección Mi Ruta para la cuadrilla con hoja de paradas e incidencias de la ruta;
- intercambio de datos en JSON;
- API REST de usuarios y API REST de gestión;
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

Se necesita PHP 8 con la extensión `mysqli`. Abrir tres terminales dentro de
la carpeta del proyecto:

```powershell
C:\xampp\php\php.exe -S localhost:8091 api-usuarios/index.php
C:\xampp\php\php.exe -S localhost:8092 api-gestion/index.php
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

### API de gestión - puerto 8092

- `GET /contenedores-publicos`
- `GET|POST /contenedores`
- `PUT|DELETE /contenedores/{id}`
- `GET|POST /camiones`
- `PUT|DELETE /camiones/{id}`
- `GET|POST /incidencias`
- `PUT|DELETE /incidencias/{id}`
- `GET|POST /rutas`
- `GET|PUT|DELETE /rutas/{id}`
- `GET|POST /cuadrillas`
- `DELETE /cuadrillas/{id}`
- `GET|POST /asignaciones`
- `GET /mi-ruta`
- `GET|POST /maquinaria`
- `PUT|DELETE /maquinaria/{id}`
- `GET|POST /centros`
- `PUT|DELETE /centros/{id}`
- `GET|POST /vertederos`
