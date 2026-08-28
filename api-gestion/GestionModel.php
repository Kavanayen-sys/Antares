<?php

declare(strict_types=1);

require_once __DIR__ . '/Conexion.php';

class GestionModel
{
    private mysqli $conexion;

    public function __construct()
    {
        $this->conexion = conectarBase();
    }

    // =========================================================
    // CONTENEDORES
    // =========================================================

    public function listarContenedores(): array
    {
        $sql = 'SELECT idCon, capacidad, calle, esquina, zona, estCon, tipoCon, repuesto
                FROM contenedor
                ORDER BY idCon';

        return $this->conexion->query($sql)->fetch_all(MYSQLI_ASSOC);
    }

    public function obtenerContenedor(int $id): ?array
    {
        $stmt = $this->conexion->prepare(
            'SELECT *
             FROM contenedor
             WHERE idCon = ?'
        );

        $stmt->bind_param('i', $id);
        $stmt->execute();

        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    public function crearContenedor(array $datos): array
    {
        $stmt = $this->conexion->prepare(
            'INSERT INTO contenedor
            (capacidad, calle, esquina, zona, estCon, tipoCon, repuesto)
            VALUES (?, ?, ?, ?, ?, ?, ?)'
        );

        $stmt->bind_param(
            'dsssssi',
            $datos['capacidad'],
            $datos['calle'],
            $datos['esquina'],
            $datos['zona'],
            $datos['estado'],
            $datos['tipo'],
            $datos['repuesto']
        );

        $stmt->execute();

        return $this->obtenerContenedor(
            (int) $this->conexion->insert_id
        );
    }

    public function actualizarContenedor(int $id, array $datos): ?array
    {
        $stmt = $this->conexion->prepare(
            'UPDATE contenedor
             SET capacidad = ?,
                 calle = ?,
                 esquina = ?,
                 zona = ?,
                 estCon = ?,
                 tipoCon = ?,
                 repuesto = ?
             WHERE idCon = ?'
        );

        $stmt->bind_param(
            'dssssssi',
            $datos['capacidad'],
            $datos['calle'],
            $datos['esquina'],
            $datos['zona'],
            $datos['estado'],
            $datos['tipo'],
            $datos['repuesto'],
            $id
        );

        $stmt->execute();

        return $this->obtenerContenedor($id);
    }

    public function eliminarContenedor(int $id): bool
    {
        $stmt = $this->conexion->prepare(
            'DELETE FROM contenedor
             WHERE idCon = ?'
        );

        $stmt->bind_param('i', $id);
        $stmt->execute();

        return $stmt->affected_rows > 0;
    }


    // =========================================================
    // VEHICULOS
    // =========================================================

    public function listarVehi(): array
    {
        $sql = 'SELECT idVehi,
                       tipoVehi,
                       matriculaVehi,
                       marcaVehi,
                       modeloVehi,
                       capVehi,
                       estVehi
                FROM vehiculo
                ORDER BY idVehi';

        return $this->conexion->query($sql)->fetch_all(MYSQLI_ASSOC);
    }

    public function obtenerVehi(int $id): ?array
    {
        $stmt = $this->conexion->prepare(
            'SELECT *
             FROM vehiculo
             WHERE idVehi = ?'
        );

        $stmt->bind_param('i', $id);
        $stmt->execute();

        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    public function matriculaExiste(
        string $matriculaVehi,
        int $ignorarId = 0
    ): bool {
        $stmt = $this->conexion->prepare(
            'SELECT idVehi
             FROM vehiculo
             WHERE matriculaVehi = ?
             AND idVehi <> ?
             LIMIT 1'
        );

        $stmt->bind_param(
            'si',
            $matriculaVehi,
            $ignorarId
        );

        $stmt->execute();

        return $stmt->get_result()->num_rows > 0;
    }

    public function crearVehi(array $datos): array
    {
        $stmt = $this->conexion->prepare(
            'INSERT INTO vehiculo
            (tipoVehi, matriculaVehi, marcaVehi, modeloVehi, capVehi, estVehi)
            VALUES (?, ?, ?, ?, ?, ?)'
        );

        $stmt->bind_param(
            'ssssds',
            $datos['tipo'],
            $datos['matricula'],
            $datos['marca'],
            $datos['modelo'],
            $datos['capacidad'],
            $datos['estado']
        );

        $stmt->execute();

        return $this->obtenerVehi(
            (int) $this->conexion->insert_id
        );
    }

    public function actualizarVehiculo(
        int $id,
        array $datos
    ): ?array {
        $stmt = $this->conexion->prepare(
            'UPDATE vehiculo
             SET tipoVehi = ?,
                 matriculaVehi = ?,
                 marcaVehi = ?,
                 modeloVehi = ?,
                 capVehi = ?,
                 estVehi = ?
             WHERE idVehi = ?'
        );

        $stmt->bind_param(
            'ssssssi',
            $datos['tipo'],
            $datos['matricula'],
            $datos['marca'],
            $datos['modelo'],
            $datos['capacidad'],
            $datos['estado'],
            $id
        );

        $stmt->execute();

        return $this->obtenerVehi($id);
    }

    public function eliminarVehiculo(int $id): bool
    {
        $stmt = $this->conexion->prepare(
            'DELETE FROM vehiculo
             WHERE idVehi = ?'
        );

        $stmt->bind_param('i', $id);
        $stmt->execute();

        return $stmt->affected_rows > 0;
    }


    // =========================================================
    // INCIDENCIAS
    // =========================================================

    public function listarIncidencias(): array
    {
        $sql = 'SELECT
                    i.idInci,
                    i.fchaInci,
                    i.idCon,
                    i.tipoInci,
                    i.descInci,
                    i.cedHashInci,
                    i.prioridad,
                    CONCAT(c.calle, " y ", c.esquina) AS ubicacion
                FROM incidencia i
                INNER JOIN contenedor c
                    ON c.idCon = i.idCon
                ORDER BY i.idInci DESC';

        return $this->conexion->query($sql)->fetch_all(MYSQLI_ASSOC);
    }

    public function crearIncidencia(array $datos): array
    {
        $identificadorProtegido = hash(
            'sha256',
            $datos['documento']
        );

        $stmt = $this->conexion->prepare(
            'INSERT INTO incidencia
            (idCon, tipoInci, descInci, cedHashInci, prioridad)
            VALUES (?, ?, ?, ?, ?)'
        );

        $stmt->bind_param(
            'issss',
            $datos['idCon'],
            $datos['tipo'],
            $datos['descripcion'],
            $identificadorProtegido,
            $datos['prioridad']
        );

        $stmt->execute();

        return [
            'idInci' => (int) $this->conexion->insert_id,
            'idCon' => $datos['idCon'],
            'tipoInci' => $datos['tipo'],
        ];
    }

    public function eliminarIncidencia(int $id): bool
    {
        $stmt = $this->conexion->prepare(
            'DELETE FROM incidencia
             WHERE idInci = ?'
        );

        $stmt->bind_param('i', $id);
        $stmt->execute();

        return $stmt->affected_rows > 0;
    }
}