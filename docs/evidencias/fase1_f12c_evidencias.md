# INFORME DE EVIDENCIAS Y ARQUITECTURA — MICROLOTE F1.2C
**CandelariaAPP &bull; Plataforma de Gestión y Producción Audiovisual**  
**Festividad de la Virgen de la Candelaria &bull; Puno, Perú**

---

## 1. RESUMEN EJECUTIVO

- **Microlote:** `F1.2C` — Configuración General y Parámetros Operativos (Soberanía Plataforma vs Organización, Fail-Closed Anti-IDOR, Concurrencia Optimista 409, Controles Alina, Integración Dinámica con Seguridad y Auditoría).
- **Baseline de Entrada Oficial:** `3e74039eef938f4c9f6fbe2ecb19fa01510e7008` (F1.2B Aprobado con 147/147 PASS).
- **Estado de Cierre:** **PASS FORMAL TÉCNICO Y VISUAL (100% PRUEBAS EXITOSAS)**.
- **Regresión Acumulada Total:** **173 / 173 PRUEBAS PASS (0 FALLOS)**.
  - `F1.1A` (Identidad, Actores, Canales, Auditoría): **15/15 PASS**
  - `F1.1B` (Seguridad, Sesiones, CSRF, Bloqueo): **26/26 PASS**
  - `F1.1C` (RBAC, Catálogo Permisos, Autorización Backend): **11/11 PASS**
  - `F1.1D` (Login Alina, DataTables, Modales, API Usuarios): **17/17 PASS**
  - `F1.1E` (Hardening, IDOR, Roles y SEGURIDAD_AUTH): **25/25 PASS**
  - `F1.2A` (Modelo Configuración, Parámetros Tipados, Branding, Caché): **25/25 PASS**
  - `F1.2B` (Ficha Institucional, Modales Alina, Branding Seguro, Anti-IDOR): **28/28 PASS**
  - `F1.2C` (Configuración General, Parámetros Operativos, Soberanía, Concurrencia 409): **26/26 PASS**
- **PHP Lint:** 0 errores sintácticos en la totalidad del repositorio (48/48 archivos verificados).
- **Composer:** Validez estricta de `composer.json` (`./composer.json is valid`).
- **Whitespace / Git Check:** Cero errores de formato o tabulaciones espurias (`git diff --check` limpio).
- **Secret Scan:** Cero credenciales ni secretos en texto claro versionados (`git grep -i "Cand26!"` verificado limpio).
- **Upstream Alina (`admin-dashboard/`):** **100% INTACTO** (cero modificaciones, working tree clean).
- **Disponibilidad HTTP:**
  - `http://app.candelaria.test/configuracion/general` (HTTP 200 con sesión, HTTP 302 redirección a login sin sesión).
  - `http://localhost/app.candelaria/configuracion/general` (HTTP 200 con sesión, HTTP 302 redirección a login sin sesión).
  - `http://app.candelaria.test/configuracion/seguridad.php` (HTTP 403 Forbidden estricto por Apache `.htaccess`).

---

## 2. ARQUITECTURA DE SOBERANÍA Y SEPARACIÓN DE ÁMBITOS

### 2.1. Catálogo Gobernado y Estricto (Prohibición de Bolsa Libre)
En estricto apego al mandato arquitectónico, CandelariaAPP **no implementa una bolsa libre `clave -> valor`** ni permite la creación o eliminación de parámetros arbitrarios desde la interfaz web o API REST. Todos los parámetros pertenecen a un catálogo cerrado, tipado y gobernado con integridad referencial:
- **Ámbito `PLATAFORMA`:** Parámetros globales soberanos que regulan el comportamiento transversal de la plataforma. Modificables **exclusivamente por el Superadministrador de Plataforma** (`superadmin_plataforma`).
- **Ámbito `ORGANIZACION`:** Parámetros operativos y comerciales del tenant (`10000`). Modificables por el Administrador de Organización (`admin_organizacion`) con el permiso `configuracion_organizacion.editar`.
- **Ámbito `EDICIÓN`:** Reservado arquitectónicamente para la Fase 2 (Ediciones Candelaria), sin implementación prematura.

