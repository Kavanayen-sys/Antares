<?php

declare(strict_types=1);

require_once __DIR__ . '/GestionModel.php';

class GestionController
{
    private GestionModel $modelo;

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
            'estado' => $this->opcion(
                $datos,
                'estado',
                ['activo', 'inactivo']
            ),
            'tipo' => $this->opcion(
                $datos,
                'tipo',
                self::TIPOS_CONTENEDOR
            ),
            'repuesto' => !empty($datos['repuesto']) ? 1 : 0,
        ];

        $lat = filter_var($datos['latitud'] ?? null, FILTER_VALIDATE_FLOAT);
        $lng = filter_var($datos['longitud'] ?? null, FILTER_VALIDATE_FLOAT);
        if ($lat !== false && $lat !== null && $lng !== false && $lng !== null) {
            $limpios['latitud'] = (float) $lat;
            $limpios['longitud'] = (float) $lng;
        } else {
            $limpios['latitud'] = null;
            $limpios['longitud'] = null;
        }

        if ($id > 0 && !$this->modelo->obtenerContenedor($id)) {
            throw new OutOfBoundsException(
                'El contenedor no existe.'
            );
        }

        if ($id > 0) {
            return $this->modelo->actualizarContenedor(
                $id,
                $limpios
            );
        }

        return $this->modelo->crearContenedor($limpios);
    }

    public function eliminarContenedor(int $id): bool
    {
        return $this->modelo->eliminarContenedor($id);
    }

    public function listarVehiculos(): array
    {
        return $this->modelo->listarVehi();
    }

    public function guardarVehiculo(
        array $datos,
        int $id = 0
    ): array {
        $matricula = strtoupper(
            $this->texto($datos, 'matricula', 10)
        );

        if (
            $this->modelo->matriculaExiste(
                $matricula,
                $id
            )
        ) {
            throw new InvalidArgumentException(
                'La matrícula ya está registrada.'
            );
        }

        $limpios = [
            'tipo' => $this->opcion(
                $datos,
                'tipo',
                self::TIPOS_VEHICULO,
                false
            ),
            'matricula' => $matricula,
            'marca' => $this->texto(
                $datos,
                'marca',
                50
            ),
            'modelo' => $this->texto(
                $datos,
                'modelo',
                50
            ),
            'capacidad' => $this->numeroPositivo(
                $datos,
                'capacidad'
            ),
            'estado' => $this->opcion(
                $datos,
                'estado',
                self::ESTADOS_VEHICULO
            ),
        ];

        if (
            $id > 0 &&
            !$this->modelo->obtenerVehi($id)
        ) {
            throw new OutOfBoundsException(
                'El vehículo no existe.'
            );
        }

        if ($id > 0) {
            return $this->modelo->actualizarVehiculo(
                $id,
                $limpios
            );
        }

        return $this->modelo->crearVehi($limpios);
    }

    public function eliminarVehiculo(int $id): bool
    {
        return $this->modelo->eliminarVehiculo($id);
    }

    public function guardarVehi(array $datos, int $id = 0): array
    {
        return $this->guardarVehiculo($datos, $id);
    }

    public function eliminarVehi(int $id): bool
    {
        return $this->eliminarVehiculo($id);
    }

    public function listarIncidencias(): array
    {
        return $this->modelo->listarIncidencias();
    }

    public function reportarIncidencia(array $datos): array
    {
        $idContenedor = filter_var(
            $datos['idCon'] ?? null,
            FILTER_VALIDATE_INT
        );

        if (
            !$idContenedor ||
            !$this->modelo->obtenerContenedor(
                (int) $idContenedor
            )
        ) {
            throw new InvalidArgumentException(
                'Seleccioná un contenedor válido.'
            );
        }

        $documento = preg_replace(
            '/\D+/',
            '',
            (string) ($datos['documento'] ?? '')
        );

        if (
            strlen($documento) < 6 ||
            strlen($documento) > 12
        ) {
            throw new InvalidArgumentException(
                'El documento debe tener entre 6 y 12 dígitos.'
            );
        }

        $tipo = $this->opcion(
            $datos,
            'tipo',
            self::TIPOS_INCIDENCIA
        );

        $limpios = [
            'idCon' => (int) $idContenedor,
            'documento' => $documento,
            'tipo' => $tipo,
            'descripcion' => $this->texto(
                $datos,
                'descripcion',
                200
            ),
            'prioridad' => $this->prioridad($tipo),
        ];

        return $this->modelo->crearIncidencia($limpios);
    }

    public function actualizarEstadoIncidencia(int $id, array $datos): array
    {
        $estado = $this->opcion(
            $datos,
            'estado',
            self::ESTADOS_INCIDENCIA
        );

        return $this->modelo->actualizarEstadoIncidencia($id, $estado);
    }

    public function eliminarIncidencia(int $id): bool
    {
        return $this->modelo->eliminarIncidencia($id);
    }

    // =========================================================
    // RUTAS
    // =========================================================

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
            throw new InvalidArgumentException('Seleccioná un centro de acopio válido.');
        }

        $contenedores = is_array($datos['contenedores'] ?? null) ? $datos['contenedores'] : [];

        $limpios = [
            'frecuencia' => $frecuencia,
            'idCentro' => (int) $idCentro,
            'contenedores' => $contenedores,
        ];

        return $id === 0 ? $this->modelo->crearRuta($limpios) : $this->modelo->actualizarRuta($id, $limpios);
    }

    public function eliminarRuta(int $id): bool
    {
        return $this->modelo->eliminarRuta($id);
    }

    // =========================================================
    // CUADRILLAS
    // =========================================================

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

        if (!$idChofer || !$idPeon) {
            throw new InvalidArgumentException('Debés seleccionar un chofer y un peón.');
        }

        if ($idChofer === $idPeon) {
            throw new InvalidArgumentException('El chofer y el peón no pueden ser el mismo usuario.');
        }

        if ($this->modelo->usuarioEstaEnCuadrilla((int) $idChofer)) {
            throw new InvalidArgumentException('El chofer seleccionado ya pertenece a otra cuadrilla.');
        }

        if ($this->modelo->usuarioEstaEnCuadrilla((int) $idPeon)) {
            throw new InvalidArgumentException('El peón seleccionado ya pertenece a otra cuadrilla.');
        }

        return $this->modelo->crearCuadrilla((int) $idChofer, (int) $idPeon);
    }

    public function eliminarCuadrilla(int $id): bool
    {
        return $this->modelo->eliminarCuadrilla($id);
    }

    // =========================================================
    // ASIGNACIONES
    // =========================================================

    public function listarAsignaciones(): array
    {
        return $this->modelo->listarAsignaciones();
    }

    public function asignarCuadrilla(array $datos): array
    {
        $idCuadrilla = filter_var($datos['idCuadrilla'] ?? null, FILTER_VALIDATE_INT);
        $idVehi = filter_var($datos['idVehi'] ?? null, FILTER_VALIDATE_INT);
        $idRuta = filter_var($datos['idRuta'] ?? null, FILTER_VALIDATE_INT) ?: null;
        $fecha = trim((string) ($datos['fecha'] ?? '')) ?: date('Y-m-d');

        if (!$idCuadrilla || !$idVehi) {
            throw new InvalidArgumentException('Debés seleccionar una cuadrilla y un vehículo.');
        }

        return $this->modelo->asignarCuadrilla([
            'idCuadrilla' => (int) $idCuadrilla,
            'idVehi' => (int) $idVehi,
            'idRuta' => $idRuta,
            'fecha' => $fecha,
        ]);
    }

    // =========================================================
    // MI RUTA (ROL CUADRILLA)
    // =========================================================

    public function obtenerMiRuta(int $idUsuario): array
    {
        return $this->modelo->obtenerMiRuta($idUsuario);
    }

    // =========================================================
    // MAQUINARIA, CENTROS Y VERTEDEROS
    // =========================================================

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
        return $this->modelo->eliminarMaquinaria($id);
    }

    public function listarCentros(): array
    {
        return $this->modelo->listarCentros();
    }

    public function guardarCentro(array $datos, int $id = 0): array
    {
        $ubicacion = $this->texto($datos, 'ubiCentro', 150);
        $tipo = $this->opcion($datos, 'tipoCentro', self::TIPOS_CONTENEDOR);
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

    private function texto(
        array $datos,
        string $campo,
        int $maximo
    ): string {
        $valor = trim(
            (string) ($datos[$campo] ?? '')
        );

        if (
            $valor === '' ||
            strlen($valor) > $maximo
        ) {
            throw new InvalidArgumentException(
                "El campo {$campo} es obligatorio y admite hasta {$maximo} caracteres."
            );
        }

        return $valor;
    }

    private function numeroPositivo(
        array $datos,
        string $campo
    ): float {
        $valor = filter_var(
            $datos[$campo] ?? null,
            FILTER_VALIDATE_FLOAT
        );

        if (
            $valor === false ||
            $valor <= 0
        ) {
            throw new InvalidArgumentException(
                "El campo {$campo} debe ser mayor que cero."
            );
        }

        return (float) $valor;
    }

    private function opcion(
        array $datos,
        string $campo,
        array $opciones,
        bool $minusculas = true
    ): string {
        $valor = trim(
            (string) ($datos[$campo] ?? '')
        );

        if ($minusculas) {
            $valor = strtolower($valor);
        }

        if (
            !in_array(
                $valor,
                $opciones,
                true
            )
        ) {
            throw new InvalidArgumentException(
                "El valor de {$campo} no es válido."
            );
        }

        return $valor;
    }

    private function prioridad(
        string $tipo
    ): string {
        return match ($tipo) {
            'incendiado' => 'alta',
            'desbordado',
            'roto' => 'media',
            default => 'baja',
        };
    }
}
