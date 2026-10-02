# INFORME DE EVIDENCIAS Y GOBERNANZA — MICROLOTE F1.1D
**CandelariaAPP &bull; Plataforma de Gestión y Producción Audiovisual**  
**Festividad de la Virgen de la Candelaria &bull; Puno, Perú**

---

## 1. RESUMEN EJECUTIVO

- **Microlote:** `F1.1D` — Login Visual Alina y CRUD Asíncrono de Usuarios con DataTables y Modales.
- **Baseline de Entrada:** `453a1d8d1aa593be74a2d9805f52193589542a13` (F1.1C Aprobado).
- **Estado de Cierre:** **PASS FORMAL TÉCNICO Y VISUAL (100%)**.
- **Regresión Acumulada:**
  - `F1.1A` (Identidad, Actores, Auditoría): **15/15 PASS**
  - `F1.1B` (Seguridad, Sesiones, CSRF, Bloqueo): **26/26 PASS**
  - `F1.1C` (RBAC, Permisos, Autorización Backend): **11/11 PASS**
  - `F1.1D` (Login, DataTables, Modales, API Usuarios): **17/17 PASS**
  - **Total acumulado:** **69/69 PASS (100%)**.
- **PHP Lint:** 0 errores sintácticos en la totalidad del repositorio.
- **Composer:** Validez de `composer.json` estricta, 30 clases optimizadas en autoload.
- **Upstream Alina (`admin-dashboard/`):** **100% INTACTO** (solo lectura respetado estrictamente).

---

## 2. COMPONENTES Y ARQUITECTURA IMPLEMENTADA

### 2.1. Pantalla de Autenticación Visual (`/login`)
- **Adaptación Visual Oficial de Alina:** Implementada en `recursos/vistas/layouts/auth.php` y `recursos/vistas/paginas/login.php` a partir de `sign_in_bg.html`.
- **Identidad de Marca:** Logotipo centrado con icono de llama (`fa-solid fa-fire-flame-curved`), tipografía institucional **Fira Sans Extra Condensed**, distintivo `O.G. Estudio Creativo`.
- **Controles Interactivos:**
  - Inputs con iconos `fa-solid fa-user` y `fa-solid fa-lock`.
  - Toggle de visibilidad de contraseña (ojo abierto/cerrado con `fa-solid fa-eye` y `fa-solid fa-eye-slash`).
  - Botón con gradiente Alina, transición visual a estado `disabled + spinner` durante la validación de credenciales.
  - Alertas modales de error/éxito con **SweetAlert2**.
  - Cero `location.reload()`: consumo directo de `POST /api/v1/auth/login` vía Fetch API y redirección suave a `/` tras éxito.

### 2.2. Módulo de Gestión de Usuarios (`/usuarios`)
- **Vista Principal (`recursos/vistas/paginas/usuarios.php`):**
  - **Tarjetas KPI Alina:** Total de usuarios, usuarios activos, inactivos y roles asignados.
  - **Tabla Dinámica con DataTables:** Renderizado responsivo con ordenamiento, búsqueda en tiempo real, paginación en español y badges estilizados de estado y roles.
  - **Patrón Asíncrono Estricto:**
    - `Skeleton -> Carga Inicial y Refresco`: Placeholders animados mientras se recuperan los datos.
    - `Disabled + Spinner -> Procesamiento`: Bloqueo inmediato de botones de modales durante peticiones `POST`, `PUT` y `PATCH`.
    - `SweetAlert2 -> Resultado`: Notificaciones emergentes accesibles y elegantes para éxito o error.
    - `Refresh Parcial -> Modificación`: Recarga atómica del DataTable vía `tablaUsuarios.ajax.reload(null, false)` sin recargar el navegador (`location.reload()` prohibido).
