<?php

declare(strict_types=1);

require_once __DIR__ . '/Conexion.php';

class LogisticaModel
{
    private mysqli $conexion;

    public function __construct(?mysqli $conexion = null)
    {
        $this->conexion = $conexion ?? conectarBase();
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

    public function validarContenedoresParaRuta(array $contenedores, int $idRutaActual = 0): array
    {
        $errores = [];
        if (empty($contenedores)) {
            return $errores;
        }

        $ids = array_map('intval', $contenedores);
        $idsStr = implode(',', $ids);

        // 1. Verificar que existan y estén activos
        $sql = "SELECT idCon, estCon FROM contenedor WHERE idCon IN ($idsStr)";
        $res = $this->conexion->query($sql);
        $encontrados = [];
        while ($row = $res->fetch_assoc()) {
            $idC = (int) $row['idCon'];
            $encontrados[$idC] = $row['estCon'];
            if ($row['estCon'] !== 'activo') {
                $errores[] = "El contenedor #{$idC} no está activo (estado: {$row['estCon']}). Solo se pueden agregar contenedores activos.";
            }
        }

        foreach ($ids as $idC) {
            if (!isset($encontrados[$idC])) {
                $errores[] = "El contenedor #{$idC} no existe en el sistema.";
            }
        }

        // 2. Verificar que no pertenezcan ya a otra ruta
        $sqlRuta = "SELECT comp.idCon, comp.idRuta, r.frecuencia
                    FROM compone comp
                    INNER JOIN ruta r ON r.idRuta = comp.idRuta
                    WHERE comp.idCon IN ($idsStr)";
        if ($idRutaActual > 0) {
            $sqlRuta .= " AND comp.idRuta <> $idRutaActual";
        }
        $resRuta = $this->conexion->query($sqlRuta);
        while ($r = $resRuta->fetch_assoc()) {
            $idC = (int) $r['idCon'];
            $errores[] = "El contenedor #{$idC} ya pertenece a la Ruta #{$r['idRuta']} ({$r['frecuencia']}). Cada contenedor solo puede estar en 1 ruta.";
        }

        return $errores;
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

    private function ordenarContenedoresEficientemente(array $contenedores): array
    {
        if (count($contenedores) <= 1) {
            return $contenedores;
        }
        $ids = array_map('intval', $contenedores);
        $idsStr = implode(',', $ids);
        $sql = "SELECT c.idCon, cc.latitud, cc.longitud 
                FROM contenedor c 
                LEFT JOIN contenedorcomunitario cc ON cc.idCon = c.idCon 
                WHERE c.idCon IN ($idsStr)";
        $res = $this->conexion->query($sql);
        $puntos = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $puntos[(int) $row['idCon']] = [
                    'lat' => $row['latitud'] !== null ? (float) $row['latitud'] : -34.9011,
                    'lng' => $row['longitud'] !== null ? (float) $row['longitud'] : -56.1645
                ];
            }
        }

        // Algoritmo del vecino más cercano para secuencia óptima de paradas
        $ordenados = [];
        $pendientes = $ids;
        $actual = array_shift($pendientes);
        $ordenados[] = $actual;

        while (!empty($pendientes)) {
            $lat1 = $puntos[$actual]['lat'] ?? -34.9011;
            $lng1 = $puntos[$actual]['lng'] ?? -56.1645;
            $mejorIndice = 0;
            $menorDist = PHP_FLOAT_MAX;

            foreach ($pendientes as $idx => $cand) {
                $lat2 = $puntos[$cand]['lat'] ?? -34.9011;
                $lng2 = $puntos[$cand]['lng'] ?? -56.1645;
                $dist = hypot($lat1 - $lat2, $lng1 - $lng2);
                if ($dist < $menorDist) {
                    $menorDist = $dist;
                    $mejorIndice = $idx;
                }
            }

            $actual = $pendientes[$mejorIndice];
            $ordenados[] = $actual;
            array_splice($pendientes, $mejorIndice, 1);
        }

        return $ordenados;
    }

