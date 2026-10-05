<?php

declare(strict_types=1);

namespace Pruebas;

require __DIR__ . '/../vendor/autoload.php';
\Nucleo\Soporte\CargadorEntorno::cargar(__DIR__ . '/../.env');
require __DIR__ . '/../nucleo/Soporte/ayudantes.php';

use Aplicacion\Autorizacion\AutorizacionServicio;
use Aplicacion\Clientes\ClienteServicio;
use Aplicacion\Configuracion\ConfiguracionServicio;
use Aplicacion\Crm\DireccionInteraccion;
use Aplicacion\Crm\EtapaOportunidad;
use Aplicacion\Crm\InteraccionServicio;
use Aplicacion\Crm\MotivoPerdida;
use Aplicacion\Crm\OportunidadServicio;
use Aplicacion\Crm\OrigenComercialServicio;
use Aplicacion\Crm\TipoInteraccion;
use Aplicacion\Entidades\Cliente;
use Aplicacion\Entidades\Persona;
use Aplicacion\Entidades\Usuario;
use Aplicacion\Excepciones\AccesoDenegadoExcepcion;
use Aplicacion\Excepciones\ConflictoConcurrenciaExcepcion;
use Aplicacion\Repositorios\AuditoriaRepositorio;
use Aplicacion\Repositorios\ClienteRepositorio;
use Aplicacion\Repositorios\EdicionRepositorio;
use Aplicacion\Repositorios\HistorialEtapaRepositorio;
use Aplicacion\Repositorios\InteraccionCrmRepositorio;
use Aplicacion\Repositorios\OportunidadRepositorio;
use Aplicacion\Repositorios\OrganizacionRepositorio;
use Aplicacion\Repositorios\OrigenComercialRepositorio;
use Aplicacion\Repositorios\PermisoRepositorio;
use Aplicacion\Repositorios\PersonaRepositorio;
use Aplicacion\Repositorios\RolRepositorio;
use Aplicacion\Repositorios\UsuarioRepositorio;
use InvalidArgumentException;
use Nucleo\BaseDatos\Conexion;
use Nucleo\Http\ContextoOperacion;
use PDO;

echo "==============================================================================\n";
echo "CANDELARIAAPP — SUITE DE PRUEBAS F2.2B: NÚCLEO CRM, PIPELINE E INTERACCIONES\n";
echo "CERTIFICACIÓN DE OPORTUNIDADES, ETAPAS, ASIGNACIÓN, CONCURRENCIA Y ACTOR SOBERANO\n";
echo "==============================================================================\n\n";

$pdo = Conexion::obtenerInstancia();

$fallos = 0;
$exitos = 0;

function afirmar(bool $condicion, string $descripcion, string $detalles = ''): void
{
    global $fallos, $exitos;
    if ($condicion) {
        $exitos++;
        echo " [PASS] " . $descripcion . "\n";
    } else {
        $fallos++;
        echo " [FAIL] " . $descripcion . ($detalles ? " -> Detalle: " . $detalles : "") . "\n";
    }
}

// Repositorios transversales
$auditoriaRepo    = new AuditoriaRepositorio($pdo);
$authzServicio    = new AutorizacionServicio(pdo: $pdo);
$configServicio   = new ConfiguracionServicio(auditoriaRepo: $auditoriaRepo, pdo: $pdo);
$clienteRepo      = new ClienteRepositorio($pdo);
$personaRepo      = new PersonaRepositorio($pdo);
$edicionRepo      = new EdicionRepositorio($pdo);
$usuarioRepo      = new UsuarioRepositorio($pdo);
$origenRepo       = new OrigenComercialRepositorio($pdo);
$oportunidadRepo  = new OportunidadRepositorio($pdo);
$historialRepo    = new HistorialEtapaRepositorio($pdo);
$interaccionRepo  = new InteraccionCrmRepositorio($pdo);

// Servicios de dominio
$oportunidadServicio = new OportunidadServicio(
    oportunidadRepo: $oportunidadRepo,
    historialRepo: $historialRepo,
    clienteRepo: $clienteRepo,
    edicionRepo: $edicionRepo,
    origenRepo: $origenRepo,
    usuarioRepo: $usuarioRepo,
    authzServicio: $authzServicio,
    auditoriaRepo: $auditoriaRepo,
    configServicio: $configServicio,
    pdo: $pdo
);

$interaccionServicio = new InteraccionServicio(
    interaccionRepo: $interaccionRepo,
    clienteRepo: $clienteRepo,
    oportunidadRepo: $oportunidadRepo,
    authzServicio: $authzServicio,
    auditoriaRepo: $auditoriaRepo,
    pdo: $pdo
);

$origenServicio = new OrigenComercialServicio(
    origenRepo: $origenRepo,
    authzServicio: $authzServicio,
    auditoriaRepo: $auditoriaRepo,
    pdo: $pdo
);

// ==============================================================================
// BLOQUE 1: ESQUEMA RELACIONAL Y PARIDAD DE INSTALACIÓN LIMPIA
// ==============================================================================
echo "\n--- BLOQUE 1: ESQUEMA RELACIONAL Y PARIDAD DE INSTALACIÓN LIMPIA ---\n";

// 1.1 Columnas origenes_comerciales
$stmtColsOri = $pdo->query("SHOW COLUMNS FROM `origenes_comerciales`");
$colsOri = $stmtColsOri->fetchAll(PDO::FETCH_COLUMN);
$colsEsperadasOri = ['id', 'organizacion_id', 'codigo', 'nombre', 'descripcion', 'activo', 'orden', 'creado_en', 'actualizado_en'];
$diffOri = array_diff($colsEsperadasOri, $colsOri);
afirmar(empty($diffOri) && count($colsOri) === 9, "1.1: Tabla 'origenes_comerciales' contiene exactamente las 9 columnas oficiales");

// 1.2 Columnas crm_oportunidades
$stmtColsOp = $pdo->query("SHOW COLUMNS FROM `crm_oportunidades`");
$colsOp = $stmtColsOp->fetchAll(PDO::FETCH_COLUMN);
$colsEsperadasOp = [
    'id', 'organizacion_id', 'edicion_id', 'cliente_id', 'usuario_asignado_id',
    'origen_comercial_id', 'titulo', 'etapa', 'valor_estimado', 'moneda',
    'proximo_seguimiento_en', 'motivo_perdida', 'motivo_perdida_detalle',
    'notas', 'version_bloqueo', 'creado_en', 'actualizado_en'
];
$diffOp = array_diff($colsEsperadasOp, $colsOp);
afirmar(empty($diffOp) && count($colsOp) === 17, "1.2: Tabla 'crm_oportunidades' contiene exactamente las 17 columnas oficiales");

