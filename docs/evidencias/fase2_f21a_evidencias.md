# INFORME DE EVIDENCIAS Y CONSOLIDACIÓN ARQUITECTÓNICA — MICROLOTE F2.1A
**CandelariaAPP &bull; Plataforma de Gestión y Producción Audiovisual**  
**Festividad de la Virgen de la Candelaria &bull; Puno, Perú**

---

## 1. RESUMEN EJECUTIVO

- **Microlote:** `F2.1A` — Modelo de Dominio Edición Candelaria y Ciclo de Vida (Alineación y Cierre Oficial).
- **Baseline de Entrada Oficial:** `b2dfa3286f7309c75692a5e4f017bc21897084e1` (Fase 1.2 Gate Aprobado con 219/219 PASS).
- **Estado de Cierre:** **PASS FORMAL TÉCNICO, DE SEGURIDAD Y ARQUITECTÓNICO (100% PRUEBAS EXITOSAS)**.
- **Regresión Acumulada Total:** **248 / 248 PRUEBAS PASS (0 FALLOS)**.
  - `F1.1A` (Identidad, Actores, Canales, Auditoría): **15/15 PASS**
  - `F1.1B` (Seguridad, Sesiones, CSRF, Bloqueo): **26/26 PASS**
  - `F1.1C` (RBAC, Catálogo Permisos, Autorización Backend): **11/11 PASS**
  - `F1.1D` (Login Alina, DataTables, Modales, API Usuarios): **17/17 PASS**
  - `F1.1E` (Hardening, IDOR, Roles y SEGURIDAD_AUTH): **25/25 PASS**
  - `F1.2A` (Modelo Configuración, Parámetros Tipados, Branding, Caché): **25/25 PASS**
  - `F1.2B` (Ficha Institucional, Modales Alina, Branding Seguro, Anti-IDOR): **28/28 PASS**
  - `F1.2C` (Configuración General, Parámetros Operativos, Soberanía, Concurrencia 409): **26/26 PASS**
  - `F1.2D` (Integración, Catálogos, Tenant Fail-Closed, Aislamiento y Preservación): **20/20 PASS**
  - `F1.2E` (Hardening Final, Concurrencia Org, Paridad DB limpia): **26/26 PASS**
  - `F2.1A` (Modelo de Dominio Edición Candelaria, Ciclo de Vida Aprobado, Terminal Estricto, Lock Pesimista, RBAC Desacoplado y Auditoría): **29/29 PASS**
- **Preservación Inviolable de la Cuenta `orlando` (ID 24):**
  - Huella Digital SHA-256 Pre-Regresión: `80e6af84e02e89e3`
  - Huella Digital SHA-256 Post-Regresión: `80e6af84e02e89e3` (**100% IDÉNTICA**)
  - Estado: `ACTIVO`
  - Intentos Fallidos: `0`
  - Bloqueo Temporal: `NULL`
  - Rol Asignado: `admin_organizacion`
- **Upstream Alina (`admin-dashboard/`):** **100% INTACTO** (cero modificaciones, working tree clean).
- **Higiene de Secretos:** Cero contraseñas en texto claro, credenciales ni tokens en repositorio, seeds, SQL versionado ni documentación.

---

## 2. AUDITORÍA PREVIA Y CLASIFICACIÓN DE COMPONENTES