### 2.2. Erradicación del Fallback `?? 10000` (Fail-Closed Estricto)
Se corrigió conceptualmente la resolución del tenant en el backend:
- Tanto en `OrganizacionControlador::resolverOrganizacionId` como en `ConfiguracionControlador::resolverOrganizacionId`, se eliminó el fallback silencioso a identificadores numéricos fijos (`?? 10000`).
- Si una operación autenticada de ámbito organizacional carece de `organizacionId` en su `ContextoOperacion`, el sistema aplica **FAIL-CLOSED** inmediato, respondiendo con **HTTP 403 Forbidden** (`AccesoDenegadoExcepcion: 'Contexto organizacional ausente o inválido para la sesión activa.'`).

### 2.3. Tipado Fuerte y Validación de Parámetros
Cada parámetro almacena su valor como cadena en la base de datos pero se valida y castea estrictamente según su definición relacional:
1. `plataforma.moneda_principal` (`STRING`): Inmutable en tiempo de ejecución (`es_editable = 0`). Bloqueado en `PEN` (Soles peruanos). Cualquier intento de modificación mediante PUT es rechazado con **HTTP 422**.
2. `plataforma.monto_minimo_pago_pe` (`DECIMAL`): Rango gobernado `[1.0, 10000.0]` con precisión decimal de 2 dígitos.
3. `plataforma.zona_horaria` (`STRING`): Validado estrictamente contra la lista oficial IANA del sistema (`timezone_identifiers_list()`). Zonas inválidas son rechazadas con **HTTP 422**.
4. `plataforma.permitir_registro_publico` (`BOOLEAN`): Representado en BD como `0` o `1` y gestionado mediante Alina Basic Switch.
5. `plataforma.max_intentos_login` (`INTEGER`): Rango gobernado `[3, 10]`.
6. `plataforma.minutos_bloqueo_login` (`INTEGER`): Rango gobernado `[5, 1440]`.
7. `organizacion.notificar_whatsapp` (`BOOLEAN`): Switch de notificaciones comerciales automáticas para el tenant.
8. `organizacion.dias_validez_cotizacion` (`INTEGER`): Rango gobernado `[1, 60]` días calendario.
9. `organizacion.porcentaje_reserva_minimo` (`DECIMAL`): Rango gobernado `[10.0, 100.0]%`.

---

## 3. INTEGRACIÓN DINÁMICA CON AUTENTICACIÓN Y SEGURIDAD

En `aplicacion/Seguridad/AutenticacionServicio.php`, el manejo de fallos y penalizaciones temporales por fuerza bruta ya no depende exclusivamente de constantes estáticas:
- Se inyectó dinámicamente `ConfiguracionServicio`.
- Cuando se registra un fallo de contraseña, el umbral de intentos máximos (`$maxIntentos`) y la duración del bloqueo (`$minutosBloqueo`) se resuelven en tiempo de ejecución desde `plataforma.max_intentos_login` y `plataforma.minutos_bloqueo_login`, manteniendo un fallback seguro hacia `ConfiguracionSeguridad`.
- **Verificación en Suite F1.2C (Prueba 20):** Al configurar `plataforma.max_intentos_login = 3`, una cuenta que acumula 3 fallos consecutivos queda automáticamente bloqueada (`bloqueado_hasta != NULL`), comprobando la reactividad dinámica del motor de seguridad.

---

## 4. CONCURRENCIA LIGERA OPTIMISTA (HTTP 409 CONFLICT)

Para evitar sobrescrituras ciegas (*blind overwrites*) entre operadores concurrentes:
1. En cada consulta `GET /api/v1/configuracion`, el backend expone el timestamp `actualizado_en` de cada parámetro.
2. Al emitir un `PUT /api/v1/configuracion`, el cliente envía el `actualizado_en` que tenía en memoria cuando cargó la pantalla.
3. Si otro operador modificó el parámetro mientras tanto (`actualizado_en` en BD > `actualizado_en` enviado):
   - El backend aborta la transacción y responde con **HTTP 409 Conflict** (`ConflictoConcurrenciaExcepcion`).
   - El frontend captura el 409, muestra una alerta modal interactiva de advertencia vía SweetAlert2 (*"Conflicto de Concurrencia: Los parámetros fueron modificados por otro operador. Se actualizarán los valores más recientes."*) y refresca los datos in-situ sin recarga de página.
