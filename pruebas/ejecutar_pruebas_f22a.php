<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
\Nucleo\Soporte\CargadorEntorno::cargar(__DIR__ . '/../.env');
require __DIR__ . '/../nucleo/Soporte/ayudantes.php';

use Aplicacion\Autorizacion\AutorizacionServicio;
use Aplicacion\Clientes\ClienteServicio;
use Aplicacion\Clientes\ConsentimientoServicio;
use Aplicacion\Clientes\EstadoCliente;
use Aplicacion\Entidades\Cliente;
use Aplicacion\Entidades\Persona;
use Aplicacion\Excepciones\AccesoDenegadoExcepcion;
use Aplicacion\Repositorios\AuditoriaRepositorio;
use Aplicacion\Repositorios\ClienteRepositorio;
use Aplicacion\Repositorios\OrganizacionRepositorio;
use Aplicacion\Repositorios\PermisoRepositorio;
use Aplicacion\Repositorios\PersonaRepositorio;
use Aplicacion\Repositorios\RolRepositorio;
use Aplicacion\Repositorios\UsuarioRepositorio;
use Aplicacion\Soporte\NormalizadorTelefono;
use Nucleo\BaseDatos\Conexion;
use Nucleo\Http\ContextoOperacion;

echo "==============================================================================\n";
echo "CANDELARIAAPP — SUITE DE PRUEBAS F2.2A: MODELO MAESTRO PERSONAS Y CLIENTES CRM\n";
echo "CERTIFICACIÓN DE IDENTIDAD, WHATSAPP E.164, MÁQUINA COMERCIAL Y CONSENTIMIENTOS\n";
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

// ==============================================================================
// BLOQUE 1: VERIFICACIÓN FÍSICA DE BASE DE DATOS Y CLEAN INSTALL
// ==============================================================================
echo "\n--- BLOQUE 1: ESQUEMA LIMPIO Y VERIFICACIÓN DE INSTALACIÓN LIMPIA ---\n";

// 1.1: Columnas y restricciones en personas
$stmtColsPer = $pdo->query("SHOW COLUMNS FROM `personas`");
$colsPersonas = $stmtColsPer->fetchAll(PDO::FETCH_ASSOC);
$colTipoDoc = array_values(array_filter($colsPersonas, fn($c) => $c['Field'] === 'tipo_documento_id'))[0] ?? null;
$colNumDoc  = array_values(array_filter($colsPersonas, fn($c) => $c['Field'] === 'numero_documento'))[0] ?? null;

afirmar($colTipoDoc !== null && $colTipoDoc['Null'] === 'YES', "1.1: 'tipo_documento_id' en 'personas' es nullable");
afirmar($colNumDoc !== null && $colNumDoc['Null'] === 'YES', "1.2: 'numero_documento' en 'personas' es nullable");

// 1.2: Índice de WhatsApp en personas
$stmtIdx = $pdo->query("SHOW INDEX FROM `personas` WHERE Key_name = 'idx_personas_org_whatsapp'");
$idxWhatsapp = $stmtIdx->fetchAll();
afirmar(!empty($idxWhatsapp), "1.3: Índice 'idx_personas_org_whatsapp' existe en tabla 'personas'");

// 1.3: Existencia y columnas de tabla 'clientes'
$stmtColsCli = $pdo->query("SHOW COLUMNS FROM `clientes`");
$colsClientes = $stmtColsCli->fetchAll(PDO::FETCH_COLUMN);
$colsEsperadasClientes = [
    'id', 'organizacion_id', 'persona_id', 'estado_comercial',
    'consentimiento_operativo', 'consentimiento_operativo_en',
    'consentimiento_promocional', 'consentimiento_promocional_en',
    'origen_captacion', 'notas_comerciales', 'creado_en', 'actualizado_en'
];
$diffClientes = array_diff($colsEsperadasClientes, $colsClientes);
afirmar(empty($diffClientes) && count($colsClientes) === 12, "1.4: Tabla 'clientes' contiene exactamente las 12 columnas oficiales");

// 1.4: Existencia y columnas de tabla 'consentimientos_cliente'
$stmtColsCons = $pdo->query("SHOW COLUMNS FROM `consentimientos_cliente`");
$colsConsentimientos = $stmtColsCons->fetchAll(PDO::FETCH_COLUMN);
$colsEsperadasCons = [
    'id', 'organizacion_id', 'cliente_id', 'tipo', 'accion',
    'canal', 'motivo', 'actor_tipo', 'usuario_id', 'actor_sistema_id',
    'correlacion_id', 'creado_en'
];
$diffCons = array_diff($colsEsperadasCons, $colsConsentimientos);
afirmar(empty($diffCons) && count($colsConsentimientos) === 12, "1.5: Tabla 'consentimientos_cliente' contiene exactamente las 12 columnas oficiales");

