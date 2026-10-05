# INFORME DE EVIDENCIAS Y CONSOLIDACIÓN ARQUITECTÓNICA — MICROLOTE F2.1B
**CandelariaAPP &bull; Plataforma de Gestión y Producción Audiovisual**  
**Festividad de la Virgen de la Candelaria &bull; Puno, Perú**

---

## 1. RESUMEN EJECUTIVO

- **Microlote:** `F2.1B` — Gestión de Ediciones, Selector Global y Contexto Activo de Edición.
- **Baseline de Entrada Oficial:** `58cde14e6589d17681812a04882bc1343aaf8442` (F2.1A aprobado formalmente con 248/248 PASS).
- **Estado de Cierre:** **PASS FORMAL TÉCNICO, DE SEGURIDAD Y ARQUITECTÓNICO (100% PRUEBAS EXITOSAS)**.
- **Regresión Acumulada Total:** **303 / 303 PRUEBAS PASS (0 FALLOS)**.
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
  - `F2.1A` (Modelo Dominio Edición, Máquina de Estados, Lock Pesimista, Auditoría): **29/29 PASS**
  - `F2.1B` (Gestión de Ediciones, Selector Global, Contexto Explícito Multi-Pestaña): **55/55 PASS**
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

## 2. DECISIÓN DE ARQUITECTURA: ELIMINACIÓN DE `configuracion_json` (OPCIÓN A)

Conforme a la instrucción rectora del usuario, se ejecutó formalmente la **Opción A**:
1. **Migración Incremental:** `base_datos/migraciones/2026_10_05_000005_eliminar_configuracion_json_ediciones.sql` aplicada:
   ```sql
   ALTER TABLE `ediciones_candelaria` DROP COLUMN `configuracion_json`;
   ```
2. **Esquema Base Saneado:** `base_datos/esquema/esquema_base.sql` actualizado para reflejar la estructura física definitiva sin contenedor genérico sin tipar.
3. **Backend y Entidades:** `Edicion.php`, `EdicionRepositorio.php` y `EdicionServicio.php` actualizados sin rastro del campo residual.
4. **Verificación en BD:** Comprobado mediante `information_schema.columns` que la columna no existe en el catálogo físico.

---

## 3. MODELO DE CONTEXTO DETERMINISTA: PESTAÑA EXPLÍCITA + FALLBACK INSTITUCIONAL

Se implementó el modelo arquitectónico exacto aprobado por la dirección del proyecto:

```text
EDICIÓN CORPORATIVA (es_actual = 1)
        │
        │ Fallback inicial (solo si header ausente)
        ▼
┌───────────────────────────────┐
│ PESTAÑA DEL NAVEGADOR         │
│ sessionStorage: id_edicion    │
│ Header HTTP: X-Edicion-Id     │
└───────────────┬───────────────┘
                │
                ▼
┌───────────────────────────────┐
│ BACKEND RESOLVER (Fail-Closed)│
│ ContextoEdicionResolver       │
└───────────────────────────────┘
```

### Reglas Rectoras Implementadas:
1. **Aislamiento Multi-Pestaña Estricto:** Cada pestaña del navegador mantiene su propio contexto independiente en `sessionStorage`. PROHIBIDO el uso de `localStorage` y PROHIBIDO mutar `$_SESSION` global con el ID de trabajo.
2. **Inyección Transparente:** `publico/js/candelaria.js` intercepta automáticamente las peticiones mutantes y de lectura mediante Fetch API, inyectando el encabezado `X-Edicion-Id` si existe en `sessionStorage`.
3. **Fail-Closed Riguroso:**
   - Si el encabezado `X-Edicion-Id` está **AUSENTE**, el backend resuelve la edición oficial con `es_actual = 1` (`origenEdicion = 'INSTITUCIONAL'`).
   - Si el encabezado `X-Edicion-Id` está **PRESENTE** pero contiene un valor no numérico, `<= 0`, inexistente o de otro tenant: el backend **RECHAZA INMEDIATAMENTE** la petición (HTTP 400 / 403 / 404). **CERO fallback silencioso a `es_actual`** ante encabezados inválidos.
4. **ContextoOperacion Inmutable:**
   - Propiedades agregadas: `$edicionTrabajoId` (?int) y `$origenEdicion` (string: `'EXPLICITA'` | `'INSTITUCIONAL'` | `'AUSENTE'`).
   - Método `conEdicionTrabajo(?int $edicionId, string $origen): self` que retorna una nueva instancia inmutable garantizando la preservación estricta del actor, usuario, organización y correlación.

---

## 4. ENDPOINTS API Y CONTROLADOR `EdicionControlador`

