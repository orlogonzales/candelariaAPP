# EVIDENCIAS DE AUDITORÍA, HARDENING Y CIERRE FORMAL DEL GATE F1.1 (MICROLOTE F1.1E)

## 1. RESUMEN EJECUTIVO
- **Fase:** Fase 1.1 — Identidad, Autenticación, RBAC y Auditoría
- **Microlote:** F1.1E — Auditoría de Código, Hardening de Seguridad y Certificación del GATE F1.1
- **Fecha:** 2026-10-02
- **Estado de Gate:** **APROBADO — PASS FORMAL (100% SUITES EXITOSAS)**
- **Regresión Acumulada Total:** **94/94 PRUEBAS PASS (0 FALLOS)**
  - F1.1A (Identidad y Auditoría): 15/15 PASS
  - F1.1B (Autenticación y Sesiones): 26/26 PASS
  - F1.1C (RBAC y Autorización): 11/11 PASS
  - F1.1D (Login y Gestión de Usuarios): 17/17 PASS
  - F1.1E (Hardening, IDOR, Roles y SEGURIDAD_AUTH): 25/25 PASS

---

## 2. MATRIZ DE RESOLUCIÓN DE HALLAZGOS DE AUDITORÍA

| ID | Severidad | Componente / Archivo | Hallazgo Original | Tratamiento y Solución Implementada | Estado |
|---|---|---|---|---|---|
| **H-01** | P1 | `AutenticacionServicio.php`, `auditoria_operaciones` | Atribución incorrecta de logins fallidos anónimos a `SISTEMA_CLI` o `LANDING_CANDELARIA`. | **Opción A (`SEGURIDAD_AUTH`):** Se incorporó el actor técnico canónico `SEGURIDAD_AUTH` ("Motor de Autenticación y Control de Acceso") con `es_critico=1` en `actores_sistema`. Resuelto dinámicamente vía `ActorSistemaRepositorio` (cero IDs mágicos). Se respeta el CHECK `chk_auditoria_actor` sin migración 003 ni mutación del esquema. | **RESUELTO / VERIFICADO** |
| **H-02** | P1 | `UsuarioControlador.php`, `AutorizacionServicio.php` | Escalamiento de privilegios: `admin_organizacion` podía manipular payloads en `crear` y `sincronizarRoles` para asignarse el rol `superadmin_plataforma`. | Validación estricta en backend mediante `AutorizacionServicio::puedeAsignarRoles()`. Se bloquea tempranamente cualquier intento de otorgar `superadmin_plataforma` si el operador no es superadministrador, respondiendo con `HTTP 403 Forbidden`. | **RESUELTO / VERIFICADO** |
| **H-03** | P2 | `UsuarioControlador.php`, `AutorizacionServicio.php` | Vulnerabilidad IDOR Multi-Tenant: Parámetros `{id}` en `detalle`, `actualizar`, `cambiarEstado`, `sincronizarRoles` y `restablecerClave` no validaban concordancia de tenant. | Se implementó `AutorizacionServicio::verificarAlcanceOrganizacion()` y el método privado `obtenerUsuarioAutorizado()` en `UsuarioControlador`. Toda petición sobre otra organización sin rango superadmin responde uniformemente `HTTP 404 Not Found` para no revelar existencia. | **RESUELTO / VERIFICADO** |
| **H-04** | P2 | `UsuarioControlador.php`, `AutorizacionServicio.php` | Exposición de catálogo completo en `/api/v1/roles`: operadores de organización veían el rol `superadmin_plataforma`. | Se implementó `AutorizacionServicio::obtenerRolesAsignables()`. El endpoint `/api/v1/roles` filtra automáticamente y oculta `superadmin_plataforma` a operadores regulares. | **RESUELTO / VERIFICADO** |
| **H-05** | P3 | `UsuarioControlador.php` | Fuga de información en captura de excepciones: `crear()` concatenaba `$e->getMessage()`, pudiendo exponer nombres de tablas o SQLSTATE. | Se eliminó `$e->getMessage()` de la respuesta HTTP 500, estandarizando el mensaje a `"Error interno al registrar usuario."`. | **RESUELTO / VERIFICADO** |
| **H-06** | P3 | `Enrutador.php` | Ausencia de manejador global de excepciones en despacho de rutas `/api/`. | Se envolvió el despacho en `Enrutador::despachar()` con `try/catch (\Throwable $e)`. Las rutas bajo `/api/` capturan cualquier error imprevisto y devuelven un JSON estándar HTTP 500 sin filtrar trazas ni estructuras internas. | **RESUELTO / VERIFICADO** |
| **H-07** | P3 | `ejecutar_pruebas_f11d.php` | Contraseñas en texto claro hardcodeadas en fixtures de prueba. | Se sanitizaron los archivos de pruebas (`ejecutar_pruebas_f11d.php`). Las contraseñas de testing se generan dinámicamente en tiempo de ejecución. `git grep` limpio de contraseñas estáticas en el repositorio. | **RESUELTO / VERIFICADO** |

---

## 3. IMPLEMENTACIONES TÉCNICAS DETALLADAS

