<?php

declare(strict_types=1);

namespace Aplicacion\Finanzas;

use RuntimeException;

/**
 * Servicio de cifrado autenticado de grado bancario (AES-256-GCM).
 * Protege credenciales de pasarelas, tokens privados y secretos de webhook en reposo.
 */
class CifradorFinanciero
{
    private const CIPHER = 'aes-256-gcm';
    private const IV_LEN = 12; // 96 bits recomendado para GCM
    private const TAG_LEN = 16; // 128 bits de tag de autenticación

    private string $clave;

    public function __construct(?string $claveSecreta = null)
    {
        $claveBase = $claveSecreta ?? ($_ENV['APP_KEY'] ?? 'candelaria_app_financial_secret_key_2027');
        $this->clave = hash('sha256', $claveBase, true);
    }

    /**
     * Cifra un texto plano devolviendo un payload empaquetado en base64 (IV + TAG + CIPHERTEXT).
     */
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
            throw new RuntimeException('Error durante el cifrado financiero GCM.');
        }

        return base64_encode($iv . $tag . $cifrado);
    }

    /**
     * Descifra un payload autenticado. Si el ciphertext o tag han sido alterados, arroja excepción.
     */
    public function descifrar(string $payloadBase64): string
    {
        if ($payloadBase64 === '') {
            return '';
        }

        $bin = base64_decode($payloadBase64, true);
        if ($bin === false || strlen($bin) < (self::IV_LEN + self::TAG_LEN)) {
            throw new RuntimeException('Payload cifrado malformado o longitud inválida.');
        }

        $iv = substr($bin, 0, self::IV_LEN);
        $tag = substr($bin, self::IV_LEN, self::TAG_LEN);
        $cifrado = substr($bin, self::IV_LEN + self::TAG_LEN);

        $plano = openssl_decrypt(
            $cifrado,
            self::CIPHER,
            $this->clave,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($plano === false) {
            throw new RuntimeException('Fallo de autenticación criptográfica: el secreto financiero ha sido alterado o la clave es incorrecta.');
        }

        return $plano;
    }
}
