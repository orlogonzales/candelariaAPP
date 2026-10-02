<?php

declare(strict_types=1);

namespace Aplicacion\Seguridad;

use Aplicacion\Entidades\Sesion;
use Aplicacion\Entidades\Usuario;
use Aplicacion\Repositorios\AuditoriaRepositorio;
use Aplicacion\Repositorios\SesionRepositorio;
use Aplicacion\Repositorios\UsuarioRepositorio;
use Nucleo\BaseDatos\Conexion;
use Nucleo\Http\ContextoOperacion;
use Nucleo\Seguridad\ConfiguracionSeguridad;
use Nucleo\Seguridad\ManejadorCookie;
use Nucleo\Seguridad\ProtectorCsrf;
use PDO;

/**
 * Servicio Central de Autenticación y Control de Sesiones de CandelariaAPP.
 * Coordina verificación criptográfica, protección contra Session Fixation,
 * políticas de bloqueo por fuerza bruta y generación de Contexto de Operación.
 */
class AutenticacionServicio
{
    private PDO $pdo;
    private UsuarioRepositorio $usuarioRepo;
    private SesionRepositorio $sesionRepo;
    private AuditoriaRepositorio $auditoriaRepo;

    public const MENSAJE_CREDENCIALES_INVALIDAS = 'Credenciales de acceso incorrectas.';
    public const MENSAJE_CUENTA_INACTIVA        = 'La cuenta de usuario se encuentra inactiva.';
    public const MENSAJE_CUENTA_BLOQUEADA       = 'La cuenta se encuentra temporalmente bloqueada por múltiples intentos fallidos.';

    public function __construct(
        ?UsuarioRepositorio $usuarioRepo = null,
        ?SesionRepositorio $sesionRepo = null,
        ?AuditoriaRepositorio $auditoriaRepo = null,
        ?PDO $pdo = null
    ) {
        $this->pdo = $pdo ?? Conexion::obtenerInstancia();
        $this->usuarioRepo = $usuarioRepo ?? new UsuarioRepositorio($this->pdo);
        $this->sesionRepo = $sesionRepo ?? new SesionRepositorio($this->pdo);
        $this->auditoriaRepo = $auditoriaRepo ?? new AuditoriaRepositorio($this->pdo);
    }

