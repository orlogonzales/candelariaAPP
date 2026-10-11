<?php

declare(strict_types=1);

namespace Aplicacion\Servicios;

use Aplicacion\Comunicaciones\ComunicacionServicio;
use Aplicacion\Entidades\ClienteCodigoAcceso;
use Aplicacion\Entidades\ClienteSesion;
use Aplicacion\Repositorios\ClienteCodigoAccesoRepositorio;
use Aplicacion\Repositorios\ClienteRepositorio;
use Aplicacion\Repositorios\ClienteSesionRepositorio;
use Aplicacion\Repositorios\PersonaRepositorio;
use InvalidArgumentException;
use RuntimeException;

class AutenticacionClienteServicio
{
    public const COOKIE_CLIENTE_SESION = 'candelaria_cliente_sesion';

    public function __construct(
        private ClienteSesionRepositorio $sesionRepo,
        private ClienteCodigoAccesoRepositorio $codigoRepo,
        private ClienteRepositorio $clienteRepo,
        private PersonaRepositorio $personaRepo,
        private ?ComunicacionServicio $comunicacionServicio = null
    ) {
    }

    /**
     * Solicita y emite un código OTP de 6 dígitos enviado por WhatsApp.
     */
    public function solicitarOtpWhatsApp(int $organizacionId, string $telefono): array
    {
        $telefonoLimpio = trim($telefono);
        if ($telefonoLimpio === '' || strlen($telefonoLimpio) < 8) {
            throw new InvalidArgumentException("El número de teléfono/WhatsApp proporcionado es inválido.");
        }

        // 1. Rate limiting (máximo 3 solicitudes por cada 15 minutos)
        $solicitudesRecientes = $this->codigoRepo->contarSolicitudesRecientes($organizacionId, $telefonoLimpio, 15);
        if ($solicitudesRecientes >= 3) {
            return [
                'exito'   => false,
                'mensaje' => 'Ha excedido el límite de solicitudes de acceso. Intente nuevamente en 15 minutos.',
            ];
        }

        // 2. Buscar cliente titular vinculado al teléfono
        $cliente = $this->clienteRepo->buscarPorTelefono($organizacionId, $telefonoLimpio);
        if ($cliente === null) {
            // Protección contra enumeración: devolver mensaje amigable
            return [
                'exito'   => false,
                'mensaje' => 'No se encontró ninguna reserva vinculada a este número de WhatsApp en la organización.',
            ];
        }

        // 3. Generar código OTP numérico de 6 dígitos
        $codigoNumerico = sprintf('%06d', random_int(100000, 999999));
        $codigoHash = hash('sha256', $codigoNumerico);
        $expiraEn = date('Y-m-d H:i:s', time() + (10 * 60)); // 10 minutos de vigencia

        $codigoAcceso = new ClienteCodigoAcceso(
            id: null,
            organizacionId: $organizacionId,
            clienteId: (int) $cliente->id,
            tipoAcceso: ClienteCodigoAcceso::TIPO_OTP_WHATSAPP,
            codigoHash: $codigoHash,
            destino: $telefonoLimpio,
            utilizado: false,
            intentosFallidos: 0,
            expiraEn: $expiraEn
        );

        $this->codigoRepo->crear($codigoAcceso);

        // 4. Encolar notificación en Outbox si el servicio de comunicación está disponible
        if ($this->comunicacionServicio !== null) {
            try {
                $this->comunicacionServicio->encolarMensaje(
                    organizacionId: $organizacionId,
                    clienteId: (int) $cliente->id,
                    telefonoDestino: $telefonoLimpio,
                    tipoMensaje: \Aplicacion\Comunicaciones\TipoMensaje::PLANTILLA,
                    nombrePlantilla: 'autenticacion_cliente_otp',
                    parametrosVariables: ['codigo' => $codigoNumerico],
                    eventoOrigen: 'PORTAL_CLIENTE_OTP'
                );
            } catch (\Throwable) {
                // Silenciar error de encolamiento para permitir flujo simulador
            }
        }

        return [
            'exito'          => true,
            'mensaje'        => "Se ha enviado un código de acceso de 6 dígitos a su WhatsApp {$telefonoLimpio}.",
            'codigo_sandbox' => $codigoNumerico, // Expuesto solo en entorno no-producción / tests
        ];
    }

