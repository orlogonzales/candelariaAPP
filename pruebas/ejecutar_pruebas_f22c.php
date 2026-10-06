<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
\Nucleo\Soporte\CargadorEntorno::cargar(__DIR__ . '/../.env');
require __DIR__ . '/../nucleo/Soporte/ayudantes.php';

use Aplicacion\Autorizacion\AutorizacionServicio;
use Aplicacion\Clientes\ClienteServicio;
use Aplicacion\Clientes\ConsentimientoServicio;
use Aplicacion\Clientes\EstadoCliente;
use Aplicacion\Configuracion\ConfiguracionServicio;
use Aplicacion\Controladores\ClienteControlador;
use Aplicacion\Controladores\InteraccionControlador;
use Aplicacion\Controladores\OportunidadControlador;
use Aplicacion\Controladores\OrigenComercialControlador;
use Aplicacion\Crm\InteraccionServicio;
use Aplicacion\Crm\OportunidadServicio;
use Aplicacion\Crm\OrigenComercialServicio;
use Aplicacion\Ediciones\ContextoEdicionResolver;
use Aplicacion\Ediciones\EdicionServicio;
use Aplicacion\Entidades\Cliente;
use Aplicacion\Entidades\Edicion;
use Aplicacion\Entidades\Organizacion;
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
use Nucleo\BaseDatos\Conexion;
use Nucleo\Http\ContextoOperacion;
use Nucleo\Http\Middleware\AutenticacionMiddleware;
use Nucleo\Http\Middleware\AutorizacionMiddleware;
use Nucleo\Seguridad\ProtectorCsrf;

echo "==============================================================================\n";
echo "CANDELARIAAPP — SUITE DE PRUEBAS F2.2C: API + INTERFAZ OPERATIVA CRM\n";
echo "PADRÓN DE CLIENTES, FICHA 360, PIPELINE OPORTUNIDADES, TIMELINE Y ORÍGENES\n";
echo "==============================================================================\n\n";

$pdo = Conexion::obtenerInstancia();

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

// 0. Huella digital previa de Orlando para certificar preservación estricta
$stmtOrlandoPre = $pdo->prepare("SELECT contrasena_hash, estado, intentos_fallidos, bloqueado_hasta FROM usuarios WHERE nombre_usuario = 'orlando'");
$stmtOrlandoPre->execute();
$orlandoPre = $stmtOrlandoPre->fetch(PDO::FETCH_ASSOC);
$fingerprintOrlandoPre = $orlandoPre ? substr(hash('sha256', (string) $orlandoPre['contrasena_hash']), 0, 16) : null;

// Iniciar transacción de pruebas para no ensuciar la base de datos
$pdo->beginTransaction();

