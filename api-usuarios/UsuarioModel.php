<?php

declare(strict_types=1);

require_once __DIR__ . '/Conexion.php';

class UsuarioModel
{
    private mysqli $conexion;

    public function __construct()
    {
        $this->conexion = conectarBase();
    }

    public function obtenerTodos(): array
    {
        $sql = 'SELECT idUsu, priNom, telUsu, email, rol, estUsu FROM usuario ORDER BY idUsu';
        return $this->conexion->query($sql)->fetch_all(MYSQLI_ASSOC);
    }

    public function obtenerPorId(int $id): ?array
    {
        $stmt = $this->conexion->prepare(
            'SELECT idUsu, priNom, telUsu, email, rol, estUsu FROM usuario WHERE idUsu = ?'
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    public function obtenerPorEmail(string $email): ?array
    {
        $stmt = $this->conexion->prepare('SELECT * FROM usuario WHERE email = ? LIMIT 1');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    public function emailExiste(string $email, int $ignorarId = 0): bool
    {
        $stmt = $this->conexion->prepare(
            'SELECT idUsu FROM usuario WHERE email = ? AND idUsu <> ? LIMIT 1'
        );
        $stmt->bind_param('si', $email, $ignorarId);
        $stmt->execute();
        return $stmt->get_result()->num_rows > 0;
    }

    public function crear(
        string $nombre,
        string $telefono,
        string $email,
        string $password,
        string $rol,
        string $estado
    ): array {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->conexion->prepare(
            'INSERT INTO usuario (priNom, telUsu, email, hash, rol, estUsu)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('ssssss', $nombre, $telefono, $email, $hash, $rol, $estado);
        $stmt->execute();
        return $this->obtenerPorId($this->conexion->insert_id);
    }

    public function actualizar(
        int $id,
        string $nombre,
        string $telefono,
        string $email,
        string $rol,
        string $estado,
        ?string $password
    ): ?array {
        if ($password !== null && $password !== '') {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $this->conexion->prepare(
                'UPDATE usuario
                 SET priNom = ?, telUsu = ?, email = ?, rol = ?, estUsu = ?, hash = ?
                 WHERE idUsu = ?'
            );
            $stmt->bind_param('ssssssi', $nombre, $telefono, $email, $rol, $estado, $hash, $id);
        } else {
            $stmt = $this->conexion->prepare(
                'UPDATE usuario
                 SET priNom = ?, telUsu = ?, email = ?, rol = ?, estUsu = ?
                 WHERE idUsu = ?'
            );
            $stmt->bind_param('sssssi', $nombre, $telefono, $email, $rol, $estado, $id);
        }
        $stmt->execute();
        return $this->obtenerPorId($id);
    }

    public function eliminar(int $id): bool
    {
        $stmt = $this->conexion->prepare('DELETE FROM usuario WHERE idUsu = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        return $stmt->affected_rows > 0;
    }
}
