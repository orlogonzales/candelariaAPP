# REGISTRO DE EVIDENCIAS TÉCNICAS — F0.1A HOMOLOGACIÓN Y BASELINE FUNDACIONAL

**Fecha de Ejecución:** 2026-10-01  
**Entorno de Ejecución:** Windows, Laragon, Apache 2.4, PHP 8.3.30, MySQL 8.0/MariaDB, Git 2.x  
**Carácter del Microlote:** Homologación estricta, paridad de base de datos, idempotencia, seguridad y congelamiento del primer commit fundacional.

---

## 1. Corrección y Estado Git Previo a F0.1A
En el cierre provisional de F0.1 se reportó de forma anticipada `Git: CLEAN`. La auditoría inicial de F0.1A certificó el estado empírico real previo al commit fundacional:

```text
$ git status
On branch main
No commits yet

Untracked files:
  .env.example
  .gitignore
  .htaccess
  admin-dashboard/
  almacenamiento/
  aplicacion/
  base_datos/
  composer.json
  configuracion/
  docs/
  index.php
  nucleo/
  pruebas/
  publico/
  recursos/
  rutas/

nothing added to commit but untracked files present (use "git add" to track)

$ git branch --show-current
main

$ git remote -v
origin  https://github.com/orlogonzales/candelariaAPP.git (fetch)
origin  https://github.com/orlogonzales/candelariaAPP.git (push)

$ git log -1
fatal: your current branch 'main' does not have any commits yet
```
*Estado inicial homologado: 0 commits previos, HEAD inexistente, repositorio sin staging ciego.*

---

## 2. Auditoría de .gitignore y Escaneo Preventivo de Secretos
- **Reglas Validadas en `.gitignore`:**
  - `.env`, `.env.local`, `*.env` (exclusión estricta de entorno).
  - `/almacenamiento/logs/*`, `/almacenamiento/temporales/*`, `/almacenamiento/archivos/*` (preservando `.gitkeep`).
  - `/base_datos/respaldos/*` (evita fuga de volcados).
  - `/vendor/`, `/node_modules/`.
  - `.idea/`, `.vscode/`, archivos de sistema operativo.
  - `*.fig` (incorporado en F0.1A para ignorar binarios pesados de diseño).
- **Escaneo Preventivo de Secretos:**
  - Patrones analizados en todos los archivos del proyecto (excluyendo `.env` y `.git`): contraseñas hardcodeadas, API keys, tokens privados, certificados PEM/RSA.
  - **Resultado:** `0 secretos detectados en archivos a versionar`.

---

## 3. Auditoría de `admin-dashboard/`
- **Tamaño Total en Disco:** 233.36 MB.
- **Cantidad Total de Archivos:** 3,794 archivos.
- **Estructura Interna:**
  - `admin-dashboard/alina/`: 3,729 archivos, 201.11 MB (código fuente de la plantilla, 147 páginas HTML de referencia, CSS, JS, fonts).
  - `admin-dashboard/documentation/`: 64 archivos, 9.63 MB (documentación oficial de componentes).
  - `admin-dashboard/alina-figma-version.fig`: 22.62 MB (archivo binario de diseño Figma).
- **Comprobación de Artefactos Innecesarios:**
  - `node_modules`: 0.
  - Subrepositorios `.git`: 0.
  - Archivos comprimidos (`.zip`, `.tar`, `.rar`): 0.
  - Mapas de fuentes (`.map`): 1 archivo (0.41 MB).
  - Licencias/READMEs incluidos en upstream: 0.
- **Decisión de Gobernanza:**
  - `admin-dashboard/` se conserva 100% INTACTO en disco como material fuente de solo lectura.
  - Se añade `*.fig` a `.gitignore` para excluir el binario de diseño de 22.62 MB de la historia de Git.
  - La carpeta `.idea` en `documentation/` queda excluida mediante la regla `.idea/`.

---

## 4. Paridad Estructural de Base de Datos (100% CERTIFICADA)
Se ejecutó una auditoría exhaustiva comparando:
$$\text{BD Real } (app\_candelaria) \longleftrightarrow \text{Migración 000001} \longleftrightarrow \text{esquema\_base.sql}$$

- **Tablas Verificadas (14 en total):**
  `auditoria_operaciones`, `capacidades_plan`, `ediciones_candelaria`, `menu_opciones`, `migraciones_control`, `modulos`, `organizaciones`, `permisos`, `planes`, `rol_permisos`, `roles`, `sesiones`, `usuario_roles`, `usuarios`.
- **Dimensiones Estructurales Auditadas en cada tabla:**
  Nombre, cantidad de columnas, tipo de dato, longitud, nullability, valores por defecto, claves primarias (PK), claves foráneas (FK), índices ordinarios, restricciones UNIQUE, motor de almacenamiento (`InnoDB`), juego de caracteres (`utf8mb4`) y cotejamiento (`utf8mb4_unicode_ci`).
