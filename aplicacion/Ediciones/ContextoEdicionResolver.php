<?php

declare(strict_types=1);

namespace Aplicacion\Ediciones;

use Aplicacion\Excepciones\AccesoDenegadoExcepcion;
use Aplicacion\Repositorios\EdicionRepositorio;
use InvalidArgumentException;
use Nucleo\Http\ContextoOperacion;

/**
 * Resolver oficial y centralizado para la determinación del Contexto de Edición de Trabajo.
 *
 * Arquitectura Oficial (F2.1B):
 * 1. Contexto explícito por pestaña/request (Header X-Edicion-Id o query/ruta equivalente).
 *    - Si se especifica un ID inválido, inexistente o perteneciente a otro tenant:
 *      RECHAZO INMEDIATO (Fail-Closed). Prohibido fallback silencioso a es_actual.
 * 2. Si el encabezado está AUSENTE:
 *    - Fallback institucional a la edición es_actual = 1 de la organización (origen: INSTITUCIONAL).
 * 3. Si tampoco existe es_actual:
 *    - Contexto de edición ausente (edicionTrabajoId = null, origen: AUSENTE).
 */
class ContextoEdicionResolver
{
    public function __construct(
        private EdicionRepositorio $edicionRepo
    ) {}

    /**
     * Resuelve el contexto de edición a partir de un encabezado o valor explícito.
     *
     * @throws InvalidArgumentException Si el encabezado es inválido o la edición no existe
     * @throws AccesoDenegadoExcepcion Si la edición pertenece a otro tenant
     */
    public function resolver(ContextoOperacion $contexto, ?string $edicionIdCandidato = null): ContextoOperacion
    {
        // 1. Contexto Explícito presente
        if ($edicionIdCandidato !== null && trim($edicionIdCandidato) !== '') {
            $candidatoLimpio = trim($edicionIdCandidato);

            // Validar formato numérico entero positivo estricto
            if (!ctype_digit($candidatoLimpio) || (int) $candidatoLimpio <= 0) {
                throw new InvalidArgumentException(
                    "El identificador de edición contextual '{$edicionIdCandidato}' debe ser un número entero mayor a 0."
                );
            }

            $edicionId = (int) $candidatoLimpio;
            $edicion = $this->edicionRepo->buscarPorId($edicionId);

            if ($edicion === null) {
                throw new InvalidArgumentException(
                    "La edición de trabajo solicitada (ID: {$edicionId}) no existe en el catálogo del sistema."
                );
            }

            // Validación estricta Anti-IDOR contra la organización del contexto
            if ($contexto->organizacionId === null || $edicion->organizacionId !== $contexto->organizacionId) {
                throw new AccesoDenegadoExcepcion(
                    "Acceso denegado: la edición solicitada (ID: {$edicionId}) no pertenece a la organización autorizada."
                );
            }

            return $contexto->conEdicionTrabajo($edicion->id, 'EXPLICITA');
        }

        // 2. Encabezado AUSENTE: Fallback institucional a la edición predeterminada de la organización
        if ($contexto->organizacionId !== null && $contexto->organizacionId > 0) {
            $edicionActual = $this->edicionRepo->obtenerActual($contexto->organizacionId);
            if ($edicionActual !== null) {
                return $contexto->conEdicionTrabajo($edicionActual->id, 'INSTITUCIONAL');
            }
        }

        // 3. Sin edición actual configurada ni contexto explícito
        return $contexto->conEdicionTrabajo(null, 'AUSENTE');
    }

    /**
     * Resuelve el contexto extrayendo el encabezado HTTP X-Edicion-Id desde el array del servidor.
     */
    public function resolverDesdeServidor(ContextoOperacion $contexto, array $servidor = []): ContextoOperacion
    {
        if (empty($servidor)) {
            $servidor = $_SERVER;
        }

        $candidato = $servidor['HTTP_X_EDICION_ID'] ?? $servidor['HTTP_X_EDICION'] ?? null;
        $candidatoStr = is_string($candidato) ? $candidato : null;

        return $this->resolver($contexto, $candidatoStr);
    }
}
