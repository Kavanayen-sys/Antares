<?php

declare(strict_types=1);

if (!function_exists('conectarBase')) {
    function conectarBase(): mysqli
    {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        if (!getenv('SIGERU_DB_HOST') && (file_exists(__DIR__ . '/../../.env') || file_exists(__DIR__ . '/../.env'))) {
            $lineas = file(file_exists(__DIR__ . '/../../.env') ? __DIR__ . '/../../.env' : __DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lineas as $linea) {
                $linea = trim($linea);
                if ($linea === '' || str_starts_with($linea, '#')) continue;
                if (str_contains($linea, '=')) {
                    [$k, $v] = explode('=', $linea, 2);
                    $k = trim($k);
                    $v = trim($v, " \t\n\r\0\x0B\"'");
                    putenv("{$k}={$v}");
                    $_ENV[$k] = $v;
                }
            }
        }

        $host = getenv('SIGERU_DB_HOST') ?: '127.0.0.1';
        $puerto = (int) (getenv('SIGERU_DB_PORT') ?: 3306);
        $usuario = getenv('SIGERU_DB_USER') ?: 'root';
        $password = getenv('SIGERU_DB_PASSWORD') ?: '';
        $base = getenv('SIGERU_DB_NAME') ?: 'sigeru';

        $conexion = new mysqli($host, $usuario, $password, $base, $puerto);
        $conexion->set_charset('utf8mb4');

        return $conexion;
    }
}