// 1.5: Permisos RBAC sembrados en el sistema
$stmtPerm = $pdo->query("SELECT codigo FROM `permisos` WHERE codigo IN ('personas.ver', 'personas.crear', 'personas.editar', 'clientes.ver', 'clientes.crear', 'clientes.editar', 'clientes.desactivar')");
$permisosSembrados = $stmtPerm->fetchAll(PDO::FETCH_COLUMN);
afirmar(count($permisosSembrados) === 7, "1.6: Los 7 nuevos permisos RBAC (personas.* y clientes.*) están registrados en la BD");

// 1.6: Clean Install Test en base de datos temporal
$dbTemp = 'candelaria_test_clean_f22a_' . time();
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

    $colsTempPersonas = $pdoTemp->query("SHOW COLUMNS FROM `personas`")->fetchAll(PDO::FETCH_COLUMN);

    afirmar(in_array('clientes', $tablasTemp, true), "1.7: Instalación limpia incluye tabla 'clientes'");
    afirmar(in_array('consentimientos_cliente', $tablasTemp, true), "1.8: Instalación limpia incluye tabla 'consentimientos_cliente'");
    afirmar(count($tablasTemp) >= 21, "1.9: Instalación limpia de 'esquema_base.sql' crea las 21 tablas canónicas sin errores");
    afirmar(!in_array('metadatos_json', array_column($colsPersonas, 'Field'), true), "1.10: Columna 'metadatos_json' ausente en 'personas' de la BD activa");
    afirmar(!in_array('metadatos_json', $colsTempPersonas, true), "1.11: Instalación limpia confirma ausencia de 'metadatos_json' en 'personas'");
    afirmar(count($colsTempPersonas) === 18, "1.12: Tabla 'personas' tiene exactamente 18 columnas oficiales sin bolsa JSON");
} finally {
    $pdo->exec("DROP DATABASE IF EXISTS `{$dbTemp}`");
}

// ==============================================================================
// BLOQUE 2: NORMALIZADOR E.164 Y MODELO TELEFÓNICO
// ==============================================================================
echo "\n--- BLOQUE 2: NORMALIZADOR E.164 Y REGLAS DE CONTACTO ---\n";

afirmar(NormalizadorTelefono::normalizar('951234567') === '+51951234567', "2.1: Celular peruano 9 dígitos normaliza con prefijo +51");
afirmar(NormalizadorTelefono::normalizar('51951234567') === '+51951234567', "2.2: Celular peruano con prefijo 51 añade signo '+' canónico");
afirmar(NormalizadorTelefono::normalizar('+51 951-234-567') === '+51951234567', "2.3: Formato con espacios y guiones se limpia a E.164 canónico");
afirmar(NormalizadorTelefono::normalizar('+1 (555) 234-5678') === '+15552345678', "2.4: Número internacional con paréntesis normaliza correctamente");
afirmar(NormalizadorTelefono::normalizar('+54 9 11 1234 5678') === '+5491112345678', "2.5: Número argentino con código de país E.164 válido normaliza correctamente");
afirmar(NormalizadorTelefono::normalizar('1234') === null, "2.6: Número demasiado corto es rechazado retornando null");
afirmar(NormalizadorTelefono::normalizar('abc951234567') === null, "2.7: Cadena con letras es rechazada retornando null");
afirmar(NormalizadorTelefono::normalizar('') === null, "2.8: Cadena vacía retorna null");
afirmar(NormalizadorTelefono::normalizar(null) === null, "2.9: Valor null retorna null");

$excepcionE164Lanzada = false;
try {
    NormalizadorTelefono::requerirE164('invalido');
} catch (InvalidArgumentException) {
    $excepcionE164Lanzada = true;
}
afirmar($excepcionE164Lanzada, "2.10: requerirE164 lanza InvalidArgumentException ante valor no normalizable");

// Iniciar transacción de aislamiento determinista para el resto de pruebas
$pdo->beginTransaction();