| COMPONENTE | CLASIFICACIÓN | ESTADO PREVIO | IMPACTO / ACCIÓN APLICADA |
| :--- | :--- | :--- | :--- |
| `ediciones_candelaria` (Tabla) | **EXISTE (Refactorizado)** | Diseñada preliminarmente en Fase 0 con 0 registros (`ano`, `fase_actual` con ENUM incompleto, sin `codigo`). | Migración incremental `004`: Normalizada con columnas `anio`, `codigo` slug, `estado` VARCHAR(30) con CHECK constraint, índices de unicidad e integridad temporal. |
| Módulo 3 (`ediciones`) | **EXISTE (Reutilizado)** | Registrado en catálogo oficial `modulos` desde la Fase 0. | Permisos RBAC vinculados a `modulo_id = 3`. |
| Permisos de Edición | **NUEVO** | 0 permisos existentes para ediciones. | Creados 5 permisos atómicos: `ediciones.ver`, `ediciones.crear`, `ediciones.editar`, `ediciones.cambiar_estado`, `ediciones.seleccionar_actual`. |
| Entidad `Edicion` | **NUEVO** | Inexistente en backend. | Creada entidad inmutable en `Aplicacion\Entidades\Edicion` con validación estricta de invariantes y normalización. |
| Enum `EstadoEdicion` | **NUEVO** | Inexistente en backend. | Creado enum tipado en `Aplicacion\Ediciones\EstadoEdicion` con etiquetas formales en español y máquina de estados estricta. |
| Repositorio `EdicionRepositorio` | **NUEVO** | Inexistente en backend. | Creado repositorio en `Aplicacion\Repositorios\EdicionRepositorio` con serialización pesimista (`FOR UPDATE`), concurrencia optimista y prohibición estricta de eliminación física. |
| Servicio `EdicionServicio` | **NUEVO** | Inexistente en backend. | Creado servicio de dominio en `Aplicacion\Ediciones\EdicionServicio` gobernando autorizaciones RBAC desacopladas, Anti-IDOR, congelamiento terminal de `CERRADA` y auditoría. |

---

## 3. MODELO DE DOMINIO Y ESTRUCTURA RELACIONAL

### 3.1. Definición Relacional (`ediciones_candelaria`)

