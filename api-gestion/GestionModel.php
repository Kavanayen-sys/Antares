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

    public function actualizarVehi(int $id, array $datos): ?array
    {
        return $this->actualizarVehiculo($id, $datos);
    }

    public function eliminarVehi(int $id): bool
    {
        return $this->eliminarVehiculo($id);
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
                    i.estado,
                    CONCAT(c.calle, " y ", c.esquina) AS ubicacion
                FROM incidencia i
                INNER JOIN contenedor c
                    ON c.idCon = i.idCon
                ORDER BY i.idInci ASC';

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
            (idCon, tipoInci, descInci, cedHashInci, prioridad, estado)
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
        $stmt = $this->conexion->prepare(
            'UPDATE incidencia
             SET estado = ?
             WHERE idInci = ?'
        );

        $stmt->bind_param('si', $estado, $id);
        $stmt->execute();

        $stmtVerif = $this->conexion->prepare(
            'SELECT idInci, estado FROM incidencia WHERE idInci = ?'
        );
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
        $stmt = $this->conexion->prepare(
            'DELETE FROM incidencia
             WHERE idInci = ?'
        );

        $stmt->bind_param('i', $id);
        $stmt->execute();

        return $stmt->affected_rows > 0;
    }

    // =========================================================
    // RUTAS Y COMPOSICION
    // =========================================================

    public function listarRutas(): array
    {
        $sql = 'SELECT r.idRuta, r.frecuencia, r.idCentro, c.tipoCentro, c.capCentro, c.ubiCentro,
                       (SELECT COUNT(*) FROM compone comp WHERE comp.idRuta = r.idRuta) AS cantidadContenedores
                FROM ruta r
                LEFT JOIN centro c ON c.idCentro = r.idCentro
                ORDER BY r.idRuta ASC';

        return $this->conexion->query($sql)->fetch_all(MYSQLI_ASSOC);
    }

    public function obtenerRuta(int $id): ?array
    {
        $stmt = $this->conexion->prepare(
            'SELECT r.idRuta, r.frecuencia, r.idCentro, c.tipoCentro, c.capCentro, c.ubiCentro
             FROM ruta r
             LEFT JOIN centro c ON c.idCentro = r.idCentro
             WHERE r.idRuta = ?'
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $ruta = $stmt->get_result()->fetch_assoc();

        if (!$ruta) {
            return null;
        }

        $stmtComp = $this->conexion->prepare(
            'SELECT comp.idComposicion, comp.orden, con.idCon, con.calle, con.esquina, con.zona, con.tipoCon, con.capacidad, con.estCon
             FROM compone comp
             INNER JOIN contenedor con ON con.idCon = comp.idCon
             WHERE comp.idRuta = ?
             ORDER BY comp.orden ASC'
        );
        $stmtComp->bind_param('i', $id);
        $stmtComp->execute();
        $ruta['contenedores'] = $stmtComp->get_result()->fetch_all(MYSQLI_ASSOC);

        return $ruta;
    }

    public function crearRuta(array $datos): array
    {
        $stmt = $this->conexion->prepare('INSERT INTO ruta (frecuencia, idCentro) VALUES (?, ?)');
        $stmt->bind_param('si', $datos['frecuencia'], $datos['idCentro']);
        $stmt->execute();
        $idRuta = (int) $this->conexion->insert_id;

        if (!empty($datos['contenedores']) && is_array($datos['contenedores'])) {
            $this->guardarContenedoresRuta($idRuta, $datos['contenedores']);
        }

        return $this->obtenerRuta($idRuta);
    }

    public function actualizarRuta(int $id, array $datos): array
    {
        $stmt = $this->conexion->prepare('UPDATE ruta SET frecuencia = ?, idCentro = ? WHERE idRuta = ?');
        $stmt->bind_param('sii', $datos['frecuencia'], $datos['idCentro'], $id);
        $stmt->execute();

        if (isset($datos['contenedores']) && is_array($datos['contenedores'])) {
            $this->guardarContenedoresRuta($id, $datos['contenedores']);
        }

        $ruta = $this->obtenerRuta($id);
        if (!$ruta) {
            throw new OutOfBoundsException('La ruta no existe.');
        }
        return $ruta;
    }

    private function guardarContenedoresRuta(int $idRuta, array $contenedores): void
    {
        $del = $this->conexion->prepare('DELETE FROM compone WHERE idRuta = ?');
        $del->bind_param('i', $idRuta);
        $del->execute();

        $ins = $this->conexion->prepare('INSERT INTO compone (idRuta, idCon, orden) VALUES (?, ?, ?)');
        $orden = 1;
        foreach ($contenedores as $idCon) {
            $idConInt = (int) $idCon;
            if ($idConInt > 0) {
                $ins->bind_param('iii', $idRuta, $idConInt, $orden);
                $ins->execute();
                $orden++;
            }
        }
    }

    public function eliminarRuta(int $id): bool
    {
        $stmtComp = $this->conexion->prepare('DELETE FROM compone WHERE idRuta = ?');
        $stmtComp->bind_param('i', $id);
        $stmtComp->execute();

        $stmtRec = $this->conexion->prepare('DELETE FROM recorrido WHERE idRuta = ?');
        $stmtRec->bind_param('i', $id);
        $stmtRec->execute();

        $stmt = $this->conexion->prepare('DELETE FROM ruta WHERE idRuta = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();

        return $stmt->affected_rows > 0;
    }

    // =========================================================
    // CUADRILLAS
    // =========================================================

    public function listarCuadrillas(): array
    {
        $sql = 'SELECT c.idCuadrilla,
                       c.idChofer, u1.priNom AS nomChofer, u1.telUsu AS telChofer, u1.email AS emailChofer,
                       c.idPeon, u2.priNom AS nomPeon, u2.telUsu AS telPeon, u2.email AS emailPeon
                FROM cuadrilla c
                INNER JOIN usuario u1 ON u1.idUsu = c.idChofer
                INNER JOIN usuario u2 ON u2.idUsu = c.idPeon
                ORDER BY c.idCuadrilla ASC';

        return $this->conexion->query($sql)->fetch_all(MYSQLI_ASSOC);
    }

    public function listarUsuariosCuadrilla(): array
    {
        $sql = "SELECT idUsu, priNom, telUsu, email
                FROM usuario
                WHERE rol = 'cuadrilla' AND estUsu = 'activo'
                ORDER BY priNom ASC";

        return $this->conexion->query($sql)->fetch_all(MYSQLI_ASSOC);
    }

    public function usuarioEstaEnCuadrilla(int $idUsuario): bool
    {
        $stmt = $this->conexion->prepare('SELECT idCuadrilla FROM cuadrilla WHERE idChofer = ? OR idPeon = ? LIMIT 1');
        $stmt->bind_param('ii', $idUsuario, $idUsuario);
        $stmt->execute();
        return $stmt->get_result()->num_rows > 0;
    }

    public function crearCuadrilla(int $idChofer, int $idPeon): array
    {
        if ($idChofer === $idPeon) {
            throw new InvalidArgumentException('El chofer y el peón no pueden ser el mismo usuario.');
        }

        if ($this->usuarioEstaEnCuadrilla($idChofer)) {
            throw new InvalidArgumentException('El chofer seleccionado ya pertenece a otra cuadrilla.');
        }

        if ($this->usuarioEstaEnCuadrilla($idPeon)) {
            throw new InvalidArgumentException('El peón seleccionado ya pertenece a otra cuadrilla.');
        }

        $stmt = $this->conexion->prepare('INSERT INTO cuadrilla (idChofer, idPeon) VALUES (?, ?)');
        $stmt->bind_param('ii', $idChofer, $idPeon);
        $stmt->execute();

        $id = (int) $this->conexion->insert_id;
        return [
            'idCuadrilla' => $id,
            'idChofer' => $idChofer,
            'idPeon' => $idPeon,
        ];
    }

    public function eliminarCuadrilla(int $id): bool
    {
        $delOp = $this->conexion->prepare('DELETE FROM opera WHERE idCuadrilla = ?');
        $delOp->bind_param('i', $id);
        $delOp->execute();

        $stmt = $this->conexion->prepare('DELETE FROM cuadrilla WHERE idCuadrilla = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();

        return $stmt->affected_rows > 0;
    }

    // =========================================================
    // ASIGNACIONES (OPERA Y RECORRIDO)
    // =========================================================

    public function listarAsignaciones(): array
    {
        $sql = 'SELECT o.idOperacion, o.fchOperacion,
                       c.idCuadrilla, u1.priNom AS nomChofer, u2.priNom AS nomPeon,
                       v.idVehi, v.matriculaVehi, v.marcaVehi, v.modeloVehi, v.tipoVehi,
                       r.idRuta, r.frecuencia, rec.idRecorrido
                FROM opera o
                INNER JOIN cuadrilla c ON c.idCuadrilla = o.idCuadrilla
                INNER JOIN usuario u1 ON u1.idUsu = c.idChofer
                INNER JOIN usuario u2 ON u2.idUsu = c.idPeon
                INNER JOIN vehiculo v ON v.idVehi = o.idVehi
                LEFT JOIN recorrido rec ON rec.idVehi = v.idVehi AND rec.fechaRec = o.fchOperacion
                LEFT JOIN ruta r ON r.idRuta = rec.idRuta
                ORDER BY o.fchOperacion DESC, o.idOperacion DESC';

        return $this->conexion->query($sql)->fetch_all(MYSQLI_ASSOC);
    }

    public function asignarCuadrilla(array $datos): array
    {
        $idCuadrilla = (int) $datos['idCuadrilla'];
        $idVehi = (int) $datos['idVehi'];
        $idRuta = !empty($datos['idRuta']) ? (int) $datos['idRuta'] : null;
        $fecha = !empty($datos['fecha']) ? (string) $datos['fecha'] : date('Y-m-d');

        $stmtOpera = $this->conexion->prepare(
            'INSERT INTO opera (idVehi, idCuadrilla, fchOperacion)
             VALUES (?, ?, ?)'
        );
        $stmtOpera->bind_param('iis', $idVehi, $idCuadrilla, $fecha);
        $stmtOpera->execute();

        if ($idRuta) {
            $stmtRec = $this->conexion->prepare(
                'INSERT INTO recorrido (idVehi, idRuta, fechaRec)
                 VALUES (?, ?, ?)'
            );
            $stmtRec->bind_param('iis', $idVehi, $idRuta, $fecha);
            $stmtRec->execute();
        }

        $updVehi = $this->conexion->prepare("UPDATE vehiculo SET estVehi = 'en uso' WHERE idVehi = ?");
        $updVehi->bind_param('i', $idVehi);
        $updVehi->execute();

        return [
            'idCuadrilla' => $idCuadrilla,
            'idVehi' => $idVehi,
            'idRuta' => $idRuta,
            'fecha' => $fecha,
        ];
    }

    // =========================================================
    // VISTA MI RUTA (ROL CUADRILLA)
    // =========================================================

    public function obtenerMiRuta(int $idUsuario): array
    {
        $stmtCuad = $this->conexion->prepare(
            'SELECT c.idCuadrilla,
                    u1.priNom AS nomChofer, u1.telUsu AS telChofer,
                    u2.priNom AS nomPeon, u2.telUsu AS telPeon
             FROM cuadrilla c
             INNER JOIN usuario u1 ON u1.idUsu = c.idChofer
             INNER JOIN usuario u2 ON u2.idUsu = c.idPeon
             WHERE c.idChofer = ? OR c.idPeon = ?
             LIMIT 1'
        );
        $stmtCuad->bind_param('ii', $idUsuario, $idUsuario);
        $stmtCuad->execute();
        $cuadrilla = $stmtCuad->get_result()->fetch_assoc();

        if (!$cuadrilla) {
            return [
                'cuadrilla' => null,
                'vehiculo' => null,
                'ruta' => null,
                'contenedores' => [],
                'incidencias' => [],
            ];
        }

        $idCuadrilla = (int) $cuadrilla['idCuadrilla'];

        // Obtener última operación / vehículo asignado
        $stmtOp = $this->conexion->prepare(
            'SELECT o.idOperacion, o.fchOperacion, v.idVehi, v.matriculaVehi, v.marcaVehi, v.modeloVehi, v.tipoVehi, v.capVehi, v.estVehi
             FROM opera o
             INNER JOIN vehiculo v ON v.idVehi = o.idVehi
             WHERE o.idCuadrilla = ?
             ORDER BY o.fchOperacion DESC, o.idOperacion DESC
             LIMIT 1'
        );
        $stmtOp->bind_param('i', $idCuadrilla);
        $stmtOp->execute();
        $operacion = $stmtOp->get_result()->fetch_assoc();

        $vehiculo = $operacion ? [
            'idVehi' => $operacion['idVehi'],
            'matriculaVehi' => $operacion['matriculaVehi'],
            'marcaVehi' => $operacion['marcaVehi'],
            'modeloVehi' => $operacion['modeloVehi'],
            'tipoVehi' => $operacion['tipoVehi'],
            'capVehi' => $operacion['capVehi'],
            'estVehi' => $operacion['estVehi'],
            'fechaAsignacion' => $operacion['fchOperacion'],
        ] : null;

        $ruta = null;
        $contenedores = [];
        $incidencias = [];

        if ($vehiculo) {
            $idVehi = (int) $vehiculo['idVehi'];
            $stmtRec = $this->conexion->prepare(
                'SELECT r.idRuta, r.frecuencia, r.idCentro, c.tipoCentro, c.capCentro, c.ubiCentro, rec.fechaRec
                 FROM recorrido rec
                 INNER JOIN ruta r ON r.idRuta = rec.idRuta
                 LEFT JOIN centro c ON c.idCentro = r.idCentro
                 WHERE rec.idVehi = ?
                 ORDER BY rec.fechaRec DESC, rec.idRecorrido DESC
                 LIMIT 1'
            );
            $stmtRec->bind_param('i', $idVehi);
            $stmtRec->execute();
            $ruta = $stmtRec->get_result()->fetch_assoc();

            if ($ruta) {
                $idRuta = (int) $ruta['idRuta'];

                // Obtener contenedores
                $stmtCont = $this->conexion->prepare(
                    'SELECT comp.orden, c.idCon, c.calle, c.esquina, c.zona, c.tipoCon, c.capacidad, c.estCon
                     FROM compone comp
                     INNER JOIN contenedor c ON c.idCon = comp.idCon
                     WHERE comp.idRuta = ?
                     ORDER BY comp.orden ASC'
                );
                $stmtCont->bind_param('i', $idRuta);
                $stmtCont->execute();
                $contenedores = $stmtCont->get_result()->fetch_all(MYSQLI_ASSOC);

                // Obtener incidencias en los contenedores de la ruta
                $stmtInci = $this->conexion->prepare(
                    'SELECT i.idInci, i.fchaInci, i.idCon, i.tipoInci, i.descInci, i.prioridad, i.estado,
                            CONCAT(c.calle, " y ", c.esquina) AS ubicacion,
                            comp.orden AS ordenParada
                     FROM incidencia i
                     INNER JOIN compone comp ON comp.idCon = i.idCon AND comp.idRuta = ?
                     INNER JOIN contenedor c ON c.idCon = i.idCon
                     ORDER BY comp.orden ASC, i.idInci ASC'
                );
                $stmtInci->bind_param('i', $idRuta);
                $stmtInci->execute();
                $incidencias = $stmtInci->get_result()->fetch_all(MYSQLI_ASSOC);
            }
        }

        return [
            'cuadrilla' => $cuadrilla,
            'vehiculo' => $vehiculo,
            'ruta' => $ruta,
            'contenedores' => $contenedores,
            'incidencias' => $incidencias,
        ];
    }

    // =========================================================
    // MAQUINARIA, CENTROS Y VERTEDEROS
    // =========================================================

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