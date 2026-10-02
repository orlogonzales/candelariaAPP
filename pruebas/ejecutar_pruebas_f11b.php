<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
\Nucleo\Soporte\CargadorEntorno::cargar(__DIR__ . '/../.env');
require_once __DIR__ . '/../nucleo/Soporte/ayudantes.php';

use Aplicacion\Entidades\Persona;
use Aplicacion\Entidades\Usuario;
use Aplicacion\Repositorios\AuditoriaRepositorio;
use Aplicacion\Repositorios\PersonaRepositorio;
use Aplicacion\Repositorios\SesionRepositorio;
use Aplicacion\Repositorios\UsuarioRepositorio;
use Aplicacion\Seguridad\AutenticacionServicio;
use Nucleo\BaseDatos\Conexion;
use Nucleo\Http\ContextoOperacion;
use Nucleo\Seguridad\ConfiguracionSeguridad;
use Nucleo\Seguridad\ProtectorCsrf;

echo "==============================================================================\n";
echo "CANDELARIAAPP — SUITE DE PRUEBAS DE SEGURIDAD Y AUTENTICACIÓN F1.1B\n";
echo "AUTENTICACIÓN, SESIONES SEGURAS, FIXATION, CSRF, BLOQUEO Y AUDITORÍA\n";
echo "==============================================================================\n\n";

$pdo = Conexion::obtenerInstancia();
$pdo->beginTransaction();

$fallos = 0;
$exitos = 0;

function afirmar(bool $condicion, string $descripcion, string $detalles = ''): void
{
    global $fallos, $exitos;
    if ($condicion) {
        $exitos++;
        echo " [PASS] {$descripcion}\n";
    } else {
        $fallos++;
        echo " [FAIL] {$descripcion}" . ($detalles !== '' ? " -> {$detalles}" : '') . "\n";
    }
}