try {
    // Instanciar repositorios y servicios
    $personaRepo = new PersonaRepositorio($pdo);
    $clienteRepo = new ClienteRepositorio($pdo);
    $orgRepo = new OrganizacionRepositorio($pdo);
    $rolRepo = new RolRepositorio($pdo);
    $permRepo = new PermisoRepositorio($pdo);
    $usrRepo = new UsuarioRepositorio($pdo);
    $auditoriaRepo = new AuditoriaRepositorio($pdo);
    $authzServicio = new AutorizacionServicio($rolRepo, $permRepo, $usrRepo, $auditoriaRepo, $pdo);
    $clienteServicio = new ClienteServicio($clienteRepo, $personaRepo, $authzServicio, $auditoriaRepo, $pdo);
    $consentimientoServicio = new ConsentimientoServicio($clienteRepo, $authzServicio, $auditoriaRepo, $pdo);

    // Tenant 10000 (O.G. Estudio) y Tenant 20000 (aislamiento)
    $stmtOrg2 = $pdo->prepare("INSERT INTO `organizaciones` (`id`, `codigo`, `nombre_comercial`, `razon_social`, `tipo_documento_id`, `numero_documento`, `codigo_pais`, `correo_contacto`, `estado`) VALUES (20000, 'org_f22a_aislada', 'TENANT AISLADO F22A', 'TENANT AISLADO F22A S.A.C.', 2, '20888777661', 'PE', 'contacto@aislado.com', 'ACTIVO')");
    $stmtOrg2->execute();

    // Contexto de operador para Org 10000 (Orlando ID 24)
    $ctxOrg1 = ContextoOperacion::paraHumano(
        usuarioId: 24,
        canalId: 1,
        canalCodigo: 'APP',
        origenIp: '127.0.0.1',
        agenteUsuario: 'PHPUnit/Gate',
        organizacionId: 10000,
        correlacionId: 'f22a-gate-uuid-00000001-org10000'
    );

    // Crear un usuario efímero en Org 20000 para pruebas de alcance
    $perOrg2 = new Persona(
        id: null, organizacionId: 20000, tipoPersona: 'NATURAL', tipoDocumentoId: 1,
        numeroDocumento: '77889900', nombres: 'Operador', apellidos: 'Tenant2',
        correoElectronico: 'operador_tenant2@test.com', estado: 'ACTIVO'
    );
    $perOrg2Id = $personaRepo->crear($perOrg2);

    $usrOrg2 = new \Aplicacion\Entidades\Usuario(
        id: null, organizacionId: 20000, personaId: $perOrg2Id, nombreUsuario: 'operador_t2',
        nombreCompleto: 'OPERADOR TENANT 2', correoElectronico: 'operador_tenant2@test.com',
        telefonoWhatsapp: null, contrasenaHash: 'hash_test_dummy', esSuperadminPlataforma: false,
        avatarUrl: null, estado: 'ACTIVO', intentosFallidos: 0, bloqueadoHasta: null,
        ultimoAccesoEn: null, creadoEn: null, actualizadoEn: null
    );
    $usrOrg2Id = $usrRepo->crear($usrOrg2);
    // Asignar rol admin_organizacion en Org 20000
    $rolRepo->asignarRolAUsuario($usrOrg2Id, 2);

    $ctxOrg2 = ContextoOperacion::paraHumano(
        usuarioId: $usrOrg2Id,
        canalId: 1,
        canalCodigo: 'APP',
        origenIp: '127.0.0.1',
        agenteUsuario: 'PHPUnit/Gate',
        organizacionId: 20000,
        correlacionId: 'f22a-gate-uuid-00000002-org20000'
    );

    // ==============================================================================
    // BLOQUE 3: MODELO MAESTRO DE PERSONAS Y COHERENCIA DE DOCUMENTO
    // ==============================================================================
    echo "\n--- BLOQUE 3: MODELO MAESTRO DE PERSONAS Y DOCUMENTOS NULLABLES ---\n";

    // 3.1: Persona sin documento (ambos NULL)
    $personaSinDoc = new Persona(
        id: null,
        organizacionId: 10000,
        tipoPersona: 'NATURAL',
        tipoDocumentoId: null,
        numeroDocumento: null,
        nombres: 'Carlos',
        apellidos: 'Mamani Sin Doc',
        telefonoWhatsapp: '+51951111222',
        estado: 'ACTIVO'
    );
    $idSinDoc = $personaRepo->crear($personaSinDoc);
    $recuperadaSinDoc = $personaRepo->buscarPorId($idSinDoc);
    afirmar($idSinDoc > 0, "3.1: Alta de persona sin documento (ambos NULL) exitosa");
    afirmar($recuperadaSinDoc->tipoDocumentoId === null && $recuperadaSinDoc->numeroDocumento === null, "3.2: Persona persistida preserva tipo y número de documento como NULL");

    // 3.2: Documento parcial: tipo presente sin número
    $errorParcial1 = false;
    try {
        new Persona(
            id: null, organizacionId: 10000, tipoPersona: 'NATURAL',
            tipoDocumentoId: 1, numeroDocumento: null,
            nombres: 'Prueba', apellidos: 'Invalida'
        );
    } catch (InvalidArgumentException) {
        $errorParcial1 = true;
    }
    afirmar($errorParcial1, "3.3: Entidad Persona rechaza tipoDocumentoId presente con numeroDocumento NULL");

    // 3.3: Documento parcial: número presente sin tipo
    $errorParcial2 = false;
    try {
        new Persona(
            id: null, organizacionId: 10000, tipoPersona: 'NATURAL',
            tipoDocumentoId: null, numeroDocumento: '70809010',
            nombres: 'Prueba', apellidos: 'Invalida'
        );
    } catch (InvalidArgumentException) {
        $errorParcial2 = true;
    }
    afirmar($errorParcial2, "3.4: Entidad Persona rechaza numeroDocumento presente con tipoDocumentoId NULL");

    // 3.4: Persona sin WhatsApp si no es cliente
    $personaSinWhatsapp = new Persona(
        id: null, organizacionId: 10000, tipoPersona: 'NATURAL',
        tipoDocumentoId: 1, numeroDocumento: '77665544',
        nombres: 'Persona', apellidos: 'Sin WhatsApp',
        telefonoWhatsapp: null
    );
    $idSinWsp = $personaRepo->crear($personaSinWhatsapp);
    afirmar($idSinWsp > 0, "3.5: Persona general sin WhatsApp es permitida a nivel de identidad base");

    // 3.5: Documento duplicado en la misma organización es rechazado
    $errorDocDuplicado = false;
    try {
        $personaRepetida = new Persona(
            id: null, organizacionId: 10000, tipoPersona: 'NATURAL',
            tipoDocumentoId: 1, numeroDocumento: '77665544',
            nombres: 'Clon', apellidos: 'Mismo Documento'
        );
        $personaRepo->crear($personaRepetida);
    } catch (\PDOException) {
        $errorDocDuplicado = true;
    }
    afirmar($errorDocDuplicado, "3.6: Registro de documento duplicado en el mismo tenant es rechazado por uk_personas_org_doc");

    // 3.6: Mismo documento en DISTINTO tenant es permitido
    $personaDocOrg2 = new Persona(
        id: null, organizacionId: 20000, tipoPersona: 'NATURAL',
        tipoDocumentoId: 1, numeroDocumento: '77665544',
        nombres: 'Persona', apellidos: 'Tenant Dos',
        telefonoWhatsapp: '+51951888999'
    );
    $idDocOrg2 = $personaRepo->crear($personaDocOrg2);
    afirmar($idDocOrg2 > 0, "3.7: Mismo documento de identidad en organizaciones distintas es permitido (aislamiento multi-tenant)");

    // ==============================================================================
    // BLOQUE 4: ALTA DE CLIENTES Y VINCULACIÓN CON PERSONAS
    // ==============================================================================
    echo "\n--- BLOQUE 4: PERFIL COMERCIAL Y VINCULACIÓN CON PERSONAS ---\n";

    // 4.1: Intentar crear perfil cliente sin WhatsApp debe ser rechazado
    $errorSinWspCliente = false;
    try {
        $clienteServicio->crearDesdePersona(
            organizacionId: 10000,
            personaId: $idSinWsp,
            datosComerciales: ['telefono_whatsapp' => null],
            contexto: $ctxOrg1
        );
    } catch (InvalidArgumentException) {
        $errorSinWspCliente = true;
    }
    afirmar($errorSinWspCliente, "4.1: Incorporación a perfil Cliente sin WhatsApp es rechazada obligatoriamente");

    // 4.2: Crear cliente a partir de persona existente con WhatsApp
    $cliente1 = $clienteServicio->crearDesdePersona(
        organizacionId: 10000,
        personaId: $idSinDoc,
        datosComerciales: [
            'telefono_whatsapp' => '+51 951 111 222',
            'origen_captacion'  => 'WHATSAPP_CAMPANA',
            'notas_comerciales' => 'Interesado en Morenada Laykakota'
        ],
        contexto: $ctxOrg1
    );
    afirmar($cliente1->id > 0, "4.2: Creación exitosa de perfil Cliente a partir de Persona existente");
    afirmar($cliente1->estadoComercial === EstadoCliente::CONTACTO, "4.3: Perfil Cliente nace en estado inicial CONTACTO por defecto");
    afirmar($cliente1->consentimientoOperativo === false && $cliente1->consentimientoPromocional === false, "4.4: Consentimientos operativo y promocional nacen en false por defecto");

    // 4.3: Intento de crear segundo perfil comercial para la misma persona en el mismo tenant es rechazado
    $errorPerfilDuplicado = false;
    try {
        $clienteServicio->crearDesdePersona(
            organizacionId: 10000,
            personaId: $idSinDoc,
            datosComerciales: ['telefono_whatsapp' => '+51951111222'],
            contexto: $ctxOrg1
        );
    } catch (InvalidArgumentException) {
        $errorPerfilDuplicado = true;
    }
    afirmar($errorPerfilDuplicado, "4.5: Intento de crear segundo perfil comercial para la misma persona es rechazado (uk_clientes_org_persona)");

    // 4.4: Alta combinada (Persona + Cliente) en una sola transacción atómica
    $cliente2 = $clienteServicio->crearPersonaYCliente(
        organizacionId: 10000,
        datosPersona: [
            'tipo_persona'       => 'NATURAL',
            'tipo_documento_id'  => 1,
            'numero_documento'   => '44556677',
            'nombres'            => 'María',
            'apellidos'          => 'Quispe Flores',
            'correo_electronico' => 'Maria.Quispe@GMAIL.COM',
            'telefono_whatsapp'  => '952334455',
        ],
        datosComerciales: [
            'origen_captacion'  => 'FERIA_PUNO',
            'notas_comerciales' => 'Contacto presencial en Puno'
        ],
        contexto: $ctxOrg1
    );
    afirmar($cliente2->id > 0, "4.6: Alta combinada Persona + Cliente en transacción atómica exitosa");

    $personaCliente2 = $personaRepo->buscarPorId($cliente2->personaId);
    afirmar($personaCliente2->correoElectronico === 'maria.quispe@gmail.com', "4.7: Correo de la persona fue normalizado a minúsculas");
    afirmar($personaCliente2->telefonoWhatsapp === '+51952334455', "4.8: WhatsApp de la persona fue normalizado a E.164 (+51952334455)");

    // 4.5: Persona a Cliente sin duplicar Persona: reuso por documento existente
    $clienteReuso = $clienteServicio->crearPersonaYCliente(
        organizacionId: 10000,
        datosPersona: [
            'tipo_persona'       => 'NATURAL',
            'tipo_documento_id'  => 1,
            'numero_documento'   => '77665544', // Documento de personaSinWhatsapp ya existente
            'nombres'            => 'Persona',
            'apellidos'          => 'Sin WhatsApp',
            'telefono_whatsapp'  => '953445566', // Se le asocia WhatsApp al convertirla a Cliente
        ],
        datosComerciales: ['origen_captacion' => 'WEB'],
        contexto: $ctxOrg1
    );
    afirmar($clienteReuso->personaId === $idSinWsp, "4.9: Al crear cliente con documento existente se reutiliza la Persona previa (cero duplicación)");
    $personaSinWspActualizada = $personaRepo->buscarPorId($idSinWsp);
    afirmar($personaSinWspActualizada->telefonoWhatsapp === '+51953445566', "4.10: WhatsApp fue asignado a la Persona existente al convertirse en Cliente");

    // ==============================================================================
    // BLOQUE 5: UNICIDAD COMERCIAL DE WHATSAPP Y AISLAMIENTO MULTI-TENANT
    // ==============================================================================
    echo "\n--- BLOQUE 5: UNICIDAD COMERCIAL DE WHATSAPP Y MULTI-TENANT ---\n";

    // 5.1: Intentar crear otro cliente en el mismo tenant con el mismo WhatsApp normalizado (+51952334455)
    $errorWhatsappDuplicado = false;
    try {
        $clienteServicio->crearPersonaYCliente(
            organizacionId: 10000,
            datosPersona: [
                'tipo_persona'      => 'NATURAL',
                'tipo_documento_id' => 1,
                'numero_documento'  => '99001122',
                'nombres'           => 'Duplicado',
                'apellidos'         => 'Mismo Telefono',
                'telefono_whatsapp' => '+51 952 334 455', // Mismo de María Quispe
            ],
            datosComerciales: [],
            contexto: $ctxOrg1
        );
    } catch (InvalidArgumentException) {
        $errorWhatsappDuplicado = true;
    }
    afirmar($errorWhatsappDuplicado, "5.1: Invariante: 1 WhatsApp normalizado = máx 1 cliente comercial activo por organización");

    // 5.2: Mismo WhatsApp en DISTINTO tenant debe ser permitido
    $clienteOrg2 = $clienteServicio->crearPersonaYCliente(
        organizacionId: 20000,
        datosPersona: [
            'tipo_persona'      => 'NATURAL',
            'tipo_documento_id' => 1,
            'numero_documento'  => '99001122',
            'nombres'           => 'Cliente',
            'apellidos'         => 'Tenant Dos',
            'telefono_whatsapp' => '+51 952 334 455', // Mismo que María Quispe pero en Org 20000
        ],
        datosComerciales: [],
        contexto: $ctxOrg2
    );
    afirmar($clienteOrg2->id > 0, "5.2: Mismo WhatsApp comercial en distinta organización es permitido (aislamiento multi-tenant)");

    // ==============================================================================
    // BLOQUE 6: MÁQUINA DE ESTADOS COMERCIALES Y PROHIBICIÓN DE CLIENTE_RECURRENTE
    // ==============================================================================
    echo "\n--- BLOQUE 6: MÁQUINA COMERCIAL Y ESTADOS GOBERNADOS ---\n";

    // 6.1: Transición legal: CONTACTO -> PROSPECTO
    $cTrans1 = $clienteServicio->cambiarEstadoComercial(
        organizacionId: 10000,
        clienteId: $cliente1->id,
        nuevoEstadoStr: 'PROSPECTO',
        motivo: 'Solicitó cotización de cobertura audiovisual',
        contexto: $ctxOrg1
    );
    afirmar($cTrans1->estadoComercial === EstadoCliente::PROSPECTO, "6.1: Transición comercial legal CONTACTO -> PROSPECTO exitosa");

    // 6.2: Transición legal: PROSPECTO -> CLIENTE
    $cTrans2 = $clienteServicio->cambiarEstadoComercial(
        organizacionId: 10000,
        clienteId: $cliente1->id,
        nuevoEstadoStr: 'CLIENTE',
        motivo: 'Firmó contrato de servicio',
        contexto: $ctxOrg1
    );
    afirmar($cTrans2->estadoComercial === EstadoCliente::CLIENTE, "6.2: Transición comercial legal PROSPECTO -> CLIENTE exitosa");

    // 6.3: Transición de desactivación: CLIENTE -> INACTIVO
    $cTrans3 = $clienteServicio->cambiarEstadoComercial(
        organizacionId: 10000,
        clienteId: $cliente1->id,
        nuevoEstadoStr: 'INACTIVO',
        motivo: 'Solicitó baja de contacto comercial',
        contexto: $ctxOrg1
    );
    afirmar($cTrans3->estadoComercial === EstadoCliente::INACTIVO, "6.3: Transición a INACTIVO exitosa (sustituto formal de borrado físico)");

    // 6.4: Reactivación: INACTIVO -> PROSPECTO
    $cTrans4 = $clienteServicio->cambiarEstadoComercial(
        organizacionId: 10000,
        clienteId: $cliente1->id,
        nuevoEstadoStr: 'PROSPECTO',
        motivo: 'Retomó comunicación para nueva festividad',
        contexto: $ctxOrg1
    );
    afirmar($cTrans4->estadoComercial === EstadoCliente::PROSPECTO, "6.4: Reactivación comercial INACTIVO -> PROSPECTO exitosa");

    // 6.5: Prohibición absoluta de CLIENTE_RECURRENTE como estado almacenable
    $errorRecurrenteEnum = false;
    try {
        EstadoCliente::desdeCadena('CLIENTE_RECURRENTE');
    } catch (InvalidArgumentException $e) {
        $errorRecurrenteEnum = str_contains($e->getMessage(), 'CLIENTE_RECURRENTE no es un estado comercial almacenable');
    }
    afirmar($errorRecurrenteEnum, "6.5: EstadoCliente rechaza 'CLIENTE_RECURRENTE' como estado comercial almacenable");

    $errorRecurrenteServicio = false;
    try {
        $clienteServicio->cambiarEstadoComercial(
            organizacionId: 10000,
            clienteId: $cliente1->id,
            nuevoEstadoStr: 'CLIENTE_RECURRENTE',
            motivo: 'Intento indebido',
            contexto: $ctxOrg1
        );
    } catch (InvalidArgumentException) {
        $errorRecurrenteServicio = true;
    }
    afirmar($errorRecurrenteServicio, "6.6: ClienteServicio rechaza categóricamente transicionar a 'CLIENTE_RECURRENTE'");

    // 6.6: Verificación de ausencia de método de eliminación física
    $metodosRepo = get_class_methods($clienteRepo);
    $tieneEliminar = in_array('eliminar', $metodosRepo, true) || in_array('delete', $metodosRepo, true);
    afirmar(!$tieneEliminar, "6.7: ClienteRepositorio no expone métodos de eliminación física (prohibición de borrado)");

    // ==============================================================================
    // BLOQUE 7: CONSENTIMIENTOS OPERATIVO Y PROMOCIONAL (BITÁCORA APPEND-ONLY)
    // ==============================================================================
    echo "\n--- BLOQUE 7: GESTIÓN DE CONSENTIMIENTOS Y BITÁCORA APPEND-ONLY ---\n";

    // 7.1: Otorgar consentimiento OPERATIVO
    $consentimientoServicio->otorgar(
        organizacionId: 10000,
        clienteId: $cliente2->id,
        tipo: 'OPERATIVO',
        canal: 'WHATSAPP',
        motivo: 'Aceptó términos de servicio y recordatorios de cobertura por WhatsApp',
        contexto: $ctxOrg1
    );

    $cliConsentido1 = $clienteRepo->buscarPorId($cliente2->id);
    afirmar($cliConsentido1->consentimientoOperativo === true, "7.1: Consentimiento OPERATIVO materializado como otorgado (true)");
    afirmar($cliConsentido1->consentimientoOperativoEn !== null, "7.2: Timestamp de consentimiento operativo materializado registrado");
    afirmar($cliConsentido1->consentimientoPromocional === false, "7.3: Consentimiento PROMOCIONAL permanece en false (independencia estricta)");

    // 7.2: Otorgar consentimiento PROMOCIONAL
    $consentimientoServicio->otorgar(
        organizacionId: 10000,
        clienteId: $cliente2->id,
        tipo: 'PROMOCIONAL',
        canal: 'WEB_CHECKBOX',
        motivo: 'Aceptó promociones y novedades de Candelaria 2027',
        contexto: $ctxOrg1
    );

    $cliConsentido2 = $clienteRepo->buscarPorId($cliente2->id);
    afirmar($cliConsentido2->consentimientoPromocional === true, "7.4: Consentimiento PROMOCIONAL materializado como otorgado (true)");
    afirmar($cliConsentido2->consentimientoOperativo === true, "7.5: Consentimiento OPERATIVO se mantiene intacto (independencia estricta)");

    // 7.3: Revocar consentimiento OPERATIVO
    $consentimientoServicio->revocar(
        organizacionId: 10000,
        clienteId: $cliente2->id,
        tipo: 'OPERATIVO',
        canal: 'CALL_CENTER',
        motivo: 'Cliente solicitó no recibir más notificaciones operativas automáticas',
        contexto: $ctxOrg1
    );

    $cliConsentido3 = $clienteRepo->buscarPorId($cliente2->id);
    afirmar($cliConsentido3->consentimientoOperativo === false, "7.6: Revocación de consentimiento OPERATIVO materializada exitosa (false)");
    afirmar($cliConsentido3->consentimientoPromocional === true, "7.7: Consentimiento PROMOCIONAL se mantiene otorgado (true) tras revocar operativo");

    // 7.4: Trazabilidad append-only en consentimientos_cliente
    $historial = $consentimientoServicio->obtenerHistorial(
        organizacionId: 10000,
        clienteId: $cliente2->id,
        contexto: $ctxOrg1
    );
    afirmar(count($historial) === 3, "7.8: Bitácora 'consentimientos_cliente' registra los 3 eventos append-only cronológicos");
    afirmar($historial[0]['tipo'] === 'OPERATIVO' && $historial[0]['accion'] === 'REVOCAR', "7.9: Último registro del historial es la revocación de OPERATIVO");
    afirmar($historial[1]['tipo'] === 'PROMOCIONAL' && $historial[1]['accion'] === 'OTORGAR', "7.10: Segundo registro del historial es el otorgamiento de PROMOCIONAL");
    afirmar($historial[2]['tipo'] === 'OPERATIVO' && $historial[2]['accion'] === 'OTORGAR', "7.11: Primer registro del historial es el otorgamiento de OPERATIVO");

    // ==============================================================================
    // BLOQUE 8: CONTROL DE ACCESO RBAC Y ANTI-IDOR
    // ==============================================================================
    echo "\n--- BLOQUE 8: CONTROL DE ACCESO RBAC Y ANTI-IDOR ---\n";

    // 8.1: Anti-IDOR: Operador de Org 20000 intentando modificar cliente de Org 10000
    $errorIdorModificar = false;
    try {
        $clienteServicio->cambiarEstadoComercial(
            organizacionId: 20000, // Forzar ID de tenant 20000 contra cliente de Org 10000
            clienteId: $cliente2->id,
            nuevoEstadoStr: 'INACTIVO',
            motivo: 'Intento IDOR',
            contexto: $ctxOrg2
        );
    } catch (AccesoDenegadoExcepcion) {
        $errorIdorModificar = true;
    }
    afirmar($errorIdorModificar, "8.1: Intento IDOR de modificar cliente de otro tenant lanza AccesoDenegadoExcepcion");

    // 8.2: Anti-IDOR: Operador de Org 20000 intentando otorgar consentimiento en cliente de Org 10000
    $errorIdorConsentimiento = false;
    try {
        $consentimientoServicio->otorgar(
            organizacionId: 20000,
            clienteId: $cliente2->id,
            tipo: 'OPERATIVO',
            canal: 'WHATSAPP',
            motivo: 'Intento IDOR',
            contexto: $ctxOrg2
        );
    } catch (AccesoDenegadoExcepcion) {
        $errorIdorConsentimiento = true;
    }
    afirmar($errorIdorConsentimiento, "8.2: Intento IDOR de registrar consentimiento en cliente de otro tenant lanza AccesoDenegadoExcepcion");

    // 8.3: Consulta de cliente de otro tenant retorna null
    $consultaAjena = $clienteServicio->obtenerPorId(
        organizacionId: 20000,
        clienteId: $cliente2->id,
        contexto: $ctxOrg2
    );
    afirmar($consultaAjena === null, "8.3: Consulta de cliente perteneciente a otro tenant retorna null sin fuga de datos");

    // ==============================================================================
    // BLOQUE 9: PISTA DE AUDITORÍA TRANSVERSAL
    // ==============================================================================
    echo "\n--- BLOQUE 9: PISTA DE AUDITORÍA TRANSVERSAL EN auditoria_operaciones ---\n";

    $stmtAud = $pdo->prepare("SELECT accion, modulo, entidad_tipo, entidad_id, datos_nuevos_json FROM `auditoria_operaciones` WHERE `organizacion_id` = 10000 ORDER BY `id` DESC LIMIT 20");
    $stmtAud->execute();
    $logsAuditoria = $stmtAud->fetchAll(PDO::FETCH_ASSOC);

    $accionesRegistradas = array_column($logsAuditoria, 'accion');
    afirmar(in_array('CREAR_PERSONA', $accionesRegistradas, true), "9.1: Auditoría registra evento 'CREAR_PERSONA'");
    afirmar(in_array('CREAR_CLIENTE', $accionesRegistradas, true), "9.2: Auditoría registra evento 'CREAR_CLIENTE'");
    afirmar(in_array('CAMBIAR_ESTADO_CLIENTE', $accionesRegistradas, true), "9.3: Auditoría registra evento 'CAMBIAR_ESTADO_CLIENTE'");
    afirmar(in_array('GESTIONAR_CONSENTIMIENTO_OPERATIVO_OTORGAR', $accionesRegistradas, true), "9.4: Auditoría registra evento 'GESTIONAR_CONSENTIMIENTO_OPERATIVO_OTORGAR'");
    afirmar(in_array('GESTIONAR_CONSENTIMIENTO_PROMOCIONAL_OTORGAR', $accionesRegistradas, true), "9.5: Auditoría registra evento 'GESTIONAR_CONSENTIMIENTO_PROMOCIONAL_OTORGAR'");
    afirmar(in_array('GESTIONAR_CONSENTIMIENTO_OPERATIVO_REVOCAR', $accionesRegistradas, true), "9.6: Auditoría registra evento 'GESTIONAR_CONSENTIMIENTO_OPERATIVO_REVOCAR'");

} finally {
    // Revertir transacción determinista para dejar la base de datos limpia de datos de prueba
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
        echo "\n[INFO] Transacción de pruebas F2.2A revertida con ROLLBACK determinista.\n";
    }
}

