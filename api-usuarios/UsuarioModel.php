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
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->conexion->prepare(
            'INSERT INTO usuario (priNom, telUsu, email, passwordHash, rol, estUsu)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('ssssss', $nombre, $telefono, $email, $passwordHash, $rol, $estado);
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
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $this->conexion->prepare(
                'UPDATE usuario
                 SET priNom = ?, telUsu = ?, email = ?, rol = ?, estUsu = ?, passwordHash = ?
                 WHERE idUsu = ?'
            );
            $stmt->bind_param('ssssssi', $nombre, $telefono, $email, $rol, $estado, $passwordHash, $id);
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
        $stmtCuad = $this->conexion->prepare('SELECT idCuadrilla FROM cuadrilla WHERE idChofer = ? OR idPeon = ?');
        $stmtCuad->bind_param('ii', $id, $id);
        $stmtCuad->execute();
        $cuadrillas = $stmtCuad->get_result()->fetch_all(MYSQLI_ASSOC);

        foreach ($cuadrillas as $c) {
            $idC = (int) $c['idCuadrilla'];

            $stmtOps = $this->conexion->prepare('SELECT idVehi, fchOperacion FROM opera WHERE idCuadrilla = ?');
            $stmtOps->bind_param('i', $idC);
            $stmtOps->execute();
            $ops = $stmtOps->get_result()->fetch_all(MYSQLI_ASSOC);

            foreach ($ops as $op) {
                $idV = (int) $op['idVehi'];
                $f = $op['fchOperacion'];
                $delR = $this->conexion->prepare('DELETE FROM recorrido WHERE idVehi = ? AND fechaRec = ?');
                $delR->bind_param('is', $idV, $f);
                $delR->execute();

                $this->conexion->query("UPDATE vehiculo SET estVehi = 'disponible' WHERE idVehi = {$idV} AND estVehi = 'en uso'");
            }

            $delO = $this->conexion->prepare('DELETE FROM opera WHERE idCuadrilla = ?');
            $delO->bind_param('i', $idC);
            $delO->execute();

            $delC = $this->conexion->prepare('DELETE FROM cuadrilla WHERE idCuadrilla = ?');
            $delC->bind_param('i', $idC);
            $delC->execute();
        }

        $delRes = $this->conexion->prepare('DELETE FROM resuelve WHERE idUsu = ?');
        $delRes->bind_param('i', $id);
        $delRes->execute();

        $stmt = $this->conexion->prepare('DELETE FROM usuario WHERE idUsu = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        return $stmt->affected_rows > 0;
    }
}
