# INFORME DE EVIDENCIAS Y ARQUITECTURA — MICROLOTE F1.2B
**CandelariaAPP &bull; Plataforma de Gestión y Producción Audiovisual**  
**Festividad de la Virgen de la Candelaria &bull; Puno, Perú**

---

## 1. RESUMEN EJECUTIVO

- **Microlote:** `F1.2B` — Ficha de Organización y Branding (Perfil Institucional, Edición Asíncrona, Seguridad de Archivos, Rechazo SVG, RBAC y Anti-IDOR).
- **Baseline de Entrada Oficial:** `dba00ef17b14ae59ce2c8ac36796dd78705385b8` (F1.2A Aprobado con 119/119 PASS).
- **Estado de Cierre:** **PASS FORMAL TÉCNICO Y VISUAL (100% PRUEBAS EXITOSAS)**.
- **Regresión Acumulada Total:** **147 / 147 PRUEBAS PASS (0 FALLOS)**.
  - `F1.1A` (Identidad, Actores, Canales, Auditoría): **15/15 PASS**
  - `F1.1B` (Seguridad, Sesiones, CSRF, Bloqueo): **26/26 PASS**
  - `F1.1C` (RBAC, Catálogo Permisos, Autorización Backend): **11/11 PASS**
  - `F1.1D` (Login Alina, DataTables, Modales, API Usuarios): **17/17 PASS**
  - `F1.1E` (Hardening, IDOR, Roles y SEGURIDAD_AUTH): **25/25 PASS**
  - `F1.2A` (Modelo Configuración, Parámetros Tipados, Branding, Caché): **25/25 PASS**
  - `F1.2B` (Ficha Institucional, Modales Alina, Branding Seguro, Anti-IDOR): **28/28 PASS**
- **PHP Lint:** 0 errores sintácticos en la totalidad del repositorio (45/45 archivos verificados).
- **Composer:** Validez estricta de `composer.json` (`./composer.json is valid`).
- **Whitespace / Git Check:** Cero errores de formato o tabulaciones espurias (`git diff --check` limpio).
- **Secret Scan:** Cero credenciales ni secretos en texto claro versionados (`git grep -i "Cand26!"` verificado).
- **Upstream Alina (`admin-dashboard/`):** **100% INTACTO** (cero modificaciones, working tree clean).
- **Disponibilidad HTTP:**
  - `http://app.candelaria.test/configuracion/organizacion` (HTTP 200 con sesión, HTTP 302 redirección a login sin sesión).
  - `http://localhost/app.candelaria/configuracion/organizacion` (HTTP 200 con sesión, HTTP 302 redirección a login sin sesión).
  - `http://app.candelaria.test/configuracion/seguridad.php` (HTTP 403 Forbidden estricto por Apache `.htaccess`).

---

## 2. ARQUITECTURA DE LA SOLUCIÓN Y GOBERNANZA

### 2.1. Alcance Operativo Single-Tenant V1
La implementación opera de manera exclusiva sobre la organización operativa `10000` (`og_estudio`, `O.G. ESTUDIO CREATIVO S.A.C.`). Siguiendo los límites estrictos del mandato:
1. No se crearon selectores multiempresa ni formularios para crear organizaciones adicionales.
2. La vista de Organización no es una DataTable ni un listado; es una **Ficha de Administración Institucional** organizada en cuatro bloques funcionales:
   - **Identidad de Marca (Branding):** Logotipo principal, isotipo/favicon, badges de estado y políticas activas.
   - **Datos Institucionales y Fiscales:** Razón social, nombre comercial, documento de identidad tributaria (RUC).
   - **Ubicación y Domicilio Fiscal:** Dirección física, código ISO de país (`PE`), departamento, provincia y distrito.
   - **Contacto y Representación:** Teléfonos (WhatsApp y fijo), correo electrónico de contacto, sitio web y persona de contacto con cargo.