### 3.1. Actor de Seguridad `SEGURIDAD_AUTH` y Repositorio de Actores
- **Tabla `actores_sistema`:**
  - `codigo`: `'SEGURIDAD_AUTH'`
  - `nombre`: `'Motor de Autenticación y Control de Acceso'`
  - `es_critico`: `1`
  - `activo`: `1`
- **Repositorio `ActorSistemaRepositorio`:** Creado en `aplicacion/Repositorios/ActorSistemaRepositorio.php` con métodos de consulta por código (`obtenerIdPorCodigo`), por ID (`buscarPorId`) y soporte de caché en memoria de proceso.
- **Invariante de Preautenticación:** Los intentos fallidos (usuario inexistente, credenciales erróneas, cuenta inactiva o bloqueada) registran `actor_tipo = 'SISTEMA'`, `actor_sistema_id = SEGURIDAD_AUTH`, `usuario_id = null`, canal `APP` (id=1). Cumplimiento del CHECK `chk_auditoria_actor` de MySQL 8.4 al 100%.

### 3.2. Autorización y Prevención de Escalamiento (RBAC Backend)
- En `AutorizacionServicio` y `AutorizacionMiddleware`:
  - `esSuperadmin(int $usuarioId): bool`: Centraliza la validación jerárquica del usuario.
  - `puedeAsignarRoles(int $operadorId, array $rolesIds): bool`: Impide la inyección de roles de infraestructura por operadores de organización.
  - `verificarAlcanceOrganizacion(int $operadorId, int $recursoOrganizacionId): bool`: Autoriza cross-tenant únicamente a superadministradores; para el resto exige estricta concordancia con su organización.
  - `obtenerRolesAsignables(int $operadorId, ?int $organizacionId = null): array`: Filtra los roles asignables en función del nivel de privilegio del operador en sesión.

### 3.3. Aislamiento Multi-Tenant Anti-IDOR en Controladores
- En `UsuarioControlador`:
  - `obtenerUsuarioAutorizado(int $id, ContextoOperacion $contexto): ?Usuario`: Verifica la existencia y el aislamiento tenant. Retorna `null` (lo cual deriva en `HTTP 404 Not Found`) si el recurso pertenece a otra organización.
  - Métodos protegidos: `detalle()`, `actualizar()`, `cambiarEstado()`, `sincronizarRoles()`, `restablecerClave()`.
  - Validación temprana en `crear()`: Valida `puedeAsignarRoles()` antes de interactuar con la base de datos, abortando con `HTTP 403 Forbidden` si se intenta escalar privilegios.

### 3.4. Manejo Global de Excepciones y Sanitización de Errores
- En `Enrutador.php`: Intercepción de `Throwable` en el ciclo de despacho. Para rutas con prefijo `/api/`, responde automáticamente un JSON uniforme:
  ```json
  {
      "exito": false,
      "codigo": 500,
      "mensaje": "Error interno del servidor.",
      "datos": null
  }
  ```
  Impidiendo cualquier filtración de trazas, archivos o esquemas de base de datos.

---

## 4. RESULTADOS DE LA SUITE DE REGRESIÓN COMPLETA (F1.1A - F1.1E)

```text
==============================================================================
CANDELARIAAPP — SUITE DE INTEGRACIÓN F1.1A (Identidad, Actores y Auditoría)
15 PRUEBAS EXITOSAS / 0 FALLOS [PASS]
==============================================================================
CANDELARIAAPP — SUITE DE SEGURIDAD Y AUTENTICACIÓN F1.1B (Sesiones y CSRF)
26 PRUEBAS EXITOSAS / 0 FALLOS [PASS]
==============================================================================
CANDELARIAAPP — SUITE DE AUTORIZACIÓN Y RBAC F1.1C (Roles y Permisos)
11 PRUEBAS EXITOSAS / 0 FALLOS [PASS]
==============================================================================
CANDELARIAAPP — SUITE DE LOGIN Y CRUD DE USUARIOS F1.1D (Vistas y API)
17 PRUEBAS EXITOSAS / 0 FALLOS [PASS]
==============================================================================
CANDELARIAAPP — SUITE DE HARDENING Y AUDITORÍA F1.1E (IDOR, Roles, SEGURIDAD_AUTH)
25 PRUEBAS EXITOSAS / 0 FALLOS [PASS]
==============================================================================
TOTAL REGRESIÓN ACUMULADA: 94 / 94 PRUEBAS PASS (100% EXITOSO)
==============================================================================
```

---

## 5. VERIFICACIONES DE INTEGRIDAD DEL SISTEMA
1. **PHP Lint:** 100% de archivos PHP sintácticamente válidos (`PHP LINT DONE`, 0 errores).
2. **Composer Validate:** `composer.json` válido (`./composer.json is valid`).
3. **Upstream Alina:** Directorio `admin-dashboard/` 100% intacto y de solo lectura (`nothing to commit, working tree clean`).
4. **Secret Scan:** `git grep -i "Cand26!"` no reporta contraseñas en texto claro; solo patrones algorítmicos generadores.
5. **Gobernanza:** Cero eliminaciones físicas (`DELETE` responde HTTP 405), auditoría inmutable Append-Only preservada.
