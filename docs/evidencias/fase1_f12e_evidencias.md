# INFORME DE EVIDENCIAS Y CERTIFICACIÓN DEL GATE DE FASE 1.2 — MICROLOTE F1.2E
**CandelariaAPP &bull; Plataforma de Gestión y Producción Audiovisual**  
**Festividad de la Virgen de la Candelaria &bull; Puno, Perú**

---

## 1. RESUMEN EJECUTIVO

- **Microfase:** `F1.2E` — Hardening Final y Gate de Fase 1.2 (Configuración y Organización).
- **Baseline de Entrada Oficial:** `a6d6022d8da03a145c713b439e511a7e971d47d0` (F1.2D Aprobado con 193/193 PASS).
- **Estado de Cierre:** **PASS FORMAL TÉCNICO, DE SEGURIDAD Y ARQUITECTÓNICO (100% PRUEBAS EXITOSAS)**.
- **Regresión Acumulada Total:** **219 / 219 PRUEBAS PASS (0 FALLOS)**.
  - `F1.1A` (Identidad, Actores, Canales, Auditoría): **15/15 PASS**
  - `F1.1B` (Seguridad, Sesiones, CSRF, Bloqueo): **26/26 PASS**
  - `F1.1C` (RBAC, Catálogo Permisos, Autorización Backend): **11/11 PASS**
  - `F1.1D` (Login Alina, DataTables, Modales, API Usuarios — *Aislada*): **17/17 PASS**
  - `F1.1E` (Hardening, IDOR, Roles y SEGURIDAD_AUTH): **25/25 PASS**
  - `F1.2A` (Modelo Configuración, Parámetros Tipados, Branding, Caché): **25/25 PASS**
  - `F1.2B` (Ficha Institucional, Modales Alina, Branding Seguro, Anti-IDOR): **28/28 PASS**
  - `F1.2C` (Configuración General, Parámetros Operativos, Soberanía, Concurrencia 409): **26/26 PASS**
  - `F1.2D` (Integración, Catálogos, Tenant Fail-Closed, Aislamiento y Preservación): **20/20 PASS**
  - `F1.2E` (Hardening Final, Tenant Fail-Closed Estricto, Concurrencia 409 en Org, Paridad DB): **26/26 PASS**
- **Preservación Inviolable de la Cuenta `orlando` (ID 24):**
  - Huella Digital SHA-256 Pre-Regresión: `80e6af84e02e89e3`
  - Huella Digital SHA-256 Post-Regresión: `80e6af84e02e89e3` (**100% INTACTA Y COINCIDENTE**)
  - Estado: `ACTIVO`
  - Intentos Fallidos: `0`
  - Bloqueo Temporal: `NULL`
  - Rol Asignado: `admin_organizacion`
- **Upstream Alina (`admin-dashboard/`):** **100% INTACTO** (cero modificaciones, working tree clean).
- **Higiene de Secretos:** Cero contraseñas en texto claro, credenciales ni tokens en repositorio, seeds, SQL versionado ni documentación.

---

## 2. AUDITORÍA Y RESOLUCIÓN DE HALLAZGOS DE HARDENING

Durante la auditoría integral de código y seguridad de F1.2E se identificaron y subsanaron los siguientes aspectos técnicos:

| COMPONENTE | UBICACIÓN | SITUACIÓN PREVIA | REMEDIACIÓN APLICADA (F1.2E) | RESULTADO |
| :--- | :--- | :--- | :--- | :--- |
| **Rutas API** | `rutas/api.php` (Línea 151) | Endpoint `/api/v1/usuarios/lista` ejecutaba `$contexto->organizacionId ?? 1`. | Sustituido por validación fail-closed estricta: si `organizacionId` es nulo o &le; 0, emite respuesta JSON HTTP 403 inmediata. | **CORREGIDO** (Cero fallbacks silenciosos a tenant 1). |
| **OrganizacionControlador** | `aplicacion/Controladores/OrganizacionControlador.php` | `$this->resolverOrganizacionId($contexto)` se invocaba fuera del bloque `try ... catch` en `detalle()`, `actualizar()` y `procesarCargaBranding()`. | Movido dentro de los bloques `try ... catch (AccesoDenegadoExcepcion $e)` respondiendo HTTP 403 JSON de manera uniforme y controlada. | **CORREGIDO** (Respuestas HTTP 403 consistentes sin excepciones no capturadas). |
| **Concurrencia Optimista Org** | `aplicacion/Controladores/OrganizacionControlador.php` | `actualizar()` no verificaba `actualizado_en_esperado`. | Incorporada verificación de timestamp de concurrencia optimista (retorna HTTP 409 Conflicto ante versiones desfasadas). | **CORREGIDO** (Paridad de concurrencia optimista entre Organización y Configuración). |
| **Esquema Base Reproducible** | `base_datos/esquema/esquema_base.sql` | Columnas `contacto_nombre` y `contacto_cargo` en tabla `organizaciones` figuraban después de `telefono_whatsapp`. | Alineado el orden exacto con la migración 003 y base de datos activa: colocadas inmediatamente después de `sitio_web`. | **CORREGIDO** (100% paridad de esquema en instalación limpia). |