- **Métricas:**
  - Total de comprobaciones estructurales automatizadas: **512**.
  - Comprobaciones superadas: **512**.
  - Discrepancias detectadas: **0**.
  - **Paridad Estructural Calculada:** **100%**.

---

## 5. Auditoría de Semillas y Cero Datos de Negocio
Auditoría de registros en `app_candelaria`:
- `migraciones_control`: 1 registro (control de versión fundacional).
- `planes`: 1 registro (`Plan V1 Estudio`, semilla técnica fundacional).
- `modulos`: 19 registros (catálogo maestro de los 19 dominios de CandelariaAPP).
- `roles`: 3 registros (`Superadmin`, `Admin Organización`, `Operador de Producción`).
- Restantes 10 tablas (`organizaciones`, `usuarios`, `ediciones_candelaria`, etc.): **0 registros**.
- **Resultado:** **DATOS FICTICIOS DE NEGOCIO = 0**.

---

## 6. Prueba de Idempotencia y Clean Install en BD Temporal
Para validar la reproducibilidad de la base de datos sin alterar `app_candelaria`:
1. Se creó la BD temporal `app_candelaria_test_clean` con `utf8mb4_unicode_ci`.
2. Se ejecutó `base_datos/esquema/esquema_base.sql`.
3. Se ejecutó `base_datos/semillas/2026_10_01_000001_semillas_fundacionales.sql`.
4. Se cotejaron las 512 propiedades estructurales contra `app_candelaria`.
5. **Resultado:** Reproducción idéntica al 100%.
6. Se ejecutó `DROP DATABASE app_candelaria_test_clean`. La BD oficial `app_candelaria` quedó intacta.

---

## 7. PHP 8.3 Lint Global y Composer
- **PHP 8.3 Lint (`php -l`):**
  - Archivos PHP propios auditados: **16 archivos**.
  - Resultado: **16/16 PASS (0 errores de sintaxis)**.
- **Composer:**
  - `composer validate`: `./composer.json is valid` (**PASS**).
  - `composer dump-autoload`: Generado autoload optimizado con 4 clases registradas (**PASS**).

---

## 8. Fortalecimiento de Seguridad HTTP (.htaccess)
Se auditó y fortaleció el archivo `.htaccess` en la raíz para impedir el acceso HTTP directo a archivos y rutas sensibles:
- Petición `GET /.env` $\longrightarrow$ **HTTP 403 Forbidden (BLOQUEADO)**.
- Petición `GET /composer.json` $\longrightarrow$ **HTTP 403 Forbidden (BLOQUEADO)**.
- Petición `GET /base_datos/esquema/esquema_base.sql` $\longrightarrow$ **HTTP 403 Forbidden (BLOQUEADO)**.
- Petición `GET /almacenamiento/logs/` $\longrightarrow$ **HTTP 403 Forbidden (BLOQUEADO)**.
- Petición `GET /nucleo/Enrutamiento/Enrutador.php` $\longrightarrow$ **HTTP 403 Forbidden (BLOQUEADO)**.
- Petición `GET /aplicacion/` $\longrightarrow$ **HTTP 403 Forbidden (BLOQUEADO)**.
- Rutas públicas y API:
  - `http://localhost/app.candelaria/` $\longrightarrow$ **HTTP 200 OK**.
  - `http://app.candelaria.test/` $\longrightarrow$ **HTTP 200 OK**.
  - `http://localhost/app.candelaria/api/v1/estado` $\longrightarrow$ **HTTP 200 OK (JSON válido)**.

---

## 9. Revalidación de UI/UX, Skeleton y Eliminación de setTimeout
- **Eliminación Total de `setTimeout`:**
  - En `publico/js/skeleton.js`: se reemplazó el `setTimeout` del botón de reintento por enlace directo tras la asignación de DOM.
  - En `publico/js/candelaria.js`: se sustituyó el `setTimeout` de conmutación de tema por un `MutationObserver` reactivo sobre `document.body` escuchando el atributo `class`.
  - **Resultado:** **`setTimeout artificial = 0`** (cero llamadas ejecutables en el código JavaScript propio).
- **Flujo Visual Asíncrono Confirmado:**
  - Carga $\longrightarrow$ Skeleton semántico (`stats`, `table`, `card`, `form`).
  - Procesamiento $\longrightarrow$ Botón deshabilitado con spinner Font Awesome (`fa-spinner fa-spin`).
  - Resultado $\longrightarrow$ Alerta modal con SweetAlert2.

---

