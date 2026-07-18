<?php

declare(strict_types=1);

require_once __DIR__ . '/GestionModel.php';

class GestionController
{
    private GestionModel $modelo;
    private const ZONAS = [
        'Municipio A', 'Municipio B', 'Municipio C', 'Municipio CH',
        'Municipio D', 'Municipio E', 'Municipio F', 'Municipio G',
    ];
    private const TIPOS_CONTENEDOR = ['comun', 'aceite', 'electronicos', 'reciclables'];
    private const TIPOS_CAMION = ['comunitario', 'intradomiciliario', 'limpieza', 'centroAcopio'];
    private const ESTADOS_CAMION = ['disponible', 'en uso', 'roto'];
    private const TIPOS_INCIDENCIA = ['roto', 'incendiado', 'desbordado', 'basura alrededor'];

    public function __construct()
    {
        $this->modelo = new GestionModel();
    }

    public function listarContenedores(): array
    {
        return $this->modelo->listarContenedores();
    }

    public function guardarContenedor(array $datos, int $id = 0): array
    {
        $limpios = [
            'capacidad' => $this->numeroPositivo($datos, 'capacidad'),
            'calle' => $this->texto($datos, 'calle', 100),
            'esquina' => $this->texto($datos, 'esquina', 100),
            'zona' => $this->opcion($datos, 'zona', self::ZONAS, false),
            'estado' => $this->opcion($datos, 'estado', ['activo', 'inactivo']),
            'tipo' => $this->opcion($datos, 'tipo', self::TIPOS_CONTENEDOR),
            'repuesto' => !empty($datos['repuesto']) ? 1 : 0,
        ];

        if ($id > 0 && !$this->modelo->obtenerContenedor($id)) {
            throw new OutOfBoundsException('El contenedor no existe.');
        }
        return $id > 0
            ? $this->modelo->actualizarContenedor($id, $limpios)
            : $this->modelo->crearContenedor($limpios);
    }

    public function eliminarContenedor(int $id): bool
    {
        return $this->modelo->eliminarContenedor($id);
    }

    public function listarCamiones(): array
    {
        return $this->modelo->listarCamiones();
    }

    public function guardarCamion(array $datos, int $id = 0): array
    {
        $matricula = strtoupper($this->texto($datos, 'matricula', 10));
        if ($this->modelo->matriculaExiste($matricula, $id)) {
            throw new InvalidArgumentException('La matrícula ya está registrada.');
        }

        $limpios = [
            'tipo' => $this->opcion($datos, 'tipo', self::TIPOS_CAMION, false),
            'matricula' => $matricula,
            'marca' => $this->texto($datos, 'marca', 50),
            'modelo' => $this->texto($datos, 'modelo', 50),
            'capacidad' => $this->numeroPositivo($datos, 'capacidad'),
            'estado' => $this->opcion($datos, 'estado', self::ESTADOS_CAMION),
            'repuesto' => !empty($datos['repuesto']) ? 1 : 0,
        ];

        if ($id > 0 && !$this->modelo->obtenerCamion($id)) {
            throw new OutOfBoundsException('El camión no existe.');
        }
        return $id > 0
            ? $this->modelo->actualizarCamion($id, $limpios)
            : $this->modelo->crearCamion($limpios);
    }

    public function eliminarCamion(int $id): bool
    {
        return $this->modelo->eliminarCamion($id);
    }

    public function listarIncidencias(): array
    {
        return $this->modelo->listarIncidencias();
    }

    public function reportarIncidencia(array $datos): array
    {
        $idContenedor = filter_var($datos['idCon'] ?? null, FILTER_VALIDATE_INT);
        if (!$idContenedor || !$this->modelo->obtenerContenedor((int) $idContenedor)) {
            throw new InvalidArgumentException('Seleccioná un contenedor válido.');
        }

        $documento = preg_replace('/\D+/', '', (string) ($datos['documento'] ?? ''));
        if (strlen($documento) < 6 || strlen($documento) > 12) {
            throw new InvalidArgumentException('El documento debe tener entre 6 y 12 dígitos.');
        }

        $limpios = [
            'idCon' => (int) $idContenedor,
            'nombre' => $this->texto($datos, 'nombre', 100),
            'documento' => $documento,
            'telefono' => $this->texto($datos, 'telefono', 20),
            'tipo' => $this->opcion($datos, 'tipo', self::TIPOS_INCIDENCIA),
            'descripcion' => $this->texto($datos, 'descripcion', 200),
            'prioridad' => $this->prioridad((string) ($datos['tipo'] ?? '')),
        ];
        return $this->modelo->crearIncidencia($limpios);
    }

    public function eliminarIncidencia(int $id): bool
    {
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
            'desbordado', 'roto' => 'media',
            default => 'baja',
        };
    }
}