4. Si el timestamp coincide, la actualización se procesa exitosamente (**HTTP 200**).

---

## 5. EXPERIENCIA DE USUARIO Y ALINEACIÓN VISUAL ALINA

### 5.1. Componentes Nativos Utilizados
La interfaz (`recursos/vistas/paginas/configuracion/general.php` y `publico/js/configuracion_general.js`) se diseñó sin tablas técnicas genéricas:
- **Alina Basic Switches:** Clase `.form-check .form-switch .switch-md .app-switch` con etiquetas reactivas ("ACTIVO" / "INACTIVO", "HABILITADO" / "DESACTIVADO") que cambian dinámicamente sin recargar la página.
- **Grupos Numéricos con Badges:** Inputs numéricos encapsulados en `.input-group` con sufijos y prefijos integrados (`días`, `%`, `S/`, `intentos`, `minutos`).
- **Select IANA de Zona Horaria:** Menú desplegable con husos horarios destacados (`America/Lima`, `UTC`, `America/La_Paz`, `America/Bogota`, `America/Santiago`).
- **Pestañas Semánticas por Ámbito:**
  - Pestaña 1: *Parámetros de Organización* (accesible para `admin_organizacion`).
  - Pestaña 2: *Soberanía de Plataforma* (exclusiva para `superadmin_plataforma`, señalizada con badge distintivo).
- **Parámetro Inmutable Protegido:** `plataforma.moneda_principal` se presenta en modo de solo lectura con badge de candado y fondo atenuado.

### 5.2. Ciclo Asíncrono Completo (Cero `location.reload()`)
- **Skeleton Shimmer:** Durante el fetch inicial, se muestra un skeleton que replica exactamente la estructura de tarjetas y controles.
- **Estado Disabled + Spinner:** Cada botón de guardado bloquea su formulario y muestra un spinner durante el envío.
- **SweetAlert2 Feedback:** Confirmaciones limpias y toasts no invasivos.
- **Refresco Parcial:** Se actualizan los valores y los badges de timestamp ("Última modificación") directamente en el DOM.

---

## 6. RESULTADOS FORMALES DE LA SUITE F1.2C (26 / 26 PASS)

```text
==============================================================================
CANDELARIAAPP — SUITE DE PRUEBAS DE CONFIGURACIÓN GENERAL Y PARÁMETROS F1.2C
SOBERANÍA PLATAFORMA/ORGANIZACIÓN, FAIL-CLOSED, CONCURRENCIA 409, CSRF Y AUDITORÍA
==============================================================================

 [PASS] 00. Organización operativa 10000 existe en la base de datos
 [PASS] 01. GET /api/v1/configuracion sin sesión activa retorna HTTP 401
 [PASS] 02. GET /api/v1/configuracion por operador sin permisos retorna HTTP 403
 [PASS] 03. Admin de Organización ve parámetros de su tenant y tiene plataforma bloqueada
 [PASS] 04. Admin de Organización intentando filtrar ?ambito=PLATAFORMA recibe HTTP 403
 [PASS] 05. Superadministrador de Plataforma tiene visibilidad y soberanía sobre ambos ámbitos
 [PASS] 06. PUT /api/v1/configuracion sin CSRF token retorna HTTP 403
 [PASS] 07. PUT /api/v1/configuracion con CSRF token inválido retorna HTTP 403
 [PASS] 08. Fail-closed: Operación de organización sin tenant_id en contexto retorna HTTP 403
 [PASS] 09. Admin actualiza correctamente booleano organizacion.notificar_whatsapp
 [PASS] 10. Admin actualiza entero organizacion.dias_validez_cotizacion a 14 días
 [PASS] 11. Valor 999 para dias_validez_cotizacion es rechazado con HTTP 422 (max: 60)
 [PASS] 12. Admin actualiza decimal organizacion.porcentaje_reserva_minimo a 50.00%
 [PASS] 13. Valor 5.00% para porcentaje_reserva_minimo es rechazado con HTTP 422 (min: 10.0)
 [PASS] 14. Admin de Organización intentando modificar PLATAFORMA recibe HTTP 403
 [PASS] 15. Mutación de parámetro protegido (plataforma.moneda_principal) rechazada con HTTP 422
 [PASS] 16. Zona horaria IANA válida (UTC) es aceptada y persistida
 [PASS] 17. Zona horaria no perteneciente a IANA es rechazada con HTTP 422
 [PASS] 18. Superadmin actualiza plataforma.monto_minimo_pago_pe a S/ 25.50
 [PASS] 19. Superadmin actualiza en lote (batch) parámetros de seguridad de autenticación
 [PASS] 20. AutenticacionServicio aplica el límite dinámico de 3 intentos configurado en plataforma
 [PASS] 21. Intento de modificación con timestamp desactualizado retorna HTTP 409 Conflict
 [PASS] 22. Modificación con timestamp vigente se procesa exitosamente (HTTP 200)
 [PASS] 23. Catálogo cerrado: Parámetros no gobernados son rechazados (HTTP 422)
 [PASS] 24. Auditoría inmutable registra ambas operaciones con datos antes/después y libre de secretos
 [PASS] 25. Caché de proceso de ConfiguracionServicio sincronizada y consistente

==============================================================================
RESULTADOS F1.2C: Exitosos: 26 | Fallidos: 0
==============================================================================
```

