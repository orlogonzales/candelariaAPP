<?php

declare(strict_types=1);

namespace Aplicacion\Clientes;

use Aplicacion\Autorizacion\AutorizacionServicio;
use Aplicacion\Excepciones\AccesoDenegadoExcepcion;
use Aplicacion\Repositorios\AuditoriaRepositorio;
use Aplicacion\Repositorios\ClienteRepositorio;
use InvalidArgumentException;
use Nucleo\BaseDatos\Conexion;
use Nucleo\Http\ContextoOperacion;
use PDO;

/**
 * Servicio de dominio oficial para gestión de consentimientos de clientes.
 * Garantiza:
 * 1. Separación estricta entre consentimiento OPERATIVO y PROMOCIONAL.
 * 2. Trazabilidad append-only inmutable en tabla consentimientos_cliente.
 * 3. Actualización atómica del estado materializado en la tabla clientes.
 * 4. Control de acceso RBAC ('clientes.editar' / 'clientes.ver') y Anti-IDOR por tenant.
 */
class ConsentimientoServicio
{
    private PDO $pdo;

    public function __construct(
        private ClienteRepositorio $clienteRepo,
        private AutorizacionServicio $authzServicio,
        private AuditoriaRepositorio $auditoriaRepo,
        ?PDO $pdo = null
    ) {
        $this->pdo = $pdo ?? Conexion::obtenerInstancia();
    }

    /**
     * Otorga un consentimiento (OPERATIVO o PROMOCIONAL).
     */
    public function otorgar(
        int $organizacionId,
        int $clienteId,
        string $tipo,
        string $canal,
        ?string $motivo,
        ContextoOperacion $contexto
    ): bool {
        return $this->registrarAccion($organizacionId, $clienteId, $tipo, 'OTORGAR', $canal, $motivo, $contexto);
    }

    /**
     * Revoca un consentimiento (OPERATIVO o PROMOCIONAL).
     */
    public function revocar(
        int $organizacionId,
        int $clienteId,
        string $tipo,
        string $canal,
        ?string $motivo,
        ContextoOperacion $contexto
    ): bool {
        return $this->registrarAccion($organizacionId, $clienteId, $tipo, 'REVOCAR', $canal, $motivo, $contexto);
    }

    /**
     * Registra transaccionalmente la acción de consentimiento (append-only y materializado).
     */
    private function registrarAccion(
        int $organizacionId,
        int $clienteId,
        string $tipo,
        string $accion,
        string $canal,
        ?string $motivo,
        ContextoOperacion $contexto
    ): bool {
        $this->validarAlcanceTenant($organizacionId, $contexto);
        $this->validarPermiso('clientes.editar', $contexto);

        $tipoNormalizado = strtoupper(trim($tipo));
        if (!in_array($tipoNormalizado, ['OPERATIVO', 'PROMOCIONAL'], true)) {
            throw new InvalidArgumentException("Tipo de consentimiento no válido: '{$tipo}'. Debe ser OPERATIVO o PROMOCIONAL.");
        }

        $accionNormalizada = strtoupper(trim($accion));
        if (!in_array($accionNormalizada, ['OTORGAR', 'REVOCAR'], true)) {
            throw new InvalidArgumentException("Acción de consentimiento no válida: '{$accion}'. Debe ser OTORGAR o REVOCAR.");
        }

        $canalNormalizado = strtoupper(trim($canal));
        if ($canalNormalizado === '') {
            throw new InvalidArgumentException("El canal de consentimiento no puede estar vacío.");
        }

        $transaccionIniciada = false;
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
            $transaccionIniciada = true;
        }

        try {
            // 1. Bloqueo pesimista del cliente
            $cliente = $this->clienteRepo->buscarPorIdBloqueante($clienteId);
            if ($cliente === null || $cliente->organizacionId !== $organizacionId) {
                throw new AccesoDenegadoExcepcion("Cliente no encontrado en la organización especificada.");
            }

            $datosPrevios = $cliente->aArreglo();
            $ahora = date('Y-m-d H:i:s');
            $esOtorgar = ($accionNormalizada === 'OTORGAR');

            // 2. Insertar en bitácora append-only
            $sqlBitacora = "INSERT INTO `consentimientos_cliente` (
                                `organizacion_id`, `cliente_id`, `tipo`, `accion`,
                                `canal`, `motivo`, `actor_tipo`, `usuario_id`,
                                `actor_sistema_id`, `correlacion_id`, `creado_en`
                            ) VALUES (
                                :organizacion_id, :cliente_id, :tipo, :accion,
                                :canal, :motivo, :actor_tipo, :usuario_id,
                                :actor_sistema_id, :correlacion_id, :creado_en
                            )";

