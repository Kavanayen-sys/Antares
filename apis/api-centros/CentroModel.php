<?php

declare(strict_types=1);

require_once __DIR__ . '/Conexion.php';

class CentroModel
{
    private mysqli $conexion;

    public function __construct(?mysqli $conexion = null)
    {
        $this->conexion = $conexion ?? conectarBase();
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
        $sql = 'SELECT c.idCentro, c.ubiCentro, c.tipoCentro, c.capCentro,
                       COALESCE(SUM(v.capVehi), 0) AS capActual,
                       COUNT(DISTINCT rec.idRecorrido) AS totalRecorridos
                FROM centro c
                LEFT JOIN ruta r ON r.idCentro = c.idCentro
                LEFT JOIN recorrido rec ON rec.idRuta = r.idRuta
                LEFT JOIN vehiculo v ON v.idVehi = rec.idVehi
                GROUP BY c.idCentro, c.ubiCentro, c.tipoCentro, c.capCentro
                ORDER BY c.idCentro ASC';
        return $this->conexion->query($sql)->fetch_all(MYSQLI_ASSOC);
    }

    public function obtenerCentro(int $id): ?array
    {
        $stmt = $this->conexion->prepare(
            'SELECT c.idCentro, c.ubiCentro, c.tipoCentro, c.capCentro,
                    COALESCE(SUM(v.capVehi), 0) AS capActual,
                    COUNT(DISTINCT rec.idRecorrido) AS totalRecorridos
             FROM centro c
             LEFT JOIN ruta r ON r.idCentro = c.idCentro
             LEFT JOIN recorrido rec ON rec.idRuta = r.idRuta
             LEFT JOIN vehiculo v ON v.idVehi = rec.idVehi
             WHERE c.idCentro = ?
             GROUP BY c.idCentro, c.ubiCentro, c.tipoCentro, c.capCentro'
        );
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

    public function obtenerVertedero(int $id): ?array
    {
        $stmt = $this->conexion->prepare('SELECT idVertedero, ubicacionVertedero FROM vertedero WHERE idVertedero = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
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

    public function actualizarVertedero(int $id, string $ubicacion): ?array
    {
        $stmt = $this->conexion->prepare('UPDATE vertedero SET ubicacionVertedero = ? WHERE idVertedero = ?');
        $stmt->bind_param('si', $ubicacion, $id);
        $stmt->execute();
        return $this->obtenerVertedero($id);
    }

    public function eliminarVertedero(int $id): bool
    {
        $stmtEnv = $this->conexion->prepare('DELETE FROM enviares WHERE idVertedero = ?');
        $stmtEnv->bind_param('i', $id);
        $stmtEnv->execute();

        $stmt = $this->conexion->prepare('DELETE FROM vertedero WHERE idVertedero = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        return $stmt->affected_rows > 0;
    }

    public function registrarLlegadaCamion(int $idVehi, int $idCentro, int $idCuadrilla, ?int $idRuta = null): array
    {
        $stmtCentro = $this->conexion->prepare('SELECT idCentro, tipoCentro FROM centro WHERE idCentro = ?');
        $stmtCentro->bind_param('i', $idCentro);
        $stmtCentro->execute();
        $centro = $stmtCentro->get_result()->fetch_assoc();
        if (!$centro) {
            throw new OutOfBoundsException('El centro de acopio no existe.');
        }

        if (!$idRuta || $idRuta <= 0) {
            $stmtRuta = $this->conexion->prepare('SELECT idRuta FROM ruta WHERE idCentro = ? LIMIT 1');
            $stmtRuta->bind_param('i', $idCentro);
            $stmtRuta->execute();
            $rutaRow = $stmtRuta->get_result()->fetch_assoc();
            if ($rutaRow) {
                $idRuta = (int) $rutaRow['idRuta'];
            } else {
                $stmtNuevaRuta = $this->conexion->prepare('INSERT INTO ruta (frecuencia, idCentro) VALUES ("diaria", ?)');
                $stmtNuevaRuta->bind_param('i', $idCentro);
                $stmtNuevaRuta->execute();
                $idRuta = (int) $this->conexion->insert_id;
            }
        }

        $stmtTipo = $this->conexion->prepare(
            'SELECT DISTINCT con.tipoCon
             FROM compone comp
             INNER JOIN contenedor con ON con.idCon = comp.idCon
             WHERE comp.idRuta = ?'
        );
        $stmtTipo->bind_param('i', $idRuta);
        $stmtTipo->execute();
        $tipos = $stmtTipo->get_result()->fetch_all(MYSQLI_ASSOC);
        foreach ($tipos as $t) {
            if ($t['tipoCon'] !== $centro['tipoCentro']) {
                throw new InvalidArgumentException(
                    "El residuo de los contenedores ({$t['tipoCon']}) no coincide con el tipo del centro de acopio ({$centro['tipoCentro']}). (RF-21)"
                );
            }
        }

        $stmtRec = $this->conexion->prepare('INSERT INTO recorrido (idVehi, idRuta, fechaRec) VALUES (?, ?, CURDATE())');
        $stmtRec->bind_param('ii', $idVehi, $idRuta);
        $stmtRec->execute();
        $idRec = (int) $this->conexion->insert_id;

        if ($idCuadrilla > 0) {
            $stmtOpCheck = $this->conexion->prepare('SELECT idOperacion FROM opera WHERE idVehi = ? AND idCuadrilla = ? AND fchOperacion = CURDATE()');
            $stmtOpCheck->bind_param('ii', $idVehi, $idCuadrilla);
            $stmtOpCheck->execute();
            if ($stmtOpCheck->get_result()->num_rows === 0) {
                $stmtOp = $this->conexion->prepare('INSERT INTO opera (idVehi, idCuadrilla, fchOperacion) VALUES (?, ?, CURDATE())');
                $stmtOp->bind_param('ii', $idVehi, $idCuadrilla);
                $stmtOp->execute();
            }
        }

        return [
            'idDescarga' => $idRec,
            'idRecorrido' => $idRec,
            'idVehi' => $idVehi,
            'idCentro' => $idCentro,
            'idCuadrilla' => $idCuadrilla,
            'idRuta' => $idRuta,
            'estado' => 'confirmada',
            'mensaje' => 'Llegada y descarga del camión registradas con éxito en el centro de acopio.'
        ];
    }

    public function confirmarDescarga(int $idDescarga, float $pesoDescargado, int $idOperario): array
    {
        return [
            'idDescarga' => $idDescarga,
            'pesoDescargado' => $pesoDescargado,
            'estado' => 'confirmada',
            'idOperario' => $idOperario
        ];
    }

    public function listarDescargasPendientes(?int $idCentro = null): array
    {
        $sql = 'SELECT rec.idRecorrido AS idDescarga,
                       rec.idRecorrido,
                       rec.idVehi,
                       rec.idRuta,
                       DATE_FORMAT(rec.fechaRec, "%Y-%m-%d") AS fchLlegada,
                       DATE_FORMAT(rec.fechaRec, "%Y-%m-%d") AS fchDescarga,
                       v.capVehi AS pesoDescargado,
                       "confirmada" AS estado,
                       v.matriculaVehi, v.marcaVehi, v.modeloVehi, v.capVehi,
                       c.idCentro, c.ubiCentro, c.tipoCentro,
                       r.frecuencia,
                       COALESCE(op.idCuadrilla, 1) AS idCuadrilla,
                       COALESCE(u1.priNom, "Chofer") AS nomChofer,
                       COALESCE(u2.priNom, "Peón") AS nomPeon
                FROM recorrido rec
                INNER JOIN vehiculo v ON v.idVehi = rec.idVehi
                INNER JOIN ruta r ON r.idRuta = rec.idRuta
                INNER JOIN centro c ON c.idCentro = r.idCentro
                LEFT JOIN opera op ON op.idVehi = rec.idVehi AND op.fchOperacion = rec.fechaRec
                LEFT JOIN cuadrilla cuad ON cuad.idCuadrilla = op.idCuadrilla
                LEFT JOIN usuario u1 ON u1.idUsu = cuad.idChofer
                LEFT JOIN usuario u2 ON u2.idUsu = cuad.idPeon';
        if ($idCentro && $idCentro > 0) {
            $sql .= ' WHERE c.idCentro = ' . (int) $idCentro;
        }
        $sql .= ' ORDER BY rec.fechaRec DESC, rec.idRecorrido DESC';

        return $this->conexion->query($sql)->fetch_all(MYSQLI_ASSOC);
    }

    // =========================================================================
    // CU-23: Registro de Vertimiento (enviares)
    // =========================================================================

    public function listarEnviosVertedero(): array
    {
        $sql = 'SELECT env.idEnvio, env.idCentro, env.idVertedero, env.fchVertido,
                       c.ubiCentro, c.tipoCentro,
                       v.ubicacionVertedero
                FROM enviares env
                INNER JOIN centro c ON c.idCentro = env.idCentro
                INNER JOIN vertedero v ON v.idVertedero = env.idVertedero
                ORDER BY env.fchVertido DESC, env.idEnvio DESC';
        return $this->conexion->query($sql)->fetch_all(MYSQLI_ASSOC);
    }

    public function registrarEnvioVertedero(int $idCentro, int $idVertedero): array
    {
        $centro = $this->obtenerCentro($idCentro);
        if (!$centro) {
            throw new OutOfBoundsException('El centro de acopio no existe.');
        }
        $vertedero = $this->obtenerVertedero($idVertedero);
        if (!$vertedero) {
            throw new OutOfBoundsException('El vertedero no existe.');
        }

        $stmt = $this->conexion->prepare('INSERT INTO enviares (idCentro, idVertedero, fchVertido) VALUES (?, ?, CURDATE())');
        $stmt->bind_param('ii', $idCentro, $idVertedero);
        $stmt->execute();

        return [
            'idEnvio' => (int) $this->conexion->insert_id,
            'idCentro' => $idCentro,
            'idVertedero' => $idVertedero,
            'fchVertido' => date('Y-m-d'),
            'mensaje' => 'Envío de residuos registrado con éxito en disposición final (vertedero).'
        ];
    }
}
