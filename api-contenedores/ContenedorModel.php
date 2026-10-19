<?php

declare(strict_types=1);

require_once __DIR__ . '/Conexion.php';

class ContenedorModel
{
    private mysqli $conexion;

    public function __construct()
    {
        $this->conexion = conectarBase();
    }

    public function listarContenedores(): array
    {
        $sql = 'SELECT c.idCon, c.capacidad, c.calle, c.esquina, c.zona, c.estCon, c.tipoCon, c.repuesto,
                       cc.latitud, cc.longitud
                FROM contenedor c
                LEFT JOIN contenedorcomunitario cc ON cc.idCon = c.idCon
                ORDER BY c.idCon';

        $contenedores = $this->conexion->query($sql)->fetch_all(MYSQLI_ASSOC);

        foreach ($contenedores as &$c) {
            if ($c['latitud'] === null || $c['longitud'] === null) {
                $c['latitud'] = round(-34.9011 - (($c['idCon'] * 7) % 25) * 0.0015, 6);
                $c['longitud'] = round(-56.1645 - (($c['idCon'] * 11) % 25) * 0.0015, 6);
            } else {
                $c['latitud'] = (float) $c['latitud'];
                $c['longitud'] = (float) $c['longitud'];
            }
        }

        return $contenedores;
    }

    public function obtenerContenedor(int $id): ?array
    {
        $stmt = $this->conexion->prepare(
            'SELECT c.idCon, c.capacidad, c.calle, c.esquina, c.zona, c.estCon, c.tipoCon, c.repuesto,
                    cc.latitud, cc.longitud
             FROM contenedor c
             LEFT JOIN contenedorcomunitario cc ON cc.idCon = c.idCon
             WHERE c.idCon = ?'
        );

        $stmt->bind_param('i', $id);
        $stmt->execute();

        $c = $stmt->get_result()->fetch_assoc() ?: null;
        if ($c) {
            if ($c['latitud'] === null || $c['longitud'] === null) {
                $c['latitud'] = round(-34.9011 - (($c['idCon'] * 7) % 25) * 0.0015, 6);
                $c['longitud'] = round(-56.1645 - (($c['idCon'] * 11) % 25) * 0.0015, 6);
            } else {
                $c['latitud'] = (float) $c['latitud'];
                $c['longitud'] = (float) $c['longitud'];
            }
        }

        return $c;
    }

