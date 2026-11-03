<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../apis/api-usuarios/UsuarioController.php';

class MockUsuarioModel extends UsuarioModel
{
    public array $usuarios = [];

    public function __construct()
    {
        $this->usuarios = [
            1 => [
                'idUsu' => 1,
                'priNom' => 'Administrador',
                'telUsu' => '099123456',
                'email' => 'admin@sigeru.local',
                'passwordHash' => password_hash('admin123', PASSWORD_DEFAULT),
                'rol' => 'administrador',
                'estUsu' => 'activo',
                'sesionActiva' => false
            ],
            2 => [
                'idUsu' => 2,
                'priNom' => 'Chofer Juan',
                'telUsu' => '099654321',
                'email' => 'chofer@sigeru.local',
                'passwordHash' => password_hash('chofer123', PASSWORD_DEFAULT),
                'rol' => 'cuadrilla',
                'estUsu' => 'activo',
                'sesionActiva' => true
            ]
        ];
    }

    public function obtenerPorEmail(string $email): ?array
    {
        foreach ($this->usuarios as $u) {
            if ($u['email'] === $email) return $u;
        }
        return null;
    }

    public function obtenerPorId(int $id): ?array
    {
        return $this->usuarios[$id] ?? null;
    }

    public function emailExiste(string $email, int $ignorarId = 0): bool
    {
        foreach ($this->usuarios as $u) {
            if ($u['email'] === $email && $u['idUsu'] !== $ignorarId) return true;
        }
        return false;
    }

    public function crear(string $nombre, string $telefono, string $email, string $password, string $rol, string $estado): array
    {
        $id = count($this->usuarios) + 1;
        $nuevo = [
            'idUsu' => $id,
            'priNom' => $nombre,
            'telUsu' => $telefono,
            'email' => $email,
            'passwordHash' => password_hash($password, PASSWORD_DEFAULT),
            'rol' => $rol,
            'estUsu' => $estado,
            'sesionActiva' => false
        ];
        $this->usuarios[$id] = $nuevo;
        return $nuevo;
    }

    public function usuarioTieneSesionActiva(int $idUsu): bool
    {
        return !empty($this->usuarios[$idUsu]['sesionActiva']);
    }

    public function eliminar(int $id): bool
    {
        if (isset($this->usuarios[$id])) {
            unset($this->usuarios[$id]);
            return true;
        }
        return false;
    }
}

class UsuarioTest extends TestCase
{
    private UsuarioController $controller;
    private MockUsuarioModel $mockModel;

    public function __construct()
    {
        parent::__construct('Gestión de Usuarios y Autenticación');
        $this->mockModel = new MockUsuarioModel();
        $this->controller = new UsuarioController($this->mockModel);
    }

    public function runAll(): void
    {
        $this->testLoginExitosoConCredencialesValidas();
        $this->testLoginFallaConPasswordIncorrecta();
        $this->testLoginFallaConEmailInexistente();
        $this->testLoginFallaConFormatoEmailInvalido();
        $this->testRegistroExitosoDeNuevoUsuario();
        $this->testRegistroFallaConEmailDuplicado();
        $this->testRegistroFallaConPasswordCorta();
        $this->testRegistroFallaConNombreVacio();
        $this->testRegistroFallaConTelefonoVacio();
        $this->testEliminarUsuarioFallaSiTieneSesionActiva();
    }

    public function testLoginExitosoConCredencialesValidas(): void
    {
        $resultado = $this->controller->ingresar([
            'email' => 'admin@sigeru.local',
            'password' => 'admin123'
        ]);

        $this->assertEquals('admin@sigeru.local', $resultado['email'], 'Login exitoso: devuelve email correcto');
        $this->assertEquals('administrador', $resultado['rol'], 'Login exitoso: rol reconocido correctamente');
        $this->assertFalse(isset($resultado['passwordHash']), 'Login seguro: no expone el hash de la contraseña');
    }

    public function testLoginFallaConPasswordIncorrecta(): void
    {
        $this->assertThrows(RuntimeException::class, function() {
            $this->controller->ingresar([
                'email' => 'admin@sigeru.local',
                'password' => 'claveInvalida999'
            ]);
        }, 'Login rechazado: contraseña errónea lanza RuntimeException');
    }

    public function testLoginFallaConEmailInexistente(): void
    {
        $this->assertThrows(RuntimeException::class, function() {
            $this->controller->ingresar([
                'email' => 'no_existe@sigeru.local',
                'password' => 'admin123'
            ]);
        }, 'Login rechazado: correo inexistente en el sistema');
    }

    public function testLoginFallaConFormatoEmailInvalido(): void
    {
        $this->assertThrows(InvalidArgumentException::class, function() {
            $this->controller->ingresar([
                'email' => 'correo_sin_arroba.com',
                'password' => 'admin123'
            ]);
        }, 'Validación email: formato de correo sintácticamente inválido');
    }

    public function testRegistroExitosoDeNuevoUsuario(): void
    {
        $nuevo = $this->controller->registrar([
            'nombre' => 'Carlos Municipal',
            'telefono' => '098112233',
            'email' => 'carlos@montevideo.gub.uy',
            'password' => 'segura1234',
            'rol' => 'municipal'
        ], true);

        $this->assertEquals('Carlos Municipal', $nuevo['priNom'], 'Registro exitoso: persiste nombre');
        $this->assertEquals('municipal', $nuevo['rol'], 'Registro exitoso: asigna rol correcto');
    }

    public function testRegistroFallaConEmailDuplicado(): void
    {
        $this->assertThrows(InvalidArgumentException::class, function() {
            $this->controller->registrar([
                'nombre' => 'Intento Duplicado',
                'telefono' => '099000000',
                'email' => 'admin@sigeru.local', // Ya existe
                'password' => 'clave123',
                'rol' => 'operario'
            ], true);
        }, 'Registro rechazado: email duplicado ya existente');
    }

    public function testRegistroFallaConPasswordCorta(): void
    {
        $this->assertThrows(InvalidArgumentException::class, function() {
            $this->controller->registrar([
                'nombre' => 'Usuario Test',
                'telefono' => '099111222',
                'email' => 'test@sigeru.local',
                'password' => '12345', // Menos de 6 caracteres
                'rol' => 'vecino'
            ], true);
        }, 'Seguridad password: exige longitud mínima de 6 caracteres');
    }

    public function testRegistroFallaConNombreVacio(): void
    {
        $this->assertThrows(InvalidArgumentException::class, function() {
            $this->controller->registrar([
                'nombre' => '',
                'telefono' => '099111222',
                'email' => 'valido@sigeru.local',
                'password' => 'password123',
                'rol' => 'vecino'
            ], true);
        }, 'Campo obligatorio: nombre no puede estar vacío');
    }

    public function testRegistroFallaConTelefonoVacio(): void
    {
        $this->assertThrows(InvalidArgumentException::class, function() {
            $this->controller->registrar([
                'nombre' => 'Pedro',
                'telefono' => '',
                'email' => 'pedro@sigeru.local',
                'password' => 'password123',
                'rol' => 'vecino'
            ], true);
        }, 'Campo obligatorio: teléfono no puede estar vacío');
    }

    public function testEliminarUsuarioFallaSiTieneSesionActiva(): void
    {
        // El usuario 2 tiene sesión activa (chofer/peon en recorrido)
        $this->assertThrows(InvalidArgumentException::class, function() {
            $this->controller->eliminar(2);
        }, 'Seguridad operativa: usuario con sesión activa no puede ser eliminado');
    }
}
