<?php

declare(strict_types=1);

namespace Nucleo\Http\Middleware;

use Nucleo\BaseDatos\Conexion;
use PDO;
use RuntimeException;

class ResolucionTenantPublicoMiddleware
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Conexion::obtenerInstancia();
    }

    /**
     * Resuelve de forma estricta y fail-closed la organización desde la petición pública.
     * Rechaza cualquier dominio o slug no autorizado con 404/403.
     *
     * @return array{organizacion_id: int, codigo: string, nombre: string}
     */
    public function resolver(array $servidor = [], array $peticion = []): array
    {
        $servidor = !empty($servidor) ? $servidor : $_SERVER;
        $host = strtolower(trim((string) ($servidor['HTTP_HOST'] ?? '')));

        // Eliminar puerto si viene en el host (ej. localhost:8080)
        $hostSinPuerto = explode(':', $host)[0];

        // 1. Identificación por FQDN o dominio personalizado registrado en organizaciones.sitio_web
        if ($hostSinPuerto !== '' && $hostSinPuerto !== 'localhost' && $hostSinPuerto !== '127.0.0.1') {
            $stmtHost = $this->pdo->prepare("
                SELECT `id`, `codigo`, `nombre_comercial`, `estado`
                FROM `organizaciones`
                WHERE (`sitio_web` = :host OR `sitio_web` = :url_http OR `sitio_web` = :url_https)
                LIMIT 1
            ");
            $stmtHost->execute([
                'host'      => $hostSinPuerto,
                'url_http'  => "http://{$hostSinPuerto}",
                'url_https' => "https://{$hostSinPuerto}",
            ]);
            $orgHost = $stmtHost->fetch(PDO::FETCH_ASSOC);

            if ($orgHost) {
                if ($orgHost['estado'] !== 'ACTIVO') {
                    throw new RuntimeException("La organización solicitada se encuentra suspendida o inactiva.");
                }
                return [
                    'organizacion_id' => (int) $orgHost['id'],
                    'codigo'          => (string) $orgHost['codigo'],
                    'nombre'          => (string) $orgHost['nombre_comercial'],
                ];
            }

            // Si se envió un host específico no-localhost y no existe en la base de datos, rechazar fail-closed
            throw new RuntimeException("El dominio '{$hostSinPuerto}' no está registrado para ninguna organización activa (404 Not Found).");
        }

        // 2. Identificación por Slug Público explícito EXCLUSIVAMENTE desde la ruta URL autorizada
        // Queda categóricamente prohibido confiar en encabezados cliente como X-Tenant-Slug para evitar spoofing o cruce no autorizado.
        $slug = $peticion['slug_organizacion']
            ?? $peticion['org_slug']
            ?? null;

        if ($slug !== null && is_string($slug) && trim($slug) !== '') {
            $slugLimpio = trim($slug);
            $stmtSlug = $this->pdo->prepare("
                SELECT `id`, `codigo`, `nombre_comercial`, `estado`
                FROM `organizaciones`
                WHERE `codigo` = :codigo
                LIMIT 1
            ");
            $stmtSlug->execute(['codigo' => $slugLimpio]);
            $orgSlug = $stmtSlug->fetch(PDO::FETCH_ASSOC);

            if ($orgSlug) {
                if ($orgSlug['estado'] !== 'ACTIVO') {
                    throw new RuntimeException("La organización solicitada se encuentra suspendida o inactiva.");
                }
                return [
                    'organizacion_id' => (int) $orgSlug['id'],
                    'codigo'          => (string) $orgSlug['codigo'],
                    'nombre'          => (string) $orgSlug['nombre_comercial'],
                ];
            }

            // Si se envió un slug explícito en la ruta y no existe o no está activo, rechazar fail-closed
            throw new RuntimeException("No se encontró ninguna organización activa con el identificador '{$slugLimpio}' (404 Not Found).");
        }

        // 3. Fallback controlado para desarrollo local / pruebas automatizadas
        if (
            $hostSinPuerto === 'localhost' ||
            $hostSinPuerto === '127.0.0.1' ||
            ((string) entorno('APP_ENV') === 'testing' && empty($servidor['HTTP_HOST']))
        ) {
            $stmtDef = $this->pdo->query("SELECT `id`, `codigo`, `nombre_comercial` FROM `organizaciones` WHERE `estado` = 'ACTIVO' ORDER BY `id` ASC LIMIT 1");
            $orgDef = $stmtDef->fetch(PDO::FETCH_ASSOC);
            if ($orgDef) {
                return [
                    'organizacion_id' => (int) $orgDef['id'],
                    'codigo'          => (string) $orgDef['codigo'],
                    'nombre'          => (string) $orgDef['nombre_comercial'],
                ];
            }
        }

        // 4. Fail-closed: si ningún mecanismo válido resolvió el tenant, rechazar
        throw new RuntimeException("No se pudo resolver la organización pública para el dominio o identificador proporcionado (404 Not Found).");
    }
}