---

## 3. SUITE DE PRUEBAS DE HARDENING FINAL Y GATE (F1.2E)

Se implementó la suite oficial `pruebas/ejecutar_pruebas_f12e.php` con 26 pruebas atómicas de certificación:

```text
==============================================================================
CANDELARIAAPP — SUITE DE PRUEBAS DE HARDENING FINAL Y GATE DE FASE 1.2 (F1.2E)
FAIL-CLOSED, RBAC, ANTI-IDOR, CONCURRENCIA, BRANDING Y PRESERVACIÓN INTEGRAL
==============================================================================

 [PASS] 01. Pre-condición: Usuario orlando (ID 24) en estado ACTIVO con fingerprint 80e6af84e02e89e3 y 0 intentos
 [PASS] 02. Tenant Fail-Closed: OrganizacionControlador rechaza detalle, actualizar y branding ante organizacionId nulo
 [PASS] 03. Tenant Fail-Closed: ConfiguracionControlador rechaza listar y actualizar en ámbito ORGANIZACION sin organizacionId
 [PASS] 04. Tenant Fail-Closed: UsuarioControlador rechaza listar y crear con HTTP 403 fail-closed si falta organizacionId
 [PASS] 05. Tenant Fail-Closed: Ruta /api/v1/usuarios/lista valida organizacionId estricto y retorna HTTP 403
 [PASS] 06. RBAC: Petición anónima a /api/v1/configuracion responde HTTP 401 Unauthorized
 [PASS] 07. RBAC: Operador sin permiso organizacion.ver recibe HTTP 403 Forbidden
 [PASS] 08. RBAC: Admin_organizacion accede exitosamente a los datos de su tenant (HTTP 200)
 [PASS] 09. RBAC: Admin_organizacion es bloqueado con HTTP 403 al intentar acceder a configuración de PLATAFORMA
 [PASS] 10. RBAC: Superadmin accede legítimamente a los parámetros de ámbito PLATAFORMA (HTTP 200)
 [PASS] 11. Anti-IDOR: OrganizacionControlador ignora organizacion_id adulterado y procesa el tenant autenticado
 [PASS] 12. Soberanía: El parámetro inmutable plataforma.moneda_principal rechaza cualquier mutación incluso por Superadmin
 [PASS] 13. Soberanía: El sistema rechaza actualización de parámetros no registrados en el catálogo canónico
 [PASS] 14. Tipado Estricto: El motor de configuración rechaza valores con tipo incompatible (INTEGER esperado)
 [PASS] 15. Concurrencia Optimista: OrganizacionControlador detecta versión desfasada y retorna HTTP 409 Conflicto
 [PASS] 16. Concurrencia Optimista: ConfiguracionControlador detecta versión desfasada y retorna HTTP 409 Conflicto
 [PASS] 17. Seguridad Branding: Carga de archivo SVG rechazada categóricamente (anti-XSS vectorial)
 [PASS] 18. Seguridad Branding: Rechazo estricto de archivos que exceden el límite de 2MB
 [PASS] 19. Seguridad Branding: Rechazo de archivos con doble extensión y patrones de path traversal
 [PASS] 20. Seguridad Branding: Rechazo de falso MIME verificado mediante finfo y getimagesize en backend
 [PASS] 21. Inmutabilidad de Auditoría: AuditoriaRepositorio no expone ningún método de actualización ni eliminación física
 [PASS] 22. Trazabilidad: Actualización legítima de organización genera registro auditable con actor, IP y valores
 [PASS] 23. Paridad de Esquema: Las 19 tablas oficiales existen y coinciden con la definición de esquema_base.sql
 [PASS] 24. Auditoría de Código: Ausencia absoluta de fallbacks indebidos ($contexto->organizacionId ?? o ?? 10000) en controladores, rutas y vistas
 [PASS] 25. Aislamiento de Storage: Directorio de subidas de branding libre de archivos residuales o contaminantes
 [PASS] 26. Certificación Post-Rollback: Cuenta orlando (ID 24) 100% inalterada con huella criptográfica idéntica

==============================================================================
RESULTADO FINAL F1.2E: 26 PRUEBAS EXITOSAS / 0 FALLOS
==============================================================================
```

---

## 4. PARIDAD Y REPRODUCIBILIDAD DEL ESQUEMA DE BASE DE DATOS