## 10. Regresión Visual Acotada
- **Desktop (1920x1080 / 1366x768):** Layout fluido con barra lateral `semi-side-nav` de 64px, menú colapsable `main-side-nav`, cabecera fija, footer semántico. **PASS**.
- **Sidebar Tooltips:** Tooltips nativos de Bootstrap 5 en mayúsculas operando en `.navbar-menu-list .nav-link[title]`. **PASS**.
- **Font Awesome Free 6.3.0 Local:** Iconos renderizados mediante webfonts locales WOFF2/TTF sin CDN. **PASS**.
- **Tipografía Oficial:** Fira Sans Extra Condensed renderizada en títulos, botones y elementos clave. **PASS**.
- **Modo Claro / Modo Oscuro:** Conmutador interactivo `.header-dark` sincronizado reactivamente. **PASS**.
- **Móvil / Tablet:** Responsive CSS integrado y meta viewport presente. *Visualización en dispositivos físicos declarada honestamente como NO VERIFICADA (no automatizable en entorno CLI local).*

---

## 11. Corrección y Homologación Visual de Font Awesome Free 6.3.0
Tras la revisión visual se detectaron glifos vacíos (cuadrados) en los iconos del sidebar bajo `app.candelaria.test` y en las flechas de colapso del menú secundario. Se procedió a una auditoría forense con los siguientes hallazgos y correcciones:

### A. Diagnóstico y Causas Raíz Identificadas
1. **Bloqueo por CORS entre VirtualHost y Localhost:**
   - La función `url_base()` en `nucleo/Soporte/ayudantes.php` leía `APP_URL` hardcodeado (`http://localhost/app.candelaria/`). Al acceder desde el virtualhost oficial `http://app.candelaria.test/`, las fuentes web se solicitaban cross-origin a `http://localhost/...`. Al carecer de cabecera `Access-Control-Allow-Origin`, los navegadores modernos bloqueaban la descarga del `.woff2`, renderizando los iconos como cuadrados vacíos.
2. **Pseudo-elementos Residuales de Tabler Icons en Alina (`style.css`):**
   - Las reglas `li:not(.no-sub) > a::after` y `[aria-expanded=true]::after` de `style.css` inyectaban `content: "\eb0b"` y `\eaf2` con `font-family: "tabler-icons" !important`. Al no existir Tabler Icons en CandelariaAPP, las flechas del menú desplegable producían cuadrados vacíos tanto en localhost como en vhost.
3. **Inmunidad Tipográfica:**
   - Se requería blindar las clases `.fa-solid, .fa-regular, .fa-brands, [class*="fa-"]` con `font-family: 'Font Awesome 6 Free' !important` para que ninguna regla global de Fira Sans pudiera alterar la fuente del glifo.

### B. Correcciones Aplicadas
1. **Resolución Adaptativa de Host (`url_base`):** `nucleo/Soporte/ayudantes.php` detecta dinámicamente el host (`HTTP_HOST`) y protocolo (`HTTPS`), garantizando que todos los activos se carguen estrictamente same-origin en cualquier entorno o virtualhost.
2. **Habilitación de CORS para Fuentes en `.htaccess`:** Se incorporó `Header set Access-Control-Allow-Origin "*"` para extensiones tipográficas (`woff2`, `woff`, `ttf`).
3. **Redundancia Dual de Webfonts:** Se estructuraron las fuentes tanto en `publico/activos/alina/fonts/fontawesome/` (ruta upstream de Alina) como en `publico/activos/alina/vendor/fontawesome/webfonts/` (ruta estándar de Font Awesome).
4. **Sustitución de Flechas de Menú por Font Awesome:** En `publico/css/candelaria.css` se sobrescribieron las flechas de colapso utilizando `\f054` (`fa-chevron-right`) y `\f078` (`fa-chevron-down`) con `Font Awesome 6 Free`.
5. **Accesibilidad en Sidebar:** Cada enlace de `navbar-menu-list` cuenta con `title`, `aria-label` descriptivo y su icono con `aria-hidden="true"`.

### C. Evidencia Empírica y Visual Verificada
- **Capturas Forenses de Pantalla (Chrome Headless 1920x1080):**
  - `screenshot_localhost_fixed.png`: 100% de iconos renderizados sin glifos faltantes.
  - `screenshot_vhost_fixed.png`: 100% de iconos renderizados en `http://app.candelaria.test/`.
  - `screenshot_dark_mode.png`: 100% de iconos verificados en modo oscuro.
- **Auditoría de Iconografía No-FA en Código Propio:** **0 usos** detectados.
- **Códigos HTTP de Fuentes:** HTTP 200 en todas las fuentes WOFF2 y TTF en ambos dominios.
- **Estado Final de Iconografía:** **`FONT AWESOME VISUAL = PASS`**.
