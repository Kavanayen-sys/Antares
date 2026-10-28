<?php

declare(strict_types=1);

require_once __DIR__ . '/LogisticaController.php';

iniciarSesionSegura();
configurarAcceso();

try {
    $controlador = new LogisticaController();
    $metodo = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    $ruta = '/' . trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
    $datos = leerJson();

    if ($metodo === 'GET' && $ruta === '/health') {
        responder(['data' => ['servicio' => 'api-logistica', 'base' => 'MySQL']]);
    }
    if ($metodo === 'GET' && $ruta === '/csrf') {
        responder(['data' => ['csrf' => tokenCsrf()]]);
    }

    $rolesGestion = ['administrador', 'municipal'];
    $rolesTodosTrabajadores = ['administrador', 'municipal', 'cuadrilla', 'operario'];
    $rolesCuadrilla = ['administrador', 'municipal', 'cuadrilla'];

    if ($metodo === 'GET' && $ruta === '/camiones') {
        exigirRoles($rolesTodosTrabajadores);
        responder(['data' => $controlador->listarVehiculos()]);
    }
    if ($metodo === 'POST' && $ruta === '/camiones') {
        exigirRoles($rolesGestion);
        verificarCsrf();
        responder(['data' => $controlador->guardarVehiculo($datos), 'message' => 'Vehiculo creado.'], 201);
    }
    if (preg_match('#^/(?:vehiculos|camiones)/(\d+)$#', $ruta, $coincide)) {
        $id = (int) $coincide[1];
        if ($metodo === 'PUT') {
            exigirRoles($rolesGestion);
            verificarCsrf();
            responder(['data' => $controlador->guardarVehiculo($datos, $id), 'message' => 'Vehiculo actualizado.']);
        }
        if ($metodo === 'DELETE') {
            exigirRoles($rolesGestion);
            verificarCsrf();
            responder(['data' => ['eliminado' => $controlador->eliminarVehiculo($id)], 'message' => 'Vehiculo eliminado.']);
        }
    }

    if ($metodo === 'GET' && $ruta === '/rutas') {
        exigirRoles($rolesTodosTrabajadores);
        responder(['data' => $controlador->listarRutas()]);
    }
    if ($metodo === 'POST' && $ruta === '/rutas') {
        exigirRoles($rolesGestion);
        verificarCsrf();
        responder(['data' => $controlador->guardarRuta($datos), 'message' => 'Ruta creada.'], 201);
    }
    if (preg_match('#^/rutas/(\d+)$#', $ruta, $coincide)) {
        $id = (int) $coincide[1];
        if ($metodo === 'GET') {
            exigirRoles($rolesTodosTrabajadores);
            responder(['data' => $controlador->obtenerRuta($id)]);
        }
        if ($metodo === 'PUT') {
            exigirRoles($rolesGestion);
            verificarCsrf();
            responder(['data' => $controlador->guardarRuta($datos, $id), 'message' => 'Ruta actualizada.']);
        }
        if ($metodo === 'DELETE') {
            exigirRoles($rolesGestion);
            verificarCsrf();
            responder(['data' => ['eliminado' => $controlador->eliminarRuta($id)], 'message' => 'Ruta eliminada.']);
        }
    }

    if ($metodo === 'GET' && $ruta === '/cuadrillas') {
        exigirRoles($rolesTodosTrabajadores);
        responder(['data' => $controlador->listarCuadrillas()]);
    }
    if ($metodo === 'GET' && $ruta === '/cuadrilleros') {
        exigirRoles($rolesGestion);
        responder(['data' => $controlador->listarUsuariosCuadrilla()]);
    }
    if ($metodo === 'POST' && $ruta === '/cuadrillas') {
        exigirRoles($rolesGestion);
        verificarCsrf();
        responder(['data' => $controlador->guardarCuadrilla($datos), 'message' => 'Cuadrilla registrada.'], 201);
    }
    if ($metodo === 'DELETE' && preg_match('#^/cuadrillas/(\d+)$#', $ruta, $coincide)) {
        exigirRoles($rolesGestion);
        verificarCsrf();
        responder(['data' => ['eliminado' => $controlador->eliminarCuadrilla((int) $coincide[1])], 'message' => 'Cuadrilla eliminada.']);
    }

    if ($metodo === 'GET' && $ruta === '/asignaciones') {
        exigirRoles($rolesCuadrilla);
        responder(['data' => $controlador->listarAsignaciones()]);
    }
    if ($metodo === 'POST' && $ruta === '/asignaciones') {
        exigirRoles($rolesGestion);
        verificarCsrf();
        responder(['data' => $controlador->asignarCuadrilla($datos), 'message' => 'Asignación realizada correctamente.'], 201);
    }
    if ($metodo === 'DELETE' && preg_match('#^/asignaciones/(\d+)$#', $ruta, $coincide)) {
        exigirRoles($rolesGestion);
        verificarCsrf();
        responder(['data' => ['eliminado' => $controlador->eliminarAsignacion((int) $coincide[1])], 'message' => 'Asignación eliminada correctamente.']);
    }

    if ($metodo === 'GET' && $ruta === '/mi-ruta') {
        $usuario = exigirRoles(['cuadrilla']);
        $idUsuario = (int) ($usuario['idUsu'] ?? 0);
        responder(['data' => $controlador->obtenerMiRuta($idUsuario)]);
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
    $permitidos = ['http://localhost:8090', 'http://127.0.0.1:8090', 'http://192.168.1.10:8090'];

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

function exigirSesion(): array
{
    if (empty($_SESSION['usuario'])) {
        responder(['error' => 'Debés iniciar sesión.'], 401);
    }
    return $_SESSION['usuario'];
}

function exigirRoles(array $rolesPermitidos): array
{
    $usuario = exigirSesion();
    $rol = $usuario['rol'] ?? '';
    if (!in_array($rol, $rolesPermitidos, true)) {
        responder(['error' => 'No tenés permisos para realizar esta acción.'], 403);
    }
    return $usuario;
}

function responder(array $contenido, int $estado = 200): never
{
    http_response_code($estado);
    echo json_encode($contenido, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
