<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../apis/api-contenedores/ContenedorController.php';

class MockContenedorModel extends ContenedorModel
{
    public array $contenedores = [];
    public array $incidencias = [];

    public function __construct()
    {
        $this->contenedores = [
            1 => [
                'idCon' => 1,
                'capacidad' => 3200.0,
                'calle' => 'Av. 18 de Julio',
                'esquina' => 'Ejido',
                'zona' => 'Municipio B',
                'estCon' => 'activo',
                'tipoCon' => 'comun',
                'repuesto' => 0,
                'latitud' => -34.90562,
                'longitud' => -56.18652
            ]
        ];
    }

    public function obtenerContenedor(int $id): ?array
    {
        return $this->contenedores[$id] ?? null;
    }

    public function crearContenedor(array $datos): array
    {
        $id = count($this->contenedores) + 1;
        $datos['idCon'] = $id;
        $this->contenedores[$id] = $datos;
        return $datos;
    }

    public function crearIncidencia(array $datos): array
    {
        $id = count($this->incidencias) + 1;
        $datos['idInci'] = $id;
        $datos['fchaInci'] = date('Y-m-d H:i:s');
        $datos['estado'] = 'en curso';
        $this->incidencias[$id] = $datos;
        return $datos;
    }
}

class ContenedorTest extends TestCase
{
    private ContenedorController $controller;
    private MockContenedorModel $mockModel;

    public function __construct()
    {
        parent::__construct('Gestión de Contenedores e Incidencias');
        $this->mockModel = new MockContenedorModel();
        $this->controller = new ContenedorController($this->mockModel);
    }

    public function runAll(): void
    {
        $this->testGuardarContenedorExitoso();
        $this->testGuardarContenedorFallaCapacidadNegativaOCero();
        $this->testGuardarContenedorFallaCalleVacia();
        $this->testGuardarContenedorFallaZonaInvalida();
        $this->testGuardarContenedorFallaTipoInvalido();
        $this->testGuardarContenedorFallaLatitudFueraDeRango();
        $this->testReportarIncidenciaExitosa();
        $this->testReportarIncidenciaFallaDocumentoInvalido();
        $this->testReportarIncidenciaFallaTipoInvalido();
        $this->testReportarIncidenciaPrioridadAutomatica();
    }

    public function testGuardarContenedorExitoso(): void
    {
        $resultado = $this->controller->guardarContenedor([
            'capacidad' => 2400.0,
            'calle' => 'Colonia',
            'esquina' => 'Río Negro',
            'zona' => 'Municipio B',
            'tipo' => 'reciclables',
            'estado' => 'activo',
            'latitud' => -34.904,
            'longitud' => -56.191
        ]);

        $this->assertEquals(2400.0, (float)$resultado['capacidad'], 'Contenedor creado: capacidad asignada correctamente');
        $this->assertEquals('reciclables', $resultado['tipo'], 'Contenedor creado: tipo de residuo asignado');
        $this->assertEquals('Municipio B', $resultado['zona'], 'Contenedor creado: zona municipal válida');
    }

    public function testGuardarContenedorFallaCapacidadNegativaOCero(): void
    {
        $this->assertThrows(InvalidArgumentException::class, function() {
            $this->controller->guardarContenedor([
                'capacidad' => -500, // Inválido
                'calle' => 'San José',
                'esquina' => 'Paraguay',
                'zona' => 'Municipio B',
                'tipo' => 'comun',
                'estado' => 'activo'
            ]);
        }, 'Restricción física: capacidad del contenedor debe ser mayor a cero');
    }

    public function testGuardarContenedorFallaCalleVacia(): void
    {
        $this->assertThrows(InvalidArgumentException::class, function() {
            $this->controller->guardarContenedor([
                'capacidad' => 1500,
                'calle' => '', // Vacío
                'esquina' => 'Paraguay',
                'zona' => 'Municipio B',
                'tipo' => 'comun',
                'estado' => 'activo'
            ]);
        }, 'Campos obligatorios: la calle del contenedor no puede estar vacía');
    }

