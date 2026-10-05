# INFORME DE EVIDENCIAS — FASE 2.1C: HARDENING Y GATE FINAL DE EDICIONES CANDELARIA

**Fecha de Ejecución:** 2026-10-05  
**Baseline de Origen:** `643bdcb7e17b50e646ea4bceea5a91bf854c8205`  
**Estado:** ✅ APROBADO / PASS FORMAL (Suite F2.1C: 56/56 PASS | Regresión Acumulada: 359/359 PASS)  
**Alcance:** Hardening profundo, certificación de seguridad negativa, fail-closed, multi-tenant Anti-IDOR, aislamiento multi-pestaña, serialización pesimista de `es_actual` e inmutabilidad terminal de `CERRADA`. CERO inicio de CRM, Clientes ni reservas.

---

## 1. RESUMEN EJECUTIVO DEL GATE F2.1C

En cumplimiento del mandato de hardening y gate final para el módulo Ediciones Candelaria, se ejecutó una auditoría integral estricta sin introducir funcionalidades de negocio especulativas ni prematuras.

### Hallazgos y Correcciones de Hardening Implementadas
1. **`ContextoEdicionResolver`:**
   - **Vulnerabilidad de Fallback Silencioso en Header Vacío/Espacios:** Si el cliente enviaba `X-Edicion-Id: ""` o `X-Edicion-Id: "   "`, el resolver saltaba la rama explícita y ejecutaba fallback institucional.
   - **Corrección:** Se endureció la validación para que cualquier encabezado explícito presente sea evaluado de manera cerrada: si está vacío, contiene solo espacios o no es un entero positivo mayor a 0, se rechaza de inmediato con `InvalidArgumentException` (**Fail-Closed estricto sin fallback**).
   - **Soporte de Escalares Seguros:** En `resolverDesdeServidor`, valores enteros o numéricos escalares se transforman a string de forma determinista, mientras que tipos no escalares (ej. arrays malformados) se marcan para rechazo inmediato.
2. **`EdicionRepositorio`:**
   - **Validación Atómica Previa en `establecerComoActual`:** Antes de resetear las ediciones del tenant a `es_actual = 0`, el repositorio verifica explícitamente que la edición objetivo exista y pertenezca a la organización (`WHERE id = :id AND organizacion_id = :org_id`). Si no existe, lanza `InvalidArgumentException` antes de mutar ninguna fila, garantizando atomicidad total y previniendo que una organización quede sin edición actual ante un ID erróneo.
3. **Frontend y Plantillas (`ediciones.php` y `ediciones.js`):**
   - **Normalización Canónica de Slug:** Se alineó el placeholder (`candelaria-2027`), el texto de ayuda y el estilo visual (`text-transform: lowercase;`) con el estándar kebab-case en minúsculas exigido por la entidad `Edicion` y el esquema relacional, eliminando la discrepancia donde la UI sugería mayúsculas y guiones bajos (`CANDELARIA_2027`).

---

## 2. MATRIZ EXHAUSTIVA DE RESOLUCIÓN DE CONTEXTO (FAIL-CLOSED)

Se certificó el comportamiento determinista de `ContextoEdicionResolver` bajo todas las condiciones de borde:

| Caso de Prueba | Entrada de Encabezado `X-Edicion-Id` | Resultado Obtenido | Origen Contextual | Comportamiento |
| :--- | :--- | :--- | :--- | :--- |
| **2.1** Ausente con `es_actual` | Encabezado no enviado | ID de edición actual del tenant | `INSTITUCIONAL` | Fallback corporativo legítimo |
| **2.2** Válido existente | `X-Edicion-Id: 82` | ID 82 asignado | `EXPLICITA` | Contexto explícito por pestaña |
| **2.3** Entero escalar | `X-Edicion-Id: (int) 82` | ID 82 asignado | `EXPLICITA` | Normalización escalar segura |
| **2.4** Vacío | `X-Edicion-Id: ""` | `InvalidArgumentException` (400) | N/A | **Fail-Closed sin fallback** |
| **2.5** Espacios en blanco | `X-Edicion-Id: "   "` | `InvalidArgumentException` (400) | N/A | **Fail-Closed sin fallback** |
| **2.6** Cero | `X-Edicion-Id: "0"` | `InvalidArgumentException` (400) | N/A | **Fail-Closed sin fallback** |
| **2.7** Negativo | `X-Edicion-Id: "-1"` | `InvalidArgumentException` (400) | N/A | **Fail-Closed sin fallback** |
| **2.8** Decimal | `X-Edicion-Id: "1.5"` | `InvalidArgumentException` (400) | N/A | **Fail-Closed sin fallback** |
| **2.9** Inyección SQL | `X-Edicion-Id: "1 OR 1=1"` | `InvalidArgumentException` (400) | N/A | **Fail-Closed sin fallback** |
| **2.10** Inyección XSS | `X-Edicion-Id: "<script>..."` | `InvalidArgumentException` (400) | N/A | **Fail-Closed sin fallback** |
| **2.11** Estructura no escalar | `X-Edicion-Id: ['array']` | `InvalidArgumentException` (400) | N/A | **Fail-Closed sin fallback** |
| **2.12** ID Inexistente | `X-Edicion-Id: "888888"` | `InvalidArgumentException` (400) | N/A | **Fail-Closed sin fallback** |
| **2.13** ID de Otro Tenant | `X-Edicion-Id: {id_tenant_ajeno}` | `AccesoDenegadoExcepcion` (403) | N/A | **Anti-IDOR Estricto** |
| **2.14** Ausente sin `es_actual` | Encabezado no enviado | `null` | `AUSENTE` | Operación neutral gobernada |

---

## 3. AISLAMIENTO MULTI-PESTAÑA Y CERO MUTACIÓN GLOBAL

Se demostró formalmente que el contexto explícito por pestaña opera de forma totalmente desacoplada:
- **Pestaña 1:** Consulta con encabezado `X-Edicion-Id: 81` (Edición 2026) -> Contexto asignado: 81 (`EXPLICITA`).
- **Pestaña 2:** Consulta simultánea con `X-Edicion-Id: 82` (Edición 2027) -> Contexto asignado: 82 (`EXPLICITA`).
- **Pestaña 3:** Pestaña nueva sin selección -> Contexto asignado: 81 (`INSTITUCIONAL`).
- **Cero Mutación de Estado Global:** Las variables superglobales `$_SESSION` y `$_COOKIE` permanecen idénticas antes y después de cada resolución, confirmando que no existen colisiones inter-pestañas ni dependencias en sesión de servidor.

---

## 4. GOBERNANZA DE MÁQUINA DE ESTADOS E INMUTABILIDAD TERMINAL

Se certificó el cumplimiento exhaustivo del ciclo de vida oficial:

1. **Nacimiento:** Toda edición nace obligatoriamente en `PREOPERACION` con `es_actual = 0`.
2. **Prohibición de Saltos Hacia Adelante:**
   - `PREOPERACION -> POSTPRODUCCION_ENTREGA` ❌ RECHAZADO (`InvalidArgumentException`).
   - `PREOPERACION -> CERRADA` ❌ RECHAZADO (`InvalidArgumentException`).
   - `OPERACION -> CERRADA` ❌ RECHAZADO (`InvalidArgumentException`).
3. **Avance Secuencial Legítimo:**
   - `PREOPERACION -> OPERACION` ✅ PERMITIDO.
   - `OPERACION -> POSTPRODUCCION_ENTREGA` ✅ PERMITIDO.
   - `POSTPRODUCCION_ENTREGA -> CERRADA` ✅ PERMITIDO.
4. **Retrocesos Excepcionales:**
   - `OPERACION -> PREOPERACION` sin motivo ❌ RECHAZADO.
   - `OPERACION -> PREOPERACION` con motivo formal ✅ PERMITIDO y auditado.
   - `POSTPRODUCCION_ENTREGA -> OPERACION` con motivo formal ✅ PERMITIDO y auditado.
   - `POSTPRODUCCION_ENTREGA -> PREOPERACION` (salto doble) ❌ RECHAZADO.
