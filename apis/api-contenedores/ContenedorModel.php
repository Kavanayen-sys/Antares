<?php

declare(strict_types=1);

require_once __DIR__ . '/Conexion.php';

class ContenedorModel
{
    private mysqli $conexion;

    public function __construct(?mysqli $conexion = null)
    {
        $this->conexion = $conexion ?? conectarBase();
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
                       cc.latitud, cc.longitud,
                       (SELECT MAX(r.fchIntento) FROM resuelve r WHERE r.idInci = i.idInci) AS ultimaResolucion,
                       a.idVehi, v.matriculaVehi, v.marcaVehi, v.modeloVehi, v.tipoVehi
                FROM incidencia i
                INNER JOIN contenedor c ON c.idCon = i.idCon
                LEFT JOIN contenedorcomunitario cc ON cc.idCon = c.idCon
                LEFT JOIN atiende a ON a.idInci = i.idInci
                LEFT JOIN vehiculo v ON v.idVehi = a.idVehi
                ORDER BY i.idInci ASC';

        return $this->conexion->query($sql)->fetch_all(MYSQLI_ASSOC);
    }

    public function asignarIncidencia(int $idInci, int $idVehi): bool
    {
        $stmtInci = $this->conexion->prepare('SELECT idInci FROM incidencia WHERE idInci = ?');
        $stmtInci->bind_param('i', $idInci);
        $stmtInci->execute();
        if ($stmtInci->get_result()->num_rows === 0) {
            throw new OutOfBoundsException('Incidencia no encontrada.');
        }

        // Borrar asignación anterior si existía
        $del = $this->conexion->prepare('DELETE FROM atiende WHERE idInci = ?');
        $del->bind_param('i', $idInci);
        $del->execute();

        // Registrar en atiende
        $stmt = $this->conexion->prepare('INSERT INTO atiende (idInci, idVehi, fchAtencion) VALUES (?, ?, NOW())');
        $stmt->bind_param('ii', $idInci, $idVehi);
        $stmt->execute();

        return $stmt->affected_rows > 0;
    }

