<?php

declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/UsuarioController.php';
require_once __DIR__ . '/TotpHelper.php';

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
        responder(['data' => $controlador->registrar($datos), 'message' => '¡Usuario registrado correctamente!'], 201);
    }
    if ($metodo === 'POST' && $ruta === '/recuperar-password') {
        verificarCsrf();
        $email = trim((string) ($datos['email'] ?? ''));
        $codigo = trim((string) ($datos['codigo'] ?? ''));
        $nuevaPassword = (string) ($datos['password'] ?? '');

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            responder(['error' => 'Ingresá un correo electrónico válido.'], 422);
        }
        if (strlen($codigo) !== 6 || !ctype_digit($codigo)) {
            responder(['error' => 'El código de Authy / Authenticator debe tener 6 dígitos.'], 422);
        }
        if (strlen($nuevaPassword) < 6) {
            responder(['error' => 'La nueva contraseña debe tener al menos 6 caracteres.'], 422);
        }

        $usuario = $controlador->obtenerPorEmail($email);
        if (!$usuario) {
            responder(['error' => 'No se encontró ninguna cuenta asociada a este correo.'], 404);
        }

        // Obtener el secreto de 2FA configurado para este usuario
        $secret = TotpHelper::obtenerSecretParaUsuario($usuario['email'], (int) $usuario['idUsu']);
        if (!TotpHelper::verificar($secret, $codigo, 2)) {
            responder(['error' => 'Código de Authy incorrecto o expirado. Verificá tu aplicación.'], 401);
        }

        // Actualizar la contraseña
        $controlador->actualizarPassword((int) $usuario['idUsu'], $nuevaPassword);
        responder(['message' => '¡Contraseña restablecida exitosamente! Ya podés ingresar con tu nueva clave.']);
    }
    if ($metodo === 'POST' && $ruta === '/login') {
        verificarCsrf();
        controlarIntentos();
        $usuario = $controlador->ingresar($datos);
        
        // Obtener secreto TOTP (Base32) constante para el usuario (no requiere cambios en la base de datos)
        $secret = TotpHelper::obtenerSecretParaUsuario($usuario['email'], (int) $usuario['idUsu']);

        // URI estándar RFC 6238 para Google Authenticator / Authy
        $urlOtp = TotpHelper::urlOtpAuth($usuario['email'], $secret, 'SiGeRU');

        $_SESSION['2fa_pendiente'] = [
            'idUsu' => (int) $usuario['idUsu'],
            'usuario' => $usuario,
            'secret' => $secret,
            'expira' => time() + 300
        ];

        responder([
            'requiere2fa' => true,
            'urlOtp' => $urlOtp,
            'secret' => $secret,
            'email' => $usuario['email'],
            'message' => 'Escaneá el código QR con Google Authenticator o Authy.'
        ]);
    }
    if ($metodo === 'POST' && $ruta === '/verificar-2fa') {
        verificarCsrf();
        $pendiente = $_SESSION['2fa_pendiente'] ?? null;
        if (!$pendiente) {
            responder(['error' => 'No hay ningún inicio de sesión pendiente o ya expiró.'], 400);
        }
        if (time() > $pendiente['expira']) {
            unset($_SESSION['2fa_pendiente']);
            responder(['error' => 'El código expiró. Por favor iniciá sesión de nuevo.'], 401);
        }
        $codigoIngresado = trim((string) ($datos['codigo'] ?? ''));
        if (!TotpHelper::verificar($pendiente['secret'], $codigoIngresado)) {
            responder(['error' => 'El código es incorrecto. Verificá el número en tu aplicación.'], 401);
        }

        $usuario = $pendiente['usuario'];
        unset($_SESSION['2fa_pendiente']);
        session_regenerate_id(true);
        $_SESSION['usuario'] = $usuario;
        $_SESSION['intentos_login'] = 0;

        // Marcar sesión como activa en la base de datos
        $controlador->marcarSesionActiva((int) $usuario['idUsu'], true);

        // CU-30: Registrar acceso con fecha y hora
        registrarLogAcceso((int) $usuario['idUsu'], $usuario['email'], $usuario['rol'], true);

        responder(['data' => $usuario, 'message' => 'Inicio de sesión correcto.']);
    }
    if ($metodo === 'POST' && $ruta === '/solicitar-recuperacion') {
        verificarCsrf();
        $email = strtolower(trim((string) ($datos['email'] ?? '')));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            responder(['error' => 'Ingresá un correo electrónico válido.'], 422);
        }
        $usuario = $controlador->obtenerPorEmail($email);
        if (!$usuario) {
            responder(['error' => 'No se encontró ninguna cuenta asociada a este correo.'], 404);
        }

        // Generar código de 6 dígitos (Narrativa 8)
        $codigo = (string) random_int(100000, 999999);
        $archivoCodigos = __DIR__ . '/recovery_codes.json';
        $codigos = file_exists($archivoCodigos) ? json_decode((string) file_get_contents($archivoCodigos), true) : [];
        if (!is_array($codigos)) $codigos = [];

        $codigos[$email] = [
            'codigo' => $codigo,
            'expira' => time() + 900 // 15 minutos
        ];
        @file_put_contents($archivoCodigos, json_encode($codigos, JSON_PRETTY_PRINT));

        // En un servidor real se envía vía SMTP. Aquí enviamos mensaje y el código simulado
        responder([
            'message' => '¡Correo enviado con éxito! Revisa tu casilla o utilizá el código generado.',
            'codigoSimulado' => $codigo,
            'email' => $email
        ]);
    }
    if ($metodo === 'POST' && $ruta === '/confirmar-recuperacion') {
        verificarCsrf();
        $email = strtolower(trim((string) ($datos['email'] ?? '')));
        $codigo = trim((string) ($datos['codigo'] ?? ''));
        $nuevaPassword = (string) ($datos['password'] ?? '');
        $confirmPassword = (string) ($datos['confirmPassword'] ?? '');

        if ($nuevaPassword !== $confirmPassword) {
            responder(['error' => 'Las contraseñas no coinciden.'], 422);
        }
        if (strlen($nuevaPassword) < 6) {
            responder(['error' => 'La contraseña debe tener al menos 6 caracteres.'], 422);
        }

        $archivoCodigos = __DIR__ . '/recovery_codes.json';
        $codigos = file_exists($archivoCodigos) ? json_decode((string) file_get_contents($archivoCodigos), true) : [];
        $reg = $codigos[$email] ?? null;

        if (!$reg || time() > $reg['expira']) {
            responder(['error' => 'El código de recuperación expiró o no fue solicitado.'], 401);
        }
        if ($reg['codigo'] !== $codigo) {
            responder(['error' => 'El código de verificación ingresado es incorrecto.'], 401);
        }

        $usuario = $controlador->obtenerPorEmail($email);
        if (!$usuario) {
            responder(['error' => 'Usuario no encontrado.'], 404);
        }

        $controlador->actualizarPassword((int) $usuario['idUsu'], $nuevaPassword);
        unset($codigos[$email]);
        @file_put_contents($archivoCodigos, json_encode($codigos, JSON_PRETTY_PRINT));

        responder(['message' => '¡Contraseña restablecida exitosamente! Ya podés iniciar sesión.']);
    }
    if ($metodo === 'PUT' && $ruta === '/mi-perfil') {
        exigirSesion();
        verificarCsrf();
        $idActual = (int) $_SESSION['usuario']['idUsu'];
        $act = $controlador->actualizarPerfil($idActual, $datos);
        $_SESSION['usuario'] = array_merge($_SESSION['usuario'], $act);
        responder(['data' => $act, 'message' => '¡Tus datos fueron actualizados correctamente!']);
    }
    if ($metodo === 'GET' && $ruta === '/logs-login') {
        exigirAdministrador();
        $archivo = __DIR__ . '/log_accesos.json';
        $logs = file_exists($archivo) ? json_decode((string) file_get_contents($archivo), true) : [];
        responder(['data' => is_array($logs) ? array_reverse($logs) : []]);
    }
    if ($metodo === 'POST' && $ruta === '/reenviar-2fa') {
        verificarCsrf();
        $pendiente = $_SESSION['2fa_pendiente'] ?? null;
        if (!$pendiente) {
            responder(['error' => 'No hay ningún inicio de sesión pendiente.'], 400);
        }
        $secret = TotpHelper::obtenerSecretParaUsuario($pendiente['usuario']['email'], (int) $pendiente['idUsu']);
        $_SESSION['2fa_pendiente']['secret'] = $secret;
        $_SESSION['2fa_pendiente']['expira'] = time() + 300;

        $urlOtp = TotpHelper::urlOtpAuth($pendiente['usuario']['email'], $secret, 'SiGeRU');

        responder([
            'urlOtp' => $urlOtp,
            'secret' => $secret,
            'message' => 'Código QR listo. Escanealo para vincular tu app.'
        ]);
    }
    if ($metodo === 'GET' && $ruta === '/sesion') {
        exigirSesion();
        responder(['data' => $_SESSION['usuario'], 'csrf' => tokenCsrf()]);
    }
    if ($metodo === 'POST' && $ruta === '/logout') {
        exigirSesion();
        verificarCsrf();
        $idUsuActual = (int) ($_SESSION['usuario']['idUsu'] ?? 0);
        if ($idUsuActual > 0) {
            $controlador->marcarSesionActiva($idUsuActual, false);
        }
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

function enmascararEmail(string $email): string
{
    $partes = explode('@', $email);
    if (count($partes) !== 2) {
        return $email;
    }
    $nombre = $partes[0];
    $visible = substr($nombre, 0, min(2, strlen($nombre)));
    return $visible . '***@' . $partes[1];
}

function registrarLogAcceso(int $idUsu, string $email, string $rol, bool $exito): void
{
    try {
        $archivo = __DIR__ . '/log_accesos.json';
        $logs = file_exists($archivo) ? json_decode((string) file_get_contents($archivo), true) : [];
        if (!is_array($logs)) {
            $logs = [];
        }
        $logs[] = [
            'idUsu' => $idUsu,
            'email' => $email,
            'rol' => $rol,
            'exito' => $exito,
            'fechaHora' => date('Y-m-d H:i:s'),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            'userAgent' => substr($_SERVER['HTTP_USER_AGENT'] ?? 'Desconocido', 0, 100)
        ];
        if (count($logs) > 500) {
            array_shift($logs);
        }
        @file_put_contents($archivo, json_encode($logs, JSON_PRETTY_PRINT));
    } catch (\Throwable $e) {
        // Silencioso
    }
}