try {
    // 0. Preparar datos base para pruebas en la transacción
    $pdo->exec("INSERT INTO `organizaciones` (`id`, `codigo`, `nombre_comercial`, `estado`) VALUES (9998, 'test_tenant_f11b', 'ORGANIZACIÓN PRUEBAS F1.1B', 'ACTIVO')");
    $orgId = 9998;

    $personaRepo = new PersonaRepositorio($pdo);
    $usuarioRepo = new UsuarioRepositorio($pdo);
    $sesionRepo = new SesionRepositorio($pdo);
    $auditoriaRepo = new AuditoriaRepositorio($pdo);
    $authServicio = new AutenticacionServicio($usuarioRepo, $sesionRepo, $auditoriaRepo, $pdo);

    // Persona base para usuarios de prueba
    $personaId1 = $personaRepo->crear(new Persona(
        id: null,
        organizacionId: $orgId,
        tipoPersona: 'NATURAL',
        tipoDocumentoId: 1, // DNI
        numeroDocumento: '71112233',
        nombres: 'Seguridad',
        apellidos: 'Tester Principal',
        correoElectronico: 'tester.seguridad@test.com',
        telefonoWhatsapp: '+51951000111',
        ciudad: 'Puno',
        codigoPais: 'PE'
    ));

    $personaId2 = $personaRepo->crear(new Persona(
        id: null,
        organizacionId: $orgId,
        tipoPersona: 'NATURAL',
        tipoDocumentoId: 1,
        numeroDocumento: '71112234',
        nombres: 'Tester',
        apellidos: 'Inactivo Bloqueado',
        correoElectronico: 'inactivo.tester@test.com',
        telefonoWhatsapp: '+51951000222',
        ciudad: 'Puno',
        codigoPais: 'PE'
    ));

    $personaId3 = $personaRepo->crear(new Persona(
        id: null,
        organizacionId: $orgId,
        tipoPersona: 'NATURAL',
        tipoDocumentoId: 1,
        numeroDocumento: '71112235',
        nombres: 'Tester',
        apellidos: 'Concurrencia Multiple',
        correoElectronico: 'concurrente.tester@test.com',
        telefonoWhatsapp: '+51951000333',
        ciudad: 'Puno',
        codigoPais: 'PE'
    ));

    $claveValida = 'Candelaria2026!Segura';
    $hashValido = password_hash($claveValida, PASSWORD_DEFAULT);

    // Usuario 1: Activo estándar
    $usuarioId1 = $usuarioRepo->crear(new Usuario(
        id: null,
        organizacionId: $orgId,
        personaId: $personaId1,
        nombreUsuario: 'usr_seguridad',
        nombreCompleto: 'Seguridad Tester',
        correoElectronico: 'tester.seguridad@test.com',
        contrasenaHash: $hashValido,
        estado: 'ACTIVO'
    ));

    // Usuario 2: Inactivo
    $usuarioId2 = $usuarioRepo->crear(new Usuario(
        id: null,
        organizacionId: $orgId,
        personaId: $personaId2,
        nombreUsuario: 'usr_inactivo',
        nombreCompleto: 'Inactivo Tester',
        correoElectronico: 'inactivo.tester@test.com',
        contrasenaHash: $hashValido,
        estado: 'INACTIVO'
    ));

    // --- 01. Contraseña Correcta ---
    $resultado01 = $authServicio->autenticar('usr_seguridad', $claveValida, '192.168.1.100', 'PHPUnitTest');
    afirmar(
        $resultado01->exitoso === true
        && $resultado01->usuario !== null
        && $resultado01->usuario->id === $usuarioId1
        && !empty($resultado01->tokenSesion)
        && $resultado01->contexto?->actorTipo === 'HUMANO',
        '01. Autenticación exitosa con contraseña correcta y generación de sesión'
    );
    $tokenSesionValido = $resultado01->tokenSesion;

    // --- 02. Contraseña Incorrecta ---
    $resultado02 = $authServicio->autenticar('usr_seguridad', 'PasswordTotalmenteErronea!', '192.168.1.100');
    afirmar(
        $resultado02->exitoso === false
        && $resultado02->mensajeError === AutenticacionServicio::MENSAJE_CREDENCIALES_INVALIDAS,
        '02. Contraseña incorrecta rechazada con mensaje de error uniforme'
    );

    // --- 03. Usuario Inexistente ---
    $resultado03 = $authServicio->autenticar('usuario_totalmente_fantasma', 'CualquierClave123!');
    afirmar(
        $resultado03->exitoso === false
        && $resultado03->mensajeError === AutenticacionServicio::MENSAJE_CREDENCIALES_INVALIDAS
        && $resultado03->mensajeError === $resultado02->mensajeError,
        '03. Usuario inexistente rechazado con respuesta uniforme idéntica (Anti-Enumeración)'
    );

    // --- 04. Usuario Inactivo ---
    $resultado04 = $authServicio->autenticar('usr_inactivo', $claveValida);
    afirmar(
        $resultado04->exitoso === false
        && $resultado04->mensajeError === AutenticacionServicio::MENSAJE_CUENTA_INACTIVA,
        '04. Usuario con estado INACTIVO es rechazado antes de crear sesión'
    );

    // --- 05. Usuario Bloqueado ---
    // Forzamos temporalmente a usr_inactivo como BLOQUEADO con fecha futura
    $pdo->prepare("UPDATE `usuarios` SET `estado` = 'BLOQUEADO', `bloqueado_hasta` = DATE_ADD(NOW(), INTERVAL 15 MINUTE) WHERE `id` = :id")
        ->execute([':id' => $usuarioId2]);
    $resultado05 = $authServicio->autenticar('usr_inactivo', $claveValida);
    afirmar(
        $resultado05->exitoso === false
        && $resultado05->mensajeError === AutenticacionServicio::MENSAJE_CUENTA_BLOQUEADA,
        '05. Usuario con estado BLOQUEADO y ventana activa es rechazado'
    );

    // --- 06. Incremento de Intentos Fallidos ---
    // Usamos usr_seguridad que ya tuvo 1 fallo en caso 02 (pero el caso 01 restableció a 0 antes del fallo de caso 02)
    $intentosAntes = $usuarioRepo->buscarPorId($usuarioId1)->intentosFallidos;
    $authServicio->autenticar('usr_seguridad', 'FalloNumeroDos!');
    $intentosDespues = $usuarioRepo->buscarPorId($usuarioId1)->intentosFallidos;
    afirmar(
        $intentosDespues === ($intentosAntes + 1),
        "06. Incremento consecutivo del contador de intentos fallidos ({$intentosAntes} -> {$intentosDespues})"
    );

    // --- 07. Bloqueo al Alcanzar Umbral ---
    // Llevamos los intentos hasta alcanzar el umbral (5 intentos máximos)
    for ($i = $intentosDespues; $i < 5; $i++) {
        $authServicio->autenticar('usr_seguridad', "FalloNumero{$i}!");
    }
    $usrBloqueado = $usuarioRepo->buscarPorId($usuarioId1);
    afirmar(
        $usrBloqueado->intentosFallidos >= 5
        && $usrBloqueado->estado === 'BLOQUEADO'
        && $usrBloqueado->bloqueadoHasta !== null
        && strtotime($usrBloqueado->bloqueadoHasta) > time(),
        '07. Bloqueo automático de cuenta y asignación de bloqueado_hasta al alcanzar 5 intentos fallidos'
    );

    // --- 08. Login Válido Reinicia Intentos ---
    // Desbloqueamos manualmente para simular intento exitoso con clave correcta y contador acumulado
    $pdo->prepare("UPDATE `usuarios` SET `estado` = 'ACTIVO', `intentos_fallidos` = 3, `bloqueado_hasta` = NULL WHERE `id` = :id")
        ->execute([':id' => $usuarioId1]);
    $resultado08 = $authServicio->autenticar('usr_seguridad', $claveValida);
    $usrRestablecido = $usuarioRepo->buscarPorId($usuarioId1);
    afirmar(
        $resultado08->exitoso === true
        && $usrRestablecido->intentosFallidos === 0
        && $usrRestablecido->bloqueadoHasta === null,
        '08. Autenticación exitosa restablece automáticamente intentos_fallidos a 0 y limpia bloqueado_hasta'
    );

    // --- 09. password_needs_rehash ---
    // Guardamos intencionalmente un hash generado con costo obsoleto (cost = 4)
    $hashBajoCosto = password_hash($claveValida, PASSWORD_BCRYPT, ['cost' => 4]);
    $pdo->prepare("UPDATE `usuarios` SET `contrasena_hash` = :hash WHERE `id` = :id")
        ->execute([':hash' => $hashBajoCosto, ':id' => $usuarioId1]);
    $usuarioConRehash = $usuarioRepo->buscarPorId($usuarioId1);
    afirmar($usuarioConRehash->necesitaRehash() === true, '09a. Detección nativa de necesidad de rehash');
    $resRehash = $authServicio->autenticar('usr_seguridad', $claveValida);
    $usrPostRehash = $usuarioRepo->buscarPorId($usuarioId1);
    afirmar(
        $resRehash->exitoso === true
        && $usrPostRehash->contrasenaHash !== $hashBajoCosto
        && password_verify($claveValida, $usrPostRehash->contrasenaHash)
        && $usrPostRehash->necesitaRehash() === false,
        '09. password_needs_rehash actualiza automáticamente el hash en BD con algoritmos y costos vigentes'
    );

    // --- 10. Regeneración de Sesión (Session Fixation Protection) ---
    // Cada autenticación debe emitir un token único y diferente; el token anterior no es reutilizado
    $resFixation1 = $authServicio->autenticar('usr_seguridad', $claveValida);
    $resFixation2 = $authServicio->autenticar('usr_seguridad', $claveValida);
    afirmar(
        $resFixation1->tokenSesion !== $resFixation2->tokenSesion
        && strlen($resFixation1->tokenSesion) === 64
        && strlen($resFixation2->tokenSesion) === 64,
        '10. Prevención de Session Fixation: Generación de token fresco e independiente en cada login'
    );

    // --- 11. Sesión Válida ---
    $tokenPrueba = $resFixation2->tokenSesion;
    $contexto11 = $authServicio->validarSesion($tokenPrueba, '192.168.1.50', 'TestAgent');
    afirmar(
        $contexto11 !== null
        && $contexto11->actorTipo === 'HUMANO'
        && $contexto11->usuarioId === $usuarioId1
        && $contexto11->canalCodigo === 'APP',
        '11. Validación de sesión activa retorna Contexto de Operación hidratado con actor humano'
    );

    // --- 12. Sesión Inexistente ---
    $tokenInexistente = bin2hex(random_bytes(32));
    $contexto12 = $authServicio->validarSesion($tokenInexistente);
    afirmar($contexto12 === null, '12. Token de sesión no existente en BD es rechazado de inmediato (retorna null)');

    // --- 13. Sesión Expirada por Inactividad ---
    // Forzamos la sesión a una última actividad superior al timeout (7200 segundos)
    $hashToken13 = SesionRepositorio::hashToken($tokenPrueba);
    $tiempoAntiguo = time() - (ConfiguracionSeguridad::timeoutInactividad() + 100);
    $pdo->prepare("UPDATE `sesiones` SET `ultima_actividad` = :tiempo WHERE `id` = :id")
        ->execute([':tiempo' => $tiempoAntiguo, ':id' => $hashToken13]);
    $contexto13 = $authServicio->validarSesion($tokenPrueba);
    $sesionBorrada = $sesionRepo->buscarPorIdHash($hashToken13);
    afirmar(
        $contexto13 === null && $sesionBorrada === null,
        '13. Sesión expirada por inactividad es rechazada y purgada físicamente de la base de datos'
    );

    // --- 14. Sesión Revocada ---
    $res14 = $authServicio->autenticar('usr_seguridad', $claveValida);
    $hash14 = SesionRepositorio::hashToken($res14->tokenSesion);
    $authServicio->revocarSesionPorHash($hash14);
    $contexto14 = $authServicio->validarSesion($res14->tokenSesion);
    afirmar($contexto14 === null, '14. Sesión revocada individualmente por su identificador hash rechaza acceso futuro');

    // --- 15. Logout Invalida Sesión ---
    $res15 = $authServicio->autenticar('usr_seguridad', $claveValida);
    $token15 = $res15->tokenSesion;
    $logoutExitoso = $authServicio->cerrarSesion($token15);
    $contexto15 = $authServicio->validarSesion($token15);
    afirmar(
        $logoutExitoso === true && $contexto15 === null,
        '15. Cierre de sesión (Logout) destruye la sesión en servidor e impide nuevos accesos'
    );

    // --- 16. Usuario Desactivado Invalida/Rechaza Sesión Activa Existente ---
    $res16 = $authServicio->autenticar('usr_seguridad', $claveValida);
    $token16 = $res16->tokenSesion;
    // Ahora simulamos que un administrador desactiva al usuario
    $usuarioRepo->actualizarEstado($usuarioId1, 'INACTIVO');
    $contexto16 = $authServicio->validarSesion($token16);
    afirmar(
        $contexto16 === null,
        '16. Backend valida estado de usuario en tiempo real: usuario desactivado no puede operar sesión preexistente'
    );
    // Restauramos a ACTIVO para siguientes pruebas
    $usuarioRepo->actualizarEstado($usuarioId1, 'ACTIVO');

    // --- 17. Múltiples Sesiones del Mismo Usuario (Concurrencia) ---
    $usuarioId3 = $usuarioRepo->crear(new Usuario(
        id: null,
        organizacionId: $orgId,
        personaId: $personaId3,
        nombreUsuario: 'usr_concurrente',
        nombreCompleto: 'Tester Concurrente',
        correoElectronico: 'concurrente.tester@test.com',
        contrasenaHash: $hashValido,
        estado: 'ACTIVO'
    ));

    $sesionDispositivo1 = $authServicio->autenticar('usr_concurrente', $claveValida, '192.168.1.10', 'Laptop Chrome');
    $sesionDispositivo2 = $authServicio->autenticar('usr_concurrente', $claveValida, '10.0.0.5', 'Mobile Safari');
    $sesionesActivas = $authServicio->obtenerSesionesActivasUsuario($usuarioId3);

    $validaDispositivo1 = $authServicio->validarSesion($sesionDispositivo1->tokenSesion);
    $validaDispositivo2 = $authServicio->validarSesion($sesionDispositivo2->tokenSesion);

    afirmar(
        count($sesionesActivas) === 2
        && $validaDispositivo1 !== null
        && $validaDispositivo2 !== null
        && $sesionDispositivo1->tokenSesion !== $sesionDispositivo2->tokenSesion,
        '17. Concurrencia: Múltiples sesiones activas simultáneas del mismo usuario sin interferencia'
    );

    // --- 18. CSRF Válido ---
    $tokenCsrf = ProtectorCsrf::generarToken();
    $csrfValido = ProtectorCsrf::validarToken($tokenCsrf, $tokenCsrf);
    afirmar(
        strlen($tokenCsrf) === 64
        && $csrfValido === true,
        '18. Token CSRF criptográficamente seguro validado exitosamente en tiempo constante'
    );

    // --- 19. CSRF Inválido ---
    $csrfInvalido = ProtectorCsrf::validarToken($tokenCsrf, 'token_manipulado_con_payload_falso');
    afirmar($csrfInvalido === false, '19. Token CSRF discrepante es rechazado de forma segura');

    // --- 20. CSRF Ausente ---
    $csrfAusente = ProtectorCsrf::validarToken($tokenCsrf, null);
    $csrfVacio = ProtectorCsrf::validarToken($tokenCsrf, '');
    afirmar($csrfAusente === false && $csrfVacio === false, '20. Token CSRF ausente o vacío es rechazado de forma segura');

    // --- 21. ContextoOperacion Humano Correcto ---
    $res21 = $authServicio->autenticar('usr_seguridad', $claveValida);
    $ctx21 = $res21->contexto;
    afirmar(
        $ctx21 !== null
        && $ctx21->actorTipo === 'HUMANO'
        && $ctx21->usuarioId === $usuarioId1
        && $ctx21->actorSistemaId === null
        && $ctx21->actorSistemaCodigo === null,
        '21. Invariantes del Contexto de Operación Humano garantizadas (HUMANO + usuario_id + actor_sistema NULL)'
    );

    // --- 22. Canal APP Correcto ---
    afirmar(
        $ctx21->canalId === 1 && $ctx21->canalCodigo === 'APP',
        '22. Canal de autenticación correctamente asignado a canal canónico APP (id=1)'
    );

    // --- 23. Correlacion_id Correcto (Aceptación Segura o Generación UUID v4) ---
    $corrValidoCandidato = 'corr-f11b-valido-12345678';
    $ctxCorrValido = ContextoOperacion::paraHumano($usuarioId1, 1, 'APP', '127.0.0.1', null, $orgId, $corrValidoCandidato);
    $ctxCorrInvalido = ContextoOperacion::paraHumano($usuarioId1, 1, 'APP', '127.0.0.1', null, $orgId, 'invalido<script>');
    afirmar(
        $ctxCorrValido->correlacionId === $corrValidoCandidato
        && $ctxCorrInvalido->correlacionId !== 'invalido<script>'
        && strlen($ctxCorrInvalido->correlacionId) === 36, // UUID v4 tiene 36 caracteres
        '23. Ciclo seguro de correlación HTTP: propagación de identificadores válidos y generación UUID v4 ante inválidos'
    );

    // --- 24. Secretos Ausentes de Auditoría / Logs ---
    $registrosAuditoria = $pdo->query("SELECT * FROM `auditoria_operaciones` WHERE `organizacion_id` = {$orgId}")->fetchAll(PDO::FETCH_ASSOC);
    $secretosDetectados = 0;
    foreach ($registrosAuditoria as $reg) {
        $texto = json_encode($reg);
        if (
            str_contains($texto, $claveValida)
            || str_contains($texto, 'PasswordTotalmenteErronea!')
            || str_contains($texto, $hashValido)
        ) {
            $secretosDetectados++;
        }
    }
    afirmar(
        $secretosDetectados === 0 && count($registrosAuditoria) > 0,
        "24. Secretos ausentes: 0 contraseñas o credenciales en texto plano detectadas en {$secretosDetectados} de " . count($registrosAuditoria) . " eventos auditados"
    );

    // --- 25. AutenticacionMiddleware (Cookie, Bearer y Contexto Transversal) ---
    $middleware = new \Nucleo\Http\Middleware\AutenticacionMiddleware($authServicio);
    $res25 = $authServicio->autenticar('usr_seguridad', $claveValida);
    $token25 = $res25->tokenSesion;

    // Simulación A: Extracción desde Cookie
    $nombreCookie = ConfiguracionSeguridad::nombreCookie();
    $ctxDesdeCookie = $middleware->procesar([], [$nombreCookie => $token25], false);
    $ctxActualA = ContextoOperacion::actual();

    // Simulación B: Extracción desde Bearer Token
    $ctxDesdeBearer = $middleware->procesar([
        'HTTP_AUTHORIZATION' => "Bearer {$token25}",
        'HTTP_X_CORRELATION_ID' => 'corr-bearer-middleware-test'
    ], [], false);
    $ctxActualB = ContextoOperacion::actual();

    afirmar(
        $ctxDesdeCookie !== null
        && $ctxActualA?->usuarioId === $usuarioId1
        && $ctxDesdeBearer !== null
        && $ctxActualB?->correlacionId === 'corr-bearer-middleware-test',
        '25. AutenticacionMiddleware resuelve sesión por Cookie o Bearer e hidrata ContextoOperacion::actual()'
    );

} catch (Throwable $e) {
    $fallos++;
    echo "\n [ERROR EXCEPCIÓN] " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
} finally {
    $pdo->rollBack();
    echo "\nTransacción de prueba revertida (ROLLBACK). Base de datos app_candelaria íntegra y libre de residuos.\n";
}

echo "\n==============================================================================\n";
echo "RESULTADO FINAL F1.1B: {$exitos} PRUEBAS EXITOSAS / {$fallos} FALLOS\n";
echo "==============================================================================\n";

if ($fallos > 0) {
    exit(1);
}