// 1.3 Columnas crm_oportunidad_historial_etapas
$stmtColsHist = $pdo->query("SHOW COLUMNS FROM `crm_oportunidad_historial_etapas`");
$colsHist = $stmtColsHist->fetchAll(PDO::FETCH_COLUMN);
$colsEsperadasHist = [
    'id', 'organizacion_id', 'oportunidad_id', 'etapa_anterior', 'etapa_nueva',
    'motivo', 'actor_tipo', 'usuario_id', 'actor_sistema_id', 'correlacion_id', 'creado_en'
];
$diffHist = array_diff($colsEsperadasHist, $colsHist);
afirmar(empty($diffHist) && count($colsHist) === 11, "1.3: Tabla 'crm_oportunidad_historial_etapas' contiene exactamente las 11 columnas oficiales");

// 1.4 Columnas crm_interacciones
$stmtColsInt = $pdo->query("SHOW COLUMNS FROM `crm_interacciones`");
$colsInt = $stmtColsInt->fetchAll(PDO::FETCH_COLUMN);
$colsEsperadasInt = [
    'id', 'organizacion_id', 'cliente_id', 'oportunidad_id', 'canal_id',
    'tipo', 'direccion', 'resumen', 'detalle', 'actor_tipo', 'usuario_id',
    'actor_sistema_id', 'correlacion_id', 'creado_en'
];
$diffInt = array_diff($colsEsperadasInt, $colsInt);
afirmar(empty($diffInt) && count($colsInt) === 14, "1.4: Tabla 'crm_interacciones' contiene exactamente las 14 columnas oficiales");

// 1.5 Saneamiento verificado: clientes.origen_captacion ausente
$colOrigenCaptacion = $pdo->query("SHOW COLUMNS FROM `clientes` LIKE 'origen_captacion'")->fetch();
afirmar(!$colOrigenCaptacion, "1.5: Saneamiento verificado: 'origen_captacion' eliminado de 'clientes'");

// 1.6 Permisos RBAC módulo CRM
$permisosEsperados = [
    'crm.oportunidades.ver',
    'crm.oportunidades.crear',
    'crm.oportunidades.editar',
    'crm.oportunidades.cambiar_etapa',
    'crm.oportunidades.asignar',
    'crm.interacciones.ver',
    'crm.interacciones.crear',
];
$stmtPerms = $pdo->query("SELECT codigo FROM permisos WHERE codigo LIKE 'crm.%'");
$permsEnBd = $stmtPerms->fetchAll(PDO::FETCH_COLUMN);
$diffPerms = array_diff($permisosEsperados, $permsEnBd);
afirmar(empty($diffPerms) && count($permsEnBd) === 7, "1.6: Los 7 nuevos permisos RBAC 'crm.*' registrados en la BD");

// 1.7 Clean install test en base de datos temporal
$dbTemp = 'candelaria_test_clean_f22b_' . time();
$pdo->exec("CREATE DATABASE `{$dbTemp}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
try {
    $driver = (string) entorno('BD_DRIVER', 'mysql');
    $host = (string) entorno('BD_HOST', '127.0.0.1');
    $puerto = (int) entorno('BD_PUERTO', 3306);
    $usuario = (string) entorno('BD_USUARIO', 'root');
    $contrasena = (string) entorno('BD_CONTRASENA', '');
    $charset = (string) entorno('BD_CHARSET', 'utf8mb4');
    $colate = (string) entorno('BD_COLATE', 'utf8mb4_unicode_ci');

    $pdoTemp = new PDO(
        "{$driver}:host={$host};port={$puerto};dbname={$dbTemp};charset={$charset}",
        $usuario,
        $contrasena,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES '{$charset}' COLLATE '{$colate}'",
        ]
    );

    $sqlEsquema = file_get_contents(__DIR__ . '/../base_datos/esquema/esquema_base.sql');
    $pdoTemp->exec($sqlEsquema);
    $tablasTemp = $pdoTemp->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

    afirmar(in_array('origenes_comerciales', $tablasTemp, true), "1.7: Instalación limpia incluye tabla 'origenes_comerciales'");
    afirmar(in_array('crm_oportunidades', $tablasTemp, true), "1.8: Instalación limpia incluye tabla 'crm_oportunidades'");
    afirmar(in_array('crm_oportunidad_historial_etapas', $tablasTemp, true), "1.9: Instalación limpia incluye tabla 'crm_oportunidad_historial_etapas'");
    afirmar(in_array('crm_interacciones', $tablasTemp, true), "1.10: Instalación limpia incluye tabla 'crm_interacciones'");
    afirmar(count($tablasTemp) === 25, "1.11: Instalación limpia de 'esquema_base.sql' crea las 25 tablas canónicas completas");
} finally {
    $pdo->exec("DROP DATABASE IF EXISTS `{$dbTemp}`");
}

// Iniciar transacción de prueba con rollback determinista
$pdo->beginTransaction();

