<?php

declare(strict_types=1);

class TotpHelper
{
    private const BASE32_CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function generarSecret(int $longitud = 16): string
    {
        $secret = '';
        for ($i = 0; $i < $longitud; $i++) {
            $secret .= self::BASE32_CHARS[random_int(0, 31)];
        }
        return $secret;
    }

    public static function base32Encode(string $data): string
    {
        $buffer = 0;
        $bitsLeft = 0;
        $output = '';
        $len = strlen($data);
        for ($i = 0; $i < $len; $i++) {
            $buffer = ($buffer << 8) | ord($data[$i]);
            $bitsLeft += 8;
            while ($bitsLeft >= 5) {
                $bitsLeft -= 5;
                $output .= self::BASE32_CHARS[($buffer >> $bitsLeft) & 0x1F];
            }
        }
        if ($bitsLeft > 0) {
            $output .= self::BASE32_CHARS[($buffer << (5 - $bitsLeft)) & 0x1F];
        }
        return $output;
    }

    public static function base32Decode(string $b32): string
    {
        $b32 = strtoupper(trim($b32));
        $buffer = 0;
        $bitsLeft = 0;
        $output = '';
        $lut = array_flip(str_split(self::BASE32_CHARS));

        for ($i = 0; $i < strlen($b32); $i++) {
            $char = $b32[$i];
            if (!isset($lut[$char])) {
                continue;
            }
            $buffer = ($buffer << 5) | $lut[$char];
            $bitsLeft += 5;
            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $output .= chr(($buffer >> $bitsLeft) & 0xFF);
            }
        }
        return $output;
    }

    /**
     * Deriva un secreto TOTP único y constante para un usuario sin tocar la base de datos.
     */
    public static function obtenerSecretParaUsuario(string $email, int $idUsu = 0): string
    {
        $emailNormalizado = strtolower(trim($email));
        $semilla = hash_hmac('sha256', "sigeru:user:{$idUsu}:{$emailNormalizado}", 'SiGeRU_TOTP_KEY_2026', true);
        return substr(self::base32Encode(substr($semilla, 0, 10)), 0, 16);
    }

    public static function calcularCodigo(string $secret, ?int $tiempo = null): string
    {
        if ($tiempo === null) {
            $tiempo = (int) floor(time() / 30);
        }
        $secretBin = self::base32Decode($secret);
        $timeBin = pack('N*', 0) . pack('N*', $tiempo);
        $hash = hash_hmac('sha1', $timeBin, $secretBin, true);
        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
        $valor = (
            ((ord($hash[$offset]) & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8) |
            (ord($hash[$offset + 3]) & 0xFF)
        ) % 1000000;

        return str_pad((string) $valor, 6, '0', STR_PAD_LEFT);
    }

    public static function verificar(string $secret, string $codigoIngresado, int $discrepancia = 1): bool
    {
        $codigoIngresado = trim($codigoIngresado);
        if (strlen($codigoIngresado) !== 6 || !ctype_digit($codigoIngresado)) {
            return false;
        }
        $tiempoActual = (int) floor(time() / 30);
        for ($i = -$discrepancia; $i <= $discrepancia; $i++) {
            if (hash_equals(self::calcularCodigo($secret, $tiempoActual + $i), $codigoIngresado)) {
                return true;
            }
        }
        return false;
    }

    public static function urlOtpAuth(string $email, string $secret, string $emisor = 'SiGeRU'): string
    {
        return sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s&algorithm=SHA1&digits=6&period=30',
            rawurlencode($emisor),
            rawurlencode($email),
            $secret,
            rawurlencode($emisor)
        );
    }
}