- **Modales Alina Integrados:**
  1. **Crear Usuario:** Permite vincular una persona existente disponible (respetando unicidad `uk_usuarios_persona`) o registrar en el mismo acto una nueva persona (Natural o Jurídica) con su rol inicial.
  2. **Editar Usuario:** Modificación de datos mutables (correo, teléfono, estado) con validaciones en servidor.
  3. **Gestión de Roles:** Modal para agregar o revocar roles (`admin_organizacion`, `operador`, etc.) con sincronización instantánea y verificación RBAC.
  4. **Restablecer Contraseña:** Generación automática de contraseña segura o ingreso de una clave manual, forzando la revocación inmediata de sesiones activas anteriores en el backend.

### 2.3. Endpoints de la API RESTful (`rutas/api.php` y `UsuarioControlador.php`)
Todos los endpoints exigen verificación de sesión, permisos RBAC en backend y validación CSRF para peticiones web:

| Método | Endpoint | Permiso Requerido | Descripción |
|---|---|---|---|
| `GET` | `/api/v1/usuarios` | `usuarios.ver` | Lista completa de usuarios con detalles para DataTables |
| `GET` | `/api/v1/usuarios/{id}` | `usuarios.ver` | Detalle específico de un usuario |
| `GET` | `/api/v1/personas/disponibles` | `usuarios.crear` | Personas activas que no tienen usuario asignado |
| `GET` | `/api/v1/tipos-documento` | `usuarios.crear` | Catálogo de tipos de documento de identidad |
| `GET` | `/api/v1/roles` | `usuarios.roles` | Catálogo de roles vigentes para asignación |
| `POST` | `/api/v1/usuarios` | `usuarios.crear` | Creación de usuario (con o sin nueva persona) + CSRF |
| `PUT` | `/api/v1/usuarios/{id}` | `usuarios.editar` | Actualización de datos mutables + CSRF |
| `PATCH` | `/api/v1/usuarios/{id}/estado` | `usuarios.desactivar` | Cambio de estado (`ACTIVO`/`INACTIVO`/`BLOQUEADO`) + revoca sesiones |
| `PUT` | `/api/v1/usuarios/{id}/roles` | `usuarios.roles` | Sincronización de roles asignados + CSRF |
| `POST` | `/api/v1/usuarios/{id}/restablecer-clave` | `usuarios.restablecer_clave` | Reseteo de credencial + invalidación de sesiones + CSRF |
| `DELETE`| `/api/v1/usuarios/{id}` | Ninguno | **HTTP 405 Method Not Allowed** (Eliminación física prohibida) |

### 2.4. Garantías de Seguridad y Gobernanza
1. **Inmutabilidad y No-Eliminación:** Los usuarios nunca se eliminan físicamente de la base de datos (`DELETE` retorna 405).
2. **Autoridad Absoluta del Backend:** La desactivación de una cuenta o el cambio a `INACTIVO` invalida de inmediato todas sus sesiones activas en la tabla `sesiones` e impide cualquier operación posterior.
3. **Protección Anti-Auto-Desactivación:** Un administrador no puede desactivar o bloquear su propia cuenta activa.
4. **Protección CSRF:** Validada en tiempo constante (`hash_equals`) para toda solicitud mutacional proveniente de sesiones web.
5. **Iconografía Font Awesome 6.3.0:** Se mantiene 100% libre de Tabler icons, sin glifos faltantes ni cuadrados vacíos en el sidebar ni en los encabezados.

---

## 3. VERIFICACIÓN VISUAL Y EVIDENCIAS DE CAPTURA

- **Captura Login (`screenshot_f11d_login_vhost.png`):** Renderizado perfecto con fondo geométrico Alina, tipografía Fira Sans, logo con llama y toggle de contraseña.
- **Captura Dashboard (`screenshot_f11d_dashboard.png`):** Navbar superior muestra al usuario autenticado `Orlando Gonzales` con rol `Administrador de Organización` y acceso al sidebar.
- **Captura Módulo Usuarios (`screenshot_f11d_usuarios_table.png`):** KPIs numéricos activos, DataTables poblado con badges de estado y botones de acción.
- **Captura Modal Crear (`screenshot_f11d_modal_crear.png`):** Formulario modal con selección de persona, inputs validados y botones con spinner.
- **Captura Modal Roles (`screenshot_f11d_modal_roles.png`):** Matriz de selección de roles con checkboxes estilizados.