Se certificó la paridad estructural completa entre una instalación limpia (`esquema_base.sql` + migraciones) y la base de datos de desarrollo activa:

1. **Total de Tablas Oficiales (19 / 19):**
   - `migraciones_control`, `organizaciones`, `planes`, `modulos`, `capacidades_plan`, `roles`, `permisos`, `rol_permisos`, `tipos_documento`, `personas`, `usuarios`, `usuario_roles`, `ediciones_candelaria`, `menu_opciones`, `sesiones`, `actores_sistema`, `canales`, `auditoria_operaciones`, `parametros_configuracion`.
2. **Total de Claves Foráneas (22 / 22):** Idénticas en restricciones, cascadas y referencias relacionales.
3. **Restricciones de Integridad CHECK:**
   - `chk_param_ambito_org` en `parametros_configuracion`: Impide que parámetros de ámbito `PLATAFORMA` tengan `organizacion_id` y fuerza que los de ámbito `ORGANIZACION` cuenten obligatoriamente con `organizacion_id`.
   - `chk_auditoria_actor` en `auditoria_operaciones`: Preserva la dualidad estricta entre actores humanos y de sistema.
4. **Semillas Fundacionales:**
   - 6 tipos de documento oficial.
   - 6 actores de sistema canónicos (incluyendo `SEGURIDAD_AUTH`).
   - 7 canales de operación oficiales.
   - 19 módulos funcionales del roadmap global.
   - 3 roles de sistema y 13 permisos atómicos de Fase 1.
   - 9 parámetros de configuración iniciales gobernados (6 de plataforma, 3 de organización).

---

## 5. TABLA CONSOLIDADA DE REGRESIÓN DE FASE 1 (F1.1 + F1.2)

| MICROFASE | DESCRIPCIÓN | PRUEBAS | RESULTADO | COBERTURA PRINCIPAL |
| :--- | :--- | :---: | :---: | :--- |
| **F1.1A** | Identidad, Actores, Canales, Auditoría | 15 / 15 | **PASS** | Personas, Invariantes, Inmutabilidad Append-Only |
| **F1.1B** | Autenticación, Sesiones, CSRF, Bloqueo | 26 / 26 | **PASS** | Tokens SHA-256, Lockout dinámico, Fixation, Rehash |
| **F1.1C** | RBAC, Roles, Permisos, Autorización | 11 / 11 | **PASS** | Autoridad backend, Jerarquía, Middleware |
| **F1.1D** | Login, DataTables, Modales Alina | 17 / 17 | **PASS** | CRUD Usuarios, Aislamiento con fixtures efímeros |
| **F1.1E** | Hardening, IDOR, SEGURIDAD_AUTH | 25 / 25 | **PASS** | Anti-IDOR, Escalamiento de roles, Erradicación CLI |
| **F1.2A** | Modelo Configuración y Organización | 25 / 25 | **PASS** | Ámbitos PLATAFORMA/ORGANIZACION, Parámetros tipados |
| **F1.2B** | Organización y Branding Seguro | 28 / 28 | **PASS** | Ficha institucional, Rechazo SVG, Sanitización 2MB |
| **F1.2C** | Configuración General y Concurrencia | 26 / 26 | **PASS** | Soberanía, HTTP 409, Parámetros dinámicos de login |
| **F1.2D** | Integración y Consolidación de Catálogos | 20 / 20 | **PASS** | Fail-Closed tenant, Preservación de Orlando, Cero hardcodes |
| **F1.2E** | Hardening Final y Gate de Fase 1.2 | 26 / 26 | **PASS** | Certificación Gate, Concurrencia Org, Paridad DB limpia |
| **TOTAL** | **REGRESIÓN TOTAL ACUMULADA** | **219 / 219** | **PASS** | **100% OPERATIVO, RESILIENTE Y GOBERNADO** |

---

## 6. DECLARACIÓN FORMAL DE CIERRE DE GATE

Habiendo cumplido rigurosamente con la totalidad de los criterios de aceptación técnicos, arquitectónicos y de gobernanza:

1. **Gate Fase 1.2:** **APROBADO — PASS FORMAL**.
2. **Cero regresiones:** Las 219 pruebas automatizadas de Fase 1.1 y Fase 1.2 pasan al 100% de manera determinista.
3. **Cero P0 y P1 abiertos:** No existen vulnerabilidades de seguridad ni fallos funcionales pendientes.
4. **Cuenta `orlando`:** Certificada inmutable con huella `80e6af84e02e89e3` y operando normalmente bajo credencial de desarrollo conocida.
5. **MANDATORY STOP ACTIVADO:** Se detiene toda actividad operativa en espera de la aprobación formal del Usuario antes de dar apertura a la **Fase 2 — Ediciones Candelaria**.
