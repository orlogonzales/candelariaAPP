# INFORME DE EVIDENCIAS — FASE 2.2A Y MICRO-GATE F2.2A-2: MODELO MAESTRO DE PERSONAS Y CLIENTES CRM

**Fecha de Ejecución:** 2026-10-05  
**Baseline de Entrada F2.2A-2:** `502cbae5e5b2483e820f9e7a4ec0a029583653a6`  
**Estado:** ✅ APROBADO / PASS FORMAL (Suite F2.2A: 73/73 PASS | Regresión Acumulada: 432/432 PASS)  
**Alcance:** Establecimiento soberano del modelo maestro de Personas, perfil comercial único de Clientes, normalizador telefónico E.164, máquina de estados comerciales gobernada, gestión de consentimientos operativos y promocionales (append-only), permisos RBAC y **saneamiento definitivo de `personas.metadatos_json` (Micro-Gate F2.2A-2)**. CERO interfaces de usuario (UI), modales, vistas, reservas, ventas ni integraciones externas.

---

## 1. RESUMEN EJECUTIVO DE ARQUITECTURA F2.2A Y F2.2A-2

En cumplimiento de las directrices arquitectónicas soberanas acordadas, se consolidaron los siguientes fundamentos:

1. **Aislamiento Multi-Tenant y Transversalidad de Persona:**
   - La tabla `personas` conserva la restricción `organizacion_id NOT NULL`. No se globalizó `personas` ni se creó una segunda tabla de personas para CRM.
   - Una persona es única por organización y transversal a todas las ediciones de la festividad.
2. **Documento de Identidad Opcional y Coherente:**
   - En `personas`, las columnas `tipo_documento_id` y `numero_documento` son `NULLABLE`.
   - Se mantiene la restricción relacional y de dominio `chk_personas_documento_coherencia CHECK ((tipo_documento_id IS NULL AND numero_documento IS NULL) OR (tipo_documento_id IS NOT NULL AND numero_documento IS NOT NULL))`.
   - Cero documentos ficticios (no se permiten '00000000', 'TEMPORAL', etc.).
3. **Eliminación Definitiva de `metadatos_json` en `personas` (Micro-Gate F2.2A-2):**
   - La auditoría F2.2A-1 certificó 0 consumidores reales, 0 contrato de validación y 0 filas con contenido en la base de datos (100% NULL).
   - Se eliminó formalmente la columna `metadatos_json` de la tabla `personas` mediante migración incremental `2026_10_05_000007_eliminar_metadatos_json_personas.sql`.
   - Se eliminó la propiedad `$metadatos` de la entidad `Persona`, del repositorio `PersonaRepositorio` y del servicio `ClienteServicio`.
   - Se alcanzó **paridad exacta de 18 columnas** entre la base de datos activa migrada y la instalación limpia desde `esquema_base.sql`.
4. **Perfil Comercial Soberano y Único (`clientes`):**
   - Tabla única `clientes` vinculada a `personas.id` con restricción de unicidad estricta `uk_clientes_org_persona (organizacion_id, persona_id)`.
   - No se crearon tablas separadas ni paralelas de prospectos o contactos.
5. **WhatsApp Soberano y Normalización Canónica E.164:**
   - La fuente única de verdad para el teléfono es `personas.telefono_whatsapp` (no se duplica en `clientes`).
   - El backend actúa como autoridad absoluta de normalización a través de `NormalizadorTelefono` (ej. `+519XXXXXXXX`).
   - WhatsApp es obligatorio para toda persona que adquiere un perfil de cliente.
   - Invariante comercial: 1 número de WhatsApp normalizado = máximo 1 perfil de cliente activo/comercial por organización, validado con bloqueo pesimista `FOR UPDATE` e índice `idx_personas_org_whatsapp`.
6. **Máquina de Estados Comerciales Gobernados (`EstadoCliente`):**
   - Valores canónicos: `CONTACTO` (inicial), `PROSPECTO`, `CLIENTE`, `INACTIVO`.
   - `CLIENTE_RECURRENTE` **NO** es un estado almacenable; los intentos de asignarlo son rechazados con excepción de dominio explícita.
   - Cero eliminación física: la salida comercial se modela exclusivamente transicionando a `INACTIVO`.