Se construyó el controlador API oficial `aplicacion/Controladores/EdicionControlador.php` respetando todas las invariantes de seguridad del proyecto:

| Método HTTP | Endpoint | Permiso Requerido | Descripción y Gobernanza |
| :--- | :--- | :--- | :--- |
| `GET` | `/ediciones` | `ediciones.ver` | Renderiza la vista Blade/Alina con layout `principal`, KPIs, contenedor Skeleton y tabla DataTables. |
| `GET` | `/api/v1/ediciones` | `ediciones.ver` | Retorna el catálogo completo de ediciones del tenant. Aislamiento Anti-IDOR estricto. |
| `GET` | `/api/v1/ediciones/{id}` | `ediciones.ver` | Retorna la ficha técnica de una edición específica asegurando que pertenezca a la organización activa (404 ante ajenas). |
| `POST` | `/api/v1/ediciones` | `ediciones.crear` | Da de alta una nueva edición. Nace siempre en `PREOPERACION` y `es_actual = false`. Validación CSRF y unicidad por año/slug. |
| `PUT` | `/api/v1/ediciones/{id}` | `ediciones.editar` | Modifica datos operativos. Concurrencia optimista (409) mediante `actualizado_en_esperado`. Inmutable si estado es `CERRADA`. |
| `POST` | `/api/v1/ediciones/{id}/estado` | `ediciones.cambiar_estado` | Transiciona el estado del ciclo. Avance secuencial ordinario. Retroceso con motivo obligatorio auditado. Bloqueo terminal en `CERRADA`. |
| `POST` | `/api/v1/ediciones/{id}/seleccionar-actual` | `ediciones.seleccionar_actual` | Establece la edición institucional con lock pesimista (`FOR UPDATE`), desmarcando la anterior automáticamente. |
| `GET` | `/api/v1/contexto/edicion` | Autenticado | Resuelve el contexto de trabajo de la pestaña (`edicion_trabajo_id`, `origen`, `edicion_trabajo`) y el catálogo disponible para el selector superior. |

---

## 5. EXPERIENCIA DE USUARIO Y FRONTEND (ALINA + BOOTSTRAP 5)

1. **Cabecera Superior (`cabecera_superior.php`):**
   - Integrado el contenedor `#contenedorSelectorEdicion` en la barra superior.
   - Componente dropdown reactivo que muestra la edición de trabajo actual con insignia `[ACTUAL]`, año y estado, permitiendo conmutar el contexto de la pestaña en tiempo real.
2. **Barra Lateral (`barra_lateral.php`):**
   - Agregado enlace oficial a `/ediciones` con icono `fa-calendar-check`, protegido condicionalmente por el permiso `ediciones.ver`.
3. **Página de Ediciones (`recursos/vistas/paginas/ediciones.php`):**
   - Cabecera con título, subtítulo, botón recargar y botón "Nueva Edición".
   - 4 KPIs estadísticos con diseño Alina: Total Ediciones, Edición Institucional, Contexto Esta Pestaña, y Gobernanza de Ciclo.
   - Contenedor con soporte Skeleton Loader para evitar saltos de layout durante la carga asíncrona.
   - 3 Modales funcionales:
     * **Modal Crear:** Código slug, año, nombre oficial, fechas y lema.
     * **Modal Editar:** Modificación de fechas, nombre y descripción con control de versión concurrente.
     * **Modal Cambiar Estado:** Selector dinámico de fases que detecta retrocesos y exige obligatoriamente la justificación operativa.
4. **JavaScript de Dominio (`publico/js/ediciones.js`):**
   - DataTable interactiva con ordenación cronológica descendente, búsqueda instantánea, paginación y responsive.
   - Integración nativa de `flatpickr` en español para selección ergonómica de fechas.
   - Actualización reactiva parcial: cero `location.reload()`, recarga limpia vía Fetch API tras cualquier mutación.
   - Notificaciones con SweetAlert2 e integración de diálogos de confirmación para acciones críticas.

---

## 6. EVIDENCIAS VISUALES (CAPTURAS DE PANTALLA)

Las 4 capturas requeridas fueron generadas en resolución nativa mediante Chrome CDP en modo headless:

1. **Desktop DataTable y KPIs:**  
   ![Ediciones Desktop](file:///C:/Users/orl55/.gemini/antigravity/brain/541c09d8-c325-44a4-b32d-01ad2625a4c4/screenshot_f21b_ediciones_desktop.png)  
   *Archivo:* `screenshot_f21b_ediciones_desktop.png`  
   *Evidencia:* Muestra el panel con los 4 KPIs (Total Ediciones: 2, Edición Institucional 2026, Contexto Pestaña, Máquina de Estados), selector global en cabecera superior y la tabla DataTable con las ediciones 2027 y 2026 cargadas de forma asíncrona.

2. **Modal Crear Nueva Edición:**  
   ![Modal Crear](file:///C:/Users/orl55/.gemini/antigravity/brain/541c09d8-c325-44a4-b32d-01ad2625a4c4/screenshot_f21b_modal_crear.png)  
   *Archivo:* `screenshot_f21b_modal_crear.png`  
   *Evidencia:* Formulario modal Alina/Bootstrap 5 con campos tipados, Flatpickr y mensaje orientativo de inicialización en `PREOPERACION` con `es_actual = 0`.

3. **Selector Global Desplegado:**  
   ![Selector Global](file:///C:/Users/orl55/.gemini/antigravity/brain/541c09d8-c325-44a4-b32d-01ad2625a4c4/screenshot_f21b_selector_global.png)  
   *Archivo:* `screenshot_f21b_selector_global.png`  
   *Evidencia:* Dropdown de cabecera superior abierto, mostrando las ediciones del tenant, destacando la edición institucional activa con insignia verde `[ACTUAL]` y permitiendo cambiar el contexto explícito de la pestaña.

4. **Vista Móvil Responsive (375x812):**  
   ![Vista Mobile](file:///C:/Users/orl55/.gemini/antigravity/brain/541c09d8-c325-44a4-b32d-01ad2625a4c4/screenshot_f21b_ediciones_mobile.png)  
   *Archivo:* `screenshot_f21b_ediciones_mobile.png`  
   *Evidencia:* Adaptación perfecta en viewport móvil (375x812), selector global colapsado y tarjetas KPI reorganizadas verticalmente.

---

## 7. SUITE DE PRUEBAS DE F2.1B Y REGRESIÓN ACUMULADA

### 7.1. Suite Específica F2.1B (`pruebas/ejecutar_pruebas_f21b.php`) — 55 / 55 PASS
- **Bloque 1:** Verificación de eliminación física de `configuracion_json` (1 prueba).
- **Bloque 2:** `ContextoOperacion` extendido e inmutabilidad estricta (9 pruebas).
- **Bloque 3:** `ContextoEdicionResolver` y reglas fail-closed (10 pruebas).
- **Bloque 4:** Demostración de aislamiento multi-pestaña concurrente (4 pruebas).
- **Bloque 5:** Configuración de actores efímeros con distintos privilegios.
- **Bloque 6:** Endpoints `EdicionControlador` y control de acceso RBAC (6 pruebas).
- **Bloque 7:** Creación de ediciones y validaciones de unicidad de año y slug (5 pruebas).
- **Bloque 8:** Actualización y concurrencia optimista HTTP 409 (2 pruebas).
- **Bloque 9:** Máquina de estados, avances, retrocesos justificados y terminal CERRADA (7 pruebas).
- **Bloque 10:** Selección de edición actual con exclusividad y lock pesimista (5 pruebas).
- **Bloque 11:** Endpoint `GET /api/v1/contexto/edicion` (4 pruebas).
- **Bloque 12:** Preservación de credenciales y estado de Orlando (2 pruebas).

### 7.2. Resumen Acumulado del Proyecto
```text
==============================================================================
FASE 0   FUNDACIÓN DE PLATAFORMA                            ✅ CERRADA
FASE 1.1 IDENTIDAD Y SEGURIDAD CENTRAL                      ✅ CERRADA (94/94 PASS)
FASE 1.2 CONFIGURACIÓN Y GOBERNANZA MULTI-TENANT           ✅ CERRADA (125/125 PASS)
FASE 2.1A MODELO DE DOMINIO EDICIÓN CANDELARIA              ✅ CERRADA (29/29 PASS)
FASE 2.1B GESTIÓN DE EDICIONES, SELECTOR Y CONTEXTO ACTIVO  ✅ CERRADA (55/55 PASS)
------------------------------------------------------------------------------
TOTAL PRUEBAS AUTOMATIZADAS REGRESIÓN:                      303 / 303 PASS (0 FAIL)
DEUDA TÉCNICA / P0 / P1:                                    0
==============================================================================
```

---

## 8. CERTIFICACIÓN DE INVIOLABILIDAD DE LA CUENTA ORLANDO (ID 24)

- **Nombre de usuario:** `orlando`
- **ID:** `24`
- **Hash de contraseña:** `$2y$10$7D5id/mDiyn10d.dVBSjp.RCeu86xfoQntEFGIQgfRf3Kz8AqOQcW`
- **Fingerprint Criptográfica:** `80e6af84e02e89e3` (**INTACTA**)
- **Intentos Fallidos:** `0`
- **Bloqueo Temporal:** `NULL`
- **Estado de Cuenta:** `ACTIVO`
