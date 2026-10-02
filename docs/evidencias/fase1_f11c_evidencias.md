# REGISTRO DE EVIDENCIAS TÉCNICAS — FASE 1.1C
## AUTORIZACIÓN BACKEND, MODELO RBAC (ROLES Y PERMISOS) Y CUENTA ADMINISTRATIVA TEMPORAL

**Fecha de Ejecución:** 2026-10-02  
**Entorno de Ejecución:** Windows, Laragon, Apache 2.4, PHP 8.3.30, MySQL 8.4.3 LTS, Composer 2.10.1, Git 2.x  
**Carácter del Microlote:** Implementación del motor de autorización RBAC (Role-Based Access Control) nativo en backend, middleware de protección de rutas, servicio central de verificación de privilegios, evaluación en base de datos y creación de la cuenta administrativa local temporal para desarrollo sin backdoors ni bypasses de código.

---

## 1. Baseline Inicial de Partida
- **Rama:** `main`
- **Hash Inicial:** `c7672545933eb733788df91a600d957396cb1576` (`c767254`)
- **Estado de Sincronización:** `Ahead 0 / Behind 0` (`origin/main...HEAD: 0 0`)
- **Working Tree:** `CLEAN`

---

## 2. Reutilización Estricta del Esquema RBAC Fundacional
Se auditaron y reutilizaron al 100% las 4 tablas fundacionales de autorización creadas en Fase 0:
1. `roles`:
   - Roles canónicos sembrados:
     - `id = 1`: `superadmin_plataforma` (SUPERADMINISTRADOR DE PLATAFORMA, `es_sistema = 1`)
     - `id = 2`: `admin_organizacion` (ADMINISTRADOR DE ORGANIZACIÓN, `es_sistema = 1`)
     - `id = 3`: `operador_produccion` (OPERADOR DE PRODUCCIÓN, `es_sistema = 1`)
2. `permisos`:
   - Catálogo inicial de permisos atómicos para el módulo Administración de Usuarios (Módulo 16):
     - `usuarios.ver`
     - `usuarios.crear`
     - `usuarios.editar`
     - `usuarios.desactivar`
     - `usuarios.roles`
     - `usuarios.restablecer_clave`
3. `rol_permisos`:
   - Matriz asociativa `(rol_id, permiso_id)`.
   - Roles 1 y 2 tienen asociados todos los permisos de gestión de usuarios.
   - Rol 3 carece de permisos sobre la administración de cuentas.
4. `usuario_roles`:
   - Tabla relacional canónica `(usuario_id, rol_id)`.
   - Cero tablas paralelas o duplicadas de permisos.

---

## 3. Principios Rectores de Autorización

```text
MENÚ ≠ AUTORIZACIÓN
BOTÓN OCULTO ≠ AUTORIZACIÓN
FRONTEND ≠ AUTORIDAD
BACKEND = AUTORIDAD

USUARIO AUTENTICADO
      ↓
    ROLES
      ↓
   PERMISOS
      ↓
MIDDLEWARE / SERVICIO DE AUTORIZACIÓN
      ↓
OPERACIÓN PERMITIDA (200) O ACCESO DENEGADO (403)
```

- **Inviolabilidad ante Manipulación de Frontend:** Cualquier intento de inyección de parámetros (`es_admin`, `rol`, `permisos`) en payloads JSON o formularios POST es completamente ignorado. La autorización se computa exclusivamente en backend desde la sesión criptográfica del usuario hacia la base de datos relacional.
- **Respuesta Uniforme por Capas:**
  - Solicitud sin autenticación válida $\longrightarrow$ **HTTP 401 Unauthorized**.
  - Solicitud autenticada pero sin el permiso requerido $\longrightarrow$ **HTTP 403 Forbidden**.
  - Solicitud autenticada con permiso $\longrightarrow$ **Permitido (HTTP 200 OK)**.
- **Herencia Dinámica e Inmediata:** Si un permiso es asignado o revocado en `rol_permisos`, todos los usuarios que posean dicho rol adquieren o pierden el privilegio en tiempo real, sin requerir recarga de token ni manipulación de usuario.
- **Autoridad en Cuentas Inactivas:** Si un usuario con roles administrativos es marcado como `INACTIVO` o `BLOQUEADO` en la tabla `usuarios`, el backend deniega de inmediato cualquier operación, incluso si conserva una sesión previa activa.

---

## 4. Componentes Desarrollados

| Archivo | Responsabilidad |
|---|---|
| `aplicacion/Entidades/Rol.php` | Entidad inmutable de dominio para roles RBAC |
| `aplicacion/Entidades/Permiso.php` | Entidad inmutable de dominio para permisos atómicos |
| `aplicacion/Repositorios/RolRepositorio.php` | Consultas de roles, asignación idempotente a usuarios y sincronización |
| `aplicacion/Repositorios/PermisoRepositorio.php` | Consultas de permisos por rol y agregación consolidada por usuario |
| `aplicacion/Autorizacion/AutorizacionServicio.php` | Servicio central de evaluación de permisos (`tienePermiso`, `tieneRol`, `autorizar`) |
| `aplicacion/Excepciones/AccesoDenegadoExcepcion.php` | Excepción tipada de seguridad con código HTTP 403 Forbidden |
| `nucleo/Http/Middleware/AutorizacionMiddleware.php` | Middleware de intercepción HTTP: evaluación de 401, 403 y paso autorizado |
| `base_datos/semillas/desarrollo/crear_admin_temporal.php` | Script de desarrollo local para creación de administrador temporal con contraseña aleatoria |
| `rutas/api.php` | Endpoint técnico `/api/v1/usuarios/lista` protegido por auth y `usuarios.ver` |
| `pruebas/ejecutar_pruebas_f11c.php` | Suite de 11 pruebas automatizadas de autorización backend y RBAC |

