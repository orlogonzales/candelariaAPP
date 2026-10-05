<?php

declare(strict_types=1);

namespace Aplicacion\Controladores;

use Aplicacion\Entidades\Persona;
use Aplicacion\Entidades\Usuario;
use Aplicacion\Repositorios\AuditoriaRepositorio;
use Aplicacion\Repositorios\PersonaRepositorio;
use Aplicacion\Repositorios\RolRepositorio;
use Aplicacion\Repositorios\SesionRepositorio;
use Aplicacion\Repositorios\UsuarioRepositorio;
use Nucleo\BaseDatos\Conexion;
use Nucleo\Http\ContextoOperacion;
use Nucleo\Http\Middleware\AutenticacionMiddleware;
use Nucleo\Http\Middleware\AutorizacionMiddleware;
use Nucleo\Seguridad\ManejadorCookie;
use Nucleo\Seguridad\ProtectorCsrf;
use PDO;

/**
 * Controlador API oficial para gestión asíncrona de usuarios y control de acceso RBAC.
 * Gobernanza: Cero eliminación física, validación estricta de permisos en backend y CSRF.
 */
class UsuarioControlador
{
    private AutenticacionMiddleware $authMiddleware;
    private AutorizacionMiddleware $authzMiddleware;
    private UsuarioRepositorio $usuarioRepo;
    private PersonaRepositorio $personaRepo;
    private RolRepositorio $rolRepo;
    private SesionRepositorio $sesionRepo;
    private AuditoriaRepositorio $auditoriaRepo;
    private PDO $pdo;

    public function __construct(
        ?AutenticacionMiddleware $authMiddleware = null,
        ?AutorizacionMiddleware $authzMiddleware = null,
        ?UsuarioRepositorio $usuarioRepo = null,
        ?PersonaRepositorio $personaRepo = null,
        ?RolRepositorio $rolRepo = null,
        ?SesionRepositorio $sesionRepo = null,
        ?AuditoriaRepositorio $auditoriaRepo = null,
        ?PDO $pdo = null
    ) {
        $this->pdo = $pdo ?? Conexion::obtenerInstancia();
        $this->authMiddleware = $authMiddleware ?? new AutenticacionMiddleware();
        $this->authzMiddleware = $authzMiddleware ?? new AutorizacionMiddleware();
        $this->usuarioRepo = $usuarioRepo ?? new UsuarioRepositorio($this->pdo);
        $this->personaRepo = $personaRepo ?? new PersonaRepositorio($this->pdo);
        $this->rolRepo = $rolRepo ?? new RolRepositorio($this->pdo);
        $this->sesionRepo = $sesionRepo ?? new SesionRepositorio($this->pdo);
        $this->auditoriaRepo = $auditoriaRepo ?? new AuditoriaRepositorio($this->pdo);
    }

    /**
     * GET /api/v1/usuarios
     * Retorna el padrón de usuarios con datos de personas y roles para DataTables.
     */
    public function listar(): string
    {
        $contexto = $this->verificarSesionYPermiso('usuarios.ver');
        $orgId = $contexto->organizacionId ?? 1;

        $usuarios = $this->usuarioRepo->obtenerTodosConDetalles($orgId);

        return $this->responderJson(true, 200, 'Padrón de usuarios obtenido exitosamente.', [
            'total'    => count($usuarios),
            'usuarios' => $usuarios,
        ]);
    }

    /**
     * GET /api/v1/usuarios/{id}
     * Retorna la ficha completa de un usuario específico.
     */
    public function detalle(string $id): string
    {
        $contexto = $this->verificarSesionYPermiso('usuarios.ver');
        $idInt = (int) $id;

        $usuario = $this->usuarioRepo->obtenerDetallePorId($idInt);
        if ($usuario === null) {
            return $this->responderJson(false, 404, 'Usuario no encontrado.');
        }

        // H-03: Aislamiento Multi-tenant IDOR (404 uniforme si es de otra organización)
        if ($contexto->usuarioId !== null) {
            if (!$this->authzMiddleware->verificarAlcanceOrganizacion($contexto->usuarioId, (int) $usuario['organizacion_id'])) {
                return $this->responderJson(false, 404, 'Usuario no encontrado.');
            }
        }

        return $this->responderJson(true, 200, 'Detalle de usuario obtenido.', [
            'usuario' => $usuario,
        ]);
    }

