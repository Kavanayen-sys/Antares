<?php

declare(strict_types=1);

require_once __DIR__ . '/UsuarioModel.php';

class UsuarioController
{
    private UsuarioModel $modelo;
    private const ROLES = ['administrador', 'municipal', 'cuadrilla', 'operario', 'vecino'];
    private const ESTADOS = ['activo', 'inactivo'];

    public function __construct()
    {
        $this->modelo = new UsuarioModel();
    }

    public function listar(): array
    {
        return $this->modelo->obtenerTodos();
    }

    public function registrar(array $datos, bool $desdePanel = false): array
    {
        $nombre = $this->texto($datos, 'nombre', 50);
        $telefono = $this->texto($datos, 'telefono', 20);
        $email = $this->email($datos);
        $password = $this->password($datos);
        $rol = $desdePanel ? $this->opcion($datos, 'rol', self::ROLES) : 'vecino';

        if ($this->modelo->emailExiste($email)) {
            throw new InvalidArgumentException('El correo ya está registrado.');
        }

        return $this->modelo->crear($nombre, $telefono, $email, $password, $rol, 'activo');
    }

    public function ingresar(array $datos): array
    {
        $email = $this->email($datos);
        $password = (string) ($datos['password'] ?? '');
        $usuario = $this->modelo->obtenerPorEmail($email);

        if (!$usuario || $usuario['estUsu'] !== 'activo' || !password_verify($password, $usuario['passwordHash'])) {
            throw new RuntimeException('Correo o contraseña incorrectos.');
        }

        unset($usuario['hash']);
        return $usuario;
    }

    public function actualizar(int $id, array $datos): array
    {
        if (!$this->modelo->obtenerPorId($id)) {
            throw new OutOfBoundsException('El usuario no existe.');
        }

        $nombre = $this->texto($datos, 'nombre', 50);
        $telefono = $this->texto($datos, 'telefono', 20);
        $email = $this->email($datos);
        $rol = $this->opcion($datos, 'rol', self::ROLES);
        $estado = $this->opcion($datos, 'estado', self::ESTADOS);
        $password = trim((string) ($datos['password'] ?? ''));

        if ($password !== '' && strlen($password) < 6) {
            throw new InvalidArgumentException('La contraseña debe tener al menos 6 caracteres.');
        }
        if ($this->modelo->emailExiste($email, $id)) {
            throw new InvalidArgumentException('El correo ya está registrado.');
        }

        return $this->modelo->actualizar(
            $id,
            $nombre,
            $telefono,
            $email,
            $rol,
            $estado,
            $password === '' ? null : $password
        );
    }

    public function eliminar(int $id): bool
    {
        return $this->modelo->eliminar($id);
    }

    private function texto(array $datos, string $campo, int $maximo): string
    {
        $valor = trim((string) ($datos[$campo] ?? ''));
        if ($valor === '' || strlen($valor) > $maximo) {
            throw new InvalidArgumentException("El campo {$campo} es obligatorio y admite hasta {$maximo} caracteres.");
        }
        return $valor;
    }

    private function email(array $datos): string
    {
        $email = strtolower(trim((string) ($datos['email'] ?? '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
            throw new InvalidArgumentException('El correo no es válido.');
        }
        return $email;
    }

    private function password(array $datos): string
    {
        $password = (string) ($datos['password'] ?? '');
        if (strlen($password) < 6) {
            throw new InvalidArgumentException('La contraseña debe tener al menos 6 caracteres.');
        }
        return $password;
    }

    private function opcion(array $datos, string $campo, array $opciones): string
    {
        $valor = strtolower(trim((string) ($datos[$campo] ?? '')));
        if (!in_array($valor, $opciones, true)) {
            throw new InvalidArgumentException("El valor de {$campo} no es válido.");
        }
        return $valor;
    }
}
