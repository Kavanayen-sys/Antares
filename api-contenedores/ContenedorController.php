<?php

declare(strict_types=1);

require_once __DIR__ . '/ContenedorModel.php';

class ContenedorController
{
    private ContenedorModel $modelo;

    private const ZONAS = [
        'Municipio A',
        'Municipio B',
        'Municipio C',
        'Municipio CH',
        'Municipio D',
        'Municipio E',
        'Municipio F',
        'Municipio G',
    ];

    private const TIPOS_CONTENEDOR = [
        'comun',
        'aceite',
        'electronicos',
        'reciclables'
    ];

    private const TIPOS_INCIDENCIA = [
        'roto',
        'incendiado',
        'desbordado',
        'basura alrededor'
    ];

    private const ESTADOS_INCIDENCIA = [
        'resuelto',
        'en curso',
        'descartado'
    ];

    public function __construct()
    {
        $this->modelo = new ContenedorModel();
    }

    public function listarContenedores(): array
    {
        return $this->modelo->listarContenedores();
    }

    public function guardarContenedor(array $datos, int $id = 0): array
    {
        $capacidad = $this->numeroPositivo($datos, 'capacidad');
        $calle = $this->texto($datos, 'calle', 100);
        $esquina = $this->texto($datos, 'esquina', 100);
        $zona = $this->opcion($datos, 'zona', self::ZONAS, false);
        $tipo = $this->opcion($datos, 'tipo', self::TIPOS_CONTENEDOR);
        $estado = $this->opcion($datos, 'estado', ['activo', 'inactivo']);
        $repuesto = !empty($datos['repuesto']) ? 1 : 0;

        $latitud = isset($datos['latitud']) && is_numeric($datos['latitud']) ? (float) $datos['latitud'] : null;
        $longitud = isset($datos['longitud']) && is_numeric($datos['longitud']) ? (float) $datos['longitud'] : null;

        if ($latitud !== null && ($latitud < -90 || $latitud > 90)) {
            throw new InvalidArgumentException('Latitud fuera de rango.');
        }
        if ($longitud !== null && ($longitud < -180 || $longitud > 180)) {
            throw new InvalidArgumentException('Longitud fuera de rango.');
        }

        $payload = [
            'capacidad' => $capacidad,
            'calle' => $calle,
            'esquina' => $esquina,
            'zona' => $zona,
            'estado' => $estado,
            'tipo' => $tipo,
            'repuesto' => $repuesto,
            'latitud' => $latitud,
            'longitud' => $longitud,
        ];

        if ($id > 0) {
            $actualizado = $this->modelo->actualizarContenedor($id, $payload);
            if (!$actualizado) {
                throw new OutOfBoundsException('El contenedor no existe.');
            }
            return $actualizado;
        }

        return $this->modelo->crearContenedor($payload);
    }

    public function eliminarContenedor(int $id): bool
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('ID de contenedor inválido.');
        }
        return $this->modelo->eliminarContenedor($id);
    }

    public function listarIncidencias(): array
    {
        return $this->modelo->listarIncidencias();
    }

    public function reportarIncidencia(array $datos): array
    {
        $idCon = filter_var($datos['idCon'] ?? null, FILTER_VALIDATE_INT);
        if (!$idCon || $idCon <= 0) {
            throw new InvalidArgumentException('El contenedor indicado no es válido.');
        }

        $contenedor = $this->modelo->obtenerContenedor((int) $idCon);
        if (!$contenedor) {
            throw new OutOfBoundsException('El contenedor seleccionado no existe.');
        }

        $tipo = $this->opcion($datos, 'tipo', self::TIPOS_INCIDENCIA);
        $descripcion = $this->texto($datos, 'descripcion', 200);
        $documento = trim((string) ($datos['documento'] ?? ''));

        if (!preg_match('/^[0-9]{7,9}$/', $documento)) {
            throw new InvalidArgumentException('El documento debe contener únicamente números y tener entre 7 y 9 dígitos.');
        }

        return $this->modelo->crearIncidencia([
            'idCon' => (int) $idCon,
            'tipo' => $tipo,
            'descripcion' => $descripcion,
            'documento' => $documento,
            'prioridad' => $this->prioridad($tipo),
        ]);
    }

    public function actualizarEstadoIncidencia(int $id, array $datos): array
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('ID de incidencia inválido.');
        }
        $estado = $this->opcion($datos, 'estado', self::ESTADOS_INCIDENCIA);
        return $this->modelo->actualizarEstadoIncidencia($id, $estado);
    }

    public function eliminarIncidencia(int $id): bool
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('ID de incidencia inválido.');
        }
        return $this->modelo->eliminarIncidencia($id);
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

    private function prioridad(string $tipo): string
    {
        return match ($tipo) {
            'incendiado' => 'alta',
            'desbordado',
            'roto' => 'media',
            default => 'baja',
        };
    }
}