    public function testGuardarContenedorFallaZonaInvalida(): void
    {
        $this->assertThrows(InvalidArgumentException::class, function() {
            $this->controller->guardarContenedor([
                'capacidad' => 1500,
                'calle' => 'San José',
                'esquina' => 'Paraguay',
                'zona' => 'Municipio Fantasma Z', // Zona inexistente en Montevideo
                'tipo' => 'comun',
                'estado' => 'activo'
            ]);
        }, 'Integridad territorial: la zona debe pertenecer a los Municipios de Montevideo (A-G)');
    }

    public function testGuardarContenedorFallaTipoInvalido(): void
    {
        $this->assertThrows(InvalidArgumentException::class, function() {
            $this->controller->guardarContenedor([
                'capacidad' => 1500,
                'calle' => 'San José',
                'esquina' => 'Paraguay',
                'zona' => 'Municipio B',
                'tipo' => 'nuclear', // Tipo no permitido
                'estado' => 'activo'
            ]);
        }, 'Categoría de residuo: solo admite tipos homologados (comun, reciclables, aceite, electronicos)');
    }

    public function testGuardarContenedorFallaLatitudFueraDeRango(): void
    {
        $this->assertThrows(InvalidArgumentException::class, function() {
            $this->controller->guardarContenedor([
                'capacidad' => 1500,
                'calle' => 'San José',
                'esquina' => 'Paraguay',
                'zona' => 'Municipio B',
                'tipo' => 'comun',
                'estado' => 'activo',
                'latitud' => 140.0 // Latitud máxima es 90
            ]);
        }, 'Georreferenciación: latitud debe encontrarse entre -90 y +90 grados');
    }

    public function testReportarIncidenciaExitosa(): void
    {
        $inci = $this->controller->reportarIncidencia([
            'idCon' => 1,
            'tipo' => 'roto',
            'descripcion' => 'Tapa dañada y pedal de apertura trabado',
            'documento' => '45678901' // Cédula uruguaya válida sin guion
        ]);

        $this->assertEquals(1, $inci['idCon'], 'Incidencia registrada: vinculada al contenedor correcto');
        $this->assertEquals('roto', $inci['tipo'], 'Incidencia registrada: tipo almacenado');
        $this->assertEquals('en curso', $inci['estado'], 'Incidencia registrada: estado inicial "en curso"');
    }

    public function testReportarIncidenciaFallaDocumentoInvalido(): void
    {
        $this->assertThrows(InvalidArgumentException::class, function() {
            $this->controller->reportarIncidencia([
                'idCon' => 1,
                'tipo' => 'desbordado',
                'descripcion' => 'Basura alrededor del contenedor',
                'documento' => '123' // Documento inválido (menos de 7 dígitos)
            ]);
        }, 'Validación ciudadana: cédula uruguaya debe ser numérica y tener entre 7 y 9 dígitos');
    }

    public function testReportarIncidenciaFallaTipoInvalido(): void
    {
        $this->assertThrows(InvalidArgumentException::class, function() {
            $this->controller->reportarIncidencia([
                'idCon' => 1,
                'tipo' => 'desaparicion_magica',
                'descripcion' => 'El contenedor desapareció',
                'documento' => '41234567'
            ]);
        }, 'Catálogo de incidencias: rechaza tipos no estandarizados');
    }

    public function testReportarIncidenciaPrioridadAutomatica(): void
    {
        // Contenedor prendido fuego debe ser prioridad ALTA automáticamente
        $inciFuego = $this->controller->reportarIncidencia([
            'idCon' => 1,
            'tipo' => 'incendiado',
            'descripcion' => 'Fuego y humo dentro del contenedor',
            'documento' => '51234567'
        ]);

        $this->assertEquals('alta', $inciFuego['prioridad'], 'Algoritmo de prioridad: siniestro incendiado asigna prioridad ALTA de urgencia');
    }
}
