<?php

declare(strict_types=1);

require_once __DIR__ . '/Conexion.php';

class CentroModel
{
    private mysqli $conexion;

    public function __construct()
    {
        $this->conexion = conectarBase();
    }

    public function listarMaquinaria(): array
    {
        $sql = 'SELECT m.idMaq, m.idCentro, m.propositoMaq, m.capMaq, m.marcaMaq, m.modeloMaq, m.numSerie,
                       c.tipoCentro, c.capCentro, c.ubiCentro
                FROM maquinaria m
                LEFT JOIN centro c ON c.idCentro = m.idCentro
                ORDER BY m.idMaq ASC';

        return $this->conexion->query($sql)->fetch_all(MYSQLI_ASSOC);
    }

    public function crearMaquinaria(array $datos): array
    {
        $stmt = $this->conexion->prepare(
            'INSERT INTO maquinaria (idCentro, propositoMaq, capMaq, marcaMaq, modeloMaq, numSerie)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('isdsss', $datos['idCentro'], $datos['proposito'], $datos['capacidad'], $datos['marca'], $datos['modelo'], $datos['numSerie']);
        $stmt->execute();
        $id = (int) $this->conexion->insert_id;

        return [
            'idMaq' => $id,
            'idCentro' => $datos['idCentro'],
            'propositoMaq' => $datos['proposito'],
            'capMaq' => $datos['capacidad'],
            'marcaMaq' => $datos['marca'],
            'modeloMaq' => $datos['modelo'],
            'numSerie' => $datos['numSerie'],
        ];
    }

    public function obtenerMaquinaria(int $id): ?array
    {
        $stmt = $this->conexion->prepare('SELECT idMaq, idCentro, propositoMaq, capMaq, marcaMaq, modeloMaq, numSerie FROM maquinaria WHERE idMaq = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    public function actualizarMaquinaria(int $id, array $datos): ?array
    {
        $stmt = $this->conexion->prepare(
            'UPDATE maquinaria
             SET idCentro = ?, propositoMaq = ?, capMaq = ?, marcaMaq = ?, modeloMaq = ?, numSerie = ?
             WHERE idMaq = ?'
        );
        $stmt->bind_param('isdsssi', $datos['idCentro'], $datos['proposito'], $datos['capacidad'], $datos['marca'], $datos['modelo'], $datos['numSerie'], $id);
        $stmt->execute();
        return $this->obtenerMaquinaria($id);
    }

    public function eliminarMaquinaria(int $id): bool
    {
        $stmt = $this->conexion->prepare('DELETE FROM maquinaria WHERE idMaq = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();

        return $stmt->affected_rows > 0;
    }

    public function listarCentros(): array
    {
        $sql = 'SELECT idCentro, ubiCentro, tipoCentro, capCentro FROM centro ORDER BY idCentro ASC';
        return $this->conexion->query($sql)->fetch_all(MYSQLI_ASSOC);
    }

    public function obtenerCentro(int $id): ?array
    {
        $stmt = $this->conexion->prepare('SELECT idCentro, ubiCentro, tipoCentro, capCentro FROM centro WHERE idCentro = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    public function crearCentro(array $datos): array
    {
        $stmt = $this->conexion->prepare('INSERT INTO centro (ubiCentro, tipoCentro, capCentro) VALUES (?, ?, ?)');
        $stmt->bind_param('ssi', $datos['ubiCentro'], $datos['tipoCentro'], $datos['capCentro']);
        $stmt->execute();
        return [
            'idCentro' => (int) $this->conexion->insert_id,
            'ubiCentro' => $datos['ubiCentro'],
            'tipoCentro' => $datos['tipoCentro'],
            'capCentro' => $datos['capCentro'],
        ];
    }

    public function actualizarCentro(int $id, array $datos): ?array
    {
        $stmt = $this->conexion->prepare('UPDATE centro SET ubiCentro = ?, tipoCentro = ?, capCentro = ? WHERE idCentro = ?');
        $stmt->bind_param('ssii', $datos['ubiCentro'], $datos['tipoCentro'], $datos['capCentro'], $id);
        $stmt->execute();
        return $this->obtenerCentro($id);
    }

    public function eliminarCentro(int $id): bool
    {
        $delMaq = $this->conexion->prepare('DELETE FROM maquinaria WHERE idCentro = ?');
        $delMaq->bind_param('i', $id);
        $delMaq->execute();

        $delEnv = $this->conexion->prepare('DELETE FROM enviares WHERE idCentro = ?');
        $delEnv->bind_param('i', $id);
        $delEnv->execute();

        $stmtRutas = $this->conexion->prepare('SELECT idRuta FROM ruta WHERE idCentro = ?');
        $stmtRutas->bind_param('i', $id);
        $stmtRutas->execute();
        $rutas = $stmtRutas->get_result()->fetch_all(MYSQLI_ASSOC);
        foreach ($rutas as $r) {
            $idRuta = (int) $r['idRuta'];
            $delComp = $this->conexion->prepare('DELETE FROM compone WHERE idRuta = ?');
            $delComp->bind_param('i', $idRuta);
            $delComp->execute();

            $delRec = $this->conexion->prepare('DELETE FROM recorrido WHERE idRuta = ?');
            $delRec->bind_param('i', $idRuta);
            $delRec->execute();

            $delRuta = $this->conexion->prepare('DELETE FROM ruta WHERE idRuta = ?');
            $delRuta->bind_param('i', $idRuta);
            $delRuta->execute();
        }

        $stmt = $this->conexion->prepare('DELETE FROM centro WHERE idCentro = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        return $stmt->affected_rows > 0;
    }

    public function listarVertederos(): array
    {
        $sql = 'SELECT idVertedero, ubicacionVertedero FROM vertedero ORDER BY idVertedero ASC';
        return $this->conexion->query($sql)->fetch_all(MYSQLI_ASSOC);
    }

    public function crearVertedero(array $datos): array
    {
        $stmt = $this->conexion->prepare('INSERT INTO vertedero (ubicacionVertedero) VALUES (?)');
        $stmt->bind_param('s', $datos['ubicacion']);
        $stmt->execute();
        return [
            'idVertedero' => (int) $this->conexion->insert_id,
            'ubicacionVertedero' => $datos['ubicacion'],
        ];
    }
}