    private function guardarContenedoresRuta(int $idRuta, array $contenedores): void
    {
        $del = $this->conexion->prepare('DELETE FROM compone WHERE idRuta = ?');
        $del->bind_param('i', $idRuta);
        $del->execute();

        $contenedoresOrdenados = $this->ordenarContenedoresEficientemente($contenedores);

        $ins = $this->conexion->prepare('INSERT INTO compone (idRuta, idCon, orden) VALUES (?, ?, ?)');
        $orden = 1;
        foreach ($contenedoresOrdenados as $idCon) {
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

    public function cuadrillaTieneMiembrosConSesionActiva(int $idCuadrilla): bool
    {
        try {
            $stmt = $this->conexion->prepare(
                'SELECT u1.sesionActiva AS sesChofer, u2.sesionActiva AS sesPeon
                 FROM cuadrilla c
                 LEFT JOIN usuario u1 ON u1.idUsu = c.idChofer
                 LEFT JOIN usuario u2 ON u2.idUsu = c.idPeon
                 WHERE c.idCuadrilla = ?'
            );
            $stmt->bind_param('i', $idCuadrilla);
            $stmt->execute();
            $res = $stmt->get_result()->fetch_assoc();
            if ($res) {
                return (bool) (($res['sesChofer'] ?? 0) || ($res['sesPeon'] ?? 0));
            }
            return false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function vehiculoTieneCuadrillaConSesionActiva(int $idVehi): bool
    {
        try {
            $stmt = $this->conexion->prepare(
                'SELECT u1.sesionActiva AS sesChofer, u2.sesionActiva AS sesPeon
                 FROM opera o
                 INNER JOIN cuadrilla c ON c.idCuadrilla = o.idCuadrilla
                 LEFT JOIN usuario u1 ON u1.idUsu = c.idChofer
                 LEFT JOIN usuario u2 ON u2.idUsu = c.idPeon
                 WHERE o.idVehi = ?'
            );
            $stmt->bind_param('i', $idVehi);
            $stmt->execute();
            $ops = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            foreach ($ops as $op) {
                if (($op['sesChofer'] ?? 0) || ($op['sesPeon'] ?? 0)) {
                    return true;
                }
            }
            return false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function rutaTieneCuadrillaConSesionActiva(int $idRuta): bool
    {
        try {
            $stmt = $this->conexion->prepare(
                'SELECT u1.sesionActiva AS sesChofer, u2.sesionActiva AS sesPeon
                 FROM recorrido rec
                 INNER JOIN opera o ON o.idVehi = rec.idVehi AND o.fchOperacion = rec.fechaRec
                 INNER JOIN cuadrilla c ON c.idCuadrilla = o.idCuadrilla
                 LEFT JOIN usuario u1 ON u1.idUsu = c.idChofer
                 LEFT JOIN usuario u2 ON u2.idUsu = c.idPeon
                 WHERE rec.idRuta = ?'
            );
            $stmt->bind_param('i', $idRuta);
            $stmt->execute();
            $recs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            foreach ($recs as $rec) {
                if (($rec['sesChofer'] ?? 0) || ($rec['sesPeon'] ?? 0)) {
                    return true;
                }
            }
            return false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function operacionTieneCuadrillaConSesionActiva(int $idOperacion): bool
    {
        try {
            $stmt = $this->conexion->prepare(
                'SELECT u1.sesionActiva AS sesChofer, u2.sesionActiva AS sesPeon
                 FROM opera o
                 INNER JOIN cuadrilla c ON c.idCuadrilla = o.idCuadrilla
                 LEFT JOIN usuario u1 ON u1.idUsu = c.idChofer
                 LEFT JOIN usuario u2 ON u2.idUsu = c.idPeon
                 WHERE o.idOperacion = ?'
            );
            $stmt->bind_param('i', $idOperacion);
            $stmt->execute();
            $res = $stmt->get_result()->fetch_assoc();
            if ($res) {
                return (bool) (($res['sesChofer'] ?? 0) || ($res['sesPeon'] ?? 0));
            }
            return false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function listarCuadrillas(): array
    {
        $sql = 'SELECT c.idCuadrilla,
                       c.idChofer, u1.priNom AS nomChofer, u1.telUsu AS telChofer, u1.email AS emailChofer,
                       c.idPeon, u2.priNom AS nomPeon, u2.telUsu AS telPeon, u2.email AS emailPeon,
                       o.idVehi, v.matriculaVehi, v.marcaVehi, v.modeloVehi, v.tipoVehi
                FROM cuadrilla c
                INNER JOIN usuario u1 ON u1.idUsu = c.idChofer
                INNER JOIN usuario u2 ON u2.idUsu = c.idPeon
                LEFT JOIN opera o ON o.idCuadrilla = c.idCuadrilla
                LEFT JOIN vehiculo v ON v.idVehi = o.idVehi
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

    public function crearCuadrilla(int $idChofer, int $idPeon, ?int $idVehi = null): array
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

        // Si se seleccionó un vehículo al crear la cuadrilla, se asigna operativamente
        if ($idVehi !== null && $idVehi > 0) {
            $stmtOp = $this->conexion->prepare('INSERT INTO opera (idVehi, idCuadrilla, fchOperacion) VALUES (?, ?, CURDATE())');
            $stmtOp->bind_param('ii', $idVehi, $id);
            $stmtOp->execute();

            $stmtV = $this->conexion->prepare("UPDATE vehiculo SET estVehi = 'en uso' WHERE idVehi = ?");
            $stmtV->bind_param('i', $idVehi);
            $stmtV->execute();
        }

        return [
            'idCuadrilla' => $id,
            'idChofer' => $idChofer,
            'idPeon' => $idPeon,
            'idVehi' => $idVehi
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
                     WHERE i.tipoInci NOT IN ("roto", "incendiado")
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

    // =========================================================================
    // CU-15 / Narrativa 7: Registrar Operación de Vehículo por Cuadrilla / Peón
    // =========================================================================

    public function usarVehiculoPorCuadrilla(int $idUsuario, int $idVehi): array
    {
        // 1. Verificar cuadrilla del usuario
        $stmtCuad = $this->conexion->prepare('SELECT idCuadrilla FROM cuadrilla WHERE idChofer = ? OR idPeon = ? LIMIT 1');
        $stmtCuad->bind_param('ii', $idUsuario, $idUsuario);
        $stmtCuad->execute();
        $cuad = $stmtCuad->get_result()->fetch_assoc();
        if (!$cuad) {
            throw new InvalidArgumentException('El usuario no pertenece a ninguna cuadrilla activa.');
        }
        $idCuadrilla = (int) $cuad['idCuadrilla'];

        // 2. Verificar vehículo
        $vehiculo = $this->obtenerVehi($idVehi);
        if (!$vehiculo) {
            throw new OutOfBoundsException('El vehículo no existe.');
        }
        if ($vehiculo['estVehi'] === 'roto') {
            throw new InvalidArgumentException('El vehículo se encuentra roto o en mantenimiento.');
        }

        // 3. Regla 6b: Si la cuadrilla ya tiene otro vehículo en uso hoy, se libera el anterior
        $stmtOld = $this->conexion->prepare('SELECT idOperacion, idVehi FROM opera WHERE idCuadrilla = ? AND fchOperacion = CURDATE()');
        $stmtOld->bind_param('i', $idCuadrilla);
        $stmtOld->execute();
        $oldOps = $stmtOld->get_result()->fetch_all(MYSQLI_ASSOC);
        foreach ($oldOps as $old) {
            $idOldV = (int) $old['idVehi'];
            $this->conexion->query("DELETE FROM opera WHERE idOperacion = {$old['idOperacion']}");
            if ($idOldV !== $idVehi) {
                $this->conexion->query("UPDATE vehiculo SET estVehi = 'disponible' WHERE idVehi = {$idOldV} AND estVehi = 'en uso'");
            }
        }

        // 4. Persistir la asignación en opera
        $stmtOpera = $this->conexion->prepare('INSERT INTO opera (idVehi, idCuadrilla, fchOperacion) VALUES (?, ?, CURDATE())');
        $stmtOpera->bind_param('ii', $idVehi, $idCuadrilla);
        $stmtOpera->execute();

        // 5. Marcar vehículo como en uso
        $this->conexion->query("UPDATE vehiculo SET estVehi = 'en uso' WHERE idVehi = {$idVehi}");

        return [
            'idCuadrilla' => $idCuadrilla,
            'idVehi' => $idVehi,
            'fchOperacion' => date('Y-m-d'),
            'vehiculo' => $this->obtenerVehi($idVehi),
            'mensaje' => 'Vehículo asignado a tu cuadrilla correctamente.'
        ];
    }

    // =========================================================================
    // CU-25 / CU-26: ABM de Garajes y Estacionamiento de Vehículos
    // =========================================================================

    public function listarGarajes(): array
    {
        $sql = 'SELECT g.idGaraje, g.ubiGaraje,
                       (SELECT COUNT(*) FROM estaciona e WHERE e.idGaraje = g.idGaraje) AS vehiculosEstacionados
                FROM garaje g
                ORDER BY g.idGaraje ASC';
        return $this->conexion->query($sql)->fetch_all(MYSQLI_ASSOC);
    }

    public function obtenerGaraje(int $id): ?array
    {
        $stmt = $this->conexion->prepare('SELECT idGaraje, ubiGaraje FROM garaje WHERE idGaraje = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    public function crearGaraje(string $ubicacion): array
    {
        $stmt = $this->conexion->prepare('INSERT INTO garaje (ubiGaraje) VALUES (?)');
        $stmt->bind_param('s', $ubicacion);
        $stmt->execute();
        return [
            'idGaraje' => (int) $this->conexion->insert_id,
            'ubiGaraje' => $ubicacion,
        ];
    }

    public function actualizarGaraje(int $id, string $ubicacion): ?array
    {
        $stmt = $this->conexion->prepare('UPDATE garaje SET ubiGaraje = ? WHERE idGaraje = ?');
        $stmt->bind_param('si', $ubicacion, $id);
        $stmt->execute();
        return $this->obtenerGaraje($id);
    }

    public function eliminarGaraje(int $id): bool
    {
        $stmtEst = $this->conexion->prepare('DELETE FROM estaciona WHERE idGaraje = ?');
        $stmtEst->bind_param('i', $id);
        $stmtEst->execute();

        $stmt = $this->conexion->prepare('DELETE FROM garaje WHERE idGaraje = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        return $stmt->affected_rows > 0;
    }

    public function listarEstacionamientos(): array
    {
        $sql = 'SELECT e.idEstacion, e.idGaraje, e.idVehi, e.horaEstacionamiento,
                       g.ubiGaraje,
                       v.matriculaVehi, v.marcaVehi, v.modeloVehi, v.tipoVehi, v.estVehi
                FROM estaciona e
                INNER JOIN garaje g ON g.idGaraje = e.idGaraje
                INNER JOIN vehiculo v ON v.idVehi = e.idVehi
                ORDER BY e.horaEstacionamiento DESC';
        return $this->conexion->query($sql)->fetch_all(MYSQLI_ASSOC);
    }

    public function estacionarVehiculo(int $idVehi, int $idGaraje): array
    {
        $vehiculo = $this->obtenerVehi($idVehi);
        if (!$vehiculo) {
            throw new OutOfBoundsException('El vehículo no existe.');
        }
        $garaje = $this->obtenerGaraje($idGaraje);
        if (!$garaje) {
            throw new OutOfBoundsException('El garaje no existe.');
        }

        // Registrar en tabla nativa estaciona
        $stmt = $this->conexion->prepare('INSERT INTO estaciona (idGaraje, idVehi, horaEstacionamiento) VALUES (?, ?, NOW())');
        $stmt->bind_param('ii', $idGaraje, $idVehi);
        $stmt->execute();

        // Actualizar vehículo a disponible si no está roto
        if ($vehiculo['estVehi'] !== 'roto') {
            $this->conexion->query("UPDATE vehiculo SET estVehi = 'disponible' WHERE idVehi = {$idVehi}");
        }

        return [
            'idEstacion' => (int) $this->conexion->insert_id,
            'idGaraje' => $idGaraje,
            'idVehi' => $idVehi,
            'mensaje' => 'Vehículo estacionado correctamente en garaje.'
        ];
    }

    // =========================================================================
    // CU-27 / CU-28 / Narrativa 10: Centros de Reparación y Mantenimiento
    // =========================================================================

    public function listarTalleres(): array
    {
        $sql = 'SELECT m.idMant, m.ubiMant,
                       (SELECT COUNT(*) FROM repara r WHERE r.idMant = m.idMant AND r.horaSalida IS NULL) AS vehiculosEnReparacion
                FROM mantenimiento m
                ORDER BY m.idMant ASC';
        return $this->conexion->query($sql)->fetch_all(MYSQLI_ASSOC);
    }

    public function obtenerTaller(int $id): ?array
    {
        $stmt = $this->conexion->prepare('SELECT idMant, ubiMant FROM mantenimiento WHERE idMant = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    public function crearTaller(string $ubicacion): array
    {
        $stmt = $this->conexion->prepare('INSERT INTO mantenimiento (ubiMant) VALUES (?)');
        $stmt->bind_param('s', $ubicacion);
        $stmt->execute();
        return [
            'idMant' => (int) $this->conexion->insert_id,
            'ubiMant' => $ubicacion,
        ];
    }

    public function actualizarTaller(int $id, string $ubicacion): ?array
    {
        $stmt = $this->conexion->prepare('UPDATE mantenimiento SET ubiMant = ? WHERE idMant = ?');
        $stmt->bind_param('si', $ubicacion, $id);
        $stmt->execute();
        return $this->obtenerTaller($id);
    }

    public function eliminarTaller(int $id): bool
    {
        $stmtRep = $this->conexion->prepare('DELETE FROM repara WHERE idMant = ?');
        $stmtRep->bind_param('i', $id);
        $stmtRep->execute();

        $stmt = $this->conexion->prepare('DELETE FROM mantenimiento WHERE idMant = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        return $stmt->affected_rows > 0;
    }

    public function listarReparaciones(): array
    {
        $sql = 'SELECT r.idReparacion, r.idMant, r.idVehi, r.horaIngreso, r.horaSalida, r.estadoReparacion,
                       m.ubiMant,
                       v.matriculaVehi, v.marcaVehi, v.modeloVehi, v.tipoVehi
                FROM repara r
                INNER JOIN mantenimiento m ON m.idMant = r.idMant
                INNER JOIN vehiculo v ON v.idVehi = r.idVehi
                ORDER BY r.horaIngreso DESC';
        return $this->conexion->query($sql)->fetch_all(MYSQLI_ASSOC);
    }

    public function registrarMantenimiento(int $idVehi, int $idMant, string $motivo): array
    {
        $vehiculo = $this->obtenerVehi($idVehi);
        if (!$vehiculo) {
            throw new OutOfBoundsException('El vehículo no existe.');
        }
        $taller = $this->obtenerTaller($idMant);
        if (!$taller) {
            throw new OutOfBoundsException('El centro de reparación no existe.');
        }

        // Si el vehículo estaba asignado en opera para hoy, se desasigna
        $delOp = $this->conexion->prepare('DELETE FROM opera WHERE idVehi = ? AND fchOperacion = CURDATE()');
        $delOp->bind_param('i', $idVehi);
        $delOp->execute();

        // Registrar en tabla nativa 'repara'
        $estadoRep = 'en reparacion';
        $stmt = $this->conexion->prepare('INSERT INTO repara (idMant, idVehi, horaIngreso, estadoReparacion) VALUES (?, ?, NOW(), ?)');
        $stmt->bind_param('iis', $idMant, $idVehi, $estadoRep);
        $stmt->execute();

        // Marcar vehículo como roto / inactivo
        $this->conexion->query("UPDATE vehiculo SET estVehi = 'roto' WHERE idVehi = {$idVehi}");

        return [
            'idReparacion' => (int) $this->conexion->insert_id,
            'idMant' => $idMant,
            'idVehi' => $idVehi,
            'estadoReparacion' => $estadoRep,
            'mensaje' => 'Ingreso a taller registrado con éxito. Vehículo marcado en reparación.'
        ];
    }

    public function finalizarMantenimiento(int $idReparacion): array
    {
        $stmtCheck = $this->conexion->prepare('SELECT idReparacion, idVehi, horaSalida FROM repara WHERE idReparacion = ?');
        $stmtCheck->bind_param('i', $idReparacion);
        $stmtCheck->execute();
        $rep = $stmtCheck->get_result()->fetch_assoc();
        if (!$rep) {
            throw new OutOfBoundsException('El registro de reparación no existe.');
        }

        $idVehi = (int) $rep['idVehi'];

        // Actualizar repara
        $stmtUpd = $this->conexion->prepare("UPDATE repara SET horaSalida = NOW(), estadoReparacion = 'reparado' WHERE idReparacion = ?");
        $stmtUpd->bind_param('i', $idReparacion);
        $stmtUpd->execute();

        // Volver vehículo a disponible
        $this->conexion->query("UPDATE vehiculo SET estVehi = 'disponible' WHERE idVehi = {$idVehi}");

        return [
            'idReparacion' => $idReparacion,
            'idVehi' => $idVehi,
            'estadoReparacion' => 'reparado',
            'mensaje' => 'Mantenimiento finalizado. Vehículo devuelto a disponibilidad operativa.'
        ];
    }
}
