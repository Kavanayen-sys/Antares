# SiGeRU - Primera entrega Full Stack

Versión simple realizada con HTML, CSS, JavaScript, PHP 8 y MySQL 8.

## Funciones incluidas

- landing page;
- registro e inicio de sesión;
- formulario público de incidencias;
- selector de contenedores cargado desde MySQL;
- panel con listados y ABM de usuarios, contenedores y camiones;
- listado y baja de incidencias;
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
- panel y endpoints de administración limitados al rol `administrador`;
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
`primera-entrega`:

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
- `DELETE /incidencias/{id}`