---

## 5. Cuenta Administrativa Temporal Local (Orlando)

- **Aislamiento de Entorno:** Generada mediante [`base_datos/semillas/desarrollo/crear_admin_temporal.php`](file:///d:/laragon/www/app.candelaria/base_datos/semillas/desarrollo/crear_admin_temporal.php), script protegido que aborta si `APP_ENV === 'produccion'`.
- **Modelo Relacional Completo:**
  - `organizaciones`: Vinculada al tenant local de desarrollo `og_estudio` (O.G. ESTUDIO CREATIVO).
  - `personas`: Vinculada a persona natural canónica (Orlando Gonzales, DNI 40123456).
  - `usuarios`: Login `orlando`, estado `ACTIVO`.
  - `usuario_roles`: Rol asignado `admin_organizacion` (id = 2).
- **Cero Bypasses:** Los permisos se validan por el motor RBAC real (`usuario_roles` $\rightarrow$ `rol_permisos` $\rightarrow$ `permisos`). No existe ninguna cláusula de excepción por nombre de usuario.
- **Seguridad de Credenciales:** La contraseña en texto plano no está registrada en ningún commit, archivo SQL, documentación, log ni auditoría. Fue emitida una sola vez en consola para pruebas del desarrollador.

---

## 6. Resultados de Pruebas Automatizadas

### A. Suite F1.1C (`pruebas/ejecutar_pruebas_f11c.php`)
```text
==============================================================================
CANDELARIAAPP — SUITE DE PRUEBAS DE AUTORIZACIÓN Y RBAC F1.1C
ROLES, PERMISOS, AUTORIDAD BACKEND, MIDDLEWARE Y CUENTA ORLANDO
==============================================================================

 [PASS] 01. Usuario sin sesión es rechazado por el middleware (equivale a HTTP 401)
 [PASS] 02. Usuario autenticado sin permiso es denegado por servicio y middleware (HTTP 403 Forbidden)
 [PASS] 03. Usuario con rol administrativo y permisos concedidos accede exitosamente (PASS)
 [PASS] 04. Múltiples roles asignados agregan privilegios correctamente sin colisiones ni pérdidas
 [PASS] 05. Herencia dinámica: cambios en matriz rol_permisos se reflejan de inmediato en la autorización
 [PASS] 06. Backend es autoridad absoluta: cuenta marcada como INACTIVO pierde toda autorización inmediatamente
 [PASS] 07. Sesión revocada es invalidada en backend e impide el acceso al middleware de autorización
 [PASS] 08. Solicitud de verificación para un código de permiso inexistente en catálogo es denegada
 [PASS] 09. Inviolabilidad: Manipulación de payloads o parámetros frontend no altera la autorización en backend
 [PASS] 10. Cuenta administrativa temporal orlando existe en base de datos y se encuentra ACTIVA
 [PASS] 11. Cuenta orlando posee rol admin_organizacion y los 6 permisos de gestión de usuarios vía RBAC real

==============================================================================
RESULTADO FINAL F1.1C: 11 PRUEBAS EXITOSAS / 0 FALLOS
==============================================================================
```

### B. Regresión F1.1A y F1.1B
- `pruebas/ejecutar_pruebas_f11a.php`: **15/15 PASS**.
- `pruebas/ejecutar_pruebas_f11b.php`: **26/26 PASS**.
- **Total acumulado de pruebas de integración:** **52/52 PASS (0 fallos)**.

---

## 7. Verificaciones de Calidad y Regresión

1. **PHP Lint:**
   - 46 archivos propios analizados: **100% PASS** (0 errores sintácticos).
2. **Composer:**
   - `composer validate`: `./composer.json is valid` (**PASS**).
   - `composer dump-autoload`: Generó mapa optimizado con 29 clases registradas (**PASS**).
3. **Git Diff Check:**
   - `git diff --check`: Sin conflictos ni alertas (**PASS**).
4. **Verificación HTTP:**
   - `GET /api/v1/usuarios/lista` sin autenticación $\longrightarrow$ **401 Unauthorized** (**PASS**).
   - `GET /api/v1/usuarios/lista` autenticado como Orlando $\longrightarrow$ **200 OK** con padrón de usuarios (**PASS**).
   - `GET /api/v1/estado` $\longrightarrow$ **200 OK** (fase `F1.1C RBAC y Autorización Backend`).
5. **Verificación Visual de Hotfix Sidebar:**
   - Capturas con Chrome Headless (`screenshot_f11c_localhost.png` y `screenshot_f11c_vhost.png`):
     - Cuadrados vacíos / glifos faltantes: **0**.
     - Tabler icons: **0**.
     - Font Awesome Free 6.3.0: **PASS**.
     - Dashboard sin chevrons residuales.
6. **Integridad de Upstream Alina:**
   - `admin-dashboard/`: **100% INTACTO**.