### 2.2. Navegación y Coexistencia de Marcas
Se respetó la separación estricta entre la **Marca de Plataforma** y la **Marca de Organización**:
- **Marca de Plataforma:** `CANDELARIAAPP` con el isotipo de la llama de la Candelaria en la cabecera superior y el sidebar principal.
- **Marca de Organización:** El sidebar secundario (`semi-side-nav`) y la cabecera superior reflejan dinámicamente el isotipo y nombre de la empresa operadora (`O.G. ESTUDIO CREATIVO`), sin degradar ni sustituir la identidad del software.
- **Ruta de Menú:** `CONFIGURACIÓN -> Organización` (nivel 2, dentro del límite de 3 niveles), visible exclusivamente para usuarios con el permiso `organizacion.ver`.

### 2.3. Ciclo Asíncrono de Interfaz (Cero `location.reload()`)
El frontend (`publico/js/organizacion.js`) implementa rigurosamente el ciclo de vida Alina:
1. **Skeleton Loader Shimmer:** Carga inicial con placeholders visuales que replican exactamente la geometría de las tarjetas.
2. **Fetch API Asíncrono:** Consulta de datos mediante `CandelariaApi.get('organizacion')` y catálogo de tipos de documento.
3. **Estado Disabled + Spinner:** Los botones de guardado institucional (`btnGuardarOrganizacion`) y de carga de branding (`btnConfirmarSubidaBranding`) deshabilitan el formulario y muestran un spinner durante el procesamiento.
4. **SweetAlert2 Feedback:** Alertas elegantes integradas con los estilos Alina, informando éxito o detalles de validación.
5. **Refresco Parcial:** Actualización selectiva del DOM y las imágenes con bypass de caché (`?t=timestamp`) sin recargar la página.

---

## 3. SEGURIDAD DE BRANDING Y POLÍTICA DE ARCHIVOS

### 3.1. Detección Server-Side y Prevención de Cargas Maliciosas
La carga de archivos (`aplicacion/Configuracion/BrandingServicio.php`) no confía en la información reportada por el cliente (`$_FILES['type']` o extensión). Implementa una cadena de 5 filtros de seguridad:
1. **Límite de Tamaño:** Máximo 2 MB por archivo (`2097152` bytes), rechazando tempranamente excesos con HTTP 422.
2. **Inspección de Buffer MIME (`finfo`):** Detección estricta de Magic Bytes en disco para validar tipos MIME reales (`image/png`, `image/jpeg`, `image/webp`).
3. **Integridad Gráfica (`getimagesize`):** Decodificación real del archivo gráfico para comprobar que no sea un script PHP camuflado con encabezados falsos.
4. **Protección contra Doble Extensión:** Detección de patrones peligrosos tipo `.php.png` o `.phtml.jpg` que intenten engañar configuraciones de servidor web.
5. **Nomenclatura Segura y Anti-Path Traversal:** Nombres criptográficos basados en prefijo, ID de organización, timestamp y bytes aleatorios (`logo_10000_1728139200_a1b2c3d4.png`), garantizando que ningún parámetro de usuario influya en el path físico.

### 3.2. Decisión Arquitectónica sobre SVG
> **MANDATO DE SEGURIDAD:** Dado que el entorno no cuenta con una biblioteca nativa de sanitización vectorial XML (tipo `DOMPurify` o `enshrined/svg-sanitize`) que neutralice ataques de Cross-Site Scripting (XSS) y XML External Entity (XXE) en archivos SVG que se servirán en el navegador, **la carga de SVG queda temporalmente deshabilitada**. Tanto el frontend como el backend emiten un rechazo controlado (HTTP 422) con el mensaje:
> *"Por políticas de seguridad, los archivos vectoriales SVG se encuentran temporalmente deshabilitados mientras se integra el motor de sanitización XML. Utilice formatos rasterizados autorizados (PNG, JPG o WEBP)."*

### 3.3. Ciclo de Sustitución Consistente (Cero Archivos Huérfanos)
Para mantener la integridad del filesystem y evitar archivos huérfanos:
1. El archivo nuevo se valida y almacena físicamente en `/publico/recursos/subidas/organizaciones/10000/branding/`.
2. Se inicia una transacción en base de datos; se actualiza la columna (`logo_url` o `isotipo_url`) y se registra el evento en `auditoria_operaciones`.
3. Tras el `commit`, si existía un archivo físico anterior dentro del directorio autorizado de la organización, se elimina de forma segura con `unlink()`.
4. Si ocurre cualquier fallo en BD, se ejecuta `rollBack()` y se elimina el archivo nuevo recién subido, impidiendo la dispersión de archivos sin referencia.