    /**
     * Procesa un intento de autenticación humana con mitigación de enumeración y timing attacks.
     */
    public function autenticar(
        string $loginOCorreo,
        string $contrasenaPlana,
        string $ip = '127.0.0.1',
        ?string $agenteUsuario = null,
        ?string $correlacionId = null
    ): ResultadoAutenticacion {
        $identificador = trim($loginOCorreo);
        $correlacion = ContextoOperacion::resolverCorrelacionId($correlacionId);

        // 1. Buscar usuario por nombre de usuario o correo electrónico
        $usuario = str_contains($identificador, '@')
            ? $this->usuarioRepo->buscarPorCorreo($identificador)
            : $this->usuarioRepo->buscarPorNombreUsuario($identificador);

        // 2. Si el usuario no existe: mitigar timing attack con verificación falsa y respuesta uniforme
        if ($usuario === null) {
            password_verify($contrasenaPlana, '$2y$10$abcdefghijklmnopqrstuvABCDEFGHIJKLMNOPQRSTUV0123456789');

            $contextoSistema = ContextoOperacion::paraSistema(
                actorSistemaId: 1, // SISTEMA_CLI / Núcleo
                actorSistemaCodigo: 'SISTEMA_CLI',
                canalId: 1, // APP
                canalCodigo: 'APP',
                origenIp: $ip,
                agenteUsuario: $agenteUsuario,
                organizacionId: null,
                correlacionId: $correlacion
            );

            $this->auditoriaRepo->registrar(
                contexto: $contextoSistema,
                modulo: 'autenticacion',
                accion: 'LOGIN_FALLIDO',
                entidadTipo: 'usuario',
                entidadId: '0',
                datosPrevios: null,
                datosNuevos: [
                    'identificador' => $identificador,
                    'motivo'        => 'USUARIO_INEXISTENTE',
                    'ip'            => $ip,
                ]
            );

            return ResultadoAutenticacion::fallo(self::MENSAJE_CREDENCIALES_INVALIDAS);
        }

        // 3. Validar estado: INACTIVO
        if ($usuario->estado === 'INACTIVO') {
            $contextoSistema = ContextoOperacion::paraSistema(
                actorSistemaId: 1,
                actorSistemaCodigo: 'SISTEMA_CLI',
                canalId: 1,
                canalCodigo: 'APP',
                origenIp: $ip,
                agenteUsuario: $agenteUsuario,
                organizacionId: $usuario->organizacionId,
                correlacionId: $correlacion
            );

            $this->auditoriaRepo->registrar(
                contexto: $contextoSistema,
                modulo: 'autenticacion',
                accion: 'LOGIN_FALLIDO',
                entidadTipo: 'usuario',
                entidadId: (string) $usuario->id,
                datosPrevios: null,
                datosNuevos: [
                    'identificador' => $identificador,
                    'motivo'        => 'CUENTA_INACTIVA',
                    'ip'            => $ip,
                ]
            );

            return ResultadoAutenticacion::fallo(self::MENSAJE_CUENTA_INACTIVA);
        }

        // 4. Validar estado: BLOQUEADO
        if ($usuario->estaBloqueado()) {
            $contextoSistema = ContextoOperacion::paraSistema(
                actorSistemaId: 1,
                actorSistemaCodigo: 'SISTEMA_CLI',
                canalId: 1,
                canalCodigo: 'APP',
                origenIp: $ip,
                agenteUsuario: $agenteUsuario,
                organizacionId: $usuario->organizacionId,
                correlacionId: $correlacion
            );

            $this->auditoriaRepo->registrar(
                contexto: $contextoSistema,
                modulo: 'autenticacion',
                accion: 'LOGIN_FALLIDO',
                entidadTipo: 'usuario',
                entidadId: (string) $usuario->id,
                datosPrevios: null,
                datosNuevos: [
                    'identificador' => $identificador,
                    'motivo'        => 'CUENTA_BLOQUEADA',
                    'bloqueado_hasta' => $usuario->bloqueadoHasta,
                    'ip'            => $ip,
                ]
            );

            return ResultadoAutenticacion::fallo(self::MENSAJE_CUENTA_BLOQUEADA);
        }

        // 5. Verificación criptográfica con password_verify nativo
        if (!$usuario->verificarContrasena($contrasenaPlana)) {
            // Manejar incremento de intentos fallidos
            $maxIntentos = ConfiguracionSeguridad::maxIntentosFallidos();
            $minutosBloqueo = ConfiguracionSeguridad::minutosBloqueo();

            $this->usuarioRepo->registrarIntentoFallido($usuario->id, $maxIntentos, $minutosBloqueo);

            $usuarioActualizado = $this->usuarioRepo->buscarPorId($usuario->id);
            $seBloqueo = $usuarioActualizado !== null && $usuarioActualizado->estaBloqueado();

            $contextoSistema = ContextoOperacion::paraSistema(
                actorSistemaId: 1,
                actorSistemaCodigo: 'SISTEMA_CLI',
                canalId: 1,
                canalCodigo: 'APP',
                origenIp: $ip,
                agenteUsuario: $agenteUsuario,
                organizacionId: $usuario->organizacionId,
                correlacionId: $correlacion
            );

            if ($seBloqueo) {
                $this->auditoriaRepo->registrar(
                    contexto: $contextoSistema,
                    modulo: 'seguridad',
                    accion: 'BLOQUEO',
                    entidadTipo: 'usuario',
                    entidadId: (string) $usuario->id,
                    datosPrevios: null,
                    datosNuevos: [
                        'motivo'          => 'EXCESO_INTENTOS_FALLIDOS',
                        'intentos'        => $usuarioActualizado->intentosFallidos,
                        'bloqueado_hasta' => $usuarioActualizado->bloqueadoHasta,
                        'ip'              => $ip,
                    ]
                );
            }

            $this->auditoriaRepo->registrar(
                contexto: $contextoSistema,
                modulo: 'autenticacion',
                accion: 'LOGIN_FALLIDO',
                entidadTipo: 'usuario',
                entidadId: (string) $usuario->id,
                datosPrevios: null,
                datosNuevos: [
                    'identificador'    => $identificador,
                    'motivo'           => 'CONTRASENA_INCORRECTA',
                    'intentos_actuales' => $usuarioActualizado ? $usuarioActualizado->intentosFallidos : 0,
                    'ip'               => $ip,
                ]
            );

            // Mensaje uniforme para mitigar enumeración de usuarios
            return ResultadoAutenticacion::fallo(self::MENSAJE_CREDENCIALES_INVALIDAS);
        }

        // 6. Autenticación exitosa
        // Restablecer contador de intentos fallidos
        $this->usuarioRepo->restablecerIntentosFallidos($usuario->id);

        // Actualizar último acceso
        $this->usuarioRepo->actualizarUltimoAcceso($usuario->id);

        // Evaluar password_needs_rehash()
        if ($usuario->necesitaRehash()) {
            $nuevoHash = password_hash($contrasenaPlana, PASSWORD_DEFAULT);
            $this->usuarioRepo->actualizarContrasenaHash($usuario->id, $nuevoHash);
        }

        // Protección contra Session Fixation: Generación de token fresco
        $tokenPlano = bin2hex(random_bytes(32));

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        // Crear sesión persistente en base de datos
        $csrfToken = ProtectorCsrf::generarToken();
        $cargaUtil = [
            'iniciado_en'          => date('Y-m-d H:i:s'),
            'creado_en_timestamp'  => time(),
            'nombre_usuario'       => $usuario->nombreUsuario,
            'csrf_token'           => $csrfToken,
        ];

        $sesion = $this->sesionRepo->crear($tokenPlano, $usuario->id, $ip, $agenteUsuario, $cargaUtil);

        // Emitir cookie segura si aplica
        ManejadorCookie::emitir($tokenPlano);

        // Construir Contexto de Operación Humano
        $contexto = ContextoOperacion::paraHumano(
            usuarioId: $usuario->id,
            canalId: 1, // APP
            canalCodigo: 'APP',
            origenIp: $ip,
            agenteUsuario: $agenteUsuario,
            organizacionId: $usuario->organizacionId,
            correlacionId: $correlacion,
            metadatos: ['csrf_token' => $csrfToken]
        );

        // Auditar LOGIN_EXITOSO
        $this->auditoriaRepo->registrar(
            contexto: $contexto,
            modulo: 'autenticacion',
            accion: 'LOGIN_EXITOSO',
            entidadTipo: 'usuario',
            entidadId: (string) $usuario->id,
            datosPrevios: null,
            datosNuevos: [
                'nombre_usuario' => $usuario->nombreUsuario,
                'ip'             => $ip,
                'resultado'      => 'EXITO',
            ]
        );

        return ResultadoAutenticacion::exito($usuario, $tokenPlano, $contexto, $sesion);
    }