    public function obtenerIncidencia(int $id): ?array
    {
        $stmt = $this->conexion->prepare(
            'SELECT i.idInci, i.fchaInci, i.idCon, i.tipoInci, i.descInci, i.cedHashInci, i.prioridad, i.estado,
                    (SELECT MAX(r.fchIntento) FROM resuelve r WHERE r.idInci = i.idInci) AS ultimaResolucion
             FROM incidencia i
             WHERE i.idInci = ?'
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    public function existeIncidenciaAbierta(int $idCon, string $tipoInci): bool
    {
        $stmt = $this->conexion->prepare(
            'SELECT idInci FROM incidencia WHERE idCon = ? AND tipoInci = ? AND estado = "en curso" LIMIT 1'
        );
        $stmt->bind_param('is', $idCon, $tipoInci);
        $stmt->execute();
        return $stmt->get_result()->num_rows > 0;
    }

    public function cedulaBloqueada(string $cedula): bool
    {
        $archivo = __DIR__ . '/cedulas_bloqueadas.json';
        if (!file_exists($archivo)) {
            return false;
        }
        $contenido = json_decode((string) file_get_contents($archivo), true);
        if (!is_array($contenido)) {
            return false;
        }
        $hash = hash('sha256', trim($cedula));
        return in_array(trim($cedula), $contenido, true) || in_array($hash, $contenido, true);
    }

    public function obtenerCedulasBloqueadas(): array
    {
        $archivo = __DIR__ . '/cedulas_bloqueadas.json';
        if (!file_exists($archivo)) {
            return [];
        }
        $contenido = json_decode((string) file_get_contents($archivo), true);
        return is_array($contenido) ? $contenido : [];
    }

    public function bloquearCedula(string $cedula): bool
    {
        $archivo = __DIR__ . '/cedulas_bloqueadas.json';
        $bloqueadas = $this->obtenerCedulasBloqueadas();
        $cedulaLimpia = trim($cedula);
        if (!in_array($cedulaLimpia, $bloqueadas, true)) {
            $bloqueadas[] = $cedulaLimpia;
            @file_put_contents($archivo, json_encode($bloqueadas, JSON_PRETTY_PRINT));
        }
        return true;
    }

    public function desbloquearCedula(string $cedula): bool
    {
        $archivo = __DIR__ . '/cedulas_bloqueadas.json';
        $bloqueadas = $this->obtenerCedulasBloqueadas();
        $cedulaLimpia = trim($cedula);
        $filtradas = array_values(array_filter($bloqueadas, fn($c) => $c !== $cedulaLimpia));
        @file_put_contents($archivo, json_encode($filtradas, JSON_PRETTY_PRINT));
        return true;
    }

    public function buscarContenedoresPorCalleYEsquina(string $calle, string $esquina): array
    {
        $calleTerm = '%' . trim($calle) . '%';
        $esqTerm = '%' . trim($esquina) . '%';
        $sql = 'SELECT c.idCon, c.capacidad, c.calle, c.esquina, c.zona, c.estCon, c.tipoCon, c.repuesto,
                       cc.latitud, cc.longitud,
                       (CASE 
                            WHEN LOWER(c.calle) = LOWER(?) AND LOWER(c.esquina) = LOWER(?) THEN 100
                            WHEN LOWER(c.calle) LIKE LOWER(?) AND LOWER(c.esquina) LIKE LOWER(?) THEN 80
                            WHEN LOWER(c.calle) LIKE LOWER(?) THEN 50
                            ELSE 10
                        END) AS relevancia
                FROM contenedor c
                LEFT JOIN contenedorcomunitario cc ON cc.idCon = c.idCon
                WHERE (c.calle LIKE ? OR c.esquina LIKE ?)
                ORDER BY relevancia DESC, c.idCon ASC';

        $stmt = $this->conexion->prepare($sql);
        $calleExacta = trim($calle);
        $esqExacta = trim($esquina);
        $stmt->bind_param('sssssss', $calleExacta, $esqExacta, $calleTerm, $esqTerm, $calleTerm, $calleTerm, $esqTerm);
        $stmt->execute();
        $resultado = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        foreach ($resultado as &$c) {
            $c['latitud'] = $c['latitud'] !== null ? (float) $c['latitud'] : null;
            $c['longitud'] = $c['longitud'] !== null ? (float) $c['longitud'] : null;
        }

        return $resultado;
    }

    public function crearIncidencia(array $datos): array
    {
        $identificadorProtegido = hash('sha256', $datos['documento']);
        $documento = trim((string) ($datos['documento'] ?? ''));

        // Regla CU4 y CU8 + Diagrama de transición de estados:
        // Si la cédula está bloqueada o si ya existe incidencia abierta para este contenedor y tipo:
        // Omitir persistencia pero devolver confirmación ("Incidencia registrada")
        if ($this->cedulaBloqueada($documento) || $this->existeIncidenciaAbierta((int) $datos['idCon'], $datos['tipo'])) {
            return [
                'idInci' => 0,
                'idCon' => $datos['idCon'],
                'tipoInci' => $datos['tipo'],
                'estado' => 'en curso',
                'omitido' => true
            ];
        }

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

    public function actualizarEstadoIncidencia(int $id, string $estado, ?string $descripcion = null, ?int $idUsu = null, ?int $idVehi = null): array
    {
        $incidenciaPrevia = $this->obtenerIncidencia($id);
        if (!$incidenciaPrevia) {
            throw new OutOfBoundsException('La incidencia no existe.');
        }

        // Regla CU-06 / CU-07 / Narrativa 9:
        // No se puede editar ni reabrir una incidencia cerrada hace más de 1 semana (7 días)
        if (in_array($incidenciaPrevia['estado'], ['resuelto', 'descartado'], true)) {
            $fchUltima = $incidenciaPrevia['ultimaResolucion'] ?? $incidenciaPrevia['fchaInci'];
            if ($fchUltima) {
                $tiempoCierre = strtotime($fchUltima);
                $diferenciaDias = (time() - $tiempoCierre) / 86400;
                if ($diferenciaDias > 7) {
                    throw new InvalidArgumentException('No se puede modificar ni reabrir una incidencia transcurrida más de una semana desde su resolución (CU-06 / CU-07).');
                }
            }
        }

        $stmt = $this->conexion->prepare('UPDATE incidencia SET estado = ? WHERE idInci = ?');
        $stmt->bind_param('si', $estado, $id);
        $stmt->execute();

        // Registrar en tabla nativa 'resuelve' (CU-06 / CU-07 / Narrativa 9)
        if ($idUsu !== null && $idUsu > 0) {
            $desc = $descripcion ?: "Actualización de estado a {$estado}";
            $estIntento = ($estado === 'resuelto') ? 'cerrado' : (($estado === 'descartado') ? 'cerrado' : 'en proceso');
            $stmtRes = $this->conexion->prepare(
                'INSERT INTO resuelve (idInci, idUsu, fchIntento, descEstado, estIntento) VALUES (?, ?, NOW(), ?, ?)'
            );
            $stmtRes->bind_param('iiss', $id, $idUsu, $desc, $estIntento);
            $stmtRes->execute();
        }

        // Registrar en tabla nativa 'atiende' (CU-29 / Atiende cuadrilla con vehículo)
        if ($idVehi !== null && $idVehi > 0) {
            $stmtAt = $this->conexion->prepare(
                'INSERT INTO atiende (idInci, idVehi, fchAtencion) VALUES (?, ?, NOW())'
            );
            $stmtAt->bind_param('ii', $id, $idVehi);
            $stmtAt->execute();
        }

        $stmtVerif = $this->conexion->prepare('SELECT idInci, estado FROM incidencia WHERE idInci = ?');
        $stmtVerif->bind_param('i', $id);
        $stmtVerif->execute();
        $registro = $stmtVerif->get_result()->fetch_assoc();

        return $registro;
    }

    public function obtenerHistorialResoluciones(int $idInci): array
    {
        $stmt = $this->conexion->prepare(
            'SELECT r.idResolucion, r.idInci, r.idUsu, u.priNom AS nomUsuario, r.fchIntento, r.descEstado, r.estIntento
             FROM resuelve r
             INNER JOIN usuario u ON u.idUsu = r.idUsu
             WHERE r.idInci = ?
             ORDER BY r.fchIntento DESC'
        );
        $stmt->bind_param('i', $idInci);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
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
