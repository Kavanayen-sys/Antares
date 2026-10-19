<?php

declare(strict_types=1);

require_once __DIR__ . '/LogisticaModel.php';

class LogisticaController
{
    private LogisticaModel $modelo;

    private const TIPOS_VEHICULO = [
        'comunitario',
        'intradomiciliario',
        'limpieza',
        'centroAcopio'
    ];

    private const ESTADOS_VEHICULO = [
        'disponible',
        'en uso',
        'roto'
    ];

    public function __construct()
    {
        $this->modelo = new LogisticaModel();
    }

    public function listarVehiculos(): array
    {
        return $this->modelo->listarVehi();
    }

    public function guardarVehiculo(array $datos, int $id = 0): array
    {
        $matricula = strtoupper($this->texto($datos, 'matricula', 10));
        $marca = $this->texto($datos, 'marca', 50);
        $modelo = $this->texto($datos, 'modelo', 50);
        $capacidad = $this->numeroPositivo($datos, 'capacidad');
        $tipo = $this->opcion($datos, 'tipo', self::TIPOS_VEHICULO, false);
        $estado = $this->opcion($datos, 'estado', self::ESTADOS_VEHICULO);

        if (!preg_match('/^[A-Z]{3}[0-9]{4}$/', $matricula)) {
            throw new InvalidArgumentException('La matrícula debe tener formato de 3 letras y 4 números (ej: STP1234).');
        }

        if ($this->modelo->matriculaExiste($matricula, $id)) {
            throw new InvalidArgumentException('Ya existe un vehículo con esa matrícula.');
        }

        $payload = [
            'tipo' => $tipo,
            'matricula' => $matricula,
            'marca' => $marca,
            'modelo' => $modelo,
            'capacidad' => $capacidad,
            'estado' => $estado,
        ];

        if ($id > 0) {
            $actualizado = $this->modelo->actualizarVehiculo($id, $payload);
            if (!$actualizado) {
                throw new OutOfBoundsException('El vehículo no existe.');
            }
            return $actualizado;
        }

        return $this->modelo->crearVehi($payload);
    }

    public function eliminarVehiculo(int $id): bool
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('ID de vehículo inválido.');
        }
        return $this->modelo->eliminarVehiculo($id);
    }

    public function listarRutas(): array
    {
        return $this->modelo->listarRutas();
    }

    public function obtenerRuta(int $id): array
    {
        $ruta = $this->modelo->obtenerRuta($id);
        if (!$ruta) {
            throw new OutOfBoundsException('La ruta no existe.');
        }
        return $ruta;
    }

    public function guardarRuta(array $datos, int $id = 0): array
    {
        $frecuencia = $this->texto($datos, 'frecuencia', 30);
        $idCentro = filter_var($datos['idCentro'] ?? null, FILTER_VALIDATE_INT);
        if (!$idCentro || $idCentro <= 0) {
            throw new InvalidArgumentException('Debe seleccionar un centro de acopio válido.');
        }

        $contenedores = isset($datos['contenedores']) && is_array($datos['contenedores']) ? $datos['contenedores'] : [];

        $payload = [
            'frecuencia' => $frecuencia,
            'idCentro' => (int) $idCentro,
            'contenedores' => $contenedores,
        ];

        if ($id > 0) {
            return $this->modelo->actualizarRuta($id, $payload);
        }

        return $this->modelo->crearRuta($payload);
    }

    public function eliminarRuta(int $id): bool
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('ID de ruta inválido.');
        }
        return $this->modelo->eliminarRuta($id);
    }

    public function listarCuadrillas(): array
    {
        return $this->modelo->listarCuadrillas();
    }

    public function listarUsuariosCuadrilla(): array
    {
        return $this->modelo->listarUsuariosCuadrilla();
    }

    public function guardarCuadrilla(array $datos): array
    {
        $idChofer = filter_var($datos['idChofer'] ?? null, FILTER_VALIDATE_INT);
        $idPeon = filter_var($datos['idPeon'] ?? null, FILTER_VALIDATE_INT);

        if (!$idChofer || $idChofer <= 0) {
            throw new InvalidArgumentException('Debe seleccionar un chofer.');
        }
        if (!$idPeon || $idPeon <= 0) {
            throw new InvalidArgumentException('Debe seleccionar un peón.');
        }

        return $this->modelo->crearCuadrilla((int) $idChofer, (int) $idPeon);
    }

    public function eliminarCuadrilla(int $id): bool
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('ID de cuadrilla inválido.');
        }
        return $this->modelo->eliminarCuadrilla($id);
    }

    public function listarAsignaciones(): array
    {
        return $this->modelo->listarAsignaciones();
    }

    public function asignarCuadrilla(array $datos): array
    {
        $idCuadrilla = filter_var($datos['idCuadrilla'] ?? null, FILTER_VALIDATE_INT);
        $idVehi = filter_var($datos['idVehi'] ?? null, FILTER_VALIDATE_INT);
        $idRuta = filter_var($datos['idRuta'] ?? null, FILTER_VALIDATE_INT);

        if (!$idCuadrilla || $idCuadrilla <= 0) {
            throw new InvalidArgumentException('Debe seleccionar una cuadrilla.');
        }
        if (!$idVehi || $idVehi <= 0) {
            throw new InvalidArgumentException('Debe seleccionar un vehículo.');
        }

        return $this->modelo->asignarCuadrilla([
            'idCuadrilla' => (int) $idCuadrilla,
            'idVehi' => (int) $idVehi,
            'idRuta' => $idRuta ? (int) $idRuta : null,
            'fecha' => !empty($datos['fecha']) ? (string) $datos['fecha'] : date('Y-m-d'),
        ]);
    }

    public function eliminarAsignacion(int $id): bool
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('ID de asignación inválido.');
        }
        return $this->modelo->eliminarAsignacion($id);
    }

    public function obtenerMiRuta(int $idUsuario): array
    {
        if ($idUsuario <= 0) {
            throw new InvalidArgumentException('Usuario no válido.');
        }
        return $this->modelo->obtenerMiRuta($idUsuario);
    }

    private function texto(array $datos, string $campo, int $maximo): string
    {
        $valor = trim((string) ($datos[$campo] ?? ''));
        if ($valor === '' || strlen($valor) > $maximo) {
            throw new InvalidArgumentException("El campo {$campo} es obligatorio y admite hasta {$maximo} caracteres.");
        }
        return $valor;
    }

    private function numeroPositivo(array $datos, string $campo): float
    {
        $valor = filter_var($datos[$campo] ?? null, FILTER_VALIDATE_FLOAT);
        if ($valor === false || $valor <= 0) {
            throw new InvalidArgumentException("El campo {$campo} debe ser mayor que cero.");
        }
        return (float) $valor;
    }

    private function opcion(array $datos, string $campo, array $opciones, bool $minusculas = true): string
    {
        $valor = trim((string) ($datos[$campo] ?? ''));
        if ($minusculas) {
            $valor = strtolower($valor);
        }
        if (!in_array($valor, $opciones, true)) {
            throw new InvalidArgumentException("El valor de {$campo} no es válido.");
        }
        return $valor;
    }
}