try {
    // Preparar contextos de prueba
    $ctxOrg1 = new ContextoOperacion(
        actorTipo: 'HUMANO',
        usuarioId: 24, // Orlando superadmin
        actorSistemaId: null,
        actorSistemaCodigo: null,
        canalId: 1, // APP
        canalCodigo: 'APP',
        correlacionId: ContextoOperacion::generarCorrelacionId(),
        origenIp: '127.0.0.1',
        agenteUsuario: 'TestRunner-F22B',
        organizacionId: 10000
    );

    $ctxOrg2 = new ContextoOperacion(
        actorTipo: 'HUMANO',
        usuarioId: 24,
        actorSistemaId: null,
        actorSistemaCodigo: null,
        canalId: 1,
        canalCodigo: 'APP',
        correlacionId: ContextoOperacion::generarCorrelacionId(),
        origenIp: '127.0.0.1',
        agenteUsuario: 'TestRunner-F22B',
        organizacionId: 20000
    );

    $ctxSistema = new ContextoOperacion(
        actorTipo: 'SISTEMA',
        usuarioId: null,
        actorSistemaId: 1, // LANDING_CANDELARIA
        actorSistemaCodigo: 'LANDING_CANDELARIA',
        canalId: 2, // WEB
        canalCodigo: 'WEB',
        correlacionId: ContextoOperacion::generarCorrelacionId(),
        origenIp: '127.0.0.1',
        agenteUsuario: 'LandingBot',
        organizacionId: 10000
    );

    // Contexto activo por defecto
    ContextoOperacion::establecerActual($ctxOrg1);

    // Preparar Organización 20000 para pruebas de Anti-IDOR y multi-tenant
    $stmtOrg2 = $pdo->prepare("INSERT INTO `organizaciones` (`id`, `codigo`, `nombre_comercial`, `estado`) VALUES (20000, 'tenant-dos-crm', 'Tenant Dos CRM', 'ACTIVO') ON DUPLICATE KEY UPDATE `estado` = 'ACTIVO'");
    $stmtOrg2->execute();

    // Crear Edición en Org 10000 y Edición en Org 20000
    $stmtEd1 = $pdo->prepare("INSERT INTO `ediciones_candelaria` (`id`, `organizacion_id`, `codigo`, `nombre`, `anio`, `estado`, `fecha_inicio`, `fecha_fin`, `es_actual`) VALUES (91001, 10000, 'ed-f22b-2027', 'FESTIVIDAD CANDELARIA 2027', 2027, 'OPERACION', '2027-02-01', '2027-02-15', 1) ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`)");
    $stmtEd1->execute();

    $stmtEd2 = $pdo->prepare("INSERT INTO `ediciones_candelaria` (`id`, `organizacion_id`, `codigo`, `nombre`, `anio`, `estado`, `fecha_inicio`, `fecha_fin`, `es_actual`) VALUES (91002, 20000, 'ed-f22b-org2', 'CANDELARIA ORG 2', 2027, 'OPERACION', '2027-02-01', '2027-02-15', 1) ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`)");
    $stmtEd2->execute();

    // Crear Personas y Clientes para Org 10000 y Org 20000
    $stmtPer1 = $pdo->prepare("INSERT INTO `personas` (`id`, `organizacion_id`, `tipo_persona`, `nombres`, `apellidos`, `telefono_whatsapp`, `codigo_pais`, `estado`) VALUES (81001, 10000, 'NATURAL', 'CARLOS', 'MAMANI', '+51951111222', 'PE', 'ACTIVO') ON DUPLICATE KEY UPDATE `telefono_whatsapp` = VALUES(`telefono_whatsapp`)");
    $stmtPer1->execute();

    $stmtCli1 = $pdo->prepare("INSERT INTO `clientes` (`id`, `organizacion_id`, `persona_id`, `estado_comercial`) VALUES (71001, 10000, 81001, 'PROSPECTO') ON DUPLICATE KEY UPDATE `estado_comercial` = 'PROSPECTO'");
    $stmtCli1->execute();

    $stmtPer2 = $pdo->prepare("INSERT INTO `personas` (`id`, `organizacion_id`, `tipo_persona`, `nombres`, `apellidos`, `telefono_whatsapp`, `codigo_pais`, `estado`) VALUES (81002, 20000, 'NATURAL', 'JUAN', 'PEREZ', '+51952222333', 'PE', 'ACTIVO') ON DUPLICATE KEY UPDATE `telefono_whatsapp` = VALUES(`telefono_whatsapp`)");
    $stmtPer2->execute();

    // Personas para asesores y usuarios de prueba
    $stmtPerInact = $pdo->prepare("INSERT INTO `personas` (`id`, `organizacion_id`, `tipo_persona`, `nombres`, `apellidos`, `telefono_whatsapp`, `codigo_pais`, `estado`) VALUES (81098, 10000, 'NATURAL', 'ASESOR', 'INACTIVO', '+51951999888', 'PE', 'ACTIVO') ON DUPLICATE KEY UPDATE `estado` = 'ACTIVO'");
    $stmtPerInact->execute();

    $stmtPerOp = $pdo->prepare("INSERT INTO `personas` (`id`, `organizacion_id`, `tipo_persona`, `nombres`, `apellidos`, `telefono_whatsapp`, `codigo_pais`, `estado`) VALUES (81099, 10000, 'NATURAL', 'OPERADOR', 'LECTURA', '+51951999777', 'PE', 'ACTIVO') ON DUPLICATE KEY UPDATE `estado` = 'ACTIVO'");
    $stmtPerOp->execute();

    // Crear un asesor activo y uno inactivo en Org 10000 con personas distintas
    $stmtAsesorActivo = $pdo->prepare("INSERT INTO `usuarios` (`id`, `organizacion_id`, `persona_id`, `nombre_usuario`, `nombre_completo`, `correo_electronico`, `telefono_whatsapp`, `contrasena_hash`, `estado`) VALUES (61001, 10000, 81001, 'asesor.activo', 'ASESOR COMERCIAL ACTIVO', 'asesor.activo@test.com', '+51951111222', '\$2y\$10\$abcdefghijklmnopqrstuu', 'ACTIVO') ON DUPLICATE KEY UPDATE `estado` = 'ACTIVO'");
    $stmtAsesorActivo->execute();

    $stmtAsesorInactivo = $pdo->prepare("INSERT INTO `usuarios` (`id`, `organizacion_id`, `persona_id`, `nombre_usuario`, `nombre_completo`, `correo_electronico`, `telefono_whatsapp`, `contrasena_hash`, `estado`) VALUES (61002, 10000, 81098, 'asesor.inactivo', 'ASESOR INACTIVO', 'asesor.inactivo@test.com', '+51951999888', '\$2y\$10\$abcdefghijklmnopqrstuu', 'INACTIVO') ON DUPLICATE KEY UPDATE `estado` = 'INACTIVO'");
    $stmtAsesorInactivo->execute();

    // Crear un asesor en Org 20000
    $stmtAsesorOrg2 = $pdo->prepare("INSERT INTO `usuarios` (`id`, `organizacion_id`, `persona_id`, `nombre_usuario`, `nombre_completo`, `correo_electronico`, `telefono_whatsapp`, `contrasena_hash`, `estado`) VALUES (61003, 20000, 81002, 'asesor.org2', 'ASESOR TENANT DOS', 'asesor.org2@test.com', '+51952222333', '\$2y\$10\$abcdefghijklmnopqrstuu', 'ACTIVO') ON DUPLICATE KEY UPDATE `estado` = 'ACTIVO'");
    $stmtAsesorOrg2->execute();

    // ==============================================================================
    // BLOQUE 2: CATÁLOGO DE ORÍGENES COMERCIALES POR TENANT
    // ==============================================================================
    echo "\n--- BLOQUE 2: CATÁLOGO DE ORÍGENES COMERCIALES POR TENANT ---\n";

    // 2.1 Semillas verificadas en Org 10000
    $origenesOrg1 = $origenRepo->listarPorOrganizacion(10000, soloActivos: true);
    afirmar(count($origenesOrg1) >= 8, "2.1: Tenant 10000 posee al menos las 8 semillas de orígenes comerciales");

    $origenWeb = $origenRepo->buscarPorCodigo(10000, 'WEB_ORGANICA');
    afirmar($origenWeb !== null && $origenWeb->activo, "2.2: Origen 'WEB_ORGANICA' existe y está activo");

    // 2.3 Crear origen personalizado
    $nuevoOrigen = $origenServicio->crear(
        organizacionId: 10000,
        codigo: 'TIKTOK_ADS',
        nombre: 'Campaña TikTok Ads Candelaria',
        descripcion: 'Prospectos captados vía TikTok Lead Ads',
        orden: 10,
        contexto: $ctxOrg1
    );
    afirmar($nuevoOrigen->id > 0 && $nuevoOrigen->codigo === 'TIKTOK_ADS', "2.3: Creación de nuevo origen comercial personalizado por organización");

    // 2.4 Código duplicado en misma organización rechazado
    $errorDuplicado = false;
    try {
        $origenServicio->crear(
            organizacionId: 10000,
            codigo: 'TIKTOK_ADS',
            nombre: 'Intento Duplicado',
            contexto: $ctxOrg1
        );
    } catch (InvalidArgumentException) {
        $errorDuplicado = true;
    }
    afirmar($errorDuplicado, "2.4: Código de origen duplicado en el mismo tenant es rechazado (uk_origenes_comerciales_org_codigo)");

    // 2.5 Mismo código en organización diferente es permitido
    $origenOrg2 = $origenServicio->crear(
        organizacionId: 20000,
        codigo: 'TIKTOK_ADS',
        nombre: 'TikTok Ads Org 2',
        contexto: $ctxOrg2
    );
    afirmar($origenOrg2->id > 0 && $origenOrg2->organizacionId === 20000, "2.5: Mismo código de origen comercial en tenant diferente es permitido (aislamiento multi-tenant)");

    // 2.6 Desactivar origen comercial
    $desactivadoOk = $origenServicio->desactivar(10000, $nuevoOrigen->id, $ctxOrg1);
    afirmar($desactivadoOk, "2.6: Desactivación exitosa de origen comercial");
    $origenVerif = $origenRepo->buscarPorId($nuevoOrigen->id);
    afirmar(!$origenVerif->activo, "2.7: Origen comercial persiste con activo = 0 (prohibición de borrado físico)");

    // ==============================================================================
    // BLOQUE 3: CREACIÓN DE OPORTUNIDADES, RESPONSABLE Y ANTI-IDOR
    // ==============================================================================
    echo "\n--- BLOQUE 3: CREACIÓN DE OPORTUNIDADES, RESPONSABLE Y ANTI-IDOR ---\n";

    // 3.1 Oportunidad sin responsable (cola general) y origen NULL
    $op1 = $oportunidadServicio->crear(
        organizacionId: 10000,
        edicionId: 91001,
        clienteId: 71001,
        titulo: 'Paquete VIP Candelaria 2027 Individual',
        usuarioAsignadoId: null,
        origenComercialId: null,
        valorEstimado: null,
        moneda: null, // Debe resolver snapshot institucional PEN
        contexto: $ctxOrg1
    );
    afirmar($op1->id > 0, "3.1: Creación exitosa de oportunidad con responsable NULL (cola general)");
    afirmar($op1->usuarioAsignadoId === null, "3.2: Oportunidad en cola general preserva usuario_asignado_id = NULL");
    afirmar($op1->origenComercialId === null, "3.3: Oportunidad sin origen asignado preserva origen_comercial_id = NULL");
    afirmar($op1->etapa === EtapaOportunidad::NUEVA, "3.4: Oportunidad nace en etapa inicial NUEVA por defecto");
    afirmar($op1->moneda === 'PEN', "3.5: Snapshot de divisa institucional 'PEN' resuelto y persistido");
    afirmar($op1->versionBloqueo === 1, "3.6: Versión de bloqueo optimista inicial es 1");

    // 3.2 Múltiples oportunidades para el mismo cliente en la misma edición (Aprobado en Dictamen A)
    $op2 = $oportunidadServicio->crear(
        organizacionId: 10000,
        edicionId: 91001,
        clienteId: 71001,
        titulo: 'Paquete Grupo Familiar 6 Personas Candelaria 2027',
        usuarioAsignadoId: 61001,
        origenComercialId: $origenWeb->id,
        valorEstimado: 8500.50,
        contexto: $ctxOrg1
    );
    afirmar($op2->id > 0 && $op2->id !== $op1->id, "3.7: Múltiples oportunidades simultáneas para el mismo cliente en la misma edición permitidas");
    afirmar($op2->clienteId === $op1->clienteId && $op2->edicionId === $op1->edicionId, "3.8: Ambas oportunidades coexisten legítimamente sin UNIQUE(cliente, edicion)");

    // 3.3 Anti-IDOR Cliente (intento de asociar cliente de otro tenant)
    $errorAntiIdorCliente = false;
    try {
        $oportunidadServicio->crear(
            organizacionId: 10000,
            edicionId: 91001,
            clienteId: 71002, // Cliente de Org 20000
            titulo: 'Intento IDOR Cliente',
            contexto: $ctxOrg1
        );
    } catch (AccesoDenegadoExcepcion) {
        $errorAntiIdorCliente = true;
    }
    afirmar($errorAntiIdorCliente, "3.9: Anti-IDOR: Intento de asociar cliente de otra organización es rechazado con AccesoDenegadoExcepcion");

    // 3.4 Anti-IDOR Edición (intento de asociar edición de otro tenant)
    $errorAntiIdorEdicion = false;
    try {
        $oportunidadServicio->crear(
            organizacionId: 10000,
            edicionId: 91002, // Edición de Org 20000
            clienteId: 71001,
            titulo: 'Intento IDOR Edición',
            contexto: $ctxOrg1
        );
    } catch (AccesoDenegadoExcepcion) {
        $errorAntiIdorEdicion = true;
    }
    afirmar($errorAntiIdorEdicion, "3.10: Anti-IDOR: Intento de asociar edición de otra organización es rechazado con AccesoDenegadoExcepcion");

    // 3.5 Asesor de otro tenant rechazado
    $errorAsesorOtroTenant = false;
    try {
        $oportunidadServicio->crear(
            organizacionId: 10000,
            edicionId: 91001,
            clienteId: 71001,
            titulo: 'Intento Asesor Otro Tenant',
            usuarioAsignadoId: 61003, // Usuario de Org 20000
            contexto: $ctxOrg1
        );
    } catch (AccesoDenegadoExcepcion) {
        $errorAsesorOtroTenant = true;
    }
    afirmar($errorAsesorOtroTenant, "3.11: Intento de asignar asesor de otro tenant es rechazado con AccesoDenegadoExcepcion");

    // 3.6 Asesor inactivo rechazado
    $errorAsesorInactivo = false;
    try {
        $oportunidadServicio->crear(
            organizacionId: 10000,
            edicionId: 91001,
            clienteId: 71001,
            titulo: 'Intento Asesor Inactivo',
            usuarioAsignadoId: 61002, // Usuario INACTIVO
            contexto: $ctxOrg1
        );
    } catch (InvalidArgumentException) {
        $errorAsesorInactivo = true;
    }
    afirmar($errorAsesorInactivo, "3.12: Intento de asignar usuario inactivo como responsable es rechazado con InvalidArgumentException");

    // 3.7 Origen comercial de otro tenant rechazado
    $errorOrigenOtroTenant = false;
    try {
        $oportunidadServicio->crear(
            organizacionId: 10000,
            edicionId: 91001,
            clienteId: 71001,
            titulo: 'Intento Origen Otro Tenant',
            origenComercialId: $origenOrg2->id, // Origen de Org 20000
            contexto: $ctxOrg1
        );
    } catch (AccesoDenegadoExcepcion) {
        $errorOrigenOtroTenant = true;
    }
    afirmar($errorOrigenOtroTenant, "3.13: Intento de asignar origen comercial de otro tenant es rechazado con AccesoDenegadoExcepcion");

    // 3.8 Origen comercial inactivo rechazado para nueva oportunidad
    $errorOrigenInactivo = false;
    try {
        $oportunidadServicio->crear(
            organizacionId: 10000,
            edicionId: 91001,
            clienteId: 71001,
            titulo: 'Intento Origen Inactivo',
            origenComercialId: $nuevoOrigen->id, // Origen desactivado en 2.6
            contexto: $ctxOrg1
        );
    } catch (InvalidArgumentException) {
        $errorOrigenInactivo = true;
    }
    afirmar($errorOrigenInactivo, "3.14: Intento de asignar origen comercial inactivo para nueva oportunidad es rechazado");

    // ==============================================================================
    // BLOQUE 4: VALOR ESTIMADO, MONEDA Y VALIDACIONES DE DATOS
    // ==============================================================================
    echo "\n--- BLOQUE 4: VALOR ESTIMADO, MONEDA Y VALIDACIONES DE DATOS ---\n";

    // 4.1 Valor estimado negativo rechazado
    $errorValorNegativo = false;
    try {
        $oportunidadServicio->crear(
            organizacionId: 10000,
            edicionId: 91001,
            clienteId: 71001,
            titulo: 'Intento Valor Negativo',
            valorEstimado: -150.00,
            contexto: $ctxOrg1
        );
    } catch (InvalidArgumentException) {
        $errorValorNegativo = true;
    }
    afirmar($errorValorNegativo, "4.1: Valor estimado negativo es rechazado por invariante de entidad");

    // 4.2 Moneda personalizada válida ISO 4217 (ej. USD)
    $opUsd = $oportunidadServicio->crear(
        organizacionId: 10000,
        edicionId: 91001,
        clienteId: 71001,
        titulo: 'Paquete Turistas Extranjeros USD',
        valorEstimado: 2500.00,
        moneda: 'USD',
        contexto: $ctxOrg1
    );
    afirmar($opUsd->moneda === 'USD', "4.2: Snapshot de divisa personalizada ISO 4217 ('USD') registrado exitosamente");

    // 4.3 Moneda inválida rechazada
    $errorMonedaInvalida = false;
    try {
        $oportunidadServicio->crear(
            organizacionId: 10000,
            edicionId: 91001,
            clienteId: 71001,
            titulo: 'Intento Moneda Inválida',
            moneda: 'SOLES',
            contexto: $ctxOrg1
        );
    } catch (InvalidArgumentException) {
        $errorMonedaInvalida = true;
    }
    afirmar($errorMonedaInvalida, "4.3: Código de moneda no ISO 4217 (longitud distinta a 3 letras) es rechazado");

    // ==============================================================================
    // BLOQUE 5: MÁQUINA DE ESTADOS Y PIPELINE DE OPORTUNIDADES
    // ==============================================================================
    echo "\n--- BLOQUE 5: MÁQUINA DE ESTADOS Y PIPELINE DE OPORTUNIDADES ---\n";

    // 5.1 Avance regular: NUEVA -> CONTACTADA
    $opAvanzada1 = $oportunidadServicio->cambiarEtapa(
        organizacionId: 10000,
        oportunidadId: $op1->id,
        nuevaEtapaStr: 'CONTACTADA',
        motivoCambio: 'Primer contacto telefónico establecido',
        contexto: $ctxOrg1
    );
    afirmar($opAvanzada1->etapa === EtapaOportunidad::CONTACTADA, "5.1: Transición legal hacia adelante: NUEVA -> CONTACTADA");

    // 5.2 Salto flexible hacia adelante: NUEVA -> COTIZACION (en opUsd)
    $opSaltada = $oportunidadServicio->cambiarEtapa(
        organizacionId: 10000,
        oportunidadId: $opUsd->id,
        nuevaEtapaStr: 'COTIZACION',
        motivoCambio: 'Cliente solicitó cotización directa inmediata',
        contexto: $ctxOrg1
    );
    afirmar($opSaltada->etapa === EtapaOportunidad::COTIZACION, "5.2: Salto flexible hacia adelante permitido: NUEVA -> COTIZACION");

    // 5.3 Salto: COTIZACION -> NEGOCIACION
    $opNegociacion = $oportunidadServicio->cambiarEtapa(
        organizacionId: 10000,
        oportunidadId: $opUsd->id,
        nuevaEtapaStr: 'NEGOCIACION',
        motivoCambio: 'Ajustando descuentos grupales',
        contexto: $ctxOrg1
    );
    afirmar($opNegociacion->etapa === EtapaOportunidad::NEGOCIACION, "5.3: Transición hacia adelante: COTIZACION -> NEGOCIACION");

    // 5.4 Cierre Exitoso: NEGOCIACION -> GANADA (terminal ordinaria)
    $opGanada = $oportunidadServicio->cambiarEtapa(
        organizacionId: 10000,
        oportunidadId: $opUsd->id,
        nuevaEtapaStr: 'GANADA',
        motivoCambio: 'Propuesta aceptada formalmente',
        contexto: $ctxOrg1
    );
    afirmar($opGanada->etapa === EtapaOportunidad::GANADA, "5.4: Transición a terminal positiva exitosa: NEGOCIACION -> GANADA");

    // 5.5 Invariante terminal: GANADA no puede cambiar a ninguna otra etapa
    $errorSalidaGanada = false;
    try {
        $oportunidadServicio->cambiarEtapa(
            organizacionId: 10000,
            oportunidadId: $opUsd->id,
            nuevaEtapaStr: 'COTIZACION',
            contexto: $ctxOrg1
        );
    } catch (InvalidArgumentException) {
        $errorSalidaGanada = true;
    }
    afirmar($errorSalidaGanada, "5.5: Invariante terminal: Oportunidad GANADA no puede transicionar a ninguna otra etapa");

    // 5.6 Prohibición de retroceso ordinario: CONTACTADA -> NUEVA en op1
    $errorRetroceso = false;
    try {
        $oportunidadServicio->cambiarEtapa(
            organizacionId: 10000,
            oportunidadId: $op1->id,
            nuevaEtapaStr: 'NUEVA',
            contexto: $ctxOrg1
        );
    } catch (InvalidArgumentException) {
        $errorRetroceso = true;
    }
    afirmar($errorRetroceso, "5.6: Retroceso ordinario en pipeline (CONTACTADA -> NUEVA) es rechazado");

    // 5.7 Cierre Negativo sin motivo rechazado obligatoriamente
    $errorPerdidaSinMotivo = false;
    try {
        $oportunidadServicio->cambiarEtapa(
            organizacionId: 10000,
            oportunidadId: $op1->id,
            nuevaEtapaStr: 'PERDIDA',
            motivoPerdidaStr: null,
            contexto: $ctxOrg1
        );
    } catch (InvalidArgumentException) {
        $errorPerdidaSinMotivo = true;
    }
    afirmar($errorPerdidaSinMotivo, "5.7: Cierre a etapa PERDIDA sin motivo de pérdida es rechazado obligatoriamente");

    // 5.8 Motivo OTRO sin detalle descriptivo rechazado
    $errorOtroSinDetalle = false;
    try {
        $oportunidadServicio->cambiarEtapa(
            organizacionId: 10000,
            oportunidadId: $op1->id,
            nuevaEtapaStr: 'PERDIDA',
            motivoPerdidaStr: 'OTRO',
            motivoPerdidaDetalle: null,
            contexto: $ctxOrg1
        );
    } catch (InvalidArgumentException) {
        $errorOtroSinDetalle = true;
    }
    afirmar($errorOtroSinDetalle, "5.8: Motivo de pérdida 'OTRO' sin detalle explicativo es rechazado obligatoriamente");

    // 5.9 Cierre Negativo con motivo válido exitoso
    $opPerdida = $oportunidadServicio->cambiarEtapa(
        organizacionId: 10000,
        oportunidadId: $op1->id,
        nuevaEtapaStr: 'PERDIDA',
        motivoPerdidaStr: 'DESISTIO_VIAJE',
        motivoPerdidaDetalle: 'Cliente canceló vacaciones por motivos laborales',
        motivoCambio: 'Cierre de oportunidad por desistimiento',
        contexto: $ctxOrg1
    );
    afirmar($opPerdida->etapa === EtapaOportunidad::PERDIDA, "5.9: Cierre a etapa PERDIDA con motivo válido exitoso");
    afirmar($opPerdida->motivoPerdida === MotivoPerdida::DESISTIO_VIAJE, "5.10: Motivo de pérdida tipado preservado correctamente");

    // 5.11 Invariante terminal: PERDIDA no puede cambiar a ninguna otra etapa
    $errorSalidaPerdida = false;
    try {
        $oportunidadServicio->cambiarEtapa(
            organizacionId: 10000,
            oportunidadId: $op1->id,
            nuevaEtapaStr: 'NUEVA',
            contexto: $ctxOrg1
        );
    } catch (InvalidArgumentException) {
        $errorSalidaPerdida = true;
    }
    afirmar($errorSalidaPerdida, "5.11: Invariante terminal: Oportunidad PERDIDA no permite reapertura ordinaria (debe crearse una nueva)");

    // ==============================================================================
    // BLOQUE 6: CONCURRENCIA OPTIMISTA REAL (VERSION_BLOQUEO)
    // ==============================================================================
    echo "\n--- BLOQUE 6: CONCURRENCIA OPTIMISTA REAL (VERSION_BLOQUEO) ---\n";

    // op2 nació con versión 1. Asignar asesor usando versión 1 debe elevar a versión 2.
    $op2Asignada = $oportunidadServicio->asignarResponsable(
        organizacionId: 10000,
        oportunidadId: $op2->id,
        nuevoUsuarioAsignadoId: 61001,
        versionBloqueoEsperada: 1,
        contexto: $ctxOrg1
    );
    afirmar($op2Asignada->versionBloqueo === 2, "6.1: Asignación concurrente eleva exitosamente la versión de bloqueo a 2");

    // Intento de modificar op2 esperando versión 1 (stale read / colisión) debe lanzar ConflictoConcurrenciaExcepcion
    $errorConcurrencia = false;
    try {
        $oportunidadServicio->editar(
            organizacionId: 10000,
            oportunidadId: $op2->id,
            titulo: 'Intento con Versión Desactualizada',
            origenComercialId: null,
            valorEstimado: 9000.00,
            proximoSeguimientoEn: null,
            notas: 'Colisión simulada',
            versionBloqueoEsperada: 1, // Versión obsoleta (actual es 2)
            contexto: $ctxOrg1
        );
    } catch (ConflictoConcurrenciaExcepcion) {
        $errorConcurrencia = true;
    }
    afirmar($errorConcurrencia, "6.2: Intento de edición con versión desactualizada lanza ConflictoConcurrenciaExcepcion (Optimistic Locking)");

    // Con versión correcta (2), la edición prospera y eleva a versión 3
    $op2Editada = $oportunidadServicio->editar(
        organizacionId: 10000,
        oportunidadId: $op2->id,
        titulo: 'Paquete Grupo Familiar 6 Personas Actualizado',
        origenComercialId: $origenWeb->id,
        valorEstimado: 9200.00,
        proximoSeguimientoEn: '2026-11-01 10:00:00',
        notas: 'Requiere hotel céntrico en Puno',
        versionBloqueoEsperada: 2,
        contexto: $ctxOrg1
    );
    afirmar($op2Editada->versionBloqueo === 3, "6.3: Edición con versión vigente se procesa exitosamente elevando a versión 3");
    afirmar($op2Editada->proximoSeguimientoEn === '2026-11-01 10:00:00', "6.4: Próximo seguimiento comercial programado correctamente");

    // ==============================================================================
    // BLOQUE 7: BITÁCORA APPEND-ONLY DE INTERACCIONES Y ACTOR SOBERANO
    // ==============================================================================
    echo "\n--- BLOQUE 7: BITÁCORA APPEND-ONLY DE INTERACCIONES Y ACTOR SOBERANO ---\n";

    // 7.1 Interacción HUMANO válida
    $intHumano = $interaccionServicio->registrar(
        organizacionId: 10000,
        clienteId: 71001,
        oportunidadId: $op2->id,
        canalId: 5, // Canal WHATSAPP
        tipoStr: 'WHATSAPP',
        direccionStr: 'SALIENTE',
        resumen: 'Envío de cotización detallada por WhatsApp',
        detalle: 'Se remitió itinerario de danzas, hoteles y traslados',
        contexto: $ctxOrg1
    );
    afirmar($intHumano->id > 0, "7.1: Registro exitoso de interacción con actor HUMANO");
    afirmar($intHumano->actorTipo === 'HUMANO' && $intHumano->usuarioId === 24 && $intHumano->actorSistemaId === null, "7.2: Actor HUMANO preserva usuarioId no nulo y actorSistemaId NULL");
    afirmar($intHumano->correlacionId === $ctxOrg1->correlacionId, "7.3: Correlación transversal de ContextoOperacion preservada");

    // 7.2 Interacción SISTEMA válida
    $intSistema = $interaccionServicio->registrar(
        organizacionId: 10000,
        clienteId: 71001,
        oportunidadId: $op2->id,
        canalId: 2, // Canal WEB
        tipoStr: 'CORREO',
        direccionStr: 'ENTRANTE',
        resumen: 'Registro automático de solicitud web desde Landing',
        detalle: 'Formulario web completado por el cliente',
        contexto: $ctxSistema
    );
    afirmar($intSistema->id > 0, "7.4: Registro exitoso de interacción con actor SISTEMA");
    afirmar($intSistema->actorTipo === 'SISTEMA' && $intSistema->actorSistemaId === 1 && $intSistema->usuarioId === null, "7.5: Actor SISTEMA preserva actorSistemaId no nulo y usuarioId NULL");

    // 7.3 Restricción estructural MySQL chk_crm_interacciones_actor
    $errorCheckActorHumano = false;
    try {
        $pdo->exec("INSERT INTO `crm_interacciones` (`organizacion_id`, `cliente_id`, `canal_id`, `tipo`, `direccion`, `resumen`, `actor_tipo`, `usuario_id`, `actor_sistema_id`, `correlacion_id`) VALUES (10000, 71001, 1, 'LLAMADA', 'SALIENTE', 'Violación Check', 'HUMANO', 24, 1, 'UUID-INVALID-12345678')");
    } catch (\PDOException) {
        $errorCheckActorHumano = true;
    }
    afirmar($errorCheckActorHumano, "7.6: Restricción CHECK rechaza HUMANO con actor_sistema_id simultáneo");

    $errorCheckActorSistema = false;
    try {
        $pdo->exec("INSERT INTO `crm_interacciones` (`organizacion_id`, `cliente_id`, `canal_id`, `tipo`, `direccion`, `resumen`, `actor_tipo`, `usuario_id`, `actor_sistema_id`, `correlacion_id`) VALUES (10000, 71001, 1, 'LLAMADA', 'ENTRANTE', 'Violación Check', 'SISTEMA', 24, null, 'UUID-INVALID-12345678')");
    } catch (\PDOException) {
        $errorCheckActorSistema = true;
    }
    afirmar($errorCheckActorSistema, "7.7: Restricción CHECK rechaza SISTEMA con usuario_id no nulo");

    // 7.4 Coherencia NOTA_INTERNA -> INTERNA
    $errorNotaInvalida = false;
    try {
        $interaccionServicio->registrar(
            organizacionId: 10000,
            clienteId: 71001,
            oportunidadId: $op2->id,
            canalId: 1,
            tipoStr: 'NOTA_INTERNA',
            direccionStr: 'SALIENTE', // Inválido para NOTA_INTERNA
            resumen: 'Nota con dirección errónea',
            contexto: $ctxOrg1
        );
    } catch (InvalidArgumentException) {
        $errorNotaInvalida = true;
    }
    afirmar($errorNotaInvalida, "7.8: Invariante de coherencia: NOTA_INTERNA con dirección distinta de INTERNA es rechazada");

    $notaValida = $interaccionServicio->registrar(
        organizacionId: 10000,
        clienteId: 71001,
        oportunidadId: $op2->id,
        canalId: 1,
        tipoStr: 'NOTA_INTERNA',
        direccionStr: 'INTERNA',
        resumen: 'El cliente prefiere contacto en horario vespertino',
        contexto: $ctxOrg1
    );
    afirmar($notaValida->id > 0 && $notaValida->tipo === TipoInteraccion::NOTA_INTERNA, "7.9: NOTA_INTERNA con dirección INTERNA es registrada exitosamente");

    // 7.5 Validación de combinación cruzada cliente - oportunidad
    $errorCombinacionCruzada = false;
    try {
        $interaccionServicio->registrar(
            organizacionId: 10000,
            clienteId: 71001, // Cliente 1
            oportunidadId: 999999, // Inexistente
            canalId: 1,
            tipoStr: 'LLAMADA',
            direccionStr: 'SALIENTE',
            resumen: 'Intento con oportunidad inexistente',
            contexto: $ctxOrg1
        );
    } catch (AccesoDenegadoExcepcion) {
        $errorCombinacionCruzada = true;
    }
    afirmar($errorCombinacionCruzada, "7.10: Intento de asociar oportunidad de otra organización o inexistente es rechazado");

    // ==============================================================================
    // BLOQUE 8: HISTORIAL DE ETAPAS APPEND-ONLY Y PISTA DE AUDITORÍA TÉCNICA
    // ==============================================================================
    echo "\n--- BLOQUE 8: HISTORIAL DE ETAPAS APPEND-ONLY Y PISTA DE AUDITORÍA ---\n";

    // 8.1 Historial de etapas cronológico
    $historialOpUsd = $historialRepo->listarPorOportunidad(10000, $opUsd->id);
    afirmar(count($historialOpUsd) === 4, "8.1: Historial append-only registró exactamente las 4 transiciones (NUEVA -> COTIZACION -> NEGOCIACION -> GANADA)");
    afirmar($historialOpUsd[0]->etapaNueva === 'NUEVA', "8.2: Primer registro histórico es la apertura en NUEVA");
    afirmar($historialOpUsd[3]->etapaNueva === 'GANADA', "8.3: Último registro histórico es el cierre en GANADA");

    // 8.2 Auditoría técnica transversal en auditoria_operaciones
    $stmtAud = $pdo->prepare("SELECT accion, entidad_tipo, modulo FROM auditoria_operaciones WHERE organizacion_id = 10000 ORDER BY id DESC LIMIT 50");
    $stmtAud->execute();
    $auditorias = $stmtAud->fetchAll(PDO::FETCH_ASSOC);
    $accionesRegistradas = array_column($auditorias, 'accion');

    afirmar(in_array('CREAR_OPORTUNIDAD', $accionesRegistradas, true), "8.4: Auditoría técnica transversal registra 'CREAR_OPORTUNIDAD'");
    afirmar(in_array('CAMBIAR_ETAPA_OPORTUNIDAD', $accionesRegistradas, true), "8.5: Auditoría técnica transversal registra 'CAMBIAR_ETAPA_OPORTUNIDAD'");
    afirmar(in_array('ASIGNAR_OPORTUNIDAD', $accionesRegistradas, true), "8.6: Auditoría técnica transversal registra 'ASIGNAR_OPORTUNIDAD'");
    afirmar(in_array('REGISTRAR_INTERACCION_CRM', $accionesRegistradas, true), "8.7: Auditoría técnica transversal registra 'REGISTRAR_INTERACCION_CRM'");

    // 8.8 Prohibición de borrado físico
    afirmar(!method_exists($oportunidadRepo, 'eliminar'), "8.8: OportunidadRepositorio no expone método eliminar()");
    afirmar(!method_exists($interaccionRepo, 'eliminar'), "8.9: InteraccionCrmRepositorio no expone método eliminar()");
    afirmar(!method_exists($historialRepo, 'eliminar'), "8.10: HistorialEtapaRepositorio no expone método eliminar()");

    // ==============================================================================
    // BLOQUE 9: CONTROL DE ACCESO RBAC Y SEGURIDAD MULTI-TENANT
    // ==============================================================================
    echo "\n--- BLOQUE 9: CONTROL DE ACCESO RBAC Y SEGURIDAD MULTI-TENANT ---\n";

    // Simular usuario operador que solo tiene permisos de ver, pero no de crear ni editar
    $stmtUsuarioOperador = $pdo->prepare("INSERT INTO `usuarios` (`id`, `organizacion_id`, `persona_id`, `nombre_usuario`, `nombre_completo`, `correo_electronico`, `telefono_whatsapp`, `contrasena_hash`, `estado`) VALUES (61099, 10000, 81099, 'operador.lectura', 'OPERADOR SOLO LECTURA', 'operador@test.com', '+51951111222', '\$2y\$10\$abcdefghijklmnopqrstuu', 'ACTIVO') ON DUPLICATE KEY UPDATE `estado` = 'ACTIVO'");
    $stmtUsuarioOperador->execute();

    // Asignarle únicamente rol operador_produccion (id=3)
    $stmtAsignarRol = $pdo->prepare("INSERT INTO `usuario_roles` (`usuario_id`, `rol_id`) VALUES (61099, 3) ON DUPLICATE KEY UPDATE `rol_id` = 3");
    $stmtAsignarRol->execute();

    $ctxOperador = new ContextoOperacion(
        actorTipo: 'HUMANO',
        usuarioId: 61099,
        actorSistemaId: null,
        actorSistemaCodigo: null,
        canalId: 1,
        canalCodigo: 'APP',
        correlacionId: ContextoOperacion::generarCorrelacionId(),
        origenIp: '127.0.0.1',
        agenteUsuario: 'TestRunner-Operador',
        organizacionId: 10000
    );

    // Intento de crear oportunidad por usuario sin crm.oportunidades.crear
    $errorRbacCrear = false;
    try {
        $oportunidadServicio->crear(
            organizacionId: 10000,
            edicionId: 91001,
            clienteId: 71001,
            titulo: 'Intento RBAC Operador',
            contexto: $ctxOperador
        );
    } catch (AccesoDenegadoExcepcion) {
        $errorRbacCrear = true;
    }
    afirmar($errorRbacCrear, "9.1: Intento de crear oportunidad sin permiso 'crm.oportunidades.crear' es denegado (HTTP 403 Forbidden)");

    // Intento de registrar interacción por usuario sin crm.interacciones.crear
    $errorRbacInteraccion = false;
    try {
        $interaccionServicio->registrar(
            organizacionId: 10000,
            clienteId: 71001,
            oportunidadId: $op2->id,
            canalId: 1,
            tipoStr: 'LLAMADA',
            direccionStr: 'SALIENTE',
            resumen: 'Intento sin permiso',
            contexto: $ctxOperador
        );
    } catch (AccesoDenegadoExcepcion) {
        $errorRbacInteraccion = true;
    }
    afirmar($errorRbacInteraccion, "9.2: Intento de registrar interacción sin permiso 'crm.interacciones.crear' es denegado (HTTP 403 Forbidden)");

    // Consulta de oportunidad perteneciente a otro tenant retorna null
    $opOtroTenant = $oportunidadServicio->buscarPorId(20000, $op2->id, $ctxOrg2);
    afirmar($opOtroTenant === null, "9.3: Consulta de oportunidad perteneciente a otro tenant retorna NULL (Anti-IDOR fail-safe)");

} finally {
    // Revertir transacciones de prueba
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
        echo "\n[INFO] Transacción de pruebas F2.2B revertida con ROLLBACK determinista.\n";
    }
}

