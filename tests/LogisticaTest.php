<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../apis/api-logistica/LogisticaController.php';

class MockLogisticaModel extends LogisticaModel
{
    public array $vehiculos = [];
    public array $rutas = [];
    public array $cuadrillas = [];

    public function __construct()
    {
        $this->vehiculos = [
            1 => [
                'idVehi' => 1,
                'matriculaVehi' => 'STP1234',
                'tipoVehi' => 'comunitario',
                'marcaVehi' => 'Mercedes-Benz',
                'modeloVehi' => 'Atego',
                'capVehi' => 15000.0,
                'estVehi' => 'disponible'
            ]
        ];

        $this->cuadrillas = [
            1 => [
                'idCuadrilla' => 1,
                'idChofer' => 5,
                'idPeon' => 6
            ]
        ];
    }

    public function matriculaExiste(string $matricula, int $ignorarId = 0): bool
    {
        foreach ($this->vehiculos as $v) {
            if ($v['matriculaVehi'] === $matricula && $v['idVehi'] !== $ignorarId) {
                return true;
            }
        }
        return false;
    }

    public function crearVehi(array $payload): array
    {
        $id = count($this->vehiculos) + 1;
        $payload['idVehi'] = $id;
        $this->vehiculos[$id] = $payload;
        return $payload;
    }

    public function vehiculoTieneCuadrillaConSesionActiva(int $idVehi): bool
    {
        return $idVehi === 1; // Simula camión en ruta activa
    }

    public function crearCuadrilla(int $idChofer, int $idPeon): array
    {
        $id = count($this->cuadrillas) + 1;
        $c = ['idCuadrilla' => $id, 'idChofer' => $idChofer, 'idPeon' => $idPeon];
        $this->cuadrillas[$id] = $c;
        return $c;
    }

    public function cuadrillaTieneMiembrosConSesionActiva(int $idCuadrilla): bool
    {
        return $idCuadrilla === 1; // Simula cuadrilla trabajando
    }

    public function validarContenedoresParaRuta(array $contenedores, int $idRutaActual = 0): array
    {
        return []; // Sin errores de validación de contenedores
    }

    public function crearRuta(array $payload): array
    {
        $id = count($this->rutas) + 1;
        $payload['idRuta'] = $id;
        $this->rutas[$id] = $payload;
        return $payload;
    }

    public function rutaTieneCuadrillaConSesionActiva(int $idRuta): bool
    {
        return $idRuta === 1;
    }
}

class LogisticaTest extends TestCase
{
    private LogisticaController $controller;
    private MockLogisticaModel $mockModel;

    public function __construct()
    {
        parent::__construct('Logística, Rutas y Cuadrillas');
        $this->mockModel = new MockLogisticaModel();
        $this->controller = new LogisticaController($this->mockModel);
    }

    public function runAll(): void
    {
        $this->testGuardarCuadrillaFallaMismoChoferYPeon();
        $this->testGuardarCuadrillaExitosa();
        $this->testEliminarCuadrillaFallaSiTieneSesionActiva();
        $this->testGuardarRutaFallaMenosDe50Contenedores();
        $this->testGuardarRutaFallaMasDe1000Contenedores();
        $this->testGuardarRutaExitosaConRangoValido();
        $this->testGuardarVehiculoFallaMatriculaFormatoInvalido();
        $this->testGuardarVehiculoFallaMatriculaDuplicada();
        $this->testEliminarVehiculoFallaSiCuadrillaEnRutaActiva();
    }

    public function testGuardarCuadrillaFallaMismoChoferYPeon(): void
    {
        $this->assertThrows(InvalidArgumentException::class, function() {
            $this->controller->guardarCuadrilla([
                'idChofer' => 5,
                'idPeon' => 5 // Mismo usuario para chofer y peón
            ]);
        }, 'Regla de negocio cuadrilla: chofer y peón deben ser usuarios distintos');
    }

    public function testGuardarCuadrillaExitosa(): void
    {
        $resultado = $this->controller->guardarCuadrilla([
            'idChofer' => 10,
            'idPeon' => 11
        ]);

        $this->assertEquals(10, $resultado['idChofer'], 'Cuadrilla creada: chofer asignado');
        $this->assertEquals(11, $resultado['idPeon'], 'Cuadrilla creada: peón asignado');
    }

    public function testEliminarCuadrillaFallaSiTieneSesionActiva(): void
    {
        $this->assertThrows(InvalidArgumentException::class, function() {
            $this->controller->eliminarCuadrilla(1);
        }, 'Protección operativa: cuadrilla en servicio activo no puede ser eliminada');
    }

    public function testGuardarRutaFallaMenosDe50Contenedores(): void
    {
        // 10 contenedores (menor al mínimo de 50)
        $contenedores = range(1, 10);

        $this->assertThrows(InvalidArgumentException::class, function() use ($contenedores) {
            $this->controller->guardarRuta([
                'frecuencia' => 'Diaria',
                'idCentro' => 1,
                'contenedores' => $contenedores
            ]);
        }, 'Capacidad de ruta: rechaza rutas con menos de 50 contenedores (mínimo operativo)');
    }

    public function testGuardarRutaFallaMasDe1000Contenedores(): void
    {
        // 1001 contenedores (mayor al máximo de 1000)
        $contenedores = range(1, 1001);

        $this->assertThrows(InvalidArgumentException::class, function() use ($contenedores) {
            $this->controller->guardarRuta([
                'frecuencia' => 'Diaria',
                'idCentro' => 1,
                'contenedores' => $contenedores
            ]);
        }, 'Capacidad de ruta: rechaza rutas con más de 1000 contenedores (máximo operativo)');
    }

    public function testGuardarRutaExitosaConRangoValido(): void
    {
        // 60 contenedores (dentro del rango 50 a 1000)
        $contenedores = range(1, 60);

        $resultado = $this->controller->guardarRuta([
            'frecuencia' => 'Diaria - Municipio B',
            'idCentro' => 1,
            'contenedores' => $contenedores
        ]);

        $this->assertEquals(1, $resultado['idCentro'], 'Ruta creada: centro de acopio vinculado');
        $this->assertEquals(60, count($resultado['contenedores']), 'Ruta creada: 60 contenedores asociados correctamente');
    }

    public function testGuardarVehiculoFallaMatriculaFormatoInvalido(): void
    {
        $this->assertThrows(InvalidArgumentException::class, function() {
            $this->controller->guardarVehiculo([
                'tipo' => 'comunitario',
                'matricula' => 'STP-12', // Formato inválido (debe ser 3 letras y 4 números)
                'marca' => 'Iveco',
                'modelo' => 'Tector',
                'capacidad' => 12000,
                'estado' => 'disponible'
            ]);
        }, 'Validación vehicular: formato estricto de matrícula (3 letras mayúsculas y 4 números)');
    }

    public function testGuardarVehiculoFallaMatriculaDuplicada(): void
    {
        $this->assertThrows(InvalidArgumentException::class, function() {
            $this->controller->guardarVehiculo([
                'tipo' => 'comunitario',
                'matricula' => 'STP1234', // Ya existe en la flota
                'marca' => 'Scania',
                'modelo' => 'P280',
                'capacidad' => 15000,
                'estado' => 'disponible'
            ]);
        }, 'Unicidad vehicular: no permite registrar matrículas duplicadas');
    }

    public function testEliminarVehiculoFallaSiCuadrillaEnRutaActiva(): void
    {
        $this->assertThrows(InvalidArgumentException::class, function() {
            $this->controller->eliminarVehiculo(1);
        }, 'Protección de flota: vehículo en recorrido activo no puede ser borrado');
    }
}
