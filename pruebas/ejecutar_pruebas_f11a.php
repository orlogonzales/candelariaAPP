<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
\Nucleo\Soporte\CargadorEntorno::cargar(__DIR__ . '/../.env');
require __DIR__ . '/../nucleo/Soporte/ayudantes.php';

use Aplicacion\Entidades\Persona;
use Aplicacion\Entidades\Usuario;
use Aplicacion\Repositorios\AuditoriaRepositorio;
use Aplicacion\Repositorios\PersonaRepositorio;
use Aplicacion\Repositorios\UsuarioRepositorio;
use Nucleo\BaseDatos\Conexion;
use Nucleo\Http\ContextoOperacion;

echo "==============================================================================\n";
echo "CANDELARIAAPP — SUITE DE PRUEBAS DE INTEGRACIÓN F1.1A\n";
echo "MODELO DE IDENTIDAD, USUARIOS, ACTORES, CANALES Y AUDITORÍA\n";
echo "==============================================================================\n\n";

$pdo = Conexion::obtenerInstancia();
$pdo->beginTransaction();

$fallos = 0;
$exitos = 0;

function afirmar(bool $condicion, string $descripcion, string &$detalles = '') : void {
    global $fallos, $exitos;
    if ($condicion) {
        $exitos++;
        echo " [PASS] {$descripcion}\n";
    } else {
        $fallos++;
        echo " [FAIL] {$descripcion} -> {$detalles}\n";
    }
}

