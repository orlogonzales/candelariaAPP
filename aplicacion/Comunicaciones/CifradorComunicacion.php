<?php

declare(strict_types=1);

namespace Aplicacion\Comunicaciones;

use RuntimeException;

/**
 * Cifrador criptográfico AES-256-GCM para secretos de Meta Cloud API y webhooks.
 */
class CifradorComunicacion
{
    private const CIPHER = 'aes-256-gcm';
    private const IV_LEN = 12;
    private const TAG_LEN = 16;

    private string $clave;

    public function __construct(?string $claveSecreta = null)
    {
        $claveBase = $claveSecreta ?? ($_ENV['APP_KEY'] ?? 'candelaria_app_communications_secret_key_2027');
        $this->clave = hash('sha256', $claveBase, true);
    }

    public function cifrar(string $textoPlano): string
    {
        if ($textoPlano === '') {
            return '';
        }

        $iv = random_bytes(self::IV_LEN);
        $tag = '';
        $cifrado = openssl_encrypt(
            $textoPlano,
            self::CIPHER,
            $this->clave,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::TAG_LEN
        );

        if ($cifrado === false) {
            throw new RuntimeException('Error durante el cifrado AES-256-GCM de comunicaciones.');
        }

        return base64_encode($iv . $tag . $cifrado);
    }

    public function descifrar(string $payloadBase64): string
    {
        if ($payloadBase64 === '') {
            return '';
        }

        $bin = base64_decode($payloadBase64, true);
        if ($bin === false || strlen($bin) < (self::IV_LEN + self::TAG_LEN)) {
            throw new RuntimeException('Payload cifrado inválido o corrupto.');
        }

        $iv = substr($bin, 0, self::IV_LEN);
        $tag = substr($bin, self::IV_LEN, self::TAG_LEN);
        $ciphertext = substr($bin, self::IV_LEN + self::TAG_LEN);

        $plano = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $this->clave,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($plano === false) {
            throw new RuntimeException('Fallo de autenticación o integridad en el descifrado GCM.');
        }

        return $plano;
    }
}