            $stmtBitacora = $this->pdo->prepare($sqlBitacora);
            $stmtBitacora->execute([
                ':organizacion_id'   => $organizacionId,
                ':cliente_id'        => $clienteId,
                ':tipo'              => $tipoNormalizado,
                ':accion'            => $accionNormalizada,
                ':canal'             => $canalNormalizado,
                ':motivo'            => $motivo !== null ? trim($motivo) : null,
                ':actor_tipo'        => $contexto->actorTipo,
                ':usuario_id'        => $contexto->usuarioId,
                ':actor_sistema_id'  => $contexto->actorSistemaId,
                ':correlacion_id'    => $contexto->correlacionId,
                ':creado_en'         => $ahora,
            ]);

            // 3. Actualizar estado materializado en clientes
            $operativo = $cliente->consentimientoOperativo;
            $operativoEn = $cliente->consentimientoOperativoEn;
            $promocional = $cliente->consentimientoPromocional;
            $promocionalEn = $cliente->consentimientoPromocionalEn;

            if ($tipoNormalizado === 'OPERATIVO') {
                $operativo = $esOtorgar;
                $operativoEn = $ahora;
            } else {
                $promocional = $esOtorgar;
                $promocionalEn = $ahora;
            }

            $this->clienteRepo->actualizarConsentimientos(
                $clienteId,
                $operativo,
                $operativoEn,
                $promocional,
                $promocionalEn
            );

            // 4. Registro en auditoría transversal
            $datosNuevos = [
                'tipo'                       => $tipoNormalizado,
                'accion'                     => $accionNormalizada,
                'canal'                      => $canalNormalizado,
                'motivo'                     => $motivo,
                'consentimiento_operativo'    => $operativo,
                'consentimiento_promocional'  => $promocional,
            ];

            $this->auditoriaRepo->registrar(
                contexto: $contexto,
                modulo: 'clientes',
                accion: "GESTIONAR_CONSENTIMIENTO_{$tipoNormalizado}_{$accionNormalizada}",
                entidadTipo: 'Cliente',
                entidadId: (string) $clienteId,
                datosPrevios: $datosPrevios,
                datosNuevos: $datosNuevos
            );

            if ($transaccionIniciada) {
                $this->pdo->commit();
            }

            return true;
        } catch (\Throwable $e) {
            if ($transaccionIniciada && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Obtiene el historial completo de consentimientos de un cliente (append-only).
     */
    public function obtenerHistorial(int $organizacionId, int $clienteId, ContextoOperacion $contexto): array
    {
        $this->validarAlcanceTenant($organizacionId, $contexto);
        $this->validarPermiso('clientes.ver', $contexto);

        $cliente = $this->clienteRepo->buscarPorId($clienteId);
        if ($cliente === null || $cliente->organizacionId !== $organizacionId) {
            throw new AccesoDenegadoExcepcion("Cliente no encontrado en la organización especificada.");
        }

        $sql = "SELECT cc.*, u.nombre_completo AS usuario_nombre
                FROM `consentimientos_cliente` cc
                LEFT JOIN `usuarios` u ON u.id = cc.usuario_id
                WHERE cc.organizacion_id = :organizacion_id
                  AND cc.cliente_id = :cliente_id
                ORDER BY cc.id DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':organizacion_id' => $organizacionId,
            ':cliente_id'      => $clienteId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Valida el alcance del operador sobre la organización (Anti-IDOR).
     */
    private function validarAlcanceTenant(int $organizacionId, ContextoOperacion $contexto): void
    {
        if ($contexto->organizacionId === null || $contexto->organizacionId <= 0) {
            throw new AccesoDenegadoExcepcion('Contexto organizacional ausente o inválido para la sesión activa.');
        }

        if ($contexto->usuarioId !== null) {
            if (!$this->authzServicio->verificarAlcanceOrganizacion($contexto->usuarioId, $organizacionId)) {
                throw new AccesoDenegadoExcepcion('Acceso denegado: el operador no cuenta con alcance sobre la organización especificada.');
            }
        }
    }

    /**
     * Valida que el operador cuente con el permiso RBAC requerido.
     */
    private function validarPermiso(string $permiso, ContextoOperacion $contexto): void
    {
        if ($contexto->usuarioId !== null) {
            if (!$this->authzServicio->tienePermiso($contexto->usuarioId, $permiso)) {
                throw new AccesoDenegadoExcepcion("Acceso denegado: se requiere el permiso '{$permiso}'.");
            }
        }
    }
}
