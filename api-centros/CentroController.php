<?php

declare(strict_types=1);

require_once __DIR__ . '/CentroModel.php';

class CentroController
{
    private CentroModel $modelo;

    private const TIPOS_CENTRO = [
        'comun',
        'aceite',
        'electronicos',
        'reciclables'
    ];

    public function __construct()
    {
        $this->modelo = new CentroModel();
    }

    public function listarMaquinaria(): array
    {
        return $this->modelo->listarMaquinaria();
    }

    public function guardarMaquinaria(array $datos, int $id = 0): array
    {
        $idCentro = filter_var($datos['idCentro'] ?? null, FILTER_VALIDATE_INT);
        if (!$idCentro || $idCentro <= 0) {
            throw new InvalidArgumentException('Seleccioná un centro válido.');
        }

        $proposito = $this->texto($datos, 'proposito', 200);
        $capacidad = filter_var($datos['capacidad'] ?? null, FILTER_VALIDATE_FLOAT);
        if ($capacidad === false || $capacidad < 0) {
            throw new InvalidArgumentException('La capacidad de la maquinaria debe ser un número mayor o igual a 0.');
        }

        $marca = $this->texto($datos, 'marca', 50);
        $modelo = $this->texto($datos, 'modelo', 50);
        $numSerie = $this->texto($datos, 'numSerie', 50);

        if ($id > 0 && !$this->modelo->obtenerMaquinaria($id)) {
            throw new OutOfBoundsException('La maquinaria no existe.');
        }

        $limpios = [
            'idCentro' => (int) $idCentro,
            'proposito' => $proposito,
            'capacidad' => (float) $capacidad,
            'marca' => $marca,
            'modelo' => $modelo,
            'numSerie' => $numSerie,
        ];

        if ($id > 0) {
            return $this->modelo->actualizarMaquinaria($id, $limpios);
        }

        return $this->modelo->crearMaquinaria($limpios);
    }

    public function eliminarMaquinaria(int $id): bool
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('ID de maquinaria inválido.');
        }
        return $this->modelo->eliminarMaquinaria($id);
    }

    public function listarCentros(): array
    {
        return $this->modelo->listarCentros();
    }

    public function guardarCentro(array $datos, int $id = 0): array
    {
        $ubicacion = $this->texto($datos, 'ubiCentro', 150);
        $tipo = $this->opcion($datos, 'tipoCentro', self::TIPOS_CENTRO);
        $capacidad = filter_var($datos['capCentro'] ?? null, FILTER_VALIDATE_INT);
        if (!$capacidad || $capacidad <= 0) {
            throw new InvalidArgumentException('La capacidad del centro debe ser mayor a 0.');
        }

        if ($id > 0 && !$this->modelo->obtenerCentro($id)) {
            throw new OutOfBoundsException('El centro no existe.');
        }

        $limpios = [
            'ubiCentro' => $ubicacion,
            'tipoCentro' => $tipo,
            'capCentro' => (int) $capacidad,
        ];

        if ($id > 0) {
            return $this->modelo->actualizarCentro($id, $limpios);
        }

        return $this->modelo->crearCentro($limpios);
    }

    public function eliminarCentro(int $id): bool
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('ID de centro inválido.');
        }
        return $this->modelo->eliminarCentro($id);
    }

    public function listarVertederos(): array
    {
        return $this->modelo->listarVertederos();
    }

    public function guardarVertedero(array $datos): array
    {
        $ubicacion = $this->texto($datos, 'ubicacion', 150);
        return $this->modelo->crearVertedero(['ubicacion' => $ubicacion]);
    }

    private function texto(array $datos, string $campo, int $maximo): string
    {
        $valor = trim((string) ($datos[$campo] ?? ''));
        if ($valor === '' || strlen($valor) > $maximo) {
            throw new InvalidArgumentException("El campo {$campo} es obligatorio y admite hasta {$maximo} caracteres.");
        }
        return $valor;
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
