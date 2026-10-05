<?php

declare(strict_types=1);

namespace Aplicacion\Clientes;

use Aplicacion\Autorizacion\AutorizacionServicio;
use Aplicacion\Entidades\Cliente;
use Aplicacion\Entidades\Persona;
use Aplicacion\Excepciones\AccesoDenegadoExcepcion;
use Aplicacion\Repositorios\AuditoriaRepositorio;
use Aplicacion\Repositorios\ClienteRepositorio;
use Aplicacion\Repositorios\PersonaRepositorio;
use Aplicacion\Soporte\NormalizadorTelefono;
use InvalidArgumentException;
use Nucleo\BaseDatos\Conexion;
use Nucleo\Http\ContextoOperacion;
use PDO;

/**
 * Servicio de dominio oficial para la gestión comercial de Clientes y CRM.
 * 
 * Reglas de negocio:
 * 1. Persona sigue perteneciendo a la organización (única por tenant y transversal a ediciones).
 * 2. Perfil comercial único: una persona tiene como máximo un perfil en tabla clientes por tenant.
 * 3. WhatsApp soberano en personas.telefono_whatsapp, obligatorio para perfiles de cliente y normalizado a E.164.
 * 4. Unicidad comercial de WhatsApp: 1 WhatsApp normalizado = máximo 1 perfil comercial activo por organización.
 * 5. Ciclo comercial gobernado: transiciones controladas por EstadoCliente, prohibiendo CLIENTE_RECURRENTE almacenado.
 * 6. Consentimientos: nacen en false, gestionados con trazabilidad append-only.
 * 7. Prohibición categórica de borrado físico.
 */
class ClienteServicio
{
    private PDO $pdo;

    public function __construct(
        private ClienteRepositorio $clienteRepo,
        private PersonaRepositorio $personaRepo,
        private AutorizacionServicio $authzServicio,
        private AuditoriaRepositorio $auditoriaRepo,
        ?PDO $pdo = null
    ) {
        $this->pdo = $pdo ?? Conexion::obtenerInstancia();
    }

    /**
     * Da de alta un perfil de cliente a partir de una persona existente.
     */
    public function crearDesdePersona(
        int $organizacionId,
        int $personaId,
        array $datosComerciales,
        ContextoOperacion $contexto
    ): Cliente {
        $this->validarAlcanceTenant($organizacionId, $contexto);
        $this->validarPermiso('clientes.crear', $contexto);

        $transaccionIniciada = false;
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
            $transaccionIniciada = true;
        }