---

## 7. MATRIZ DE REGRESIÓN ACUMULADA CONSOLIDADA

| Microlote | Componente / Dominio | Pruebas | Resultado | Estado |
|---|---|:---:|:---:|:---:|
| **F1.1A** | Identidad, Actores del Sistema, Canales, Auditoría Append-Only | 15 | 15 / 15 | **PASS** |
| **F1.1B** | Autenticación Segura, Sesiones Criptográficas, Fixation, CSRF, Rehash | 26 | 26 / 26 | **PASS** |
| **F1.1C** | RBAC, Catálogo de Permisos, Autorización Backend, Roles | 11 | 11 / 11 | **PASS** |
| **F1.1D** | Login Visual Alina, DataTables Usuarios, Modales CRUD, API-First | 17 | 17 / 17 | **PASS** |
| **F1.1E** | Hardening Transversal, Anti-IDOR, SEGURIDAD_AUTH, Integridad | 25 | 25 / 25 | **PASS** |
| **F1.2A** | Modelo de Configuración y Organización, Parámetros Tipados, Branding | 25 | 25 / 25 | **PASS** |
| **F1.2B** | Ficha Institucional, Modales Alina, Branding Seguro, Anti-IDOR | 28 | 28 / 28 | **PASS** |
| **F1.2C** | Configuración General, Soberanía Plataforma/Organización, Concurrencia 409 | 26 | 26 / 26 | **PASS** |
| **TOTAL** | **Regresión Acumulada Integral CandelariaAPP** | **173** | **173 / 173** | **PASS FORMAL (100%)** |

---

## 8. EVIDENCIAS VISUALES GENERADAS VÍA CHROME CDP

Se capturaron y verificaron cuatro evidencias gráficas de alta resolución en el directorio de artefactos:

1. **Vista Desktop de Organización (`screenshot_f12c_configuracion_desktop.png`):**
   - Muestra la cabecera institucional, badge "Entorno Gobernado", botón "Actualizar", pestaña activa "Parámetros de Organización", tarjeta de Políticas Comerciales (Validez de Cotizaciones y Reserva Mínima con timestamps) y tarjeta de Canales de Comunicación (Switch Alina de WhatsApp con badge "ACTIVO").
2. **Reactividad e Interacción (`screenshot_f12c_configuracion_interaccion.png`):**
   - Demuestra la respuesta del switch de WhatsApp al ser desactivado (el badge conmuta a "INACTIVO" en tiempo real) y la modificación de validez a "15 días".
3. **Vista Mobile (`screenshot_f12c_configuracion_mobile.png`):**
   - Demuestra la perfecta adaptabilidad responsive a 375x812 píxeles conforme al sistema de diseño Alina.
4. **Pestaña Soberana de Plataforma (`screenshot_f12c_configuracion_plataforma_desktop.png`):**
   - Evidencia el acceso exclusivo del Superadministrador con el banner de advertencia, parámetro inmutable `PEN`, monto mínimo de pago, selector IANA de huso horario y switch de registro público.