// ==============================================================================
// BLOQUE 10: CERTIFICACIÓN DE PRESERVACIÓN DE CREDENCIALES (ORLANDO ID 24)
// ==============================================================================
echo "\n--- BLOQUE 10: CERTIFICACIÓN DE PRESERVACIÓN DE CREDENCIALES (ORLANDO ID 24) ---\n";

$stmtOrlando = $pdo->prepare("SELECT `id`, `estado`, `intentos_fallidos`, `bloqueado_hasta`, SUBSTRING(SHA2(`contrasena_hash`, 256), 1, 16) AS `huella` FROM `usuarios` WHERE `id` = 24");
$stmtOrlando->execute();
$orlando = $stmtOrlando->fetch(PDO::FETCH_ASSOC);

afirmar($orlando !== false, "10.1: Usuario Orlando (ID 24) existe en la base de datos");
afirmar($orlando['estado'] === 'ACTIVO', "10.2: Estado de Orlando es ACTIVO");
afirmar((int) $orlando['intentos_fallidos'] === 0, "10.3: Intentos fallidos de login de Orlando es exactamente 0");
afirmar($orlando['bloqueado_hasta'] === null, "10.4: Bloqueo temporal de cuenta de Orlando es NULL");
afirmar($orlando['huella'] === '80e6af84e02e89e3', "10.5: Huella criptográfica SHA-256 (truncada 16) coincide exactamente con '80e6af84e02e89e3'");

echo "\n==============================================================================\n";
echo "RESUMEN DE SUITE F2.2B: ÉXITOS: {$exitos} | FALLOS: {$fallos}\n";
echo "==============================================================================\n";

if ($fallos > 0) {
    exit(1);
}
