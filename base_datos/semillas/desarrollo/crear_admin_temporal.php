<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * CANDELARIAAPP - SCRIPT DE DESARROLLO LOCAL: CREACIÓN DE ADMINISTRADOR TEMPORAL
 * ==============================================================================
 * Exclusivo para entornos locales de desarrollo/pruebas.
 * Genera una contraseña aleatoria de alta entropía, la almacena mediante
 * password_hash() y la muestra UNA SOLA VEZ en consola.
 * Vincula la cuenta a Persona, Tenant y Rol mediante RBAC real (sin bypasses).
 * ==============================================================================
 */

require_once dirname(__DIR__, 3) . '/vendor/autoload.php';
\Nucleo\Soporte\CargadorEntorno::cargar(dirname(__DIR__, 3) . '/.env');
require_once dirname(__DIR__, 3) . '/nucleo/Soporte/ayudantes.php';

use Aplicacion\Entidades\Persona;
use Aplicacion\Entidades\Usuario;
use Aplicacion\Repositorios\PersonaRepositorio;
use Aplicacion\Repositorios\RolRepositorio;
use Aplicacion\Repositorios\UsuarioRepositorio;
use Nucleo\BaseDatos\Conexion;

// Protección estricta de entorno: Prohibido en producción
$entornoActual = (string) entorno('APP_ENV', 'desarrollo');
if ($entornoActual === 'produccion') {
    fwrite(STDERR, "ERROR: La creación de cuentas temporales de prueba está terminantemente prohibida en producción.\n");
    exit(1);
}

$pdo = Conexion::obtenerInstancia();

// 1. Asegurar Tenant de Desarrollo Local en `organizaciones`
$stmtOrg = $pdo->prepare("SELECT `id` FROM `organizaciones` WHERE `codigo` = :codigo LIMIT 1");
$stmtOrg->execute([':codigo' => 'og_estudio']);
$orgId = $stmtOrg->fetchColumn();

if (!$orgId) {
    $sqlOrg = "INSERT INTO `organizaciones` (
                    `codigo`, `nombre_comercial`, `razon_social`, `numero_documento`,
                    `correo_contacto`, `telefono_contacto`, `estado`
                ) VALUES (
                    'og_estudio', 'O.G. ESTUDIO CREATIVO', 'O.G. ESTUDIO CREATIVO S.A.C.',
                    '20601234567', 'contacto@ogestudiocreativo.com', '+51951000000', 'ACTIVO'
                )";
    $pdo->exec($sqlOrg);
    $orgId = (int) $pdo->lastInsertId();
} else {
    $orgId = (int) $orgId;
}

// 2. Asegurar Persona de Orlando Gonzales en `personas`
$personaRepo = new PersonaRepositorio($pdo);
$stmtPer = $pdo->prepare("SELECT `id` FROM `personas` WHERE `organizacion_id` = :org_id AND `numero_documento` = :doc LIMIT 1");
$stmtPer->execute([':org_id' => $orgId, ':doc' => '40123456']);
$personaId = $stmtPer->fetchColumn();

if (!$personaId) {
    $persona = new Persona(
        id: null,
        organizacionId: $orgId,
        tipoPersona: 'NATURAL',
        tipoDocumentoId: 1, // DNI
        numeroDocumento: '40123456',
        nombres: 'Orlando',
        apellidos: 'Gonzales',
        correoElectronico: 'orlando@ogestudiocreativo.com',
        telefonoWhatsapp: '+51951234567',
        ciudad: 'Puno',
        codigoPais: 'PE',
        estado: 'ACTIVO'
    );
    $personaId = $personaRepo->crear($persona);
} else {
    $personaId = (int) $personaId;
}

// 3. Generación aleatoria criptográfica de contraseña temporal
// 14 caracteres con prefijo, entropía hexadecimal y caracteres especiales
$bytesAleatorios = bin2hex(random_bytes(4));
$contrasenaTemporal = 'Cand26!' . $bytesAleatorios . '$';
$contrasenaHash = password_hash($contrasenaTemporal, PASSWORD_DEFAULT);

// 4. Crear o Actualizar Cuenta de Usuario `orlando`
$usuarioRepo = new UsuarioRepositorio($pdo);
$usuarioExistente = $usuarioRepo->buscarPorNombreUsuario('orlando');

if ($usuarioExistente === null) {
    $nuevoUsuario = new Usuario(
        id: null,
        organizacionId: $orgId,
        personaId: $personaId,
        nombreUsuario: 'orlando',
        nombreCompleto: 'Orlando Gonzales',
        correoElectronico: 'orlando@ogestudiocreativo.com',
        contrasenaHash: $contrasenaHash,
        esSuperadminPlataforma: false, // Se asigna rol formal mediante RBAC en usuario_roles
        estado: 'ACTIVO'
    );
    $usuarioId = $usuarioRepo->crear($nuevoUsuario);
} else {
    $usuarioId = $usuarioExistente->id;
    $usuarioRepo->actualizarContrasenaHash($usuarioId, $contrasenaHash);
    $usuarioRepo->actualizarEstado($usuarioId, 'ACTIVO');
    $usuarioRepo->restablecerIntentosFallidos($usuarioId);
}

// 5. Asignar Rol Administrativo Real mediante RBAC (`admin_organizacion`)
$rolRepo = new RolRepositorio($pdo);
$rolAdmin = $rolRepo->buscarPorCodigo('admin_organizacion');

if ($rolAdmin === null) {
    fwrite(STDERR, "ERROR: El rol canónico 'admin_organizacion' no existe en la base de datos.\n");
    exit(1);
}

$rolRepo->sincronizarRolesUsuario($usuarioId, [$rolAdmin->id]);

// 6. Reportar salida UNA SOLA VEZ por consola para el usuario
echo "\n============================================================\n";
echo "ACCESO TEMPORAL DE DESARROLLO\n";
echo "============================================================\n";
echo "Usuario:              orlando\n";
echo "Contraseña temporal:  {$contrasenaTemporal}\n";
echo "Entorno:              DESARROLLO LOCAL\n";
echo "Organización:         {$orgId} (O.G. ESTUDIO CREATIVO)\n";
echo "Rol:                  {$rolAdmin->nombre} ({$rolAdmin->codigo})\n";
echo "Estado:               ACTIVO\n";
echo "============================================================\n\n";