try {
    // ==========================================================================
    // PREPARACIÓN DE ENTORNOS TENANT Y ACTORES
    // ==========================================================================
    $orgRepo = new OrganizacionRepositorio($pdo);
    $usuarioRepo = new UsuarioRepositorio($pdo);
    $personaRepo = new PersonaRepositorio($pdo);
    $rolRepo = new RolRepositorio($pdo);
    $permisoRepo = new PermisoRepositorio($pdo);
    $auditoriaRepo = new AuditoriaRepositorio($pdo);
    $clienteRepo = new ClienteRepositorio($pdo);
    $edicionRepo = new EdicionRepositorio($pdo);
    $origenRepo = new OrigenComercialRepositorio($pdo);
    $oportunidadRepo = new OportunidadRepositorio($pdo);
    $historialRepo = new HistorialEtapaRepositorio($pdo);
    $interaccionRepo = new InteraccionCrmRepositorio($pdo);

    $authzServicio = new AutorizacionServicio(rolRepo: $rolRepo, permisoRepo: $permisoRepo, usuarioRepo: $usuarioRepo, auditoriaRepo: $auditoriaRepo, pdo: $pdo);
    $configServicio = new ConfiguracionServicio(auditoriaRepo: $auditoriaRepo, pdo: $pdo);
    $clienteServicio = new ClienteServicio($clienteRepo, $personaRepo, $authzServicio, $auditoriaRepo, $pdo);
    $consentimientoServicio = new ConsentimientoServicio($clienteRepo, $authzServicio, $auditoriaRepo, $pdo);
    $oportunidadServicio = new OportunidadServicio($oportunidadRepo, $historialRepo, $clienteRepo, $edicionRepo, $origenRepo, $usuarioRepo, $authzServicio, $auditoriaRepo, $configServicio, $pdo);
    $interaccionServicio = new InteraccionServicio($interaccionRepo, $clienteRepo, $oportunidadRepo, $authzServicio, $auditoriaRepo, $pdo);
    $origenServicio = new OrigenComercialServicio($origenRepo, $authzServicio, $auditoriaRepo, $pdo);

    // Tenant Principal (Org A)
    // Tenant Principal (Org A)
    $stmtOrgA = $pdo->prepare("INSERT INTO `organizaciones` (`codigo`, `nombre_comercial`, `razon_social`, `tipo_documento_id`, `numero_documento`, `codigo_pais`, `correo_contacto`, `estado`) VALUES (:codigo, :nombre, :razon, 2, :doc, 'PE', 'contacto@crm-a.pe', 'ACTIVO')");
    $stmtOrgA->execute([
        ':codigo' => 'org-crm-a-' . time(),
        ':nombre' => 'Productora Candelaria A',
        ':razon'  => 'Estudio Productor Candelaria A S.A.C.',
        ':doc'    => '20' . substr(strval(time()), 0, 9)
    ]);
    $orgAId = (int) $pdo->lastInsertId();

    // Tenant Secundario para IDOR (Org B)
    $stmtOrgB = $pdo->prepare("INSERT INTO `organizaciones` (`codigo`, `nombre_comercial`, `razon_social`, `tipo_documento_id`, `numero_documento`, `codigo_pais`, `correo_contacto`, `estado`) VALUES (:codigo, :nombre, :razon, 2, :doc, 'PE', 'contacto@crm-b.pe', 'ACTIVO')");
    $stmtOrgB->execute([
        ':codigo' => 'org-crm-b-' . time(),
        ':nombre' => 'Productora Candelaria B',
        ':razon'  => 'Estudio Productor Candelaria B S.A.C.',
        ':doc'    => '20' . substr(strval(time() + 1), 0, 9)
    ]);
    $orgBId = (int) $pdo->lastInsertId();

    // Sembrar catálogo de 8 orígenes para Tenant A
    $stmtSembrarOri = $pdo->prepare("
        INSERT INTO `origenes_comerciales` (`organizacion_id`, `codigo`, `nombre`, `descripcion`, `activo`, `orden`)
        SELECT :org_id, s.`codigo`, s.`nombre`, s.`descripcion`, 1, s.`orden`
        FROM (
            SELECT 'WEB_ORGANICA' AS `codigo`, 'Web Orgánica' AS `nombre`, 'Búsqueda directa u orgánica en sitio web' AS `descripcion`, 1 AS `orden` UNION ALL
            SELECT 'REDES_SOCIALES', 'Redes Sociales', 'Contacto proveniente de Facebook, Instagram o TikTok', 2 UNION ALL
            SELECT 'CAMPANA_PUBLICITARIA', 'Campaña Publicitaria', 'Pauta digital o anuncios de pago (Ads)', 3 UNION ALL
            SELECT 'REFERIDO', 'Referido', 'Recomendación de cliente anterior o contacto personal', 4 UNION ALL
            SELECT 'FERIA_EVENTO', 'Feria / Evento', 'Captación presencial en ferias turísticas o activaciones', 5 UNION ALL
            SELECT 'PROSPECCION_DIRECTA', 'Prospección Directa', 'Contacto directo por asesor comercial', 6 UNION ALL
            SELECT 'CONVENIO_INSTITUCIONAL', 'Convenio Institucional', 'Acuerdos corporativos o alianzas estratégicas', 7 UNION ALL
            SELECT 'OTRO', 'Otro Origen', 'Fuente de captación no tipada en catálogo inicial', 8
        ) s
    ");
    $stmtSembrarOri->execute([':org_id' => $orgAId]);

    // Usuario Administrador en Org A (Roles 1 y 2)
    $perAdminA = $personaRepo->crear(new Persona(
        id: null,
        organizacionId: $orgAId,
        tipoPersona: 'NATURAL',
        tipoDocumentoId: 1,
        numeroDocumento: '44556677',
        nombres: 'Admin',
        apellidos: 'Comercial A',
        razonSocial: null,
        nombreComercial: null,
        correoElectronico: 'admin.crm@candelaria.pe',
        telefonoMovil: '+51951000111',
        telefonoWhatsapp: '+51951000111',
        codigoPais: 'PE',
        ciudad: 'Puno'
    ));

    $usrAdminAId = $usuarioRepo->crear(new Usuario(
        id: null,
        organizacionId: $orgAId,
        personaId: $perAdminA,
        nombreUsuario: 'admin_crm_a_' . time(),
        nombreCompleto: 'Admin Comercial A',
        correoElectronico: 'admin.crm@candelaria.pe',
        contrasenaHash: password_hash('AdminCrm2026!', PASSWORD_BCRYPT),
        estado: 'ACTIVO'
    ));
    $rolRepo->sincronizarRolesUsuario($usrAdminAId, [2]); // Administrador de Organización

    // Usuario Limitado en Org A (Operador de Producción: solo lectura, sin clientes.crear ni crm.oportunidades.crear)
    $perOperadorA = $personaRepo->crear(new Persona(
        id: null,
        organizacionId: $orgAId,
        tipoPersona: 'NATURAL',
        tipoDocumentoId: 1,
        numeroDocumento: '44556688',
        nombres: 'Operador',
        apellidos: 'Produccion A',
        razonSocial: null,
        nombreComercial: null,
        correoElectronico: 'operador.crm@candelaria.pe',
        telefonoMovil: '+51951000222',
        telefonoWhatsapp: '+51951000222',
        codigoPais: 'PE',
        ciudad: 'Puno'
    ));

    $usrOperadorAId = $usuarioRepo->crear(new Usuario(
        id: null,
        organizacionId: $orgAId,
        personaId: $perOperadorA,
        nombreUsuario: 'operador_crm_a_' . time(),
        nombreCompleto: 'Operador Produccion A',
        correoElectronico: 'operador.crm@candelaria.pe',
        contrasenaHash: password_hash('OperadorCrm2026!', PASSWORD_BCRYPT),
        estado: 'ACTIVO'
    ));
    $rolRepo->sincronizarRolesUsuario($usrOperadorAId, [3]); // Operador de Producción (solo consulta)

    // Contextos de Operación
    $ctxAdminA = ContextoOperacion::paraHumano(
        usuarioId: $usrAdminAId,
        canalId: 1,
        canalCodigo: 'WEB',
        origenIp: '127.0.0.1',
        agenteUsuario: 'PHPUnit-CLI-Admin',
        organizacionId: $orgAId,
        metadatos: ['csrf_token' => 'token_admin_seguro_123']
    );

    $ctxOperadorA = ContextoOperacion::paraHumano(
        usuarioId: $usrOperadorAId,
        canalId: 1,
        canalCodigo: 'WEB',
        origenIp: '127.0.0.1',
        agenteUsuario: 'PHPUnit-CLI-Operador',
        organizacionId: $orgAId,
        metadatos: ['csrf_token' => 'token_operador_seguro_123']
    );

    // Mock/Stub Middlewares para pruebas controladas de controladores
    $authMiddlewareAdminMock = new class($ctxAdminA) extends AutenticacionMiddleware {
        public function __construct(private ContextoOperacion $ctx) {}
        public function procesar(array $servidor = [], array $cookies = [], bool $bloquearSiInvalido = true): ?ContextoOperacion {
            return $this->ctx;
        }
    };

    $authMiddlewareOperadorMock = new class($ctxOperadorA) extends AutenticacionMiddleware {
        public function __construct(private ContextoOperacion $ctx) {}
        public function procesar(array $servidor = [], array $cookies = [], bool $bloquearSiInvalido = true): ?ContextoOperacion {
            return $this->ctx;
        }
    };

    $authzMiddlewareReal = new AutorizacionMiddleware($authzServicio);

    // Sembrar Edición para Org A y Org B
    $edicionAId = $edicionRepo->crear(new Edicion(
        id: null,
        organizacionId: $orgAId,
        codigo: 'candelaria-2026-' . time(),
        nombre: 'Festividad Candelaria 2026 Tenant A',
        anio: 2026,
        fechaInicio: '2026-02-01',
        fechaFin: '2026-02-15',
        estado: \Aplicacion\Ediciones\EstadoEdicion::PREOPERACION,
        descripcion: 'Edición oficial de pruebas F2.2C',
        esActual: true
    ));
    $edicionA = $edicionRepo->buscarPorId($edicionAId);

    $edicionBId = $edicionRepo->crear(new Edicion(
        id: null,
        organizacionId: $orgBId,
        codigo: 'candelaria-2026-b-' . time(),
        nombre: 'Festividad Candelaria 2026 Tenant B',
        anio: 2026,
        fechaInicio: '2026-02-01',
        fechaFin: '2026-02-15',
        estado: \Aplicacion\Ediciones\EstadoEdicion::PREOPERACION,
        descripcion: 'Edición oficial tenant B',
        esActual: true
    ));
    $edicionB = $edicionRepo->buscarPorId($edicionBId);

    // ==========================================================================
    // BLOQUE 1: CONTROLADOR DE CLIENTES (ClienteControlador)
    // ==========================================================================
    echo "\n--- BLOQUE 1: CLIENTECONTROLADOR (PADRÓN, BUSCAR, ALTA, ESTADO Y CONSENTIMIENTOS) ---\n";

    $clienteCtrl = new ClienteControlador(
        authMiddleware: $authMiddlewareAdminMock,
        authzMiddleware: $authzMiddlewareReal,
        clienteServicio: $clienteServicio,
        consentimientoServicio: $consentimientoServicio,
        clienteRepo: $clienteRepo,
        personaRepo: $personaRepo,
        pdo: $pdo
    );

    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'token_admin_seguro_123';

    // 1.1 Listado inicial vacío
    $resListar = json_decode($clienteCtrl->listar(), true);
    afirmar($resListar['exito'] === true, "GET /api/v1/clientes responde 200 y JSON exitoso");
    afirmar($resListar['datos']['total'] === 0, "Padrón de clientes de Tenant A inicia en 0");

    // 1.2 Búsqueda flexible de personas
    $_GET['q'] = 'Admin';
    $resBuscar = json_decode($clienteCtrl->buscarPersonas(), true);
    afirmar($resBuscar['exito'] === true, "GET /api/v1/personas/buscar responde 200");
    afirmar(count($resBuscar['datos']['personas']) >= 1, "Encuentra persona 'Admin Comercial A' disponible para vincular");
    afirmar($resBuscar['datos']['personas'][0]['ya_es_cliente'] === false, "Persona encontrada tiene flag ya_es_cliente = false");

    // 1.3 Alta de Cliente vinculando Persona existente
    $_POST = [
        'modo_cliente' => 'existente',
        'persona_id'   => $perAdminA,
        'estado_comercial' => 'PROSPECTO',
        'notas_comerciales' => 'Cliente institucional de pruebas'
    ];
    $resCrear1 = json_decode($clienteCtrl->crear(), true);
    afirmar($resCrear1['exito'] === true, "POST /api/v1/clientes vincula persona existente y crea cliente");
    $cliente1Id = (int) $resCrear1['datos']['cliente']['id'];
    afirmar($cliente1Id > 0, "Cliente creado con ID #{$cliente1Id}");

    // 1.4 Búsqueda flexible detecta que la persona ahora es cliente
    $resBuscar2 = json_decode($clienteCtrl->buscarPersonas(), true);
    afirmar($resBuscar2['datos']['personas'][0]['ya_es_cliente'] === true, "Búsqueda de personas reporta ya_es_cliente = true tras el alta");

    // 1.5 Alta dual: Nueva Persona + Cliente en una sola transacción
    $_POST = [
        'modo_cliente'       => 'nueva',
        'tipo_persona'       => 'NATURAL',
        'tipo_documento_id'  => 1,
        'numero_documento'   => '76543210',
        'nombres'            => 'Mario',
        'apellidos'          => 'Vargas Quispe',
        'correo_electronico' => 'mario.vargas@candelaria.pe',
        'telefono_whatsapp'  => '951987654',
        'codigo_pais'        => 'PE',
        'ciudad'             => 'Puno',
        'estado_comercial'   => 'PROSPECTO'
    ];
    $resCrear2 = json_decode($clienteCtrl->crear(), true);
    afirmar($resCrear2['exito'] === true, "POST /api/v1/clientes crea nueva Persona y Cliente atómicamente");
    $cliente2Id = (int) $resCrear2['datos']['cliente']['id'];
    $persona2Id = (int) $resCrear2['datos']['cliente']['persona_id'];
    $persona2Db = $personaRepo->buscarPorId($persona2Id);
    afirmar($persona2Db->telefonoWhatsapp === '+51951987654', "WhatsApp normalizado automáticamente a formato E.164 (+51951987654)");

    // 1.6 Detalle Ficha de Cliente
    $resDetalle = json_decode($clienteCtrl->detalle($cliente2Id), true);
    afirmar($resDetalle['exito'] === true, "GET /api/v1/clientes/{$cliente2Id} retorna detalle de cliente y persona");
    afirmar($resDetalle['datos']['cliente']['estado_comercial'] === 'PROSPECTO', "Estado comercial inicial PROSPECTO");
    afirmar($resDetalle['datos']['persona']['nombres'] === 'MARIO', "Datos de la persona vinculada cargados correctamente (normalización MAYÚSCULAS)");

    // 1.7 Cambio de estado comercial (PROSPECTO -> CLIENTE)
    $_POST = ['estado_comercial' => 'CLIENTE'];
    $resEstado = json_decode($clienteCtrl->cambiarEstado($cliente2Id), true);
    afirmar($resEstado['exito'] === true, "PATCH /api/v1/clientes/{$cliente2Id}/estado transiciona a CLIENTE");
    afirmar($resEstado['datos']['cliente']['estado_comercial'] === 'CLIENTE', "Cliente transicionado exitosamente a CLIENTE");

    // 1.8 Registro de consentimientos con trazabilidad
    $_POST = [
        'tipo'      => 'OPERATIVO',
        'otorgado'  => true,
        'medio'     => 'WHATSAPP',
        'evidencia' => 'Confirmación en mensaje de texto #MSG-1002'
    ];
    $resCons = json_decode($clienteCtrl->actualizarConsentimientos($cliente2Id), true);
    afirmar($resCons['exito'] === true, "POST /api/v1/clientes/{$cliente2Id}/consentimientos registra consentimiento con evidencia");
    afirmar($resCons['datos']['cliente']['consentimiento_operativo'] === true, "Consentimiento operativo otorgado en cliente");

    // 1.9 Prohibición de borrado físico en clientes (405 Method Not Allowed)
    $resDelete = json_decode($clienteCtrl->eliminar($cliente2Id), true);
    afirmar($resDelete['codigo'] === 405, "DELETE /api/v1/clientes/{$cliente2Id} rechazado con código 405 (Prohibición estricta de borrado)");

    // 1.10 Anti-IDOR: Cliente de Tenant B no accesible por usuario de Tenant A
    $perTenantB = $personaRepo->crear(new Persona(
        id: null,
        organizacionId: $orgBId,
        tipoPersona: 'NATURAL',
        tipoDocumentoId: 1,
        numeroDocumento: '99887766',
        nombres: 'Cliente',
        apellidos: 'Tenant B',
        razonSocial: null,
        nombreComercial: null,
        correoElectronico: 'cliente.b@tenantb.pe',
        telefonoMovil: '+51951999888',
        telefonoWhatsapp: '+51951999888',
        codigoPais: 'PE',
        ciudad: 'Juliaca'
    ));
    $clienteTenantBId = $clienteRepo->crear(new Cliente(
        id: null,
        organizacionId: $orgBId,
        personaId: $perTenantB,
        estadoComercial: EstadoCliente::PROSPECTO,
        notasComerciales: null
    ));
    $resIdorCliente = json_decode($clienteCtrl->detalle($clienteTenantBId), true);
    afirmar($resIdorCliente['codigo'] === 404, "Anti-IDOR: Detalle de cliente de Tenant B devuelve 404 para operador de Tenant A");

    // 1.11 RBAC: Operador sin clientes.crear no puede crear cliente
    $clienteCtrlOperador = new ClienteControlador(
        authMiddleware: $authMiddlewareOperadorMock,
        authzMiddleware: $authzMiddlewareReal,
        clienteServicio: $clienteServicio,
        consentimientoServicio: $consentimientoServicio,
        clienteRepo: $clienteRepo,
        personaRepo: $personaRepo,
        pdo: $pdo
    );
    $_POST = [
        'modo_cliente' => 'existente',
        'persona_id'   => $perAdminA
    ];
    $resRbacCrear = json_decode($clienteCtrlOperador->crear(), true);
    afirmar($resRbacCrear['codigo'] === 403, "RBAC: Operador de producción sin 'clientes.crear' es denegado con 403");

    // ==========================================================================
    // BLOQUE 2: CONTROLADOR DE ORÍGENES COMERCIALES (OrigenComercialControlador)
    // ==========================================================================
    echo "\n--- BLOQUE 2: ORIGENCOMERCIALCONTROLADOR (CATÁLOGO INSTITUCIONAL) ---\n";

    $origenCtrl = new OrigenComercialControlador(
        authMiddleware: $authMiddlewareAdminMock,
        authzMiddleware: $authzMiddlewareReal,
        origenServicio: $origenServicio,
        origenRepo: $origenRepo,
        pdo: $pdo
    );

    // 2.1 Listado inicial de orígenes del tenant
    $resListarOrigenes = json_decode($origenCtrl->listar(), true);
    afirmar($resListarOrigenes['exito'] === true, "GET /api/v1/crm/origenes responde 200");
    afirmar(count($resListarOrigenes['datos']['origenes']) >= 8, "Catálogo inicial de 8 semillas institucionales cargado");

    // 2.2 Creación de nuevo origen
    $_POST = [
        'codigo' => 'tiktok_ads_puno',
        'nombre' => 'Campañas TikTok Ads Puno',
        'descripcion' => 'Pauta digital para trajes y bandas',
        'orden' => 15
    ];
    $resCrearOrigen = json_decode($origenCtrl->crear(), true);
    afirmar($resCrearOrigen['exito'] === true, "POST /api/v1/crm/origenes crea nuevo canal de captación");
    $origenCreadoId = (int) $resCrearOrigen['datos']['origen']['id'];
    afirmar($resCrearOrigen['datos']['origen']['codigo'] === 'TIKTOK_ADS_PUNO', "Código canónico forzado a mayúsculas");

    // 2.3 Edición de origen con protección de código inmutable
    $_POST = [
        'nombre' => 'TikTok Ads Región Sur',
        'descripcion' => 'Pauta digital ampliada para el sur',
        'orden' => 16
    ];
    $resEditOrigen = json_decode($origenCtrl->actualizar($origenCreadoId), true);
    afirmar($resEditOrigen['exito'] === true, "PUT /api/v1/crm/origenes/{$origenCreadoId} actualiza nombre y descripción");
    afirmar($resEditOrigen['datos']['origen']['codigo'] === 'TIKTOK_ADS_PUNO', "Código canónico se mantiene estrictamente inmutable");

    // 2.4 Desactivación y Reactivación lógica
    $_POST = ['activo' => false];
    $resDesactOrigen = json_decode($origenCtrl->cambiarEstado($origenCreadoId), true);
    afirmar($resDesactOrigen['exito'] === true && $resDesactOrigen['datos']['origen']['activo'] === false, "PATCH /api/v1/crm/origenes/{$origenCreadoId}/estado desactiva origen lógicamente");

    $_POST = ['activo' => true];
    $resActOrigen = json_decode($origenCtrl->cambiarEstado($origenCreadoId), true);
    afirmar($resActOrigen['exito'] === true && $resActOrigen['datos']['origen']['activo'] === true, "PATCH /api/v1/crm/origenes/{$origenCreadoId}/estado reactiva origen");

    // 2.5 Prohibición de borrado físico
    $resDeleteOrigen = json_decode($origenCtrl->eliminar($origenCreadoId), true);
    afirmar($resDeleteOrigen['codigo'] === 405, "DELETE /api/v1/crm/origenes/{$origenCreadoId} responde 405 Method Not Allowed");

    // 2.6 RBAC: Permiso 'crm.origenes.administrar' registrado y asignado
    $stmtPerm33 = $pdo->prepare("SELECT id FROM `permisos` WHERE `codigo` = 'crm.origenes.administrar'");
    $stmtPerm33->execute();
    afirmar((int) $stmtPerm33->fetchColumn() === 33, "Permiso soberano 'crm.origenes.administrar' (ID 33) formalmente registrado");

    // 2.7 RBAC: Operador sin 'crm.origenes.administrar' es rechazado con 403
    $origenCtrlOperador = new OrigenComercialControlador(
        authMiddleware: $authMiddlewareOperadorMock,
        authzMiddleware: $authzMiddlewareReal,
        origenServicio: $origenServicio,
        origenRepo: $origenRepo,
        pdo: $pdo
    );
    $_POST = [
        'codigo' => 'ORIGEN_NO_AUTORIZADO',
        'nombre' => 'Intento No Autorizado'
    ];
    $resRbacOrigen = json_decode($origenCtrlOperador->crear(), true);
    afirmar($resRbacOrigen['codigo'] === 403, "RBAC: Operador sin 'crm.origenes.administrar' es rechazado con 403 al intentar crear origen");

    // ==========================================================================
    // BLOQUE 3: CONTROLADOR DE OPORTUNIDADES (OportunidadControlador)
    // ==========================================================================
    echo "\n--- BLOQUE 3: OPORTUNIDADCONTROLADOR (PIPELINE, CONCURRENCIA 409, MOTIVOS Y MONEDA) ---\n";

    $oportunidadCtrl = new OportunidadControlador(
        authMiddleware: $authMiddlewareAdminMock,
        authzMiddleware: $authzMiddlewareReal,
        oportunidadServicio: $oportunidadServicio,
        oportunidadRepo: $oportunidadRepo,
        historialRepo: $historialRepo,
        interaccionRepo: $interaccionRepo,
        clienteRepo: $clienteRepo,
        edicionRepo: $edicionRepo,
        origenRepo: $origenRepo,
        usuarioRepo: $usuarioRepo,
        contextoEdicionResolver: new ContextoEdicionResolver($edicionRepo),
        pdo: $pdo
    );

    // 3.1 Apertura de Oportunidad (moneda soberana PEN resuelta por plataforma)
    $_POST = [
        'edicion_id'          => $edicionA->id,
        'cliente_id'          => $cliente2Id,
        'titulo'              => 'Cobertura Morenada Laykakota Bloque Oro',
        'usuario_asignado_id' => $usrAdminAId,
        'origen_comercial_id' => $origenCreadoId,
        'valor_estimado'      => 4500.00,
        'proximo_seguimiento_en' => '2026-01-20',
        'notas'               => 'Reunión preliminar pactada'
    ];
    $resCrearOp = json_decode($oportunidadCtrl->crear(), true);
    afirmar($resCrearOp['exito'] === true, "POST /api/v1/crm/oportunidades crea oportunidad comercial");
    $op1Id = (int) $resCrearOp['datos']['oportunidad']['id'];
    $monedaEsperada = (string) $configServicio->obtenerPlataforma('plataforma.moneda_principal');
    afirmar($resCrearOp['datos']['oportunidad']['moneda'] === $monedaEsperada, "Moneda adopta soberanamente el snapshot institucional de 'plataforma.moneda_principal' ('{$monedaEsperada}')");
    afirmar($resCrearOp['datos']['oportunidad']['etapa'] === 'NUEVA', "Etapa inicial NUEVA");
    afirmar($resCrearOp['datos']['oportunidad']['version_bloqueo'] === 1, "version_bloqueo inicial = 1");

    // 3.2 Listado contextualizado por edición
    $_GET['edicion_id'] = (string) $edicionA->id;
    $resListarOp = json_decode($oportunidadCtrl->listar(), true);
    afirmar($resListarOp['exito'] === true && $resListarOp['datos']['total'] >= 1, "GET /api/v1/crm/oportunidades contextualizado por edición responde 200");

    // 3.3 Detalle de oportunidad con historial de etapas e interacciones
    $resDetalleOp = json_decode($oportunidadCtrl->detalle($op1Id), true);
    afirmar($resDetalleOp['exito'] === true, "GET /api/v1/crm/oportunidades/{$op1Id} retorna detalle");
    afirmar(count($resDetalleOp['datos']['historial']) === 1, "Historial registra la apertura inicial en etapa NUEVA");

    // 3.4 Transición válida de etapa: NUEVA -> COTIZACION
    $_POST = [
        'etapa'           => 'COTIZACION',
        'version_bloqueo' => 1,
        'motivo_cambio'   => 'Envío de propuesta económica formal'
    ];
    $resEtapa1 = json_decode($oportunidadCtrl->cambiarEtapa($op1Id), true);
    afirmar($resEtapa1['exito'] === true, "POST /api/v1/crm/oportunidades/{$op1Id}/etapa transiciona a COTIZACION");
    afirmar($resEtapa1['datos']['oportunidad']['version_bloqueo'] === 2, "version_bloqueo incrementado a 2 tras cambio de etapa");

    // 3.5 CONCURRENCIA OPTIMISTA 409: Intento de modificación con version_bloqueo obsoleta (stale version 1)
    $_POST = [
        'titulo'          => 'Intento concurrente desfasado',
        'version_bloqueo' => 1 // Desfasada, la versión actual en BD es 2
    ];
    $resConflicto = json_decode($oportunidadCtrl->actualizar($op1Id), true);
    afirmar($resConflicto['codigo'] === 409, "Optimistic Locking: Intento de actualización con version_bloqueo desfasada retorna HTTP 409");

    // 3.6 Transición a PERDIDA exige motivo
    $_POST = [
        'etapa'           => 'PERDIDA',
        'version_bloqueo' => 2,
        'motivo_perdida'  => null
    ];
    $resPerdidaSinMotivo = json_decode($oportunidadCtrl->cambiarEtapa($op1Id), true);
    afirmar($resPerdidaSinMotivo['codigo'] === 400, "Validación gobernada: Transición a PERDIDA sin motivo es rechazada con 400");

    // 3.7 Transición a PERDIDA con motivo OTRO exige detalle
    $_POST = [
        'etapa'                  => 'PERDIDA',
        'version_bloqueo'        => 2,
        'motivo_perdida'         => 'OTRO',
        'motivo_perdida_detalle' => null
    ];
    $resPerdidaOtroSinDetalle = json_decode($oportunidadCtrl->cambiarEtapa($op1Id), true);
    afirmar($resPerdidaOtroSinDetalle['codigo'] === 400, "Validación gobernada: Motivo PERDIDA 'OTRO' sin detalle explicativo es rechazado con 400");

    // 3.8 Transición legítima a PERDIDA con motivo COMPETENCIA
    $_POST = [
        'etapa'                  => 'PERDIDA',
        'version_bloqueo'        => 2,
        'motivo_perdida'         => 'COMPETENCIA',
        'motivo_perdida_detalle' => 'Contrataron a productora de Lima',
        'motivo_cambio'          => 'Cierre por competencia'
    ];
    $resPerdidaValida = json_decode($oportunidadCtrl->cambiarEtapa($op1Id), true);
    afirmar($resPerdidaValida['exito'] === true, "POST /api/v1/crm/oportunidades/{$op1Id}/etapa a PERDIDA con motivo válido aceptada");
    afirmar($resPerdidaValida['datos']['oportunidad']['version_bloqueo'] === 3, "version_bloqueo incrementado a 3");

    // 3.9 Inmutabilidad terminal: No se puede transicionar desde PERDIDA
    $_POST = [
        'etapa'           => 'NEGOCIACION',
        'version_bloqueo' => 3
    ];
    $resRevivir = json_decode($oportunidadCtrl->cambiarEtapa($op1Id), true);
    afirmar($resRevivir['codigo'] === 400, "Inmutabilidad terminal: Intento de salir de PERDIDA a NEGOCIACION es rechazado con 400");

    // 3.10 Prohibición de borrado físico en oportunidades
    $resDeleteOp = json_decode($oportunidadCtrl->eliminar($op1Id), true);
    afirmar($resDeleteOp['codigo'] === 405, "DELETE /api/v1/crm/oportunidades/{$op1Id} responde 405 Method Not Allowed");

    // 3.11 Anti-IDOR: Oportunidad de Edición de Tenant B para Cliente de Tenant A es rechazada
    $_POST = [
        'edicion_id' => $edicionB->id, // Tenant B
        'cliente_id' => $cliente2Id,   // Tenant A
        'titulo'     => 'Intento IDOR cross-tenant'
    ];
    $resIdorEdicion = json_decode($oportunidadCtrl->crear(), true);
    afirmar($resIdorEdicion['codigo'] === 403, "Anti-IDOR: Oportunidad con edición de otro tenant rechazada con 403");

    // ==========================================================================
    // BLOQUE 4: CONTROLADOR DE INTERACCIONES Y TIMELINE (InteraccionControlador)
    // ==========================================================================
    echo "\n--- BLOQUE 4: INTERACCIONCONTROLADOR (APPEND-ONLY Y TIMELINE UNIFICADO) ---\n";

    $interaccionCtrl = new InteraccionControlador(
        authMiddleware: $authMiddlewareAdminMock,
        authzMiddleware: $authzMiddlewareReal,
        interaccionServicio: $interaccionServicio,
        interaccionRepo: $interaccionRepo,
        historialRepo: $historialRepo,
        clienteRepo: $clienteRepo,
        oportunidadRepo: $oportunidadRepo,
        pdo: $pdo
    );

    // 4.1 Registrar llamada comercial
    $_POST = [
        'cliente_id'     => $cliente2Id,
        'oportunidad_id' => $op1Id,
        'canal_id'       => 2, // Llamada
        'tipo'           => 'LLAMADA',
        'direccion'      => 'SALIENTE',
        'resumen'        => 'Llamada de presentación de propuesta comercial',
        'detalle'        => 'Se presentó el desglose de cámaras, drones y fechas de entrega'
    ];
    $resInt1 = json_decode($interaccionCtrl->crear(), true);
    afirmar($resInt1['exito'] === true, "POST /api/v1/crm/interacciones registra llamada comercial append-only");
    $int1Id = (int) $resInt1['datos']['interaccion']['id'];

    // 4.2 Registrar mensaje de WhatsApp
    $_POST = [
        'cliente_id'     => $cliente2Id,
        'oportunidad_id' => $op1Id,
        'canal_id'       => 1, // WhatsApp
        'tipo'           => 'WHATSAPP',
        'direccion'      => 'ENTRANTE',
        'resumen'        => 'Cliente envía dudas sobre itinerario de pasacalle',
        'detalle'        => 'Se aclaró la hora de concentración en Parque del Pino'
    ];
    $resInt2 = json_decode($interaccionCtrl->crear(), true);
    afirmar($resInt2['exito'] === true, "POST /api/v1/crm/interacciones registra mensaje de WhatsApp");

    // 4.3 Timeline comercial unificado (Interacciones + Historial de Etapas)
    $resTimeline = json_decode($interaccionCtrl->timeline($cliente2Id), true);
    afirmar($resTimeline['exito'] === true, "GET /api/v1/crm/clientes/{$cliente2Id}/timeline responde 200");
    $eventos = $resTimeline['datos']['eventos'];
    afirmar(count($eventos) >= 4, "Timeline unificado consolida 2 interacciones + 2 transiciones de etapa");
    afirmar($eventos[0]['fecha'] >= $eventos[1]['fecha'], "Eventos del timeline ordenados cronológicamente descendente");

    // 4.4 Prohibición de borrado físico en interacciones
    $resDeleteInt = json_decode($interaccionCtrl->eliminar($int1Id), true);
    afirmar($resDeleteInt['codigo'] === 405, "DELETE /api/v1/crm/interacciones/{$int1Id} responde 405 Method Not Allowed");

    // 4.5 Anti-cruce de oportunidad: Interacción para cliente A con oportunidad de cliente B rechazada
    $_POST = [
        'cliente_id'     => $cliente1Id, // Cliente 1
        'oportunidad_id' => $op1Id,      // Oportunidad pertenece a Cliente 2
        'canal_id'       => 1,
        'tipo'           => 'LLAMADA',
        'direccion'      => 'SALIENTE',
        'resumen'        => 'Intento de cruce de cliente y oportunidad'
    ];
    $resAntiCruce = json_decode($interaccionCtrl->crear(), true);
    afirmar($resAntiCruce['codigo'] === 400, "Anti-cruce: Interacción con oportunidad que pertenece a otro cliente es rechazada con 400");

    // ==========================================================================
    // BLOQUE 5: VERIFICACIÓN DE VISTAS ALINA (SINTAXIS Y RENDER)
    // ==========================================================================
    echo "\n--- BLOQUE 5: VISTAS ALINA NATIVAS (INDEX, FICHA, OPORTUNIDADES, ORIGENES) ---\n";

    // Vista Clientes Index
    ob_start();
    include __DIR__ . '/../recursos/vistas/paginas/clientes/index.php';
    $htmlClientes = ob_get_clean();
    afirmar(str_contains($htmlClientes, 'contenedorTablaClientes'), "Vista clientes/index contiene contenedorTablaClientes con soporte Skeleton");
    afirmar(str_contains($htmlClientes, 'modalCrearCliente'), "Vista clientes/index contiene modalCrearCliente para alta dual");
    afirmar(str_contains($htmlClientes, 'select2-persona-ajax'), "Vista clientes/index integra Select2 asíncrono para búsqueda de personas");

    // Vista Ficha 360
    $cliente = $clienteRepo->buscarPorId($cliente2Id);
    $persona = $personaRepo->buscarPorId($cliente->personaId);
    ob_start();
    include __DIR__ . '/../recursos/vistas/paginas/clientes/ficha.php';
    $htmlFicha = ob_get_clean();
    afirmar(str_contains($htmlFicha, 'tabsCliente'), "Vista clientes/ficha contiene navegación por tabs nativos de Alina");
    afirmar(str_contains($htmlFicha, 'app-timeline-box'), "Vista clientes/ficha integra contenedor app-timeline-box de Alina");
    afirmar(str_contains($htmlFicha, 'modalRegistrarInteraccion'), "Vista clientes/ficha incluye modal de interacciones append-only");

    // Vista Oportunidades
    $ediciones = [$edicionA];
    $origenes = $origenRepo->listarPorOrganizacion($orgAId, true);
    $asesores = [$usuarioRepo->buscarPorId($usrAdminAId)];
    ob_start();
    include __DIR__ . '/../recursos/vistas/paginas/crm/oportunidades.php';
    $htmlOp = ob_get_clean();
    afirmar(str_contains($htmlOp, 'contenedorTablaOportunidades'), "Vista crm/oportunidades contiene contenedorTablaOportunidades");
    afirmar(str_contains($htmlOp, 'modalCambiarEtapa'), "Vista crm/oportunidades contiene modalCambiarEtapa con soporte 409");
    afirmar(str_contains($htmlOp, 'seccionMotivoPerdida'), "Vista crm/oportunidades incluye panel condicional de motivos para etapa PERDIDA");

    // Vista Orígenes
    ob_start();
    include __DIR__ . '/../recursos/vistas/paginas/crm/origenes.php';
    $htmlOrig = ob_get_clean();
    afirmar(str_contains($htmlOrig, 'contenedorTablaOrigenes'), "Vista crm/origenes contiene contenedorTablaOrigenes");
    afirmar(str_contains($htmlOrig, 'modalEditarOrigen'), "Vista crm/origenes contiene modalEditarOrigen con código canónico protegido");

} finally {
    // Revertir transacción para dejar la base de datos limpia
    $pdo->rollBack();
}

// ==============================================================================
// BLOQUE 6: CERTIFICACIÓN DE PRESERVACIÓN ESTRICTA DE ORLANDO ID 24
// ==============================================================================
echo "\n--- BLOQUE 6: CERTIFICACIÓN DE PRESERVACIÓN ESTRICTA DE ORLANDO ID 24 ---\n";

$stmtOrlandoPost = $pdo->prepare("SELECT contrasena_hash, estado, intentos_fallidos, bloqueado_hasta FROM usuarios WHERE nombre_usuario = 'orlando'");
$stmtOrlandoPost->execute();
$orlandoPost = $stmtOrlandoPost->fetch(PDO::FETCH_ASSOC);
$fingerprintOrlandoPost = $orlandoPost ? substr(hash('sha256', (string) $orlandoPost['contrasena_hash']), 0, 16) : null;

afirmar($orlandoPost !== false, "Usuario orlando existe en la base de datos");
afirmar($orlandoPost['estado'] === 'ACTIVO', "Estado de orlando se mantiene ACTIVO");
afirmar((int) $orlandoPost['intentos_fallidos'] === 0, "Intentos fallidos de orlando se mantienen en 0");
afirmar($orlandoPost['bloqueado_hasta'] === null, "Bloqueado hasta de orlando se mantiene NULL");
afirmar($fingerprintOrlandoPre === $fingerprintOrlandoPost, "Huella criptográfica de contraseña de orlando intacta ({$fingerprintOrlandoPost})");

// ==============================================================================
// BALANCE FINAL DE LA SUITE F2.2C
// ==============================================================================
echo "\n==============================================================================\n";
echo "RESULTADOS SUITE F2.2C: ÉXITOS = {$exitos} | FALLOS = {$fallos}\n";
echo "==============================================================================\n";

if ($fallos > 0) {
    exit(1);
}
exit(0);
