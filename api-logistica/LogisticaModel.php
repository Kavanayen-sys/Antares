<?php

declare(strict_types=1);

require_once __DIR__ . '/Conexion.php';

class LogisticaModel
{
    private mysqli $conexion;

    public function __construct()
    {
        $this->conexion = conectarBase();
    }

    public function listarVehi(): array
    {
        $sql = 'SELECT idVehi, tipoVehi, matriculaVehi, marcaVehi, modeloVehi, capVehi, estVehi
                FROM vehiculo
                ORDER BY idVehi';

        return $this->conexion->query($sql)->fetch_all(MYSQLI_ASSOC);
    }

    public function obtenerVehi(int $id): ?array
    {
        $stmt = $this->conexion->prepare('SELECT * FROM vehiculo WHERE idVehi = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();

        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    public function matriculaExiste(string $matriculaVehi, int $ignorarId = 0): bool
    {
        $stmt = $this->conexion->prepare('SELECT idVehi FROM vehiculo WHERE matriculaVehi = ? AND idVehi <> ? LIMIT 1');
        $stmt->bind_param('si', $matriculaVehi, $ignorarId);
        $stmt->execute();

        return $stmt->get_result()->num_rows > 0;
    }

    public function crearVehi(array $datos): array
    {
        $stmt = $this->conexion->prepare(
            'INSERT INTO vehiculo (tipoVehi, matriculaVehi, marcaVehi, modeloVehi, capVehi, estVehi)
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

        return $this->obtenerVehi((int) $this->conexion->insert_id);
    }

    public function actualizarVehiculo(int $id, array $datos): ?array
    {
        $stmt = $this->conexion->prepare(
            'UPDATE vehiculo
             SET tipoVehi = ?, matriculaVehi = ?, marcaVehi = ?, modeloVehi = ?, capVehi = ?, estVehi = ?
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
        $delRec = $this->conexion->prepare('DELETE FROM recorrido WHERE idVehi = ?');
        $delRec->bind_param('i', $id);
        $delRec->execute();

        $delOp = $this->conexion->prepare('DELETE FROM opera WHERE idVehi = ?');
        $delOp->bind_param('i', $id);
        $delOp->execute();

        $delAtiende = $this->conexion->prepare('DELETE FROM atiende WHERE idVehi = ?');
        $delAtiende->bind_param('i', $id);
        $delAtiende->execute();

        $delEstaciona = $this->conexion->prepare('DELETE FROM estaciona WHERE idVehi = ?');
        $delEstaciona->bind_param('i', $id);
        $delEstaciona->execute();

        $delRepara = $this->conexion->prepare('DELETE FROM repara WHERE idVehi = ?');
        $delRepara->bind_param('i', $id);
        $delRepara->execute();

        $stmt = $this->conexion->prepare('DELETE FROM vehiculo WHERE idVehi = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();

        return $stmt->affected_rows > 0;
    }

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
        $stmtOps = $this->conexion->prepare('SELECT idVehi, fchOperacion FROM opera WHERE idCuadrilla = ?');
        $stmtOps->bind_param('i', $id);
        $stmtOps->execute();
        $operaciones = $stmtOps->get_result()->fetch_all(MYSQLI_ASSOC);

        foreach ($operaciones as $op) {
            $idVehi = (int) $op['idVehi'];
            $fecha = $op['fchOperacion'];

            $delRec = $this->conexion->prepare('DELETE FROM recorrido WHERE idVehi = ? AND fechaRec = ?');
            $delRec->bind_param('is', $idVehi, $fecha);
            $delRec->execute();
        }

        $delOp = $this->conexion->prepare('DELETE FROM opera WHERE idCuadrilla = ?');
        $delOp->bind_param('i', $id);
        $delOp->execute();

        foreach ($operaciones as $op) {
            $idVehi = (int) $op['idVehi'];
            $check = $this->conexion->prepare('SELECT COUNT(*) AS total FROM opera WHERE idVehi = ?');
            $check->bind_param('i', $idVehi);
            $check->execute();
            $total = (int) ($check->get_result()->fetch_assoc()['total'] ?? 0);
            if ($total === 0) {
                $upd = $this->conexion->prepare("UPDATE vehiculo SET estVehi = 'disponible' WHERE idVehi = ? AND estVehi = 'en uso'");
                $upd->bind_param('i', $idVehi);
                $upd->execute();
            }
        }

        $stmt = $this->conexion->prepare('DELETE FROM cuadrilla WHERE idCuadrilla = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();

        return $stmt->affected_rows > 0;
    }

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

    public function eliminarAsignacion(int $idOperacion): bool
    {
        $stmtOp = $this->conexion->prepare('SELECT idVehi, fchOperacion FROM opera WHERE idOperacion = ?');
        $stmtOp->bind_param('i', $idOperacion);
        $stmtOp->execute();
        $op = $stmtOp->get_result()->fetch_assoc();
        if (!$op) {
            return false;
        }

        $idVehi = (int) $op['idVehi'];
        $fecha = $op['fchOperacion'];

        $delRec = $this->conexion->prepare('DELETE FROM recorrido WHERE idVehi = ? AND fechaRec = ?');
        $delRec->bind_param('is', $idVehi, $fecha);
        $delRec->execute();

        $delOp = $this->conexion->prepare('DELETE FROM opera WHERE idOperacion = ?');
        $delOp->bind_param('i', $idOperacion);
        $delOp->execute();

        $check = $this->conexion->prepare('SELECT COUNT(*) AS total FROM opera WHERE idVehi = ?');
        $check->bind_param('i', $idVehi);
        $check->execute();
        $total = (int) ($check->get_result()->fetch_assoc()['total'] ?? 0);
        if ($total === 0) {
            $upd = $this->conexion->prepare("UPDATE vehiculo SET estVehi = 'disponible' WHERE idVehi = ? AND estVehi = 'en uso'");
            $upd->bind_param('i', $idVehi);
            $upd->execute();
        }

        return true;
    }

    public function asignarCuadrilla(array $datos): array
    {
        $idCuadrilla = (int) $datos['idCuadrilla'];
        $idVehi = (int) $datos['idVehi'];
        $idRuta = !empty($datos['idRuta']) ? (int) $datos['idRuta'] : null;
        $fecha = !empty($datos['fecha']) ? (string) $datos['fecha'] : date('Y-m-d');

        // Si la cuadrilla ya tenía asignación en esta fecha, la reemplazamos liberando la previa
        $stmtOld = $this->conexion->prepare('SELECT idOperacion, idVehi FROM opera WHERE idCuadrilla = ? AND fchOperacion = ?');
        $stmtOld->bind_param('is', $idCuadrilla, $fecha);
        $stmtOld->execute();
        $oldOps = $stmtOld->get_result()->fetch_all(MYSQLI_ASSOC);
        foreach ($oldOps as $old) {
            $oldVehi = (int) $old['idVehi'];
            $delOldRec = $this->conexion->prepare('DELETE FROM recorrido WHERE idVehi = ? AND fechaRec = ?');
            $delOldRec->bind_param('is', $oldVehi, $fecha);
            $delOldRec->execute();

            $delOldOp = $this->conexion->prepare('DELETE FROM opera WHERE idOperacion = ?');
            $delOldOp->bind_param('i', $old['idOperacion']);
            $delOldOp->execute();

            if ($oldVehi !== $idVehi) {
                $chk = $this->conexion->prepare('SELECT COUNT(*) AS total FROM opera WHERE idVehi = ?');
                $chk->bind_param('i', $oldVehi);
                $chk->execute();
                if ((int) ($chk->get_result()->fetch_assoc()['total'] ?? 0) === 0) {
                    $this->conexion->query("UPDATE vehiculo SET estVehi = 'disponible' WHERE idVehi = {$oldVehi} AND estVehi = 'en uso'");
                }
            }
        }

        // Si la ruta ya estaba asignada a otro vehículo en esta fecha, la desasignamos del anterior
        if ($idRuta) {
            $delOldRuta = $this->conexion->prepare('DELETE FROM recorrido WHERE idRuta = ? AND fechaRec = ?');
            $delOldRuta->bind_param('is', $idRuta, $fecha);
            $delOldRuta->execute();
        }

        $stmtOpera = $this->conexion->prepare('INSERT INTO opera (idVehi, idCuadrilla, fchOperacion) VALUES (?, ?, ?)');
        $stmtOpera->bind_param('iis', $idVehi, $idCuadrilla, $fecha);
        $stmtOpera->execute();

        if ($idRuta) {
            $stmtRec = $this->conexion->prepare('INSERT INTO recorrido (idVehi, idRuta, fechaRec) VALUES (?, ?, ?)');
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

                $stmtCont = $this->conexion->prepare(
                    'SELECT comp.orden, c.idCon, c.calle, c.esquina, c.zona, c.tipoCon, c.capacidad, c.estCon,
                            cc.latitud, cc.longitud
                     FROM compone comp
                     INNER JOIN contenedor c ON c.idCon = comp.idCon
                     LEFT JOIN contenedorcomunitario cc ON cc.idCon = c.idCon
                     WHERE comp.idRuta = ?
                     ORDER BY comp.orden ASC'
                );
                $stmtCont->bind_param('i', $idRuta);
                $stmtCont->execute();
                $contenedores = $stmtCont->get_result()->fetch_all(MYSQLI_ASSOC);

                $stmtInci = $this->conexion->prepare(
                    'SELECT i.idInci, i.fchaInci, i.idCon, i.tipoInci, i.descInci, i.prioridad, i.estado,
                            CONCAT(c.calle, " y ", c.esquina) AS ubicacion,
                            comp.orden AS ordenParada,
                            cc.latitud, cc.longitud
                     FROM incidencia i
                     INNER JOIN compone comp ON comp.idCon = i.idCon AND comp.idRuta = ?
                     INNER JOIN contenedor c ON c.idCon = i.idCon
                     LEFT JOIN contenedorcomunitario cc ON cc.idCon = c.idCon
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
}
