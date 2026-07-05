<?php

declare(strict_types=1);

require_once __DIR__ . '/UsuarioController.php';

iniciarSesionSegura();
configurarAcceso();

try {
    $controlador = new UsuarioController();
    $metodo = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    $ruta = '/' . trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
    $datos = leerJson();

    if ($metodo === 'GET' && $ruta === '/health') {
        responder(['data' => ['servicio' => 'api-usuarios', 'base' => 'MySQL']]);
    }
    if ($metodo === 'GET' && $ruta === '/csrf') {
        responder(['data' => ['csrf' => tokenCsrf()]]);
    }
    if ($metodo === 'POST' && $ruta === '/registro') {
        verificarCsrf();
        responder(['data' => $controlador->registrar($datos), 'message' => 'Usuario registrado.'], 201);
    }
    if ($metodo === 'POST' && $ruta === '/login') {
        verificarCsrf();
        controlarIntentos();
        $usuario = $controlador->ingresar($datos);
        session_regenerate_id(true);
        $_SESSION['usuario'] = $usuario;
        $_SESSION['intentos_login'] = 0;
        responder(['data' => $usuario, 'message' => 'Inicio de sesión correcto.']);
    }
    if ($metodo === 'GET' && $ruta === '/sesion') {
        exigirSesion();
        responder(['data' => $_SESSION['usuario'], 'csrf' => tokenCsrf()]);
    }
    if ($metodo === 'POST' && $ruta === '/logout') {
        exigirSesion();
        verificarCsrf();
        $_SESSION = [];
        session_destroy();
        responder(['message' => 'Sesión cerrada.']);
    }

    exigirAdministrador();

    if ($metodo === 'GET' && $ruta === '/usuarios') {
        responder(['data' => $controlador->listar()]);
    }
    if ($metodo === 'POST' && $ruta === '/usuarios') {
        verificarCsrf();
        responder(['data' => $controlador->registrar($datos, true), 'message' => 'Usuario creado.'], 201);
    }
    if (preg_match('#^/usuarios/(\d+)$#', $ruta, $coincide)) {
        $id = (int) $coincide[1];
        verificarCsrf();
        if ($metodo === 'PUT') {
            responder(['data' => $controlador->actualizar($id, $datos), 'message' => 'Usuario actualizado.']);
        }
        if ($metodo === 'DELETE') {
            if ($id === (int) $_SESSION['usuario']['idUsu']) {
                responder(['error' => 'No podés eliminar el usuario de tu sesión.'], 409);
            }
            responder(['data' => ['eliminado' => $controlador->eliminar($id)], 'message' => 'Usuario eliminado.']);
        }
    }

    responder(['error' => 'Ruta no encontrada.'], 404);
} catch (InvalidArgumentException $error) {
    responder(['error' => $error->getMessage()], 422);
} catch (OutOfBoundsException $error) {
    responder(['error' => $error->getMessage()], 404);
} catch (mysqli_sql_exception $error) {
    error_log($error->getMessage());
    $codigo = $error->getCode() === 1451 ? 409 : 500;
    $mensaje = $codigo === 409
        ? 'No se puede eliminar porque el registro está siendo utilizado.'
        : 'No se pudo acceder a la base de datos.';
    responder(['error' => $mensaje], $codigo);
} catch (RuntimeException $error) {
    $_SESSION['intentos_login'] = (int) ($_SESSION['intentos_login'] ?? 0) + 1;
    responder(['error' => $error->getMessage()], 401);
} catch (Throwable $error) {
    error_log($error->getMessage());
    responder(['error' => 'Ocurrió un error inesperado.'], 500);
}

function iniciarSesionSegura(): void
{
    session_name('SIGERU_PRIMERA_ENTREGA');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']),
        'samesite' => 'Lax',
        'path' => '/',
    ]);
    session_start();
}

function configurarAcceso(): void
{
    $origen = $_SERVER['HTTP_ORIGIN'] ?? '';
    $permitidos = ['http://localhost:8090', 'http://127.0.0.1:8090'];

    if ($origen !== '' && !in_array($origen, $permitidos, true)) {
        responder(['error' => 'Origen no autorizado.'], 403);
    }
    if ($origen !== '') {
        header('Access-Control-Allow-Origin: ' . $origen);
        header('Access-Control-Allow-Credentials: true');
        header('Vary: Origin');
    }
    header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

function leerJson(): array
{
    $contenido = file_get_contents('php://input') ?: '';
    if ($contenido === '') {
        return [];
    }
    $datos = json_decode($contenido, true);
    if (!is_array($datos)) {
        responder(['error' => 'El cuerpo debe ser JSON válido.'], 400);
    }
    return $datos;
}

function tokenCsrf(): string
{
    $_SESSION['csrf'] ??= bin2hex(random_bytes(24));
    return $_SESSION['csrf'];
}

function verificarCsrf(): void
{
    $recibido = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if ($recibido === '' || !hash_equals(tokenCsrf(), $recibido)) {
        responder(['error' => 'Solicitud no autorizada.'], 403);
    }
}

function exigirSesion(): void
{
    if (empty($_SESSION['usuario'])) {
        responder(['error' => 'Debés iniciar sesión.'], 401);
    }
}

function exigirAdministrador(): void
{
    exigirSesion();
    if (($_SESSION['usuario']['rol'] ?? '') !== 'administrador') {
        responder(['error' => 'No tenés permisos para realizar esta acción.'], 403);
    }
}

function controlarIntentos(): void
{
    if ((int) ($_SESSION['intentos_login'] ?? 0) >= 5) {
        responder(['error' => 'Demasiados intentos. Cerrá el navegador y volvé a intentar.'], 429);
    }
}

function responder(array $contenido, int $estado = 200): never
{
    http_response_code($estado);
    echo json_encode($contenido, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