    /**
     * GET /api/v1/personas/disponibles
     * Retorna las personas de la organización que no tienen usuario vinculado.
     */
    public function personasDisponibles(): string
    {
        $contexto = $this->verificarSesionYPermiso('usuarios.crear');
        $orgId = $contexto->organizacionId ?? 1;

        $personas = $this->personaRepo->buscarDisponiblesSinUsuario($orgId);

        return $this->responderJson(true, 200, 'Personas disponibles obtenidas.', [
            'total'    => count($personas),
            'personas' => $personas,
        ]);
    }

    /**
     * GET /api/v1/tipos-documento
     * Retorna los tipos de documento activos.
     */
    public function tiposDocumento(): string
    {
        $this->verificarSesionYPermiso('usuarios.crear');

        $stmt = $this->pdo->query("SELECT id, codigo, nombre, aplica_a, longitud_exacta, es_alfanumerico FROM tipos_documento WHERE activo = 1 ORDER BY id ASC");
        $tipos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $this->responderJson(true, 200, 'Tipos de documento obtenidos.', [
            'tipos_documento' => $tipos,
        ]);
    }

    /**
     * GET /api/v1/roles
     * Retorna el catálogo de roles disponibles filtrados según rango del operador (H-04).
     */
    public function roles(): string
    {
        $contexto = $this->verificarSesionYPermiso('usuarios.roles');
        $operadorId = $contexto->usuarioId ?? 0;
        $roles = $this->authzMiddleware->obtenerRolesAsignables($operadorId, $contexto->organizacionId);

        return $this->responderJson(true, 200, 'Catálogo de roles obtenido.', [
            'roles' => array_map(fn($r) => [
                'id'          => $r->id,
                'codigo'      => $r->codigo,
                'nombre'      => $r->nombre,
                'descripcion' => $r->descripcion,
            ], $roles),
        ]);
    }

