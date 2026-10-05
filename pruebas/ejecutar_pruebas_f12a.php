<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
\Nucleo\Soporte\CargadorEntorno::cargar(__DIR__ . '/../.env');
require_once __DIR__ . '/../nucleo/Soporte/ayudantes.php';

use Aplicacion\Autorizacion\AutorizacionServicio;
use Aplicacion\Configuracion\BrandingServicio;
use Aplicacion\Configuracion\ConfiguracionServicio;
use Aplicacion\Entidades\Organizacion;
use Aplicacion\Entidades\ParametroConfiguracion;
use Aplicacion\Entidades\Persona;
use Aplicacion\Entidades\Usuario;
use Aplicacion\Excepciones\AccesoDenegadoExcepcion;
use Aplicacion\Repositorios\AuditoriaRepositorio;
use Aplicacion\Repositorios\ConfiguracionRepositorio;
use Aplicacion\Repositorios\OrganizacionRepositorio;
use Aplicacion\Repositorios\PermisoRepositorio;
use Aplicacion\Repositorios\PersonaRepositorio;
use Aplicacion\Repositorios\RolRepositorio;
use Aplicacion\Repositorios\UsuarioRepositorio;
use Nucleo\BaseDatos\Conexion;
use Nucleo\Http\ContextoOperacion;

echo "==============================================================================\n";
echo "CANDELARIAAPP — SUITE DE PRUEBAS DE MODELO DE CONFIGURACIÓN Y ORGANIZACIÓN F1.2A\n";
echo "ÁMBITOS PLATAFORMA/ORGANIZACIÓN, PARÁMETROS TIPADOS, BRANDING, RBAC Y AUDITORÍA\n";
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

// 0. Instanciar componentes
$orgRepo         = new OrganizacionRepositorio($pdo);
$configRepo      = new ConfiguracionRepositorio($pdo);
$rolRepo         = new RolRepositorio($pdo);
$permisoRepo     = new PermisoRepositorio($pdo);
$usuarioRepo     = new UsuarioRepositorio($pdo);
$personaRepo     = new PersonaRepositorio($pdo);
$auditoriaRepo   = new AuditoriaRepositorio($pdo);
$authzServicio   = new AutorizacionServicio($rolRepo, $permisoRepo, $usuarioRepo, $auditoriaRepo, $pdo);
$configServicio  = new ConfiguracionServicio($configRepo, $orgRepo, $authzServicio, $auditoriaRepo, $pdo);

$pdo->beginTransaction();