        try {
            // 1. Bloqueo pesimista de la persona y validación de existencia
            $persona = $this->personaRepo->buscarPorIdBloqueante($personaId);
            if ($persona === null || $persona->organizacionId !== $organizacionId) {
                throw new InvalidArgumentException("La persona indicada (ID: {$personaId}) no existe en la organización.");
            }

            // 2. Verificar que no cuente ya con un perfil de cliente en este tenant
            $clienteExistente = $this->clienteRepo->buscarPorPersonaBloqueante($organizacionId, $personaId);
            if ($clienteExistente !== null) {
                throw new InvalidArgumentException("La persona ya cuenta con un perfil comercial en esta organización.");
            }

            // 3. WhatsApp soberano: obligatorio para cliente y normalizado a E.164
            $telefonoWhatsapp = $datosComerciales['telefono_whatsapp'] ?? $persona->telefonoWhatsapp;
            if (empty($telefonoWhatsapp)) {
                throw new InvalidArgumentException("El número de WhatsApp es obligatorio para incorporar una persona como cliente.");
            }

            $whatsappNormalizado = NormalizadorTelefono::normalizar((string) $telefonoWhatsapp, $persona->codigoPais);
            if ($whatsappNormalizado === null) {
                throw new InvalidArgumentException("El número de WhatsApp '{$telefonoWhatsapp}' no cumple con el formato canónico E.164.");
            }

            // 4. Invariante: 1 WhatsApp normalizado = máximo 1 perfil comercial activo por organización
            $clienteConWhatsapp = $this->clienteRepo->buscarPorWhatsappBloqueante($organizacionId, $whatsappNormalizado);
            if ($clienteConWhatsapp !== null) {
                throw new InvalidArgumentException("Ya existe un cliente comercial registrado con el número de WhatsApp '{$whatsappNormalizado}' en esta organización.");
            }

            // Si el teléfono de la persona varió o se normalizó, actualizar la persona
            if ($persona->telefonoWhatsapp !== $whatsappNormalizado) {
                $personaActualizada = new Persona(
                    id: $persona->id,
                    organizacionId: $persona->organizacionId,
                    tipoPersona: $persona->tipoPersona,
                    tipoDocumentoId: $persona->tipoDocumentoId,
                    numeroDocumento: $persona->numeroDocumento,
                    nombres: $persona->nombres,
                    apellidos: $persona->apellidos,
                    razonSocial: $persona->razonSocial,
                    nombreComercial: $persona->nombreComercial,
                    correoElectronico: $persona->correoElectronico,
                    telefonoMovil: $persona->telefonoMovil,
                    telefonoWhatsapp: $whatsappNormalizado,
                    direccion: $persona->direccion,
                    ciudad: $persona->ciudad,
                    codigoPais: $persona->codigoPais,
                    estado: $persona->estado
                );
                $this->personaRepo->actualizar($personaActualizada);
            }

            // 5. Estado comercial inicial
            $estado = EstadoCliente::CONTACTO;
            if (!empty($datosComerciales['estado_comercial'])) {
                $estado = EstadoCliente::desdeCadena((string) $datosComerciales['estado_comercial']);
            }

            // 6. Crear cliente (consentimientos siempre nacen en false por defecto)
            $nuevoCliente = new Cliente(
                id: null,
                organizacionId: $organizacionId,
                personaId: $personaId,
                estadoComercial: $estado,
                consentimientoOperativo: false,
                consentimientoOperativoEn: null,
                consentimientoPromocional: false,
                consentimientoPromocionalEn: null,
                origenCaptacion: !empty($datosComerciales['origen_captacion']) ? (string) $datosComerciales['origen_captacion'] : null,
                notasComerciales: !empty($datosComerciales['notas_comerciales']) ? (string) $datosComerciales['notas_comerciales'] : null
            );

            $clienteId = $this->clienteRepo->crear($nuevoCliente);
            $clienteCreado = $this->clienteRepo->buscarPorId($clienteId);

            // 7. Registro de auditoría
            $this->auditoriaRepo->registrar(
                contexto: $contexto,
                modulo: 'clientes',
                accion: 'CREAR_CLIENTE',
                entidadTipo: 'Cliente',
                entidadId: (string) $clienteId,
                datosPrevios: null,
                datosNuevos: $clienteCreado->aArreglo()
            );

            if ($transaccionIniciada) {
                $this->pdo->commit();
            }

            return $clienteCreado;
        } catch (\Throwable $e) {
            if ($transaccionIniciada && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Da de alta persona y cliente en una sola transacción atómica, reutilizando
     * la persona si ya existe por documento dentro del tenant.
     */
    public function crearPersonaYCliente(
        int $organizacionId,
        array $datosPersona,
        array $datosComerciales,
        ContextoOperacion $contexto
    ): Cliente {
        $this->validarAlcanceTenant($organizacionId, $contexto);
        $this->validarPermiso('personas.crear', $contexto);
        $this->validarPermiso('clientes.crear', $contexto);

        $transaccionIniciada = false;
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
            $transaccionIniciada = true;
        }

        try {
            // 1. WhatsApp obligatorio y normalizado a E.164
            $whatsappRaw = $datosPersona['telefono_whatsapp'] ?? ($datosComerciales['telefono_whatsapp'] ?? null);
            if (empty($whatsappRaw)) {
                throw new InvalidArgumentException("El número de WhatsApp es obligatorio para incorporar un cliente.");
            }

            $codigoPais = (string) ($datosPersona['codigo_pais'] ?? 'PE');
            $whatsappNormalizado = NormalizadorTelefono::normalizar((string) $whatsappRaw, $codigoPais);
            if ($whatsappNormalizado === null) {
                throw new InvalidArgumentException("El número de WhatsApp '{$whatsappRaw}' no cumple con el formato canónico E.164.");
            }

            // 2. Unicidad de WhatsApp comercial en la organización
            $clienteConWhatsapp = $this->clienteRepo->buscarPorWhatsappBloqueante($organizacionId, $whatsappNormalizado);
            if ($clienteConWhatsapp !== null) {
                throw new InvalidArgumentException("Ya existe un cliente comercial registrado con el número de WhatsApp '{$whatsappNormalizado}' en esta organización.");
            }

            // 3. Coherencia de documento de identidad
            $tipoDocId = isset($datosPersona['tipo_documento_id']) && $datosPersona['tipo_documento_id'] !== '' && $datosPersona['tipo_documento_id'] !== null
                ? (int) $datosPersona['tipo_documento_id']
                : null;
            $numDoc = isset($datosPersona['numero_documento']) && trim((string) $datosPersona['numero_documento']) !== ''
                ? trim((string) $datosPersona['numero_documento'])
                : null;

            if (($tipoDocId !== null && $numDoc === null) || ($tipoDocId === null && $numDoc !== null)) {
                throw new InvalidArgumentException("El tipo y número de documento deben proporcionarse ambos o ninguno.");
            }

            // 4. Identificar o crear Persona
            $personaId = null;
            if ($tipoDocId !== null && $numDoc !== null) {
                // Verificar si ya existe la persona por documento en este tenant
                $personaExistente = $this->personaRepo->buscarPorDocumento($organizacionId, $tipoDocId, $numDoc);
                if ($personaExistente !== null) {
                    $personaId = $personaExistente->id;
                    // Asegurar que tenga el WhatsApp actualizado
                    if ($personaExistente->telefonoWhatsapp !== $whatsappNormalizado) {
                        $personaActualizada = new Persona(
                            id: $personaExistente->id,
                            organizacionId: $personaExistente->organizacionId,
                            tipoPersona: $personaExistente->tipoPersona,
                            tipoDocumentoId: $personaExistente->tipoDocumentoId,
                            numeroDocumento: $personaExistente->numeroDocumento,
                            nombres: $personaExistente->nombres,
                            apellidos: $personaExistente->apellidos,
                            razonSocial: $personaExistente->razonSocial,
                            nombreComercial: $personaExistente->nombreComercial,
                            correoElectronico: $personaExistente->correoElectronico,
                            telefonoMovil: $personaExistente->telefonoMovil,
                            telefonoWhatsapp: $whatsappNormalizado,
                            direccion: $personaExistente->direccion,
                            ciudad: $personaExistente->ciudad,
                            codigoPais: $personaExistente->codigoPais,
                            estado: $personaExistente->estado
                        );
                        $this->personaRepo->actualizar($personaActualizada);
                    }
                }
            }

            if ($personaId === null) {
                // Crear nueva persona
                $correo = !empty($datosPersona['correo_electronico'])
                    ? normalizar_minusculas((string) $datosPersona['correo_electronico'])
                    : null;

                $nuevaPersona = new Persona(
                    id: null,
                    organizacionId: $organizacionId,
                    tipoPersona: (string) ($datosPersona['tipo_persona'] ?? 'NATURAL'),
                    tipoDocumentoId: $tipoDocId,
                    numeroDocumento: $numDoc,
                    nombres: $datosPersona['nombres'] ?? null,
                    apellidos: $datosPersona['apellidos'] ?? null,
                    razonSocial: $datosPersona['razon_social'] ?? null,
                    nombreComercial: $datosPersona['nombre_comercial'] ?? null,
                    correoElectronico: $correo,
                    telefonoMovil: $datosPersona['telefono_movil'] ?? null,
                    telefonoWhatsapp: $whatsappNormalizado,
                    direccion: $datosPersona['direccion'] ?? null,
                    ciudad: $datosPersona['ciudad'] ?? null,
                    codigoPais: $codigoPais,
                    estado: 'ACTIVO'
                );

                $personaId = $this->personaRepo->crear($nuevaPersona);

                $this->auditoriaRepo->registrar(
                    contexto: $contexto,
                    modulo: 'personas',
                    accion: 'CREAR_PERSONA',
                    entidadTipo: 'Persona',
                    entidadId: (string) $personaId,
                    datosPrevios: null,
                    datosNuevos: [
                        'id'                => $personaId,
                        'organizacion_id'   => $organizacionId,
                        'tipo_persona'      => $nuevaPersona->tipoPersona,
                        'tipo_documento_id' => $tipoDocId,
                        'numero_documento'  => $numDoc,
                        'telefono_whatsapp' => $whatsappNormalizado,
                    ]
                );
            }

            // 5. Crear el perfil comercial
            $datosComerciales['telefono_whatsapp'] = $whatsappNormalizado;
            $clienteCreado = $this->crearDesdePersona($organizacionId, $personaId, $datosComerciales, $contexto);

            if ($transaccionIniciada) {
                $this->pdo->commit();
            }

            return $clienteCreado;
        } catch (\Throwable $e) {
            if ($transaccionIniciada && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Transiciona el estado comercial gobernado.
     */
    public function cambiarEstadoComercial(
        int $organizacionId,
        int $clienteId,
        string $nuevoEstadoStr,
        ?string $motivo,
        ContextoOperacion $contexto
    ): Cliente {
        $this->validarAlcanceTenant($organizacionId, $contexto);

        $nuevoEstado = EstadoCliente::desdeCadena($nuevoEstadoStr);

        // Si se transiciona a INACTIVO se requiere permiso de desactivación
        if ($nuevoEstado === EstadoCliente::INACTIVO) {
            $this->validarPermiso('clientes.desactivar', $contexto);
        } else {
            $this->validarPermiso('clientes.editar', $contexto);
        }

        $transaccionIniciada = false;
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
            $transaccionIniciada = true;
        }

        try {
            $cliente = $this->clienteRepo->buscarPorIdBloqueante($clienteId);
            if ($cliente === null || $cliente->organizacionId !== $organizacionId) {
                throw new AccesoDenegadoExcepcion("Cliente no encontrado en la organización especificada.");
            }

            if (!$cliente->estadoComercial->puedeTransicionarA($nuevoEstado)) {
                throw new InvalidArgumentException(
                    "Transición comercial inválida: no es posible pasar de '{$cliente->estadoComercial->value}' a '{$nuevoEstado->value}'."
                );
            }

            $datosPrevios = $cliente->aArreglo();
            $this->clienteRepo->actualizarEstadoComercial($clienteId, $nuevoEstado);

            $clienteActualizado = $this->clienteRepo->buscarPorId($clienteId);

            $this->auditoriaRepo->registrar(
                contexto: $contexto,
                modulo: 'clientes',
                accion: 'CAMBIAR_ESTADO_CLIENTE',
                entidadTipo: 'Cliente',
                entidadId: (string) $clienteId,
                datosPrevios: $datosPrevios,
                datosNuevos: [
                    'estado_comercial_previo' => $cliente->estadoComercial->value,
                    'estado_comercial_nuevo'  => $nuevoEstado->value,
                    'motivo'                  => $motivo !== null ? trim($motivo) : null,
                ]
            );

            if ($transaccionIniciada) {
                $this->pdo->commit();
            }

            return $clienteActualizado;
        } catch (\Throwable $e) {
            if ($transaccionIniciada && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Consulta un cliente por ID retornando su información comercial y de identidad.
     */
    public function obtenerPorId(int $organizacionId, int $clienteId, ContextoOperacion $contexto): ?array
    {
        $this->validarAlcanceTenant($organizacionId, $contexto);
        $this->validarPermiso('clientes.ver', $contexto);

        $cliente = $this->clienteRepo->buscarPorId($clienteId);
        if ($cliente === null || $cliente->organizacionId !== $organizacionId) {
            return null;
        }

        $persona = $this->personaRepo->buscarPorId($cliente->personaId);

        return [
            'cliente' => $cliente->aArreglo(),
            'persona' => $persona ? [
                'id'                 => $persona->id,
                'tipo_persona'       => $persona->tipoPersona,
                'nombre_completo'    => $persona->obtenerNombreCompleto(),
                'tipo_documento_id'  => $persona->tipoDocumentoId,
                'numero_documento'   => $persona->numeroDocumento,
                'correo_electronico' => $persona->correoElectronico,
                'telefono_whatsapp'  => $persona->telefonoWhatsapp,
                'codigo_pais'        => $persona->codigoPais,
            ] : null,
        ];
    }

    /**
     * Lista clientes de la organización con filtrado opcional por estado comercial.
     */
    public function listarPorOrganizacion(
        int $organizacionId,
        ?string $estadoStr,
        int $limite,
        int $offset,
        ContextoOperacion $contexto
    ): array {
        $this->validarAlcanceTenant($organizacionId, $contexto);
        $this->validarPermiso('clientes.ver', $contexto);

        $estado = !empty($estadoStr) ? EstadoCliente::desdeCadena($estadoStr) : null;
        return $this->clienteRepo->buscarPorOrganizacion($organizacionId, $estado, $limite, $offset);
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