    /**
     * POST /api/v1/usuarios
     * Crea un nuevo usuario vinculado a una persona existente o nueva, y asigna roles.
     */
    public function crear(): string
    {
        $contexto = $this->verificarSesionYPermiso('usuarios.crear');
        $this->verificarCsrf($contexto);

        $datos = $this->obtenerCuerpo();
        $orgId = $contexto->organizacionId ?? 1;

        // 1. Validar campos requeridos de usuario
        $nombreUsuario = normalizar_minusculas(trim((string) ($datos['nombre_usuario'] ?? '')));
        $correo = normalizar_minusculas(trim((string) ($datos['correo_electronico'] ?? '')));
        $contrasena = (string) ($datos['contrasena'] ?? '');

        if (empty($nombreUsuario) || strlen($nombreUsuario) < 3) {
            return $this->responderJson(false, 400, 'El nombre de usuario es obligatorio (mínimo 3 caracteres).');
        }
        if (!preg_match('/^[a-z0-9_.\-]+$/', $nombreUsuario)) {
            return $this->responderJson(false, 400, 'El nombre de usuario solo permite caracteres alfanuméricos, guiones y puntos.');
        }
        if (empty($correo) || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            return $this->responderJson(false, 400, 'Debe ingresar un correo electrónico válido.');
        }
        if (empty($contrasena) || strlen($contrasena) < 8) {
            return $this->responderJson(false, 400, 'La contraseña debe contener al menos 8 caracteres.');
        }

        $roles = !empty($datos['roles']) && is_array($datos['roles'])
            ? array_map('intval', $datos['roles'])
            : [];

        // H-02: Validación temprana de privilegios para impedir escalamiento
        if (!empty($roles) && !$this->authzMiddleware->puedeAsignarRoles($contexto->usuarioId ?? 0, $roles)) {
            return $this->responderJson(false, 403, 'Acceso denegado: no tiene privilegios para asignar uno o más de los roles especificados.');
        }

        $transaccionPropia = false;
        try {
            // 2. Validar unicidad de login y correo
            if ($this->usuarioRepo->buscarPorNombreUsuario($nombreUsuario) !== null) {
                return $this->responderJson(false, 400, 'El nombre de usuario ya está registrado en el sistema.');
            }
            if ($this->usuarioRepo->buscarPorCorreo($correo) !== null) {
                return $this->responderJson(false, 400, 'El correo electrónico ya está registrado en el sistema.');
            }

            // 3. Resolver Persona
            $modoPersona = (string) ($datos['modo_persona'] ?? 'existente');
            $personaId = null;
            $nombreCompleto = '';

            if (!$this->pdo->inTransaction()) {
                $this->pdo->beginTransaction();
                $transaccionPropia = true;
            }

            if ($modoPersona === 'nueva') {
                $tipoPersona = strtoupper(trim((string) ($datos['tipo_persona'] ?? 'NATURAL')));
                $tipoDocId = (int) ($datos['tipo_documento_id'] ?? 1);
                $numDoc = trim((string) ($datos['numero_documento'] ?? ''));

                if (empty($numDoc)) {
                    if ($transaccionPropia && $this->pdo->inTransaction()) {
                        $this->pdo->rollBack();
                    }
                    return $this->responderJson(false, 400, 'El número de documento de la persona es obligatorio.');
                }

                // Verificar si ya existe persona con ese documento en el tenant
                if ($this->personaRepo->buscarPorDocumento($orgId, $tipoDocId, $numDoc) !== null) {
                    if ($transaccionPropia && $this->pdo->inTransaction()) {
                        $this->pdo->rollBack();
                    }
                    return $this->responderJson(false, 400, 'Ya existe una persona registrada con ese tipo y número de documento.');
                }

                $nombres = !empty($datos['nombres']) ? trim((string) $datos['nombres']) : null;
                $apellidos = !empty($datos['apellidos']) ? trim((string) $datos['apellidos']) : null;
                $razonSocial = !empty($datos['razon_social']) ? trim((string) $datos['razon_social']) : null;
                $nombreComercial = !empty($datos['nombre_comercial']) ? trim((string) $datos['nombre_comercial']) : null;

                if ($tipoPersona === 'NATURAL') {
                    if (empty($nombres) || empty($apellidos)) {
                        if ($transaccionPropia && $this->pdo->inTransaction()) {
                            $this->pdo->rollBack();
                        }
                        return $this->responderJson(false, 400, 'Nombres y apellidos son obligatorios para persona natural.');
                    }
                    $nombreCompleto = normalizar_mayusculas("{$nombres} {$apellidos}");
                } elseif ($tipoPersona === 'JURIDICA') {
                    if (empty($razonSocial)) {
                        if ($transaccionPropia && $this->pdo->inTransaction()) {
                            $this->pdo->rollBack();
                        }
                        return $this->responderJson(false, 400, 'La razón social es obligatoria para persona jurídica.');
                    }
                    $nombreCompleto = normalizar_mayusculas($nombreComercial ? "{$razonSocial} ({$nombreComercial})" : $razonSocial);
                } else {
                    if ($transaccionPropia && $this->pdo->inTransaction()) {
                        $this->pdo->rollBack();
                    }
                    return $this->responderJson(false, 400, 'Tipo de persona inválido.');
                }

                $persona = new Persona(
                    id: null,
                    organizacionId: $orgId,
                    tipoPersona: $tipoPersona,
                    tipoDocumentoId: $tipoDocId,
                    numeroDocumento: $numDoc,
                    nombres: $nombres,
                    apellidos: $apellidos,
                    razonSocial: $razonSocial,
                    nombreComercial: $nombreComercial,
                    correoElectronico: $correo,
                    telefonoMovil: $datos['telefono_whatsapp'] ?? null,
                    telefonoWhatsapp: $datos['telefono_whatsapp'] ?? null,
                    direccion: $datos['direccion'] ?? null,
                    ciudad: $datos['ciudad'] ?? null,
                    codigoPais: strtoupper(trim((string) ($datos['codigo_pais'] ?? 'PE'))),
                    estado: 'ACTIVO'
                );

                $personaId = $this->personaRepo->crear($persona);
            } else {
                $personaId = (int) ($datos['persona_id'] ?? 0);
                if ($personaId <= 0) {
                    if ($transaccionPropia && $this->pdo->inTransaction()) {
                        $this->pdo->rollBack();
                    }
                    return $this->responderJson(false, 400, 'Debe seleccionar una persona existente válida.');
                }

                $personaExistente = $this->personaRepo->buscarPorId($personaId);
                if ($personaExistente === null || $personaExistente->organizacionId !== $orgId) {
                    if ($transaccionPropia && $this->pdo->inTransaction()) {
                        $this->pdo->rollBack();
                    }
                    return $this->responderJson(false, 404, 'La persona seleccionada no existe en la organización.');
                }

                // Verificar que no tenga ya un usuario asignado (restricción uk_usuarios_persona)
                if ($this->usuarioRepo->buscarPorPersonaId($personaId) !== null) {
                    if ($transaccionPropia && $this->pdo->inTransaction()) {
                        $this->pdo->rollBack();
                    }
                    return $this->responderJson(false, 400, 'La persona seleccionada ya cuenta con un usuario asignado.');
                }

                $nombreCompleto = !empty($datos['nombre_completo'])
                    ? normalizar_mayusculas(trim((string) $datos['nombre_completo']))
                    : normalizar_mayusculas($personaExistente->obtenerNombreCompleto());
            }

            // 4. Crear registro en usuarios
            $hash = password_hash($contrasena, PASSWORD_DEFAULT);
            $usuario = new Usuario(
                id: null,
                organizacionId: $orgId,
                personaId: $personaId,
                nombreUsuario: $nombreUsuario,
                nombreCompleto: $nombreCompleto,
                correoElectronico: $correo,
                contrasenaHash: $hash,
                telefonoWhatsapp: !empty($datos['telefono_whatsapp']) ? trim((string) $datos['telefono_whatsapp']) : null,
                estado: in_array(($datos['estado'] ?? 'ACTIVO'), ['ACTIVO', 'INACTIVO', 'BLOQUEADO'], true) ? $datos['estado'] : 'ACTIVO'
            );

            $usuarioId = $this->usuarioRepo->crear($usuario);

            // 5. Asignar roles seleccionados (previamente validados)
            if (!empty($roles)) {
                $this->rolRepo->sincronizarRolesUsuario($usuarioId, $roles);
            }

            // 6. Auditar creación
            $this->auditoriaRepo->registrar(
                contexto: $contexto,
                modulo: 'usuarios',
                accion: 'CREAR_USUARIO',
                entidadTipo: 'USUARIO',
                entidadId: (string) $usuarioId,
                datosPrevios: null,
                datosNuevos: [
                    'nombre_usuario'  => $nombreUsuario,
                    'nombre_completo' => $nombreCompleto,
                    'correo'          => $correo,
                    'persona_id'      => $personaId,
                    'roles'           => $roles,
                    'estado'          => $usuario->estado,
                ]
            );

            if ($transaccionPropia && $this->pdo->inTransaction()) {
                $this->pdo->commit();
            }

            return $this->responderJson(true, 201, 'Usuario creado exitosamente.', [
                'id'             => $usuarioId,
                'nombre_usuario' => $nombreUsuario,
            ]);
        } catch (\Throwable $e) {
            if ($transaccionPropia && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return $this->responderJson(false, 500, 'Error interno al registrar usuario.');
        }
    }

    /**
     * PUT /api/v1/usuarios/{id}
     * Actualiza atributos mutables del usuario.
     */
    public function actualizar(string $id): string
    {
        $contexto = $this->verificarSesionYPermiso('usuarios.editar');
        $this->verificarCsrf($contexto);

        $idInt = (int) $id;
        $usuario = $this->obtenerUsuarioAutorizado($idInt, $contexto);
        if ($usuario === null) {
            return $this->responderJson(false, 404, 'Usuario no encontrado.');
        }

        $datos = $this->obtenerCuerpo();
        $cambios = [];

        if (!empty($datos['nombre_completo'])) {
            $cambios['nombre_completo'] = trim((string) $datos['nombre_completo']);
        }

        if (!empty($datos['correo_electronico'])) {
            $nuevoCorreo = normalizar_minusculas(trim((string) $datos['correo_electronico']));
            if (!filter_var($nuevoCorreo, FILTER_VALIDATE_EMAIL)) {
                return $this->responderJson(false, 400, 'El formato del correo electrónico es inválido.');
            }
            if ($nuevoCorreo !== $usuario->correoElectronico) {
                $otroUsuario = $this->usuarioRepo->buscarPorCorreo($nuevoCorreo);
                if ($otroUsuario !== null && $otroUsuario->id !== $idInt) {
                    return $this->responderJson(false, 400, 'El correo electrónico ya está registrado por otro usuario.');
                }
                $cambios['correo_electronico'] = $nuevoCorreo;
            }
        }

        if (array_key_exists('telefono_whatsapp', $datos)) {
            $cambios['telefono_whatsapp'] = !empty($datos['telefono_whatsapp']) ? trim((string) $datos['telefono_whatsapp']) : null;
        }

        if (array_key_exists('avatar_url', $datos)) {
            $cambios['avatar_url'] = !empty($datos['avatar_url']) ? trim((string) $datos['avatar_url']) : null;
        }

        if (!empty($datos['estado']) && in_array($datos['estado'], ['ACTIVO', 'INACTIVO', 'BLOQUEADO'], true)) {
            if ($idInt === $contexto->usuarioId && $datos['estado'] !== 'ACTIVO') {
                return $this->responderJson(false, 400, 'No puede desactivar su propia cuenta en uso.');
            }
            $cambios['estado'] = $datos['estado'];
        }

        if (empty($cambios)) {
            return $this->responderJson(true, 200, 'No se detectaron modificaciones en los datos del usuario.');
        }

        $previos = [
            'nombre_completo'    => $usuario->nombreCompleto,
            'correo_electronico' => $usuario->correoElectronico,
            'telefono_whatsapp'  => $usuario->telefonoWhatsapp,
            'avatar_url'         => $usuario->avatarUrl,
            'estado'             => $usuario->estado,
        ];

        $this->usuarioRepo->actualizar($idInt, $cambios);

        // Si el estado cambió a no activo, revocar sesiones
        if (isset($cambios['estado']) && $cambios['estado'] !== 'ACTIVO') {
            $this->sesionRepo->revocarTodasDeUsuario($idInt);
        }

        $this->auditoriaRepo->registrar(
            contexto: $contexto,
            modulo: 'usuarios',
            accion: 'EDITAR_USUARIO',
            entidadTipo: 'USUARIO',
            entidadId: (string) $idInt,
            datosPrevios: $previos,
            datosNuevos: $cambios
        );

        return $this->responderJson(true, 200, 'Usuario actualizado exitosamente.');
    }

    /**
     * PATCH /api/v1/usuarios/{id}/estado
     * Modifica el estado del usuario (ACTIVO, INACTIVO, BLOQUEADO) e invalida sesiones si aplica.
     */
    public function cambiarEstado(string $id): string
    {
        $contexto = $this->verificarSesionYPermiso('usuarios.desactivar');
        $this->verificarCsrf($contexto);

        $idInt = (int) $id;
        $usuario = $this->obtenerUsuarioAutorizado($idInt, $contexto);
        if ($usuario === null) {
            return $this->responderJson(false, 404, 'Usuario no encontrado.');
        }

        $datos = $this->obtenerCuerpo();
        $nuevoEstado = strtoupper(trim((string) ($datos['estado'] ?? '')));

        if (!in_array($nuevoEstado, ['ACTIVO', 'INACTIVO', 'BLOQUEADO'], true)) {
            return $this->responderJson(false, 400, 'Estado no válido. Valores admitidos: ACTIVO, INACTIVO, BLOQUEADO.');
        }

        if ($idInt === $contexto->usuarioId && $nuevoEstado !== 'ACTIVO') {
            return $this->responderJson(false, 400, 'No puede desactivar o bloquear su propia cuenta de usuario en sesión.');
        }

        $estadoPrevio = $usuario->estado;
        $this->usuarioRepo->actualizarEstado($idInt, $nuevoEstado);

        // Gobernanza: Si se suspende o bloquea, invalidar inmediatamente las sesiones activas en backend
        if ($nuevoEstado !== 'ACTIVO') {
            $this->sesionRepo->revocarTodasDeUsuario($idInt);
        }

        $this->auditoriaRepo->registrar(
            contexto: $contexto,
            modulo: 'usuarios',
            accion: 'CAMBIAR_ESTADO',
            entidadTipo: 'USUARIO',
            entidadId: (string) $idInt,
            datosPrevios: ['estado' => $estadoPrevio],
            datosNuevos: ['estado' => $nuevoEstado]
        );

        return $this->responderJson(true, 200, "Estado del usuario actualizado a {$nuevoEstado} correctamente.");
    }

    /**
     * PUT /api/v1/usuarios/{id}/roles
     * Sincroniza la lista de roles asignados al usuario.
     */
    public function sincronizarRoles(string $id): string
    {
        $contexto = $this->verificarSesionYPermiso('usuarios.roles');
        $this->verificarCsrf($contexto);

        $idInt = (int) $id;
        $usuario = $this->obtenerUsuarioAutorizado($idInt, $contexto);
        if ($usuario === null) {
            return $this->responderJson(false, 404, 'Usuario no encontrado.');
        }

        $datos = $this->obtenerCuerpo();
        $rolesNuevos = array_map('intval', (array) ($datos['roles'] ?? []));

        // H-02: Prevención de escalamiento de privilegios
        if (!empty($rolesNuevos) && !$this->authzMiddleware->puedeAsignarRoles($contexto->usuarioId ?? 0, $rolesNuevos)) {
            return $this->responderJson(false, 403, 'Acceso denegado: no tiene privilegios para asignar uno o más de los roles especificados.');
        }

        $rolesPrevios = array_map(fn($r) => $r->id, $this->rolRepo->obtenerRolesDeUsuario($idInt));

        $this->rolRepo->sincronizarRolesUsuario($idInt, $rolesNuevos);

        $this->auditoriaRepo->registrar(
            contexto: $contexto,
            modulo: 'usuarios',
            accion: 'SINCRONIZAR_ROLES',
            entidadTipo: 'USUARIO',
            entidadId: (string) $idInt,
            datosPrevios: ['roles' => $rolesPrevios],
            datosNuevos: ['roles' => $rolesNuevos]
        );

        return $this->responderJson(true, 200, 'Roles de usuario sincronizados exitosamente.');
    }

    /**
     * POST /api/v1/usuarios/{id}/restablecer-clave
     * Genera o asigna una nueva contraseña, revoca sesiones y restablece intentos fallidos.
     */
    public function restablecerClave(string $id): string
    {
        $contexto = $this->verificarSesionYPermiso('usuarios.restablecer_clave');
        $this->verificarCsrf($contexto);

        $idInt = (int) $id;
        $usuario = $this->obtenerUsuarioAutorizado($idInt, $contexto);
        if ($usuario === null) {
            return $this->responderJson(false, 404, 'Usuario no encontrado.');
        }

        $datos = $this->obtenerCuerpo();
        $clave = trim((string) ($datos['contrasena'] ?? $datos['nueva_contrasena'] ?? ''));
        $claveGenerada = null;

        if (empty($clave)) {
            // Generar clave segura temporal de 12 caracteres
            $claveGenerada = 'Cand26!' . substr(bin2hex(random_bytes(4)), 0, 6) . '$';
            $clave = $claveGenerada;
        } elseif (strlen($clave) < 8) {
            return $this->responderJson(false, 400, 'La nueva contraseña debe contener al menos 8 caracteres.');
        }

        $hash = password_hash($clave, PASSWORD_DEFAULT);
        $this->usuarioRepo->actualizarContrasenaHash($idInt, $hash);
        $this->usuarioRepo->restablecerIntentosFallidos($idInt);

        // Revocar todas las sesiones del usuario para forzar reautenticación
        $this->sesionRepo->revocarTodasDeUsuario($idInt);

        // Auditar evento con secretos redactados
        $this->auditoriaRepo->registrar(
            contexto: $contexto,
            modulo: 'usuarios',
            accion: 'RESTABLECER_CONTRASENA',
            entidadTipo: 'USUARIO',
            entidadId: (string) $idInt,
            datosPrevios: null,
            datosNuevos: ['evento' => 'contrasena_actualizada']
        );

        return $this->responderJson(true, 200, 'Contraseña restablecida exitosamente.', [
            'clave_temporal' => $claveGenerada,
        ]);
    }

    /**
     * DELETE /api/v1/usuarios/{id}
     * Prohibición absoluta de eliminación física conforme a mandato de gobernanza.
     */
    public function eliminar(string $id): string
    {
        return $this->responderJson(
            false,
            405,
            'La eliminación física de usuarios está prohibida por directriz de gobernanza y auditoría. Utilice la desactivación de cuenta en su lugar.'
        );
    }

    /**
     * Resuelve y valida el acceso al usuario dentro del alcance de la organización (Anti-IDOR).
     * Si no existe o pertenece a otra organización sin ser superadmin, retorna null (HTTP 404).
     */
    private function obtenerUsuarioAutorizado(int $id, ContextoOperacion $contexto): ?Usuario
    {
        $usuario = $this->usuarioRepo->buscarPorId($id);
        if ($usuario === null) {
            return null;
        }

        if ($contexto->usuarioId !== null) {
            if (!$this->authzMiddleware->verificarAlcanceOrganizacion($contexto->usuarioId, $usuario->organizacionId)) {
                return null;
            }
        }

        return $usuario;
    }

    /**
     * Verifica la autenticación y el permiso RBAC requerido. Detiene la ejecución si falla.
     */
    private function verificarSesionYPermiso(string $permiso): ContextoOperacion
    {
        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        $this->authzMiddleware->verificarPermiso($permiso, $contexto, true);
        return $contexto;
    }

    /**
     * Valida el token CSRF para solicitudes basadas en sesiones de navegador.
     */
    private function verificarCsrf(ContextoOperacion $contexto): void
    {
        $tokenCookie = ManejadorCookie::extraerDePeticion();
        // Si la petición viene con Cookie de sesión interactiva, exigir y comprobar CSRF
        if ($tokenCookie !== null) {
            $tokenEsperado = $contexto->metadatos['csrf_token'] ?? null;
            if (!ProtectorCsrf::verificarPeticion($_SERVER['REQUEST_METHOD'] ?? 'GET', $tokenEsperado, $_POST, $_SERVER)) {
                http_response_code(403);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'exito'   => false,
                    'codigo'  => 403,
                    'mensaje' => 'Token CSRF inválido o ausente. Por favor recargue la página.',
                ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                exit;
            }
        }
    }

    private function obtenerCuerpo(): array
    {
        $json = json_decode(file_get_contents('php://input'), true);
        if (is_array($json)) {
            return $json;
        }
        return $_POST;
    }

    private function responderJson(bool $exito, int $codigo, string $mensaje, mixed $datos = null): string
    {
        if (!headers_sent()) {
            http_response_code($codigo);
            header('Content-Type: application/json; charset=utf-8');
        }

        $res = [
            'exito'   => $exito,
            'codigo'  => $codigo,
            'mensaje' => $mensaje,
        ];

        if ($datos !== null) {
            $res['datos'] = $datos;
        }

        return json_encode($res, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}