7. **Consentimientos Operativo y Promocional Desacoplados:**
   - Estados independientes: `consentimiento_operativo` y `consentimiento_promocional`.
   - Ambos nacen en `false` por defecto al registrar al cliente.
   - Historial y trazabilidad append-only inmutable en tabla `consentimientos_cliente` con correlación UUID v4.
   - Estado materializado en `clientes` gobernado exclusivamente por `ConsentimientoServicio`.
8. **Control de Acceso RBAC y Privilegios Mínimos:**
   - 7 nuevos permisos sembrados: `personas.ver`, `personas.crear`, `personas.editar`, `clientes.ver`, `clientes.crear`, `clientes.editar`, `clientes.desactivar`.

---

## 2. CAMBIOS EN ESQUEMA RELACIONAL Y BASE DE DATOS

### Migraciones Incrementales
1. `2026_10_05_000006_crear_modulo_clientes_consentimientos.sql`:
   - `personas`: `tipo_documento_id` y `numero_documento` pasan a `NULL`.
   - Restricción `chk_personas_documento_coherencia` e índice `idx_personas_org_whatsapp`.
   - Creación de tabla `clientes` (12 columnas) y `consentimientos_cliente` (12 columnas).
   - Sembrado de 7 permisos RBAC (módulo 5).
2. `2026_10_05_000007_eliminar_metadatos_json_personas.sql` (Micro-Gate F2.2A-2):
   - `ALTER TABLE personas DROP COLUMN metadatos_json;`
   - Deja la tabla `personas` con exactamente 18 columnas relacionales tipadas.

### Paridad de Esquema Base (`esquema_base.sql`)
- Actualizado sin `metadatos_json` en `personas`.
- Certificado mediante test de Clean Install en base de datos temporal creando 21 tablas canónicas sin errores.
- Paridad de columnas en `personas`: **18 columnas en BD activa vs 18 columnas en Clean Install (100% de paridad)**.

---

## 3. MATRIZ DE CERTIFICACIÓN DE PRUEBAS F2.2A / F2.2A-2 (73/73 PASS)

| Bloque | Descripción | Casos Evaluados | Resultado |
| :--- | :--- | :--- | :--- |
| **Bloque 1** | Esquema Físico, Saneamiento y Clean Install | Documentos nullables, índices WhatsApp, tablas `clientes` y `consentimientos_cliente`, 7 permisos RBAC, clean install 21 tablas, **ausencia de `metadatos_json` en BD activa y Clean install, conteo exacto de 18 columnas oficiales** | **12/12 PASS** |
| **Bloque 2** | Normalizador E.164 | Móviles peruanos (9 dígitos, prefijo 51), caracteres de formateo, números internacionales (+1, +54), rechazo de letras y longitud inválida, requerirE164 | **10/10 PASS** |
| **Bloque 3** | Modelo de Personas y Coherencia | Documento NULL legítimo, rechazo de documento parcial (tipo sin número o número sin tipo), persona sin WhatsApp, unicidad por tenant, aislamiento multi-tenant | **7/7 PASS** |
| **Bloque 4** | Perfil Comercial y Vinculación | Rechazo cliente sin WhatsApp, creación desde persona existente, estado inicial CONTACTO, consentimientos iniciales en false, rechazo de perfil duplicado para la misma persona, alta combinada atómica, reuso de persona por documento sin duplicación | **10/10 PASS** |
| **Bloque 5** | Unicidad Comercial de WhatsApp | 1 WhatsApp = máx 1 cliente en el mismo tenant, mismo WhatsApp permitido en tenants distintos (aislamiento) | **2/2 PASS** |
| **Bloque 6** | Máquina Comercial Gobernada | Transición CONTACTO -> PROSPECTO, PROSPECTO -> CLIENTE, CLIENTE -> INACTIVO, reactivación INACTIVO -> PROSPECTO, rechazo categórico de CLIENTE_RECURRENTE en enum y servicio, cero métodos de borrado físico | **7/7 PASS** |
| **Bloque 7** | Consentimientos Append-Only | Otorgamiento operativo independiente, otorgamiento promocional independiente, revocación operativa sin afectar promocional, bitácora `consentimientos_cliente` con 3 eventos append-only en orden cronológico | **11/11 PASS** |
| **Bloque 8** | Control RBAC y Anti-IDOR | Intento IDOR de mutar cliente ajeno (403), intento IDOR de consentimientos ajenos (403), consulta de cliente ajeno retorna null | **3/3 PASS** |
| **Bloque 9** | Auditoría Transversal | Registro en `auditoria_operaciones` de `CREAR_PERSONA`, `CREAR_CLIENTE`, `CAMBIAR_ESTADO_CLIENTE`, `GESTIONAR_CONSENTIMIENTO_*` | **6/6 PASS** |
| **Bloque 10** | Preservación de Orlando (ID 24) | Existencia, estado ACTIVO, 0 intentos fallidos, bloqueo NULL, coincidencia exacta de huella SHA-256 corta | **5/5 PASS** |