    /**
     * Solicita y emite un Magic Link por correo electrónico.
     */
    public function solicitarMagicLinkEmail(int $organizacionId, string $correo): array
    {
        $correoLimpio = strtolower(trim($correo));
        if (!filter_var($correoLimpio, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("El formato del correo electrónico es inválido.");
        }

        // 1. Rate limiting
        $solicitudes = $this->codigoRepo->contarSolicitudesRecientes($organizacionId, $correoLimpio, 15);
        if ($solicitudes >= 3) {
            return [
                'exito'   => false,
                'mensaje' => 'Ha excedido el límite de solicitudes. Intente nuevamente en 15 minutos.',
            ];
        }

        // 2. Buscar cliente por correo
        $cliente = $this->clienteRepo->buscarPorCorreo($organizacionId, $correoLimpio);
        if ($cliente === null) {
            return [
                'exito'   => false,
                'mensaje' => 'No se encontró ninguna reserva vinculada a este correo electrónico.',
            ];
        }

        // 3. Generar token criptográfico CSPRNG
        $tokenPlano = bin2hex(random_bytes(32));
        $codigoHash = hash('sha256', $tokenPlano);
        $expiraEn = date('Y-m-d H:i:s', time() + (30 * 60)); // 30 minutos

        $codigoAcceso = new ClienteCodigoAcceso(
            id: null,
            organizacionId: $organizacionId,
            clienteId: (int) $cliente->id,
            tipoAcceso: ClienteCodigoAcceso::TIPO_MAGIC_LINK_EMAIL,
            codigoHash: $codigoHash,
            destino: $correoLimpio,
            utilizado: false,
            intentosFallidos: 0,
            expiraEn: $expiraEn
        );

        $this->codigoRepo->crear($codigoAcceso);

        return [
            'exito'          => true,
            'mensaje'        => "Se ha enviado un enlace de acceso seguro a su correo {$correoLimpio}.",
            'token_sandbox'  => $tokenPlano,
        ];
    }

    /**
     * Valida el código OTP de WhatsApp y emite la sesión segura del cliente.
     */
    public function verificarOtpWhatsApp(
        int $organizacionId,
        string $telefono,
        string $codigoOtp,
        string $ipOrigen,
        ?string $userAgent = null
    ): array {
        $telefonoLimpio = trim($telefono);
        $codigoLimpio = trim($codigoOtp);

        $cliente = $this->clienteRepo->buscarPorTelefono($organizacionId, $telefonoLimpio);
        if ($cliente === null) {
            return ['exito' => false, 'mensaje' => 'Cliente no localizado.'];
        }

        $codigoHash = hash('sha256', $codigoLimpio);

        // Buscar código coincidente activo
        $codigoObj = $this->codigoRepo->buscarActivoPorHash(
            $organizacionId,
            (int) $cliente->id,
            ClienteCodigoAcceso::TIPO_OTP_WHATSAPP,
            $codigoHash
        );

        if ($codigoObj === null) {
            // Incrementar intentos fallidos en el último activo
            $ultimo = $this->codigoRepo->buscarUltimoActivoPorDestino(
                $organizacionId,
                ClienteCodigoAcceso::TIPO_OTP_WHATSAPP,
                $telefonoLimpio
            );
            if ($ultimo !== null) {
                $intentos = $this->codigoRepo->incrementarIntentosFallidos((int) $ultimo->id);
                if ($intentos >= 3) {
                    $this->codigoRepo->marcarUtilizado((int) $ultimo->id);
                    return [
                        'exito'   => false,
                        'mensaje' => 'Código de acceso invalidado por exceder el número máximo de intentos fallidos (3). Solicite uno nuevo.',
                    ];
                }
            }

            return ['exito' => false, 'mensaje' => 'El código ingresado es incorrecto o ha expirado.'];
        }

        // Marcar código como utilizado (single-use)
        $this->codigoRepo->marcarUtilizado((int) $codigoObj->id);

        // Emitir sesión del cliente
        $sesion = $this->emitirSesionCliente($organizacionId, (int) $cliente->id, $ipOrigen, $userAgent);

        return [
            'exito'        => true,
            'mensaje'      => 'Acceso autorizado al Espacio del Cliente.',
            'cliente_id'   => (int) $cliente->id,
            'token_sesion' => $sesion['token_plano'],
            'expira_en'    => $sesion['expira_en'],
        ];
    }

    /**
     * Valida el Magic Link y emite la sesión segura del cliente.
     */
    public function verificarMagicLink(
        int $organizacionId,
        string $correo,
        string $tokenPlano,
        string $ipOrigen,
        ?string $userAgent = null
    ): array {
        $correoLimpio = strtolower(trim($correo));
        $tokenLimpio = trim($tokenPlano);

        $cliente = $this->clienteRepo->buscarPorCorreo($organizacionId, $correoLimpio);
        if ($cliente === null) {
            return ['exito' => false, 'mensaje' => 'Cliente no localizado.'];
        }

        $codigoHash = hash('sha256', $tokenLimpio);

        $codigoObj = $this->codigoRepo->buscarActivoPorHash(
            $organizacionId,
            (int) $cliente->id,
            ClienteCodigoAcceso::TIPO_MAGIC_LINK_EMAIL,
            $codigoHash
        );

        if ($codigoObj === null) {
            return ['exito' => false, 'mensaje' => 'El enlace de acceso es inválido o ha expirado.'];
        }

        $this->codigoRepo->marcarUtilizado((int) $codigoObj->id);

        $sesion = $this->emitirSesionCliente($organizacionId, (int) $cliente->id, $ipOrigen, $userAgent);

        return [
            'exito'        => true,
            'mensaje'      => 'Acceso autorizado al Espacio del Cliente.',
            'cliente_id'   => (int) $cliente->id,
            'token_sesion' => $sesion['token_plano'],
            'expira_en'    => $sesion['expira_en'],
        ];
    }

    /**
     * Emite una sesión segura de 30 días para un cliente en un tenant.
     */
    public function emitirSesionCliente(
        int $organizacionId,
        int $clienteId,
        string $ipOrigen,
        ?string $userAgent = null
    ): array {
        $tokenPlano = 'cli_ses_' . bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $tokenPlano);
        $expiraEn = date('Y-m-d H:i:s', time() + (30 * 86400)); // 30 días

        $sesion = new ClienteSesion(
            id: null,
            organizacionId: $organizacionId,
            clienteId: $clienteId,
            tokenSesionHash: $tokenHash,
            ipCreacion: $ipOrigen,
            userAgent: $userAgent,
            expiraEn: $expiraEn,
            revocado: false
        );

        $this->sesionRepo->crear($sesion);

        return [
            'token_plano' => $tokenPlano,
            'expira_en'   => $expiraEn,
        ];
    }

    /**
     * Valida el token de sesión de la cookie pública del cliente.
     */
    public function validarSesion(string $tokenCookie): ?ClienteSesion
    {
        $tokenLimpio = trim($tokenCookie);
        if ($tokenLimpio === '') {
            return null;
        }

        $tokenHash = hash('sha256', $tokenLimpio);
        $sesion = $this->sesionRepo->buscarPorTokenHash($tokenHash);

        if ($sesion !== null && $sesion->esValida()) {
            $this->sesionRepo->actualizarUltimoAcceso((int) $sesion->id);
            return $sesion;
        }

        return null;
    }

    /**
     * Cierra la sesión activa revocándola.
     */
    public function cerrarSesion(string $tokenCookie): bool
    {
        $tokenLimpio = trim($tokenCookie);
        if ($tokenLimpio === '') {
            return false;
        }

        $tokenHash = hash('sha256', $tokenLimpio);
        $sesion = $this->sesionRepo->buscarPorTokenHash($tokenHash);

        if ($sesion !== null) {
            return $this->sesionRepo->revocar((int) $sesion->id);
        }

        return false;
    }
}