---

## 4. AUDITORÍA INMUTABLE Y PROTECCIÓN ANTI-IDOR

1. **Aislamiento Multi-Tenant Anti-IDOR:**
   - Los endpoints `GET /api/v1/organizacion`, `PUT /api/v1/organizacion`, `POST /api/v1/organizacion/branding/logo` y `POST /api/v1/organizacion/branding/isotipo` ignoran cualquier parámetro `organizacion_id` enviado por GET o POST.
   - El backend resuelve el tenant estrictamente a partir del `ContextoOperacion` de la sesión autenticada (`$contexto->organizacionId ?? 10000`).
2. **Pista de Auditoría Limpia (Cero Binarios ni Secretos):**
   - Los eventos auditados (`ACTUALIZAR_PERFIL_ORGANIZACION`, `ACTUALIZAR_BRANDING_LOGO`, `ACTUALIZAR_BRANDING_ISOTIPO`) registran la ruta relativa del archivo, dimensiones en píxeles y peso en KB.
   - **Prohibición de BLOBs y Base64:** Queda estrictamente verificado que ningún contenido binario, data URI o secreto viaja hacia `auditoria_operaciones`.

---

## 5. RESULTADOS DE LA SUITE F1.2B (28 / 28 PASS)

```text
==============================================================================
CANDELARIAAPP — SUITE DE PRUEBAS DE ORGANIZACIÓN Y BRANDING F1.2B
FICHA INSTITUCIONAL, EDICIÓN, BRANDING SEGURO, RECHAZO SVG, RBAC Y ANTI-IDOR
==============================================================================

 [PASS] 00. Organización operativa 10000 existe en la base de datos
 [PASS] 01. GET /api/v1/organizacion sin sesión activa responde HTTP 401 Unauthorized
 [PASS] 02. GET /api/v1/organizacion sin permiso organizacion.ver responde HTTP 403 Forbidden
 [PASS] 03. GET /api/v1/organizacion con organizacion.ver retorna HTTP 200 y datos de la organización operativa actual
 [PASS] 04. Aislamiento Anti-IDOR: El backend resuelve la organización desde el contexto autenticado, ignorando parámetros externos
 [PASS] 05. PUT /api/v1/organizacion sin token CSRF es rechazado con HTTP 403 Forbidden
 [PASS] 06. PUT /api/v1/organizacion con token CSRF inválido o alterado es rechazado con HTTP 403 Forbidden
 [PASS] 07. PUT /api/v1/organizacion sin permiso organizacion.editar es rechazado con HTTP 403 Forbidden
 [PASS] 08. PUT /api/v1/organizacion con credenciales y CSRF válidos actualiza exitosamente (HTTP 200)
 [PASS] 09. Normalización de textos de negocio: Nombres, razones sociales, cargos y direcciones se persisten en MAYÚSCULAS
 [PASS] 10. Normalización de canales digitales: Correo electrónico y sitio web se persisten en minúsculas limpias
 [PASS] 11. Validación: PUT con formato de correo electrónico inválido es rechazado con HTTP 422
 [PASS] 12. Validación: PUT con URL que no inicia con http:// o https:// es rechazada con HTTP 422
 [PASS] 13. Auditoría inmutable: Modificación de organización registra evento ACTUALIZAR_PERFIL_ORGANIZACION con datos previos y nuevos
 [PASS] 14. Branding: Carga de logotipo por usuario sin permiso branding.editar es rechazada con HTTP 403 Forbidden
 [PASS] 15. Branding: Carga de logotipo sin token CSRF válido es rechazada con HTTP 403 Forbidden
 [PASS] 16. Branding: Carga de archivo PNG válido es procesada exitosamente con nombre seguro y ruta relativa
 [PASS] 16b. Archivo físico de logotipo persistido exitosamente en el filesystem
 [PASS] 17. Branding: Carga de archivo JPG válido es procesada exitosamente con normalización y dimensiones correctas
 [PASS] 17b. Nuevo archivo JPG persistido en disco
 [PASS] 18. Seguridad de Archivos: Archivo con peso superior a 2 MB es rechazado tempranamente con HTTP 422
 [PASS] 19. Seguridad de Archivos: Falso MIME (script PHP camuflado como PNG) detectado por finfo/decodificador y rechazado con HTTP 422
 [PASS] 20. Seguridad de Archivos: Nombre peligroso con doble extensión ejecutable (ej. payload.php.png) es bloqueado con HTTP 422
 [PASS] 21. Seguridad de Archivos: Intentos de path traversal en rutas de branding son neutralizados estructuralmente
 [PASS] 22. Política de SVG: Archivo SVG es rechazado con mensaje controlado de seguridad por ausencia de sanitizer vectorial
 [PASS] 23. Sustitución consistente: La actualización de branding elimina de forma segura el archivo anterior en disco, evitando huérfanos
 [PASS] 24. Auditoría inmutable de branding: Registra metadatos y ruta relativa, sin almacenar contenido binario en BD
 [PASS] 25. Carga de Isotipo: Actualiza isotipo_url de forma atómica e independiente sin alterar ni degradar el logo_url

==============================================================================
RESULTADO FINAL F1.2B: 28 PRUEBAS EXITOSAS / 0 FALLOS
==============================================================================
```