    public function crearContenedor(array $datos): array
    {
        $stmt = $this->conexion->prepare(
            'INSERT INTO contenedor (capacidad, calle, esquina, zona, estCon, tipoCon, repuesto)
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
        $id = (int) $this->conexion->insert_id;

        if (isset($datos['latitud'], $datos['longitud']) && $datos['latitud'] !== null && $datos['longitud'] !== null) {
            $stmtGeo = $this->conexion->prepare(
                'INSERT INTO contenedorcomunitario (idCon, latitud, longitud) VALUES (?, ?, ?)'
            );
            $stmtGeo->bind_param('idd', $id, $datos['latitud'], $datos['longitud']);
            $stmtGeo->execute();
        }

        return $this->obtenerContenedor($id);
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

        if (isset($datos['latitud'], $datos['longitud']) && $datos['latitud'] !== null && $datos['longitud'] !== null) {
            $stmtGeo = $this->conexion->prepare(
                'INSERT INTO contenedorcomunitario (idCon, latitud, longitud)
                 VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE latitud = VALUES(latitud), longitud = VALUES(longitud)'
            );
            $stmtGeo->bind_param('idd', $id, $datos['latitud'], $datos['longitud']);
            $stmtGeo->execute();
        }

        return $this->obtenerContenedor($id);
    }

    public function eliminarContenedor(int $id): bool
    {
        $delGeo = $this->conexion->prepare('DELETE FROM contenedorcomunitario WHERE idCon = ?');
        $delGeo->bind_param('i', $id);
        $delGeo->execute();

        $delDom = $this->conexion->prepare('DELETE FROM contenedordomiciliario WHERE idCon = ?');
        $delDom->bind_param('i', $id);
        $delDom->execute();

        $delComp = $this->conexion->prepare('DELETE FROM compone WHERE idCon = ?');
        $delComp->bind_param('i', $id);
        $delComp->execute();

        $stmtIncis = $this->conexion->prepare('SELECT idInci FROM incidencia WHERE idCon = ?');
        $stmtIncis->bind_param('i', $id);
        $stmtIncis->execute();
        $incis = $stmtIncis->get_result()->fetch_all(MYSQLI_ASSOC);
        foreach ($incis as $i) {
            $idInci = (int) $i['idInci'];
            $delAt = $this->conexion->prepare('DELETE FROM atiende WHERE idInci = ?');
            $delAt->bind_param('i', $idInci);
            $delAt->execute();

            $delRes = $this->conexion->prepare('DELETE FROM resuelve WHERE idInci = ?');
            $delRes->bind_param('i', $idInci);
            $delRes->execute();
        }

        $delInci = $this->conexion->prepare('DELETE FROM incidencia WHERE idCon = ?');
        $delInci->bind_param('i', $id);
        $delInci->execute();

        $stmt = $this->conexion->prepare('DELETE FROM contenedor WHERE idCon = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();

        return $stmt->affected_rows > 0;
    }

    public function listarIncidencias(): array
    {
        $sql = 'SELECT i.idInci, i.fchaInci, i.idCon, i.tipoInci, i.descInci, i.cedHashInci, i.prioridad, i.estado,
                       CONCAT(c.calle, " y ", c.esquina) AS ubicacion,
                       cc.latitud, cc.longitud
                FROM incidencia i
                INNER JOIN contenedor c ON c.idCon = i.idCon
                LEFT JOIN contenedorcomunitario cc ON cc.idCon = c.idCon
                ORDER BY i.idInci ASC';

        return $this->conexion->query($sql)->fetch_all(MYSQLI_ASSOC);
    }

    public function crearIncidencia(array $datos): array
    {
        $identificadorProtegido = hash('sha256', $datos['documento']);

        $stmt = $this->conexion->prepare(
            'INSERT INTO incidencia (idCon, tipoInci, descInci, cedHashInci, prioridad, estado)
             VALUES (?, ?, ?, ?, ?, ?)'
        );

        $estadoInicial = 'en curso';
        $stmt->bind_param(
            'isssss',
            $datos['idCon'],
            $datos['tipo'],
            $datos['descripcion'],
            $identificadorProtegido,
            $datos['prioridad'],
            $estadoInicial
        );

        $stmt->execute();

        return [
            'idInci' => (int) $this->conexion->insert_id,
            'idCon' => $datos['idCon'],
            'tipoInci' => $datos['tipo'],
            'estado' => $estadoInicial,
        ];
    }

    public function actualizarEstadoIncidencia(int $id, string $estado): array
    {
        $stmt = $this->conexion->prepare('UPDATE incidencia SET estado = ? WHERE idInci = ?');
        $stmt->bind_param('si', $estado, $id);
        $stmt->execute();

        $stmtVerif = $this->conexion->prepare('SELECT idInci, estado FROM incidencia WHERE idInci = ?');
        $stmtVerif->bind_param('i', $id);
        $stmtVerif->execute();
        $registro = $stmtVerif->get_result()->fetch_assoc();

        if (!$registro) {
            throw new OutOfBoundsException('La incidencia no existe.');
        }

        return $registro;
    }

    public function eliminarIncidencia(int $id): bool
    {
        $delAt = $this->conexion->prepare('DELETE FROM atiende WHERE idInci = ?');
        $delAt->bind_param('i', $id);
        $delAt->execute();

        $delRes = $this->conexion->prepare('DELETE FROM resuelve WHERE idInci = ?');
        $delRes->bind_param('i', $id);
        $delRes->execute();

        $stmt = $this->conexion->prepare('DELETE FROM incidencia WHERE idInci = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();

        return $stmt->affected_rows > 0;
    }
}