try {
    // ==============================================================================
    // 1. ORGANIZACIÓN EXISTENTE PRESERVADA (ID 10000 O.G. ESTUDIO CREATIVO)
    // ==============================================================================
    $org10000 = $orgRepo->buscarPorId(10000);
    afirmar(
        $org10000 !== null
        && $org10000->id === 10000
        && $org10000->codigo === 'og_estudio'
        && $org10000->nombreComercial === 'O.G. ESTUDIO CREATIVO'
        && $org10000->tipoDocumentoId === 2
        && $org10000->numeroDocumento === '20601234567'
        && $org10000->departamento === 'PUNO'
        && $org10000->codigoPais === 'PE'
        && $org10000->estado === 'ACTIVO',
        '01. Organización existente (10000 O.G. ESTUDIO CREATIVO) preservada con campos relacionales extendidos'
    );

    // ==============================================================================
    // 2. NORMALIZACIÓN ESTRICTA EN ENTIDAD ORGANIZACIÓN
    // ==============================================================================
    $orgTestNormalizacion = Organizacion::desdeArreglo([
        'codigo'           => 'TENANT_TEST ',
        'nombre_comercial' => 'mi estudio fotográfico  ',
        'razon_social'     => 'mi empresa sac',
        'direccion'        => 'av. floral 123',
        'departamento'     => 'puno',
        'correo_contacto'  => 'CONTACTO@EMPRESA.COM',
        'sitio_web'        => 'HTTPS://WWW.EMPRESA.COM',
        'contacto_nombre'  => 'juan perez',
        'codigo_pais'      => 'pe',
        'estado'           => 'activo',
    ]);

    afirmar(
        $orgTestNormalizacion->codigo === 'tenant_test'
        && $orgTestNormalizacion->nombreComercial === 'MI ESTUDIO FOTOGRÁFICO'
        && $orgTestNormalizacion->razonSocial === 'MI EMPRESA SAC'
        && $orgTestNormalizacion->direccion === 'AV. FLORAL 123'
        && $orgTestNormalizacion->departamento === 'PUNO'
        && $orgTestNormalizacion->correoContacto === 'contacto@empresa.com'
        && $orgTestNormalizacion->sitioWeb === 'https://www.empresa.com'
        && $orgTestNormalizacion->contactoNombre === 'JUAN PEREZ'
        && $orgTestNormalizacion->codigoPais === 'PE'
        && $orgTestNormalizacion->estado === 'ACTIVO',
        '02. Normalización de Organización: textos de negocio a MAYÚSCULAS, emails/URLs a minúsculas'
    );

    // ==============================================================================
    // 3. VALIDACIÓN DE INVARIANTES EN ENTIDAD ORGANIZACIÓN
    // ==============================================================================
    $invarianteSlugFallo = false;
    try {
        new Organizacion(
            id: null,
            codigo: 'codigo invalido con espacios!',
            nombreComercial: 'Test'
        );
    } catch (\InvalidArgumentException) {
        $invarianteSlugFallo = true;
    }

    $invariantePaisFallo = false;
    try {
        new Organizacion(
            id: null,
            codigo: 'tenant_valido',
            nombreComercial: 'Test',
            codigoPais: 'PERU' // Debe ser exactamente 2 caracteres ISO
        );
    } catch (\InvalidArgumentException) {
        $invariantePaisFallo = true;
    }

    $invarianteEmailFallo = false;
    try {
        new Organizacion(
            id: null,
            codigo: 'tenant_valido',
            nombreComercial: 'Test',
            correoContacto: 'correo_invalido_sin_arroba'
        );
    } catch (\InvalidArgumentException) {
        $invarianteEmailFallo = true;
    }

    afirmar(
        $invarianteSlugFallo === true && $invariantePaisFallo === true && $invarianteEmailFallo === true,
        '03. Entidad Organizacion valida invariantes de código slug, código ISO de país y formato de email'
    );

    // ==============================================================================
    // 4. BÚSQUEDA EN REPOSITORIO DE ORGANIZACIÓN (ID Y CÓDIGO)
    // ==============================================================================
    $busquedaId = $orgRepo->buscarPorId(10000);
    $busquedaCod = $orgRepo->buscarPorCodigo('og_estudio');
    $busquedaInexistente = $orgRepo->buscarPorCodigo('codigo_inexistente_xyz');

    afirmar(
        $busquedaId !== null
        && $busquedaCod !== null
        && $busquedaId->id === $busquedaCod->id
        && $busquedaInexistente === null,
        '04. OrganizacionRepositorio resuelve correctamente por ID y código canónico'
    );

    // ==============================================================================
    // 5. ACTUALIZACIÓN DE PERFIL INSTITUCIONAL RELACIONAL
    // ==============================================================================
    $datosActualizar = [
        'telefono_contacto' => '+51951112233',
        'contacto_nombre'   => 'ORLANDO GONZALES MAMANI',
        'contacto_cargo'    => 'GERENTE GENERAL',
    ];
    $orgRepo->actualizar(10000, $datosActualizar);
    $orgModificada = $orgRepo->buscarPorId(10000);

    afirmar(
        $orgModificada->telefonoContacto === '+51951112233'
        && $orgModificada->contactoNombre === 'ORLANDO GONZALES MAMANI'
        && $orgModificada->contactoCargo === 'GERENTE GENERAL',
        '05. Actualización relacional de datos institucionales de organización persistida exitosamente'
    );

    // ==============================================================================
    // 6. ACTUALIZACIÓN DE BRANDING EN REPOSITORIO
    // ==============================================================================
    $marcaConfig = [
        'color_primario'   => '#e63946',
        'color_secundario' => '#1d3557',
        'fuente_titulos'   => 'Poppins',
    ];
    $orgRepo->actualizarBranding(10000, '/recursos/subidas/organizaciones/10000/branding/logo_test.png', '/recursos/subidas/organizaciones/10000/branding/isotipo_test.png', $marcaConfig);
    $orgBranding = $orgRepo->buscarPorId(10000);

    afirmar(
        $orgBranding->logoUrl === '/recursos/subidas/organizaciones/10000/branding/logo_test.png'
        && $orgBranding->isotipoUrl === '/recursos/subidas/organizaciones/10000/branding/isotipo_test.png'
        && is_array($orgBranding->marcaConfiguracion)
        && $orgBranding->marcaConfiguracion['color_primario'] === '#e63946',
        '06. Gestión de branding almacena rutas relativas seguras y paleta institucional (sin BLOBs en MySQL)'
    );

    // ==============================================================================
    // 7. DISTINCIÓN FORMAL DE ÁMBITOS: PLATAFORMA VS ORGANIZACIÓN
    // ==============================================================================
    $paramsPlataforma = $configRepo->obtenerParametrosPlataforma();
    $paramsOrganizacion = $configRepo->obtenerParametrosOrganizacion(10000);

    $todosPlataformaSinOrg = true;
    foreach ($paramsPlataforma as $p) {
        if ($p->ambito !== 'PLATAFORMA' || $p->organizacionId !== null) {
            $todosPlataformaSinOrg = false;
        }
    }

    $todosOrgConTenant = true;
    foreach ($paramsOrganizacion as $p) {
        if ($p->ambito !== 'ORGANIZACION' || $p->organizacionId !== 10000) {
            $todosOrgConTenant = false;
        }
    }

    afirmar(
        count($paramsPlataforma) >= 6
        && count($paramsOrganizacion) >= 3
        && $todosPlataformaSinOrg === true
        && $todosOrgConTenant === true,
        '07. Separación formal de ámbitos: PLATAFORMA (organizacion_id IS NULL) y ORGANIZACION (organizacion_id obligatorio)'
    );

    // ==============================================================================
    // 8. CONSTRAINT ESTRUCTURAL MYSQL: chk_param_ambito_org
    // ==============================================================================
    $checkRechazaPlataformaConOrg = false;
    try {
        $stmtChk = $pdo->prepare("INSERT INTO parametros_configuracion (organizacion_id, ambito, codigo, tipo_dato, valor, etiqueta) VALUES (10000, 'PLATAFORMA', 'plataforma.invalido', 'STRING', 'v', 'E')");
        $stmtChk->execute();
    } catch (\PDOException) {
        $checkRechazaPlataformaConOrg = true;
    }

    $checkRechazaOrgSinOrg = false;
    try {
        $stmtChk = $pdo->prepare("INSERT INTO parametros_configuracion (organizacion_id, ambito, codigo, tipo_dato, valor, etiqueta) VALUES (NULL, 'ORGANIZACION', 'organizacion.invalido', 'STRING', 'v', 'E')");
        $stmtChk->execute();
    } catch (\PDOException) {
        $checkRechazaOrgSinOrg = true;
    }

    afirmar(
        $checkRechazaPlataformaConOrg === true && $checkRechazaOrgSinOrg === true,
        '08. MySQL CHECK constraint chk_param_ambito_org bloquea estructuralmente estados cruzados de ámbito'
    );

    // ==============================================================================
    // 9. CONFIGURACIÓN TIPADA: BOOLEAN
    // ==============================================================================
    $boolPlataforma = $configServicio->obtenerPlataforma('plataforma.permitir_registro_publico');
    $boolOrg = $configServicio->obtenerOrganizacion(10000, 'organizacion.notificar_whatsapp');

    afirmar(
        is_bool($boolPlataforma) && $boolPlataforma === false
        && is_bool($boolOrg) && $boolOrg === true,
        '09. Parámetros tipados BOOLEAN retornan tipos nativos booleanos (false / true) convertidos por backend'
    );

    // ==============================================================================
    // 10. CONFIGURACIÓN TIPADA: DECIMAL
    // ==============================================================================
    $montoMinimo = $configServicio->obtenerPlataforma('plataforma.monto_minimo_pago_pe');
    $porcentajeReserva = $configServicio->obtenerOrganizacion(10000, 'organizacion.porcentaje_reserva_minimo');

    afirmar(
        is_float($montoMinimo) && $montoMinimo === 50.0
        && is_float($porcentajeReserva) && $porcentajeReserva === 30.0,
        '10. Parámetros tipados DECIMAL retornan flotantes nativos validados con precisión adecuada'
    );

    // ==============================================================================
    // 11. CONFIGURACIÓN TIPADA: INTEGER
    // ==============================================================================
    $maxLogin = $configServicio->obtenerPlataforma('plataforma.max_intentos_login');
    $diasCotiz = $configServicio->obtenerOrganizacion(10000, 'organizacion.dias_validez_cotizacion');

    afirmar(
        is_int($maxLogin) && $maxLogin === 5
        && is_int($diasCotiz) && $diasCotiz === 7,
        '11. Parámetros tipados INTEGER retornan enteros nativos'
    );

    // ==============================================================================
    // 12. CONFIGURACIÓN TIPADA: STRING
    // ==============================================================================
    $zonaHoraria = $configServicio->obtenerPlataforma('plataforma.zona_horaria');
    $moneda = $configServicio->obtenerPlataforma('plataforma.moneda_principal');

    afirmar(
        is_string($zonaHoraria) && $zonaHoraria === 'America/Lima'
        && is_string($moneda) && $moneda === 'PEN',
        '12. Parámetros tipados STRING retornan cadenas de texto validadas'
    );

    // ==============================================================================
    // 13. CONFIGURACIÓN TIPADA: DATE Y DATETIME
    // ==============================================================================
    $validaDatePass = true;
    $validaDateFail = false;
    try {
        ParametroConfiguracion::validarValorParaTipo('DATE', '2027-02-02');
    } catch (\InvalidArgumentException) {
        $validaDatePass = false;
    }

    try {
        ParametroConfiguracion::validarValorParaTipo('DATE', '02/02/2027');
    } catch (\InvalidArgumentException) {
        $validaDateFail = true;
    }

    $validaDateTimePass = true;
    $validaDateTimeFail = false;
    try {
        ParametroConfiguracion::validarValorParaTipo('DATETIME', '2027-02-02 08:30:00');
    } catch (\InvalidArgumentException) {
        $validaDateTimePass = false;
    }

    try {
        ParametroConfiguracion::validarValorParaTipo('DATETIME', '2027-02-02T08:30:00Z');
    } catch (\InvalidArgumentException) {
        $validaDateTimeFail = true;
    }

    afirmar(
        $validaDatePass && $validaDateFail && $validaDateTimePass && $validaDateTimeFail,
        '13. Tipado DATE (YYYY-MM-DD) y DATETIME (YYYY-MM-DD HH:MM:SS) estrictamente validado'
    );

    // ==============================================================================
    // 14. VALIDACIÓN DE REGLAS NUMÉRICAS (MIN Y MAX)
    // ==============================================================================
    $reglaRangoMinFalla = false;
    try {
        ParametroConfiguracion::validarValorParaTipo('INTEGER', '1', ['min' => 3, 'max' => 10]);
    } catch (\InvalidArgumentException) {
        $reglaRangoMinFalla = true;
    }

    $reglaRangoMaxFalla = false;
    try {
        ParametroConfiguracion::validarValorParaTipo('INTEGER', '25', ['min' => 3, 'max' => 10]);
    } catch (\InvalidArgumentException) {
        $reglaRangoMaxFalla = true;
    }

    $reglaRangoValido = true;
    try {
        ParametroConfiguracion::validarValorParaTipo('INTEGER', '5', ['min' => 3, 'max' => 10]);
    } catch (\InvalidArgumentException) {
        $reglaRangoValido = false;
    }

    afirmar(
        $reglaRangoMinFalla && $reglaRangoMaxFalla && $reglaRangoValido,
        '14. Reglas de validación numérica (mínimo, máximo) aplicadas rigurosamente'
    );

    // ==============================================================================
    // 15. VALIDACIÓN DE REGLAS DE OPCIONES PERMITIDAS
    // ==============================================================================
    $opcionInvalidaFalla = false;
    try {
        ParametroConfiguracion::validarValorParaTipo('STRING', 'Europe/Madrid', ['opciones' => ['America/Lima', 'UTC']]);
    } catch (\InvalidArgumentException) {
        $opcionInvalidaFalla = true;
    }

    $opcionValidaPass = true;
    try {
        ParametroConfiguracion::validarValorParaTipo('STRING', 'America/Lima', ['opciones' => ['America/Lima', 'UTC']]);
    } catch (\InvalidArgumentException) {
        $opcionValidaPass = false;
    }

    afirmar(
        $opcionInvalidaFalla && $opcionValidaPass,
        '15. Reglas de lista blanca de opciones permitidas aplicadas rigurosamente'
    );

    // ==============================================================================
    // 16. PROHIBICIÓN ESTRICTA DE SECRETOS TÉCNICOS EN CONFIGURACIÓN
    // ==============================================================================
    $bloqueoSecretPassword = false;
    try {
        new ParametroConfiguracion(
            id: null, organizacionId: null, ambito: 'PLATAFORMA',
            codigo: 'plataforma.db_password', tipoDato: 'STRING', valor: 'secreto123'
        );
    } catch (\InvalidArgumentException) {
        $bloqueoSecretPassword = true;
    }

    $bloqueoSecretApiKey = false;
    try {
        new ParametroConfiguracion(
            id: null, organizacionId: null, ambito: 'PLATAFORMA',
            codigo: 'plataforma.api_key_pasarela', tipoDato: 'STRING', valor: 'key_123'
        );
    } catch (\InvalidArgumentException) {
        $bloqueoSecretApiKey = true;
    }

    afirmar(
        $bloqueoSecretPassword === true && $bloqueoSecretApiKey === true,
        '16. Mandato de Seguridad: Prohibido almacenar contraseñas, API keys o secretos en tablas de configuración'
    );

    // ==============================================================================
    // 17. GOBERNANZA: RECHAZO DE CLAVES DESCONOCIDAS EN RUNTIME
    // ==============================================================================
    $usrSuper = $usuarioRepo->buscarPorNombreUsuario('orlando');
    $ctxOrlando = ContextoOperacion::paraHumano($usrSuper->id, 1, 'APP', '127.0.0.1', 'CLI-Tester', 10000);

    // Preparar superadmin para operaciones soberanas de plataforma
    $stmtSuper = $pdo->query("SELECT id FROM usuarios WHERE es_superadmin_plataforma = 1 LIMIT 1");
    $superId = (int) $stmtSuper->fetchColumn();
    if (!$superId) {
        $superId = $usrSuper->id;
        $stmtSuperRol = $pdo->query("SELECT id FROM roles WHERE nombre = 'superadmin_plataforma'");
        $rolSuperId = (int) $stmtSuperRol->fetchColumn();
        if ($rolSuperId > 0) {
            $rolRepo->asignarRolAUsuario($superId, $rolSuperId);
        }
        $pdo->prepare("UPDATE usuarios SET es_superadmin_plataforma = 1 WHERE id = :id")->execute([':id' => $superId]);
    }
    $ctxSuper = ContextoOperacion::paraHumano($superId, 1, 'APP', '127.0.0.1', 'CLI-Tester', 10000);

    $claveInexistenteFalla = false;
    try {
        $configServicio->actualizarPlataforma('plataforma.clave_no_gobernada_fantasma', 'valor', $superId, $ctxSuper);
    } catch (\InvalidArgumentException) {
        $claveInexistenteFalla = true;
    }

    afirmar(
        $claveInexistenteFalla === true,
        '17. Catálogo Gobernado: Código en runtime no puede inventar nuevas claves de configuración arbitrarias'
    );

    // ==============================================================================
    // 18. CACHÉ EN MEMORIA DE PROCESO
    // ==============================================================================
    $configServicio->limpiarCache();
    $lectura1 = $configServicio->obtenerPlataforma('plataforma.monto_minimo_pago_pe');

    // Modificamos directamente en BD sin pasar por el servicio
    $pdo->prepare("UPDATE parametros_configuracion SET valor = '99.00' WHERE codigo = 'plataforma.monto_minimo_pago_pe'")->execute();

    // Segunda lectura debe responder desde la caché en memoria con el valor anterior
    $lectura2Cache = $configServicio->obtenerPlataforma('plataforma.monto_minimo_pago_pe');

    afirmar(
        $lectura1 === 50.0 && $lectura2Cache === 50.0,
        '18. Caché de proceso optimiza lecturas frecuentes evitando roundtrips a la base de datos'
    );

    // ==============================================================================
    // 19. INVALIDACIÓN INMEDIATA DE CACHÉ TRAS ACTUALIZACIÓN
    // ==============================================================================
    $configServicio->actualizarPlataforma('plataforma.monto_minimo_pago_pe', '75.00', $superId, $ctxSuper);
    $lecturaPostActualizacion = $configServicio->obtenerPlataforma('plataforma.monto_minimo_pago_pe');

    afirmar(
        $lecturaPostActualizacion === 75.0,
        '19. Actualización formal mediante servicio invalida inmediatamente la caché y entrega el nuevo valor'
    );

    // ==============================================================================
    // 20. AISLAMIENTO MULTI-TENANT EN PARÁMETROS DE ORGANIZACIÓN
    // ==============================================================================
    // Crear tenant beta temporal
    $pdo->exec("INSERT INTO organizaciones (id, codigo, nombre_comercial, razon_social, estado) VALUES (20000, 'tenant_beta', 'BETA', 'BETA SAC', 'ACTIVO')");
    $pdo->exec("INSERT INTO parametros_configuracion (organizacion_id, ambito, codigo, tipo_dato, valor, etiqueta) VALUES (20000, 'ORGANIZACION', 'organizacion.notificar_whatsapp', 'BOOLEAN', '0', 'Notif')");

    $notif10000 = $configServicio->obtenerOrganizacion(10000, 'organizacion.notificar_whatsapp');
    $notif20000 = $configServicio->obtenerOrganizacion(20000, 'organizacion.notificar_whatsapp');

    afirmar(
        $notif10000 === true && $notif20000 === false,
        '20. Aislamiento Multi-Tenant: Parámetros del Tenant A no interfieren con los parámetros del Tenant B'
    );

    // ==============================================================================
    // 21. RBAC: SUPERADMIN PUEDE ACTUALIZAR PLATAFORMA Y ORGANIZACIÓN
    // ==============================================================================
    $superPuedePlataforma = true;
    try {
        $configServicio->actualizarPlataforma('plataforma.max_intentos_login', '6', $superId, $ctxOrlando);
    } catch (\Throwable) {
        $superPuedePlataforma = false;
    }

    afirmar(
        $superPuedePlataforma === true
        && $configServicio->obtenerPlataforma('plataforma.max_intentos_login') === 6,
        '21. RBAC: Superadministrador de Plataforma cuenta con autorización plena para actualizar parámetros soberanos'
    );

    // ==============================================================================
    // 22. RBAC: ADMIN DE ORGANIZACIÓN BLOQUEADO DE PLATAFORMA
    // ==============================================================================
    // Crear persona y usuario admin de organización sin superadmin
    $perAdminPuro = new \Aplicacion\Entidades\Persona(
        id: null,
        organizacionId: 10000,
        tipoPersona: 'NATURAL',
        tipoDocumentoId: 1,
        numeroDocumento: '79998888',
        nombres: 'Admin',
        apellidos: 'Puro',
        estado: 'ACTIVO'
    );
    $perAdminPuroId = $personaRepo->crear($perAdminPuro);

    $stmtAdminOrg = $pdo->prepare("INSERT INTO usuarios (organizacion_id, persona_id, nombre_usuario, nombre_completo, correo_electronico, contrasena_hash, es_superadmin_plataforma, estado)
                                   VALUES (10000, :persona_id, 'admin_puro', 'Admin Puro', 'puro@test.com', 'hash', 0, 'ACTIVO')");
    $stmtAdminOrg->execute([':persona_id' => $perAdminPuroId]);
    $adminPuroId = (int) $pdo->lastInsertId();
    $rolRepo->asignarRolAUsuario($adminPuroId, 2); // admin_organizacion

    $adminBloqueadoDePlataforma = false;
    try {
        $configServicio->actualizarPlataforma('plataforma.max_intentos_login', '8', $adminPuroId, $ctxOrlando);
    } catch (AccesoDenegadoExcepcion) {
        $adminBloqueadoDePlataforma = true;
    }

    afirmar(
        $adminBloqueadoDePlataforma === true,
        '22. RBAC: Administrador de Organización es rechazado (AccesoDenegadoExcepcion) al intentar modificar parámetros de Plataforma'
    );

    // ==============================================================================
    // 23. AUDITORÍA INMUTABLE REGISTRA MODIFICACIONES DE CONFIGURACIÓN
    // ==============================================================================
    $stmtUltimaAudit = $pdo->query("SELECT * FROM auditoria_operaciones WHERE modulo = 'configuracion' ORDER BY id DESC LIMIT 1");
    $auditFila = $stmtUltimaAudit->fetch(PDO::FETCH_ASSOC);

    afirmar(
        $auditFila !== false
        && $auditFila['modulo'] === 'configuracion'
        && $auditFila['accion'] === 'ACTUALIZAR_CONFIGURACION_PLATAFORMA'
        && $auditFila['entidad_tipo'] === 'parametro_configuracion'
        && !empty($auditFila['datos_previos_json'])
        && !empty($auditFila['datos_nuevos_json']),
        '23. Pista de auditoría inmutable registra cambios de configuración con valores anteriores y nuevos'
    );

    // ==============================================================================
    // 24. CENSURA DE SECRETOS EN AUDITORÍA DE CONFIGURACIÓN
    // ==============================================================================
    $auditDatosNuevos = (string) $auditFila['datos_nuevos_json'];
    $auditDatosPrevios = (string) $auditFila['datos_previos_json'];

    afirmar(
        !str_contains($auditDatosNuevos, 'password')
        && !str_contains($auditDatosNuevos, 'secret')
        && !str_contains($auditDatosPrevios, 'password')
        && !str_contains($auditDatosPrevios, 'secret'),
        '24. Auditoría de configuración se encuentra 100% libre de secretos técnicos'
    );

    // ==============================================================================
    // 25. POLÍTICAS DE BRANDING Y GESTIÓN DE ARCHIVOS
    // ==============================================================================
    $mimeValido = true;
    try {
        BrandingServicio::validarArchivo('image/png', 500000, 'png');
        BrandingServicio::validarArchivo('image/webp', 300000, 'webp');
        BrandingServicio::validarArchivo('image/svg+xml', 150000, 'svg');
    } catch (\Throwable) {
        $mimeValido = false;
    }

    $mimeInvalidoFalla = false;
    try {
        BrandingServicio::validarArchivo('application/pdf', 500000, 'pdf');
    } catch (\InvalidArgumentException) {
        $mimeInvalidoFalla = true;
    }

    $pesoExcedidoFalla = false;
    try {
        BrandingServicio::validarArchivo('image/png', 3000000, 'png'); // 3 MB > 2 MB
    } catch (\InvalidArgumentException) {
        $pesoExcedidoFalla = true;
    }

    $nombreLogo = BrandingServicio::generarNombreArchivo('logo', 'png');
    $rutaRelativa = BrandingServicio::generarRutaRelativa(10000, $nombreLogo);

    afirmar(
        $mimeValido === true
        && $mimeInvalidoFalla === true
        && $pesoExcedidoFalla === true
        && str_starts_with($nombreLogo, 'logo_')
        && str_ends_with($nombreLogo, '.png')
        && str_contains($rutaRelativa, '/recursos/subidas/organizaciones/10000/branding/'),
        '25. Branding: Políticas de tipos MIME, tamaño máximo (2MB), nomenclatura segura y rutas relativas verificadas'
    );

} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

echo "\n==============================================================================\n";
echo "RESULTADO FINAL F1.2A: {$exitos} PRUEBAS EXITOSAS / {$fallos} FALLOS\n";
echo "==============================================================================\n";

if ($fallos > 0) {
    exit(1);
}