try {
    // 0. Preparar Organización de Prueba Temporal
    $pdo->exec("INSERT INTO `organizaciones` (`id`, `codigo`, `nombre_comercial`, `estado`) VALUES (9999, 'test_tenant', 'ORGANIZACIÓN DE PRUEBA F1.1A', 'ACTIVO')");
    $orgId = 9999;

    $personaRepo = new PersonaRepositorio($pdo);
    $usuarioRepo = new UsuarioRepositorio($pdo);
    $auditoriaRepo = new AuditoriaRepositorio($pdo);

    // 1. Persona Natural Válida
    $pNatural = new Persona(
        id: null,
        organizacionId: $orgId,
        tipoPersona: 'NATURAL',
        tipoDocumentoId: 1, // DNI
        numeroDocumento: '70123456',
        nombres: 'Carlos Alberto',
        apellidos: 'Mamani Quispe',
        correoElectronico: 'carlos.mamani@test.com',
        telefonoWhatsapp: '+51951234567',
        ciudad: 'Juliaca',
        codigoPais: 'PE'
    );
    $pNaturalId = $personaRepo->crear($pNatural);
    $pNaturalRec = $personaRepo->buscarPorId($pNaturalId);
    afirmar(
        $pNaturalRec !== null && $pNaturalRec->nombres === 'CARLOS ALBERTO' && $pNaturalRec->apellidos === 'MAMANI QUISPE' && $pNaturalRec->ciudad === 'JULIACA',
        'Persona Natural válida se crea y normaliza a MAYÚSCULAS sin default hardcodeado'
    );

    // 2. Persona Natural Inválida (debe fallar si tiene razón social)
    $invalidaDetectada = false;
    try {
        new Persona(
            id: null,
            organizacionId: $orgId,
            tipoPersona: 'NATURAL',
            tipoDocumentoId: 1,
            numeroDocumento: '70123457',
            nombres: 'Juan',
            apellidos: 'Perez',
            razonSocial: 'EMPRESA INVENTADA SAC'
        );
    } catch (\InvalidArgumentException $e) {
        $invalidaDetectada = true;
    }
    afirmar($invalidaDetectada, 'Persona Natural con razón social es bloqueada por invariante de entidad');

    // 3. Persona Jurídica Válida
    $pJuridica = new Persona(
        id: null,
        organizacionId: $orgId,
        tipoPersona: 'JURIDICA',
        tipoDocumentoId: 2, // RUC
        numeroDocumento: '20601234567',
        razonSocial: 'Estudio Audiovisual del Altiplano SAC',
        nombreComercial: 'Altiplano Films',
        correoElectronico: 'contacto@altiplanofilms.pe',
        ciudad: 'Arequipa',
        codigoPais: 'PE'
    );
    $pJuridicaId = $personaRepo->crear($pJuridica);
    $pJuridicaRec = $personaRepo->buscarPorId($pJuridicaId);
    afirmar(
        $pJuridicaRec !== null && $pJuridicaRec->razonSocial === 'ESTUDIO AUDIOVISUAL DEL ALTIPLANO SAC' && $pJuridicaRec->nombres === null,
        'Persona Jurídica válida se crea con razón social y nombres estrictamente NULL'
    );

    // 4. Persona Jurídica Inválida (debe fallar si tiene nombres)
    $juridicaInvalida = false;
    try {
        new Persona(
            id: null,
            organizacionId: $orgId,
            tipoPersona: 'JURIDICA',
            tipoDocumentoId: 2,
            numeroDocumento: '20601234568',
            razonSocial: 'Inversiones SAC',
            nombres: 'Pedro'
        );
    } catch (\InvalidArgumentException $e) {
        $juridicaInvalida = true;
    }
    afirmar($juridicaInvalida, 'Persona Jurídica con nombres personales es bloqueada por invariante de entidad');

    // 5. Persona sin Usuario (Identidad independiente)
    afirmar($usuarioRepo->buscarPorPersonaId($pNaturalId) === null, 'Persona existe de forma independiente sin requerir usuario');

    // 6. Usuario vinculado a Persona con password_verify nativo
    $passwordPlana = 'Candelaria2026@Segura!';
    $hash = password_hash($passwordPlana, PASSWORD_DEFAULT);
    $usuario = new Usuario(
        id: null,
        organizacionId: $orgId,
        personaId: $pNaturalId,
        nombreUsuario: 'cmamani',
        nombreCompleto: $pNaturalRec->obtenerNombreCompleto(),
        correoElectronico: 'cmamani@candelaria.pe',
        contrasenaHash: $hash
    );
    $usuarioId = $usuarioRepo->crear($usuario);
    $usuarioRec = $usuarioRepo->buscarPorId($usuarioId);

    $loginValido = $usuarioRec->verificarContrasena($passwordPlana);
    $loginInvalido = !$usuarioRec->verificarContrasena('ContraseñaEquivocada123');
    $rehashCheck = !$usuarioRec->necesitaRehash();

    afirmar(
        $usuarioRec !== null && $loginValido && $loginInvalido && $rehashCheck,
        'Usuario vinculado a Persona verifica contraseña nativamente con password_verify() y password_needs_rehash()'
    );

    // 7. Unicidad de Persona en Usuarios (1 persona -> máximo 1 usuario)
    $duplicadoPersonaBloqueado = false;
    try {
        $usuarioDuplicado = new Usuario(
            id: null,
            organizacionId: $orgId,
            personaId: $pNaturalId, // Mismo ID de persona
            nombreUsuario: 'cmamani2',
            nombreCompleto: 'Otro Usuario',
            correoElectronico: 'cmamani2@candelaria.pe',
            contrasenaHash: $hash
        );
        $usuarioRepo->crear($usuarioDuplicado);
    } catch (\PDOException $e) {
        $duplicadoPersonaBloqueado = str_contains($e->getMessage(), 'uk_usuarios_persona') || $e->getCode() == 23000;
    }
    afirmar($duplicadoPersonaBloqueado, 'Unicidad 1 Persona -> 0..1 Usuario garantizada por restricción uk_usuarios_persona');

    // 8. Intentos fallidos y bloqueo temporal
    for ($i = 1; $i <= 5; $i++) {
        $usuarioRepo->registrarIntentoFallido($usuarioId, 5, 15);
    }
    $usuarioBloqueado = $usuarioRepo->buscarPorId($usuarioId);
    $estaBloqueado = $usuarioBloqueado->estaBloqueado();
    $usuarioRepo->restablecerIntentosFallidos($usuarioId);
    $usuarioDesbloqueado = $usuarioRepo->buscarPorId($usuarioId);

    afirmar(
        $estaBloqueado && $usuarioDesbloqueado->estaActivo(),
        'Políticas de seguridad: 5 intentos fallidos activan bloqueo temporal y restablecimiento opera correctamente'
    );

    // 9. Actor Humano Válido en Auditoría
    $ctxHumano = ContextoOperacion::paraHumano(
        usuarioId: $usuarioId,
        canalId: 1, // APP
        canalCodigo: 'APP',
        origenIp: '192.168.1.50',
        agenteUsuario: 'Mozilla/5.0 Chrome',
        organizacionId: $orgId
    );
    $auditHumanoId = $auditoriaRepo->registrar(
        contexto: $ctxHumano,
        modulo: 'usuarios',
        accion: 'CREAR',
        entidadTipo: 'usuario',
        entidadId: (string) $usuarioId,
        datosPrevios: null,
        datosNuevos: ['nombre_usuario' => 'cmamani', 'contrasena' => 'Secreto123', 'rol' => 'ADMINISTRADOR']
    );
    afirmar($auditHumanoId > 0, 'Auditoría con Actor Humano válido registrada exitosamente');

    // 10. Actor Sistema Válido en Auditoría
    $ctxSistema = ContextoOperacion::paraSistema(
        actorSistemaId: 1, // LANDING_CANDELARIA
        actorSistemaCodigo: 'LANDING_CANDELARIA',
        canalId: 2, // WEB
        canalCodigo: 'WEB',
        origenIp: '190.235.10.20',
        organizacionId: $orgId
    );
    $auditSistemaId = $auditoriaRepo->registrar(
        contexto: $ctxSistema,
        modulo: 'crm_prospectos',
        accion: 'RESERVAR_ONLINE',
        entidadTipo: 'reserva',
        entidadId: 'RES-001',
        datosPrevios: null,
        datosNuevos: ['paquete' => 'FOTOGRAFIA_ORO', 'origen' => 'landing_v1']
    );
    afirmar($auditSistemaId > 0, 'Auditoría con Actor Sistema válido (LANDING_CANDELARIA / WEB) registrada exitosamente');

    // 11. Estados contradictorios bloqueados por Contexto y CHECK constraint
    $contradictorio1 = false;
    try {
        new ContextoOperacion(
            actorTipo: 'HUMANO',
            usuarioId: $usuarioId,
            actorSistemaId: 1, // CONTRADICCIÓN: Humano con actor_sistema
            actorSistemaCodigo: 'LANDING',
            canalId: 1,
            canalCodigo: 'APP',
            correlacionId: ContextoOperacion::generarCorrelacionId()
        );
    } catch (\InvalidArgumentException $e) {
        $contradictorio1 = true;
    }
    afirmar($contradictorio1, 'Estado contradictorio (HUMANO + actor_sistema) bloqueado por invariante de Contexto');

    $contradictorio2 = false;
    try {
        new ContextoOperacion(
            actorTipo: 'SISTEMA',
            usuarioId: $usuarioId, // CONTRADICCIÓN: Sistema con usuario
            actorSistemaId: 1,
            actorSistemaCodigo: 'LANDING',
            canalId: 2,
            canalCodigo: 'WEB',
            correlacionId: ContextoOperacion::generarCorrelacionId()
        );
    } catch (\InvalidArgumentException $e) {
        $contradictorio2 = true;
    }
    afirmar($contradictorio2, 'Estado contradictorio (SISTEMA + usuario_id) bloqueado por invariante de Contexto');

    // Prueba directa contra el CHECK constraint de base de datos `chk_auditoria_actor`
    $checkDbBloqueado = false;
    try {
        $pdo->exec("INSERT INTO `auditoria_operaciones` (
                        `organizacion_id`, `actor_tipo`, `usuario_id`, `actor_sistema_id`,
                        `canal_id`, `correlacion_id`, `modulo`, `accion`,
                        `entidad_tipo`, `entidad_id`
                    ) VALUES (
                        {$orgId}, 'HUMANO', {$usuarioId}, 1,
                        1, 'test-correlacion-check-violacion', 'test', 'TEST',
                        'test', '1'
                    )");
    } catch (\PDOException $e) {
        $checkDbBloqueado = str_contains($e->getMessage(), 'chk_auditoria_actor') || $e->getCode() == 3819 || $e->getCode() == 23000;
    }
    afirmar($checkDbBloqueado, 'Restricción CHECK chk_auditoria_actor de MySQL rechaza estructuralmente estados contradictorios');

    // 12. Sanitización automática de secretos en auditoría
    $eventos = $auditoriaRepo->buscarPorCorrelacion($ctxHumano->correlacionId);
    $datosNuevosAudit = json_decode($eventos[0]['datos_nuevos_json'], true);
    $secretoCensurado = isset($datosNuevosAudit['contrasena']) && $datosNuevosAudit['contrasena'] === '[REDACTADO]';
    afirmar($secretoCensurado, 'Sanitización automática en Auditoría: contraseñas y secretos censurados a [REDACTADO]');

    // 13. Inmutabilidad Append-Only
    $metodosAuditoria = get_class_methods(AuditoriaRepositorio::class);
    $inmutable = !in_array('actualizar', $metodosAuditoria) &&
                 !in_array('modificar', $metodosAuditoria) &&
                 !in_array('eliminar', $metodosAuditoria) &&
                 !in_array('borrar', $metodosAuditoria);
    afirmar($inmutable, 'AuditoriaRepositorio es inmutable (Append-Only): 0 métodos de modificación o eliminación');

} finally {
    // Rollback para no dejar datos residuales de prueba en app_candelaria
    $pdo->rollBack();
    echo "\nTransacción de prueba revertida (ROLLBACK). Base de datos app_candelaria libre de datos de prueba.\n";
}

echo "\n==============================================================================\n";
echo "RESULTADO FINAL: {$exitos} PRUEBAS EXITOSAS / {$fallos} FALLOS\n";
echo "==============================================================================\n";

if ($fallos > 0) {
    exit(1);
}