```sql
CREATE TABLE `ediciones_candelaria` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organizacion_id` INT UNSIGNED NOT NULL,
    `codigo` VARCHAR(60) NOT NULL COMMENT 'Slug canónico único por tenant (ej. candelaria-2027)',
    `nombre` VARCHAR(120) NOT NULL COMMENT 'Nombre formal normalizado en MAYÚSCULAS',
    `anio` SMALLINT UNSIGNED NOT NULL COMMENT 'Año calendario de la edición (ej. 2027)',
    `estado` VARCHAR(30) NOT NULL DEFAULT 'PREOPERACION' COMMENT 'Ciclo de vida gobernado',
    `fecha_inicio` DATE NOT NULL,
    `fecha_fin` DATE NOT NULL,
    `descripcion` VARCHAR(255) DEFAULT NULL COMMENT 'Lema o descripción general de la edición',
    `es_actual` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 si es la edición operativa activa por defecto del tenant',
    `flyer_oficial_url` VARCHAR(255) DEFAULT NULL COMMENT 'Ruta relativa de imagen publicitaria oficial',
    `configuracion_json` JSON DEFAULT NULL COMMENT 'Parámetros e hitos específicos de la edición',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE CASCADE,
    UNIQUE KEY `uk_ediciones_org_anio` (`organizacion_id`, `anio`),
    UNIQUE KEY `uk_ediciones_org_codigo` (`organizacion_id`, `codigo`),
    KEY `idx_ediciones_estado` (`estado`),
    KEY `idx_ediciones_actual` (`es_actual`),
    CONSTRAINT `chk_ediciones_fechas` CHECK (`fecha_inicio` <= `fecha_fin`),
    CONSTRAINT `chk_ediciones_anio` CHECK (`anio` >= 2000 AND `anio` <= 2100),
    CONSTRAINT `chk_ediciones_estado` CHECK (`estado` IN ('PREOPERACION', 'OPERACION', 'POSTPRODUCCION_ENTREGA', 'CERRADA'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Ediciones anuales de la Festividad de la Virgen de la Candelaria';
```

### 3.2. Reglas de Invariantes y Normalización

1. **Unicidad de Año (`uk_ediciones_org_anio`):** Una organización no puede registrar dos ediciones para el mismo año calendario. Distintas organizaciones sí pueden operar ediciones para el mismo año (aislamiento multi-tenant).
2. **Unicidad de Código (`uk_ediciones_org_codigo`):** El código slug (ej. `candelaria-2027`) es único por tenant, admitiendo caracteres alfanuméricos en minúsculas y guiones.
3. **Nombre Oficial:** Todo nombre se normaliza a **MAYÚSCULAS** (ej. `CANDELARIA 2027: FIESTA PATRONAL`) con longitud mínima de 3 y máxima de 120 caracteres.
4. **Coherencia Temporal:** `fecha_inicio <= fecha_fin` garantizado en entidad PHP y en restricción CHECK de base de datos.
5. **Rango de Años:** Limitado al intervalo de gobernanza `[2000, 2100]`.

---

## 4. CICLO DE VIDA OFICIAL Y MÁQUINA DE ESTADOS APROBADA

### 4.1. Fases Operativas

```text
PREOPERACION ────────► OPERACION ────────► POSTPRODUCCION_ENTREGA ────────► CERRADA
 (Preoperación)        (Operación)          (Postproducción / Entrega)      (Cerrada - Congelada)
```

| ESTADO | ETIQUETA HUMANA | NATURALEZA | DESCRIPCIÓN |
| :--- | :--- | :--- | :--- |
| `PREOPERACION` | PREOPERACIÓN | Inicial | Fase de planificación, configuración de hitos, paquetes, cotizaciones tempranas y logística previa. |
| `OPERACION` | OPERACIÓN | Activa | Desarrollo en vivo de los días centrales de la Festividad (concursos, pasacalles, coberturas de campo y rodaje). |
| `POSTPRODUCCION_ENTREGA` | POSTPRODUCCIÓN / ENTREGA | Posterior | Selección, edición de material audiovisual, catalogación, generación de galerías y entrega a clientes. |
| `CERRADA` | CERRADA | Terminal Estricto | Ejercicio operativo, documental y comercial concluido. Históricamente congelada: CERO transiciones permitidas. |

### 4.2. Reglas de Transición y Gobernanza de Estados

1. **Avance Secuencial Estricto:**
   - `PREOPERACION` &rarr; `OPERACION`
   - `OPERACION` &rarr; `POSTPRODUCCION_ENTREGA`
   - `POSTPRODUCCION_ENTREGA` &rarr; `CERRADA`
   - Se prohíben saltos ilegales (ej. `PREOPERACION` &rarr; `CERRADA` o `PREOPERACION` &rarr; `POSTPRODUCCION_ENTREGA`).
2. **Retrocesos Operativos Extraordinarios:**
   - Admitidos únicamente entre fases operativas activas:
     - `OPERACION` &rarr; `PREOPERACION`
     - `POSTPRODUCCION_ENTREGA` &rarr; `OPERACION`
   - **Exigen obligatoriamente:** motivo formal no vacío, permiso RBAC `ediciones.cambiar_estado` y registro inmutable en `auditoria_operaciones` bajo la acción `RETROCEDER_ESTADO_EDICION`.
3. **Estado `CERRADA` Congelado (Terminal Estricto):**
   - Una vez que la edición alcanza el estado `CERRADA`, queda **históricamente congelada**.
   - **CERO transiciones de salida permitidas:** Se eliminó cualquier mecanismo de reapertura (`CERRADA` &rarr; `POSTPRODUCCION_ENTREGA`, `OPERACION` o `PREOPERACION` son categóricamente RECHAZADOS con `InvalidArgumentException`).
   - Se bloquea además cualquier mutación ordinaria a nombres, fechas o configuración en `EdicionServicio::actualizar()`.

---

## 5. EDICIÓN ACTUAL / ACTIVA: SEMÁNTICA Y EXCLUSIVIDAD TRANSACCIONAL

1. **Semántica de Negocio Aprobada:**
   - `es_actual` designa la **edición de trabajo predeterminada para la organización**, no su fase operativa.
   - Es completamente independiente del ciclo de vida: una edición en `PREOPERACION` puede tener `es_actual = 1` (ej. la organización preparando Candelaria 2027 durante meses previos).
   - De igual manera, una edición en `POSTPRODUCCION_ENTREGA` o `CERRADA` puede tener `es_actual = 0`.
2. **Exclusividad Concurrente con Bloqueo Pesimista (Lock de Fila):**
   - En `EdicionRepositorio::establecerComoActual($id, $organizacionId)` se implementó un bloqueo pesimista `SELECT id FROM organizaciones WHERE id = :org_id FOR UPDATE` dentro de la transacción.
   - Esto serializa de forma estricta las peticiones concurrentes a nivel de tenant antes de ejecutar `UPDATE es_actual = 0` y `UPDATE es_actual = 1`, erradicando cualquier condición de carrera.
3. **Desacoplamiento RBAC:**
   - La selección de la edición actual exige exclusivamente el permiso `ediciones.seleccionar_actual`.
   - No requiere ni confiere el permiso `ediciones.cambiar_estado`. Ambos permisos operan de manera 100% aislada.

---

## 6. AUDITORÍA TÉCNICA DE `configuracion_json`

Conforme al mandato del Gate Funcional, se realizó una auditoría exhaustiva de la columna `configuracion_json`:

1. **Propósito Inicial en el Diseño:** Concebida originalmente en la migración `004` como un campo abierto para albergar hitos específicos, cupos por danza o parámetros operativos locales de una edición.
2. **Estructura y Tipado:** Columna MySQL de tipo `JSON DEFAULT NULL`. En la entidad PHP `Edicion` se mapea simplemente como `?array $configuracion = null`.
3. **Validación y Schema:** **Ausencia total de esquema o contrato.** No existe JSON Schema, ni validación de campos requeridos, tipos o rangos en PHP ni en BD. Acepta cualquier arreglo asociativo serializado mediante `json_encode`.
4. **Lectores (Readers) en el Código Base:** CERO lectores funcionales. Ningún controlador, servicio ni vista consulta atributos dentro de `$edicion->configuracion`.
5. **Escritores (Writers) en el Código Base:** Únicamente `EdicionRepositorio::crear` y `EdicionRepositorio::actualizar` cuando se les pasa la clave `'configuracion'`.
6. **Datos Existentes en Base de Datos:** **0 filas en la tabla `ediciones_candelaria`** (la tabla se encuentra completamente vacía). Cero datos persistidos.
7. **Riesgo Identificado:** Se comporta como una bolsa libre desregulada (arbitrary key-value store), lo que contradice el principio de gobernanza, tipado y soberanía aplicado a `parametros_configuracion` en la Fase 1.2.
8. **Recomendación Formal para el Usuario:**
   - **OPCIÓN A (Recomendada):** Eliminar la columna `configuracion_json` en la migración de F2.1B y mantener la entidad limpia hasta que los módulos de Danzas/Comparsas o Logística definan sus requerimientos funcionales concretos con columnas relacionales fuertemente tipadas.
   - **OPCIÓN B:** Si se conservara, definir un Value Object tipado (`EdicionConfiguracion`) con esquema formal, validación cerrada e inmutabilidad, prohibiendo la inyección de claves arbitrarias.
   - *Nota de Cumplimiento:* **NO se ha modificado la estructura física en BD**, permaneciendo a la espera de la decisión del usuario.

---

## 7. CONTROL DE ACCESO (RBAC) Y AISLAMIENTO MULTI-TENANT

### 7.1. Permisos Atómicos Incorporados (Módulo 3: Ediciones)

| ID | CÓDIGO | NOMBRE | DESCRIPCIÓN | ASIGNACIÓN INICIAL |
| :---: | :--- | :--- | :--- | :--- |
| `14` | `ediciones.ver` | Ver Ediciones | Consulta de catálogo y detalle de ediciones. | Superadmin, Admin Org, Operador Producción |
| `15` | `ediciones.crear` | Crear Edición | Alta de nueva edición en la organización. | Superadmin, Admin Org |
| `16` | `ediciones.editar` | Editar Edición | Modificación de datos y fechas de la edición. | Superadmin, Admin Org |
| `17` | `ediciones.cambiar_estado` | Cambiar Estado | Transición del ciclo de vida de la edición. | Superadmin, Admin Org |
| `18` | `ediciones.seleccionar_actual` | Seleccionar Edición Actual | Designación de la edición operativa predeterminada. | Superadmin, Admin Org |

### 7.2. Aislamiento Anti-IDOR

- Todas las operaciones en `EdicionServicio` exigen `organizacionId` y validan el contexto de operación activo.
- Un operador perteneciente a la Organización A es categóricamente rechazado al intentar consultar, crear, modificar o transicionar una edición perteneciente a la Organización B.
- Ausencia de tenant en el contexto de operación resulta en **Fail-Closed inmediato (AccesoDenegadoExcepcion / HTTP 403)**.

---

## 8. CONCURRENCIA OPTIMISTA Y CONSERVACIÓN HISTÓRICA

1. **Concurrencia Optimista (HTTP 409):** `EdicionRepositorio::actualizar()` compara la marca temporal `actualizado_en` existente en la base de datos contra el valor esperado recibido desde la petición. Si detecta desfase, lanza `ConflictoConcurrenciaExcepcion`.
2. **Prohibición de Eliminación Física (Append-Only):** `EdicionRepositorio` no expone ningún método `delete()`, `destroy()` o `truncate()`. Las ediciones son activos históricos permanentes del negocio; para cesar operaciones se utiliza el estado terminal `CERRADA`.

---

## 9. SUITE DE PRUEBAS DE DOMINIO F2.1A (29/29 PASS)

Se actualizó la suite automatizada [`pruebas/ejecutar_pruebas_f21a.php`](file:///d:/laragon/www/app.candelaria/pruebas/ejecutar_pruebas_f21a.php) con 29 pruebas atómicas:

```text
==============================================================================
CANDELARIAAPP — SUITE DE PRUEBAS DE DOMINIO EDICIÓN CANDELARIA F2.1A
ENTIDAD, PERSISTENCIA, CICLO DE VIDA, RBAC, ANTI-IDOR, CONCURRENCIA Y AUDITORÍA
==============================================================================

 [PASS] 01. Creación válida de edición con normalización de nombre a MAYÚSCULAS y código slug
 [PASS] 02. Invariante de Entidad: Organización obligatoria y rechazo de ID <= 0
 [PASS] 03. Integridad Relacional: Rechazo de creación para tenant inexistente
 [PASS] 04. Rango de Años: Aceptación legítima de año en rango [2000, 2100]
 [PASS] 05. Rango de Años: Rechazo riguroso de años fuera de rango (<2000 o >2100)
 [PASS] 06. Unicidad de Negocio: Rechazo de año duplicado en la misma organización
 [PASS] 07. Unicidad de Código: Rechazo de código slug duplicado en la misma organización
 [PASS] 08. Multi-Tenant: Mismo año y código admitidos simultáneamente en distintas organizaciones
 [PASS] 09. Coherencia Temporal: Intervalo de fechas válido (fecha_inicio <= fecha_fin)
 [PASS] 10. Coherencia Temporal: Rechazo estricto si fecha_inicio es posterior a fecha_fin
 [PASS] 11. Ciclo de Vida: Toda nueva edición nace en estado PREOPERACION con etiqueta formal
 [PASS] 12. Máquina de Estados: Rechazo de estados arbitrarios o no gobernados
 [PASS] 13. Máquina de Estados: Avance legítimo de PREOPERACION a OPERACION
 [PASS] 14. Máquina de Estados: Avance secuencial a POSTPRODUCCION_ENTREGA y estado terminal CERRADA
 [PASS] 15. Máquina de Estados: Rechazo de saltos de fase ilegales (PREOPERACION -> CERRADA)
 [PASS] 16. Máquina de Estados: Retroceso controlado entre fases operativas con motivo formal
 [PASS] 17. Máquina de Estados: Rechazo de retroceso si no se provee motivo justificativo
 [PASS] 18. Máquina de Estados: Retroceso controlado (POSTPRODUCCION_ENTREGA -> OPERACION) con motivo formal y rechazo sin motivo
 [PASS] 19. Invariante de Cierre: Edición CERRADA bloquea modificaciones operativas ordinarias
 [PASS] 20. Terminal Estricto: Edición CERRADA congelada prohíbe categóricamente toda transición o reapertura
 [PASS] 21. Edición Actual: Exclusividad transaccional garantizada (solo una edición actual por tenant) con lock pesimista
 [PASS] 22. Semántica de Negocio: es_actual es independiente del estado operativo (PREOPERACION puede ser actual, CERRADA inactiva)
 [PASS] 23. Desacoplamiento RBAC: ediciones.seleccionar_actual y ediciones.cambiar_estado son permisos independientes
 [PASS] 24. Aislamiento Anti-IDOR: Operador no puede consultar ni mutar ediciones pertenecientes a otra organización
 [PASS] 25. Concurrencia Optimista: ConflictoConcurrenciaExcepcion ante versión desfasada
 [PASS] 26. Conservación Histórica: EdicionRepositorio prohíbe eliminación física por diseño
 [PASS] 27. Auditoría Inmutable: Eventos de creación, actualización, avance, retroceso y selección actual registrados
 [PASS] 28. RBAC: Operador de producción sin permiso ediciones.crear es denegado
 [PASS] 29. Preservación Post-Rollback: Cuenta orlando (ID 24) 100% inalterada con huella criptográfica idéntica

==============================================================================
RESULTADO FINAL F2.1A: 29 PRUEBAS EXITOSAS / 0 FALLOS
==============================================================================
```

---

## 10. TABLA CONSOLIDADA DE REGRESIÓN DE CANDELARIAAPP

| FASE | SUITE | PRUEBAS | RESULTADO | COBERTURA |
| :--- | :--- | :---: | :---: | :--- |
| **Fase 1.1** | `F1.1A` — Identidad, Actores, Auditoría | 15 / 15 | **PASS** | Personas, Invariantes, Append-Only |
| | `F1.1B` — Seguridad, Sesiones, Lockout | 26 / 26 | **PASS** | Hashes, Tokens, CSRF, Bloqueo dinámico |
| | `F1.1C` — RBAC, Permisos, Autorización | 11 / 11 | **PASS** | Jerarquía, Middleware, Autoridad backend |
| | `F1.1D` — Login, DataTables, Modales Alina | 17 / 17 | **PASS** | CRUD Usuarios, Aislamiento determinista |
| | `F1.1E` — Hardening, Anti-IDOR, SEGURIDAD_AUTH | 25 / 25 | **PASS** | Hardening final, Escalamiento de roles |
| **Fase 1.2** | `F1.2A` — Modelo Configuración y Organización | 25 / 25 | **PASS** | Ámbitos PLATAFORMA/ORGANIZACION, Parámetros |
| | `F1.2B` — Organización y Branding Seguro | 28 / 28 | **PASS** | Ficha institucional, Rechazo SVG, Subidas |
| | `F1.2C` — Configuración General y Parámetros | 26 / 26 | **PASS** | Parámetros dinámicos, Concurrencia 409 |
| | `F1.2D` — Integración y Catálogos Transversales | 20 / 20 | **PASS** | Fail-Closed, Preservación de Orlando |
| | `F1.2E` — Hardening Final y Gate Fase 1.2 | 26 / 26 | **PASS** | Gate F1.2 certificado, Paridad DB limpia |
| **Fase 2.1** | `F2.1A` — Dominio Edición y Ciclo de Vida | 29 / 29 | **PASS** | Entidad Edición, Máquina de Estados, RBAC, Terminal CERRADA, Lock Pesimista |
| **TOTAL** | **REGRESIÓN TOTAL CONSOLIDADA** | **248 / 248** | **PASS** | **100% OPERATIVO, ESTABLE Y AUDITADO** |