---

## 4. REGRESIÓN ACUMULADA TOTAL (432/432 PASS)

Se ejecutaron las 14 suites del sistema sobre el entorno de desarrollo bajo aislamiento determinista de transacciones:

| Suite | Módulo / Fase | Pruebas | Resultado |
| :--- | :--- | :--- | :--- |
| `ejecutar_pruebas_f11a.php` | F1.1A — Dominio y Criptografía | 15 | ✅ PASS |
| `ejecutar_pruebas_f11b.php` | F1.1B — Sesiones y Cookies | 26 | ✅ PASS |
| `ejecutar_pruebas_f11c.php` | F1.1C — Protección CSRF | 11 | ✅ PASS |
| `ejecutar_pruebas_f11d.php` | F1.1D — Login y Rate Limiting | 17 | ✅ PASS |
| `ejecutar_pruebas_f11e.php` | F1.1E — Gate Final de Seguridad | 25 | ✅ PASS |
| `ejecutar_pruebas_f12a.php` | F1.2A — Parámetros Gobernados | 25 | ✅ PASS |
| `ejecutar_pruebas_f12b.php` | F1.2B — Organización Multi-Tenant | 28 | ✅ PASS |
| `ejecutar_pruebas_f12c.php` | F1.2C — Configuración UI | 26 | ✅ PASS |
| `ejecutar_pruebas_f12d.php` | F1.2D — Hardening Multi-Tenant | 20 | ✅ PASS |
| `ejecutar_pruebas_f12e.php` | F1.2E — Gate Organización | 26 | ✅ PASS |
| `ejecutar_pruebas_f21a.php` | F2.1A — Dominio Edición y Ciclo de Vida | 29 | ✅ PASS |
| `ejecutar_pruebas_f21b.php` | F2.1B — Gestión y Contexto de Edición | 55 | ✅ PASS |
| `ejecutar_pruebas_f21c.php` | F2.1C — Hardening y Gate Final Edición | 56 | ✅ PASS |
| `ejecutar_pruebas_f22a.php` | **F2.2A / F2.2A-2 — Personas y Clientes CRM** | **73** | ✅ **PASS** |
| **TOTAL GENERAL** | **14 Suites Oficiales** | **432** | ✅ **432/432 PASS** |

---

## 5. PRESERVACIÓN ESTRICTA DE CREDENCIALES DE ORLANDO (ID 24)

En estricto cumplimiento de las políticas de privacidad y seguridad:
- **Usuario ID:** 24
- **Estado:** `ACTIVO`
- **Intentos Fallidos:** 0
- **Bloqueado Hasta:** `NULL`
- **Huella SHA-256 Truncada (16 caracteres):** `80e6af84e02e89e3`
- **Confidencialidad:** 0 exposición de hashes completos, correos o datos confidenciales.
