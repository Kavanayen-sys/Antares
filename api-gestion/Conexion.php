<?php

declare(strict_types=1);

function conectarBase(): mysqli
{
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    $host = getenv('SIGERU_DB_HOST') ?: 'localhost';
    $puerto = (int) (getenv('SIGERU_DB_PORT') ?: 3306);
    $usuario = getenv('SIGERU_DB_USER') ?: 'root';
    $password = getenv('SIGERU_DB_PASSWORD') ?: '';
    $base = getenv('SIGERU_DB_NAME') ?: 'sigeru';

    $conexion = new mysqli($host, $usuario, $password, $base, $puerto);
    $conexion->set_charset('utf8mb4');

    return $conexion;
}