// ==============================================================================
// BLOQUE 10: PRESERVACIÓN ABSOLUTA DE CREDENCIALES DE ORLANDO (ID 24)
// ==============================================================================
echo "\n--- BLOQUE 10: CERTIFICACIÓN DE PRESERVACIÓN DE CREDENCIALES (ORLANDO ID 24) ---\n";

$stmtOrlando = $pdo->prepare("SELECT id, estado, intentos_fallidos, bloqueado_hasta, contrasena_hash FROM `usuarios` WHERE `id` = 24");
$stmtOrlando->execute();
$orlando = $stmtOrlando->fetch(PDO::FETCH_ASSOC);

afirmar($orlando !== false, "10.1: Usuario Orlando (ID 24) existe en la base de datos");
afirmar($orlando['estado'] === 'ACTIVO', "10.2: Estado de Orlando es ACTIVO");
afirmar((int) $orlando['intentos_fallidos'] === 0, "10.3: Intentos fallidos de login de Orlando es exactamente 0");
afirmar($orlando['bloqueado_hasta'] === null, "10.4: Bloqueo temporal de cuenta de Orlando es NULL");

// REGLA DE SEGURIDAD: Nunca imprimir el hash, solo el fingerprint truncado a 16 caracteres
$fingerprintHash = substr(hash('sha256', (string) $orlando['contrasena_hash']), 0, 16);
afirmar($fingerprintHash === '80e6af84e02e89e3', "10.5: Huella criptográfica SHA-256 (truncada 16) coincide exactamente con '80e6af84e02e89e3'");

echo "\n==============================================================================\n";
echo "RESUMEN DE SUITE F2.2A: ÉXITOS: {$exitos} | FALLOS: {$fallos}\n";
echo "==============================================================================\n";

if ($fallos > 0) {
    exit(1);
}
