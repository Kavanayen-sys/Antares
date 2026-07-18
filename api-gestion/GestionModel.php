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

    public function listarContenedores(): array
    {
        $sql = 'SELECT idCon, capacidad, calle, esquina, zona, estCon, tipoCon, repuesto
                FROM contenedor ORDER BY idCon';
        return $this->conexion->query($sql)->fetch_all(MYSQLI_ASSOC);
    }

    public function obtenerContenedor(int $id): ?array
    {
        $stmt = $this->conexion->prepare('SELECT * FROM contenedor WHERE idCon = ?');
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
        return $this->obtenerContenedor($this->conexion->insert_id);
    }

    public function actualizarContenedor(int $id, array $datos): ?array
    {
        $stmt = $this->conexion->prepare(
            'UPDATE contenedor
             SET capacidad = ?, calle = ?, esquina = ?, zona = ?, estCon = ?, tipoCon = ?, repuesto = ?
             WHERE idCon = ?'
        );
        $stmt->bind_param(
            'dsssssii',
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
        $stmt = $this->conexion->prepare('DELETE FROM contenedor WHERE idCon = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        return $stmt->affected_rows > 0;
    }

    public function listarCamiones(): array
    {
        $sql = 'SELECT idCamion, tipoCamion, matricula, marca, modelo, capacidad, estCamion, repuesto
                FROM camion ORDER BY idCamion';
        return $this->conexion->query($sql)->fetch_all(MYSQLI_ASSOC);
    }

    public function obtenerCamion(int $id): ?array
    {
        $stmt = $this->conexion->prepare('SELECT * FROM camion WHERE idCamion = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    public function matriculaExiste(string $matricula, int $ignorarId = 0): bool
    {
        $stmt = $this->conexion->prepare(
            'SELECT idCamion FROM camion WHERE matricula = ? AND idCamion <> ? LIMIT 1'
        );
        $stmt->bind_param('si', $matricula, $ignorarId);
        $stmt->execute();
        return $stmt->get_result()->num_rows > 0;
    }

    public function crearCamion(array $datos): array
    {
        $stmt = $this->conexion->prepare(
            'INSERT INTO camion
             (tipoCamion, matricula, marca, modelo, capacidad, estCamion, repuesto)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param(
            'ssssdsi',
            $datos['tipo'],
            $datos['matricula'],
            $datos['marca'],
            $datos['modelo'],
            $datos['capacidad'],
            $datos['estado'],
            $datos['repuesto']
        );
        $stmt->execute();
        return $this->obtenerCamion($this->conexion->insert_id);
    }

    public function actualizarCamion(int $id, array $datos): ?array
    {
        $stmt = $this->conexion->prepare(
            'UPDATE camion
             SET tipoCamion = ?, matricula = ?, marca = ?, modelo = ?, capacidad = ?, estCamion = ?, repuesto = ?
             WHERE idCamion = ?'
        );
        $stmt->bind_param(
            'ssssdsii',
            $datos['tipo'],
            $datos['matricula'],
            $datos['marca'],
            $datos['modelo'],
            $datos['capacidad'],
            $datos['estado'],
            $datos['repuesto'],
            $id
        );
        $stmt->execute();
        return $this->obtenerCamion($id);
    }

    public function eliminarCamion(int $id): bool
    {
        $stmt = $this->conexion->prepare('DELETE FROM camion WHERE idCamion = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        return $stmt->affected_rows > 0;
    }

    public function listarIncidencias(): array
    {
        $sql = 'SELECT i.idIncidencia, i.fchInci, i.idCon, i.tipoInci, i.descInci,
                       i.nomReporte, i.telReporte, i.prioridad,
                       CONCAT(c.calle, " y ", c.esquina) AS ubicacion
                FROM incidencia i
                INNER JOIN contenedor c ON c.idCon = i.idCon
                ORDER BY i.idIncidencia DESC';
        return $this->conexion->query($sql)->fetch_all(MYSQLI_ASSOC);
    }

    public function crearIncidencia(array $datos): array
    {
        $identificadorProtegido = hash('sha256', $datos['documento']);
        $stmt = $this->conexion->prepare(
            'INSERT INTO incidencia
             (idCon, tipoInci, descInci, nomReporte, cedHashInci, telReporte, prioridad)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param(
            'issssss',
            $datos['idCon'],
            $datos['tipo'],
            $datos['descripcion'],
            $datos['nombre'],
            $identificadorProtegido,
            $datos['telefono'],
            $datos['prioridad']
        );
        $stmt->execute();

        return [
            'idIncidencia' => $this->conexion->insert_id,
            'idCon' => $datos['idCon'],
            'tipoInci' => $datos['tipo'],
        ];
    }

    public function eliminarIncidencia(int $id): bool
    {
        $stmt = $this->conexion->prepare('DELETE FROM incidencia WHERE idIncidencia = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        return $stmt->affected_rows > 0;
    }
}