    /**
     * Valida una sesión activa a partir del token presentado por el cliente.
     * Retorna el Contexto de Operación si es válida, o null si fue revocada, expiró o el usuario no está activo.
     */
    public function validarSesion(
        string $tokenPlano,
        string $ip = '127.0.0.1',
        ?string $agenteUsuario = null,
        ?string $correlacionId = null
    ): ?ContextoOperacion {
        $sesion = $this->sesionRepo->buscarPorToken($tokenPlano);
        if ($sesion === null) {
            return null;
        }

        // 1. Validar expiración por inactividad
        $timeoutInactividad = ConfiguracionSeguridad::timeoutInactividad();
        if ($sesion->haExpirado($timeoutInactividad)) {
            $this->sesionRepo->revocarPorToken($tokenPlano);
            return null;
        }

        // 2. Validar tiempo de vida absoluto
        $tiempoAbsoluto = ConfiguracionSeguridad::tiempoVidaAbsoluto();
        if ($sesion->haSuperadoTiempoVidaAbsoluto($tiempoAbsoluto)) {
            $this->sesionRepo->revocarPorToken($tokenPlano);
            return null;
        }

        // 3. Validar estado del usuario en tiempo real en la base de datos
        if ($sesion->usuarioId === null) {
            return null;
        }

        $usuario = $this->usuarioRepo->buscarPorId($sesion->usuarioId);
        if ($usuario === null || $usuario->estado !== 'ACTIVO' || $usuario->estaBloqueado()) {
            // Usuario inactivo, eliminado o bloqueado: invalidar la sesión inmediatamente
            $this->sesionRepo->revocarPorToken($tokenPlano);
            return null;
        }

        // 4. Actualizar timestamp de última actividad
        $this->sesionRepo->actualizarActividad($tokenPlano);

        $correlacion = ContextoOperacion::resolverCorrelacionId($correlacionId);

        return ContextoOperacion::paraHumano(
            usuarioId: $usuario->id,
            canalId: 1, // APP
            canalCodigo: 'APP',
            origenIp: $ip,
            agenteUsuario: $agenteUsuario,
            organizacionId: $usuario->organizacionId,
            correlacionId: $correlacion,
            metadatos: [
                'sesion_id_hash' => $sesion->id,
                'csrf_token'     => $sesion->obtenerDato('csrf_token'),
            ]
        );
    }

