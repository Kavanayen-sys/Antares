<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../apis/api-centros/CentroController.php';

class MockCentroModel extends CentroModel
{
    public array $centros = [];
    public array $maquinarias = [];
    public array $vertederos = [];

    public function __construct()
    {
        $this->centros = [
            1 => [
                'idCentro' => 1,
                'ubiCentro' => 'Camino Corrales 2800',
                'tipoCentro' => 'comun',
                'capCentro' => 50000,
                'capActual' => 12000
            ]
        ];
    }

    public function crearCentro(array $datos): array
    {
        $id = count($this->centros) + 1;
        $datos['idCentro'] = $id;
        $this->centros[$id] = $datos;
        return $datos;
    }

    public function crearMaquinaria(array $datos): array
    {
        $id = count($this->maquinarias) + 1;
        $datos['idMaq'] = $id;
        $this->maquinarias[$id] = $datos;
        return $datos;
    }

    public function crearVertedero(array $datos): array
    {
        $id = count($this->vertederos) + 1;
        $datos['idVertedero'] = $id;
        $this->vertederos[$id] = $datos;
        return $datos;
    }

    public function registrarLlegadaCamion(int $idVehi, int $idCentro, int $idCuadrilla, ?int $idRuta = null): array
    {
        // Si el centro es comun y el residuo es aceite, simula el fallo de RF-21
        if ($idCentro === 1 && $idRuta === 99) {
            throw new InvalidArgumentException("El residuo de los contenedores (aceite) no coincide con el tipo del centro de acopio (comun). (RF-21)");
        }

        return [
            'idDescarga' => 1,
            'idRecorrido' => 1,
            'idVehi' => $idVehi,
            'idCentro' => $idCentro,
            'idCuadrilla' => $idCuadrilla,
            'estado' => 'confirmada',
            'mensaje' => 'Llegada y descarga del camión registradas con éxito en el centro de acopio.'
        ];
    }
}

class CentroTest extends TestCase
{
    private CentroController $controller;
    private MockCentroModel $mockModel;

    public function __construct()
    {
        parent::__construct('Centros de Acopio y Disposición Final (RF-20, RF-21)');
        $this->mockModel = new MockCentroModel();
        $this->controller = new CentroController($this->mockModel);
    }

    public function runAll(): void
    {
        $this->testGuardarCentroExitoso();
        $this->testGuardarCentroFallaCapacidadNegativa();
        $this->testGuardarCentroFallaTipoInvalido();
        $this->testGuardarMaquinariaValidaCampos();
        $this->testLlegadaResiduosValidaTipoCompatibleRF21();
        $this->testLlegadaResiduosFallaTipoIncompatibleRF21();
    }

    public function testGuardarCentroExitoso(): void
    {
        $resultado = $this->controller->guardarCentro([
            'ubiCentro' => 'Av. 8 de Octubre 3400',
            'tipoCentro' => 'reciclables',
            'capCentro' => 30000
        ]);

        $this->assertEquals('reciclables', $resultado['tipoCentro'], 'Centro de acopio: tipo registrado');
        $this->assertEquals(30000, $resultado['capCentro'], 'Centro de acopio: capacidad máxima registrada');
    }

    public function testGuardarCentroFallaCapacidadNegativa(): void
    {
        $this->assertThrows(InvalidArgumentException::class, function() {
            $this->controller->guardarCentro([
                'ubiCentro' => 'Calle Falsa 123',
                'tipoCentro' => 'comun',
                'capCentro' => -100
            ]);
        }, 'Restricción de planta: capacidad de centro debe ser estrictamente positiva');
    }

    public function testGuardarCentroFallaTipoInvalido(): void
    {
        $this->assertThrows(InvalidArgumentException::class, function() {
            $this->controller->guardarCentro([
                'ubiCentro' => 'Ruta 1',
                'tipoCentro' => 'desechos_quimicos_no_permitidos',
                'capCentro' => 20000
            ]);
        }, 'Categorización ambiental: tipo de centro debe ser comun, aceite, electronicos o reciclables');
    }

    public function testGuardarMaquinariaValidaCampos(): void
    {
        $maq = $this->controller->guardarMaquinaria([
            'idCentro' => 1,
            'proposito' => 'Compactadora hidráulica de fardos',
            'capacidad' => 500.0,
            'marca' => 'Caterpillar',
            'modelo' => 'CAT-920',
            'numSerie' => 'SN-998877'
        ]);

        $this->assertEquals(1, $maq['idCentro'], 'Inventario maquinaria: asignada al centro correspondiente');
        $this->assertEquals('SN-998877', $maq['numSerie'], 'Inventario maquinaria: número de serie único');
    }

    public function testLlegadaResiduosValidaTipoCompatibleRF21(): void
    {
        $res = $this->controller->registrarLlegadaCamion([
            'idVehi' => 1,
            'idCentro' => 1,
            'idCuadrilla' => 1
        ]);

        $this->assertEquals('confirmada', $res['estado'], 'RF-21: llegada aceptada al coincidir tipo de residuo con planta');
    }

    public function testLlegadaResiduosFallaTipoIncompatibleRF21(): void
    {
        // Simula ruta con aceite enviada a planta de residuos comunes
        $this->assertThrows(InvalidArgumentException::class, function() {
            $this->controller->registrarLlegadaCamion([
                'idVehi' => 1,
                'idCentro' => 1,
                'idCuadrilla' => 1,
                'idRuta' => 99 // Ruta con residuo incompatible
            ]);
        }, 'RF-21: sistema rechaza descarga si el tipo de residuo no coincide con el centro de acopio');
    }
}