---

## 6. REGRESIÓN GLOBAL ACUMULADA (147 / 147 PASS)

| Suite | Módulo / Microlote | Total Pruebas | Exitosas | Fallidas | Estado |
|---|---|---|---|---|---|
| `F1.1A` | Modelo de Identidad, Actores, Canales y Auditoría | 15 | 15 | 0 | **PASS** |
| `F1.1B` | Seguridad, Autenticación, Sesiones, CSRF, Bloqueo | 26 | 26 | 0 | **PASS** |
| `F1.1C` | RBAC, Autorización Backend, Roles y Permisos | 11 | 11 | 0 | **PASS** |
| `F1.1D` | Login Alina, CRUD Asíncrono de Usuarios, Modales | 17 | 17 | 0 | **PASS** |
| `F1.1E` | Hardening, Anti-IDOR, SEGURIDAD_AUTH, Erradicación | 25 | 25 | 0 | **PASS** |
| `F1.2A` | Modelo Configuración, Parámetros Tipados, Branding | 25 | 25 | 0 | **PASS** |
| `F1.2B` | Ficha de Organización, Modales Alina, Branding Seguro | 28 | 28 | 0 | **PASS** |
| **TOTAL** | **COBERTURA INTEGRAL FASE 1** | **147** | **147** | **0** | **PASS (100%)** |

---

## 7. EVIDENCIA VISUAL

Se capturaron y preservaron como artefactos visuales oficiales:

1. **Ficha de Organización Desktop (1600x1000):**  
   `screenshot_f12b_organizacion_desktop.png`  
   Muestra el layout Alina con breadcrumb, badge de estado, tarjetas institucionales por pasos y políticas de branding.
2. **Modal de Edición Institucional (`#modalEditarOrganizacion`):**  
   `screenshot_f12b_modal_editar.png`  
   Muestra el modal centralizado Bootstrap 5 con todos los campos fiscales, de ubicación y contacto pre-poblados y organizados.
3. **Modal de Carga de Branding (`#modalSubirBranding`):**  
   `screenshot_f12b_modal_branding.png`  
   Muestra la zona de carga de archivos con validación en vivo, límites y política SVG visible.
4. **Vista Responsiva Mobile (375x812):**  
   `screenshot_f12b_organizacion_mobile.png`  
   Muestra la perfecta adaptación en dispositivos móviles sin desbordamientos horizontales.

---

## 8. CONCLUSIÓN Y ESTADO DE CIERRE

El microlote **F1.2B — Ficha de Organización y Branding** cumple con el 100% de las especificaciones funcionales, de seguridad, arquitectura y diseño. El repositorio se encuentra sincronizado, limpio y verificado contra regresiones.

Se establece el **MANDATORY STOP** antes de proceder a la planificación o apertura de `F1.2C`.