    /**
     * Cierra de forma segura una sesión activa (servidor + PHP + cookie) y audita el evento.
     */
    public function cerrarSesion(string $tokenPlano, ?ContextoOperacion $contexto = null): bool
    {
        $sesion = $this->sesionRepo->buscarPorToken($tokenPlano);
        if ($sesion !== null) {
            if ($contexto === null && $sesion->usuarioId !== null) {
                $contexto = ContextoOperacion::paraHumano(
                    usuarioId: $sesion->usuarioId,
                    canalId: 1,
                    canalCodigo: 'APP',
                    origenIp: $sesion->direccionIp,
                    agenteUsuario: $sesion->agenteUsuario
                );
            }

            $this->sesionRepo->revocarPorToken($tokenPlano);

            if ($contexto !== null && $sesion->usuarioId !== null) {
                $this->auditoriaRepo->registrar(
                    contexto: $contexto,
                    modulo: 'autenticacion',
                    accion: 'LOGOUT',
                    entidadTipo: 'usuario',
                    entidadId: (string) $sesion->usuarioId,
                    datosPrevios: null,
                    datosNuevos: ['motivo' => 'VOLUNTARIO', 'ip' => $sesion->direccionIp]
                );
            }
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_unset();
            session_destroy();
        }

        ManejadorCookie::eliminar();

        return true;
    }

    /**
     * Revoca una sesión por su hash identificador primario (usado en gestión de sesiones concurrentes).
     */
    public function revocarSesionPorHash(string $idHash, ?ContextoOperacion $contexto = null): bool
    {
        $sesion = $this->sesionRepo->buscarPorIdHash($idHash);
        if ($sesion === null) {
            return false;
        }

        $exito = $this->sesionRepo->revocarPorIdHash($idHash);

        if ($exito && $contexto !== null && $sesion->usuarioId !== null) {
            $this->auditoriaRepo->registrar(
                contexto: $contexto,
                modulo: 'seguridad',
                accion: 'REVOCACION_SESION',
                entidadTipo: 'usuario',
                entidadId: (string) $sesion->usuarioId,
                datosPrevios: null,
                datosNuevos: [
                    'sesion_id_hash' => $idHash,
                    'motivo'         => 'REVOCACION_ADMINISTRATIVA',
                ]
            );
        }

        return $exito;
    }

    /**
     * Revoca todas las sesiones activas de un usuario determinado.
     */
    public function revocarTodasSesionesUsuario(
        int $usuarioId,
        ?string $exceptoToken = null,
        ?ContextoOperacion $contexto = null
    ): int {
        $eliminadas = $this->sesionRepo->revocarTodasDeUsuario($usuarioId, $exceptoToken);

        if ($eliminadas > 0 && $contexto !== null) {
            $this->auditoriaRepo->registrar(
                contexto: $contexto,
                modulo: 'seguridad',
                accion: 'REVOCACION_SESION',
                entidadTipo: 'usuario',
                entidadId: (string) $usuarioId,
                datosPrevios: null,
                datosNuevos: [
                    'total_revocadas' => $eliminadas,
                    'motivo'          => 'REVOCACION_TOTAL_USUARIO',
                ]
            );
        }

        return $eliminadas;
    }

    /**
     * Consulta las sesiones activas de un usuario.
     * @return Sesion[]
     */
    public function obtenerSesionesActivasUsuario(int $usuarioId): array
    {
        return $this->sesionRepo->buscarActivasPorUsuario($usuarioId);
    }
}