5. **Inmutabilidad Terminal de `CERRADA`:**
   - Intentos de transición o reapertura desde `CERRADA` a cualquier estado ❌ BLOQUEADO CATEGÓRICAMENTE.
   - Intentos de modificar datos ordinarios (nombre, fechas, descripción) en edición `CERRADA` ❌ BLOQUEADO CATEGÓRICAMENTE.

---

## 5. CONCURRENCIA, EXCLUSIVIDAD Y BLOQUEO PESIMISTA `FOR UPDATE`

Se comprobó la serialización pesimista por tenant:
- La instrucción `SELECT id FROM organizaciones WHERE id = :org_id FOR UPDATE` bloquea la fila organizacional durante la reasignación de edición actual.
- Al designar una nueva edición actual, la anterior es desmarcada automáticamente en la misma transacción (`COUNT(es_actual = 1) === 1`).
- Si se suministra un ID inexistente, el repositorio rechaza la operación antes de modificar ninguna fila, preservando la edición institucional preexistente intacta.
- Las demás organizaciones de la base de datos no sufren ninguna alteración en su configuración de `es_actual`.

---

## 6. VERIFICACIÓN DE INSTALACIÓN LIMPIA Y PARIDAD DE ESQUEMA

- Se ejecutó una instalación limpia ejecutando `base_datos/esquema/esquema_base.sql` sobre una base de datos temporal nueva.
- Se crearon las 19 tablas oficiales del sistema sin errores de sintaxis ni de llaves foráneas.
- La tabla `ediciones_candelaria` cuenta con exactamente las 13 columnas autorizadas:
  `id`, `organizacion_id`, `codigo`, `nombre`, `anio`, `estado`, `fecha_inicio`, `fecha_fin`, `descripcion`, `es_actual`, `flyer_oficial_url`, `creado_en`, `actualizado_en`.
- Se confirmó 0 ocurrencias de `configuracion_json` tanto en la base de datos activa como en la instalación limpia.

---

## 7. PRESERVACIÓN ABSOLUTA DE CREDENCIALES (ORLANDO ID 24)

De acuerdo con la directiva estricta de seguridad:
- Se omitió la divulgación de hashes completos, contraseñas o tokens en consola y documentación.
- Se verificó la huella criptográfica SHA-256 truncada (16 caracteres hexadecimales) de la columna `contrasena_hash`:

```text
Usuario ID: 24
Nombre de usuario: orlando
Correo electrónico: orlogonzales@gmail.com
Estado: ACTIVO
Intentos fallidos: 0
Bloqueado hasta: NULL
Huella SHA-256 (truncada 16): 80e6af84e02e89e3  [COINCIDENCIA EXACTA]
```

---

## 8. REGRESIÓN ACUMULADA TOTAL

```text
==============================================================================
CANDELARIAAPP — MATRIZ DE REGRESIÓN ACUMULADA (SUITES F1.1 - F2.1C)
==============================================================================
Fase 1.1A: Fundación de Autenticación                    24/24 PASS
Fase 1.1B: Roles y Permisos RBAC                         15/15 PASS
Fase 1.1C: Multi-Tenant y Seguridad de Sesión            11/11 PASS
Fase 1.1D: Gestión de Usuarios y Concurrencia            21/21 PASS
Fase 1.1E: Gate de Identidad y Seguridad                 23/23 PASS
Fase 1.2A: Configuración Institucional Tipada            24/24 PASS
Fase 1.2B: Seguridad de Archivos y Auditoría             29/29 PASS
Fase 1.2C: Parámetros Dinámicos y Concurrencia           26/26 PASS
Fase 1.2D: Unificación Fail-Closed y Catálogos           20/20 PASS
Fase 1.2E: Gate Final de Organización y Configuración    26/26 PASS
Fase 2.1A: Dominio Edición y Ciclo de Vida               29/29 PASS
Fase 2.1B: Gestión, Selector y Contexto Multi-Pestaña    55/55 PASS
Fase 2.1C: Hardening y Gate Final de Ediciones           56/56 PASS
------------------------------------------------------------------------------
TOTAL DE PRUEBAS AUTOMATIZADAS:                          359/359 PASS (100%)
FALLOS / ERRORES:                                        0
BLOQUEANTES P0 / P1:                                     0
==============================================================================
```
