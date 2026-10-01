# REGISTRO DE EVIDENCIAS TÉCNICAS — F0.1B NORMALIZACIÓN FONT AWESOME Y PRIMER PUSH

**Fecha de Ejecución:** 2026-10-01  
**Entorno de Ejecución:** Windows, Laragon, Apache 2.4, PHP 8.3.30, MariaDB/MySQL 8.0, Composer 2.10.1, Git 2.x  
**Carácter del Microlote:** Normalización de ubicación canónica de fuentes, eliminación de duplicidad física de webfonts, rediseño limpio de cascada tipográfica en `candelaria.css`, retiro de CORS innecesario en `.htaccess`, hardening de `url_base()`, verificación visual/técnica, nuevo commit fundacional y primer `git push origin main`.

---

## 1. Preservación Estricta de Upstream (`admin-dashboard/`)
- El directorio de referencia adquirida `admin-dashboard/alina/` se mantuvo **100% de solo lectura**.
- **0 archivos modificados**, **0 archivos eliminados** y **0 alteraciones** en la plantilla original.
- Comprobación en `git status`: ningún archivo dentro de `admin-dashboard/` ha sido tocado.

---

## 2. Normalización de Ubicación Canónica de Font Awesome (Cero Duplicados)
- **Diagnóstico previo:** Durante el microlote F0.1A se habían creado archivos webfonts tanto en `publico/activos/alina/fonts/fontawesome/` como en `publico/activos/alina/vendor/fontawesome/webfonts/`.
- **Análisis de upstream Alina:** El archivo vendor oficial `publico/activos/alina/vendor/fontawesome/css/all.css` referencia nativamente las fuentes mediante `url("../../../fonts/fontawesome/fa-*.woff2")`.
- **Acción ejecutada:** Se eliminó por completo el directorio redundante `publico/activos/alina/vendor/fontawesome/webfonts/` (8 archivos eliminados, ~900 KB recuperados).
- **Ubicación Canónica Única Establecida:** `publico/activos/alina/fonts/fontawesome/`
  1. `fa-brands-400.ttf`
  2. `fa-brands-400.woff2`
  3. `fa-regular-400.ttf`
  4. `fa-regular-400.woff2`
  5. `fa-solid-900.ttf`
  6. `fa-solid-900.woff2`
  7. `fa-v4compatibility.ttf`
  8. `fa-v4compatibility.woff2`
- **Métricas:**
  - `COPIAS DUPLICADAS DE WEBFONTS = 0`
  - `RUTAS RELATIVAS ALL.CSS INTACTAS = 100%`

---

## 3. Rediseño de la Cascada Tipográfica en `candelaria.css`
- **Problema corregido:** Se eliminó el selector global excesivamente agresivo (`html, body, p, span, a, label, .nav-link ... { font-family: ... !important; }`), el cual degradaba la especificidad y competía destructivamente con los glifos de Font Awesome.
- **Solución implementada:** Cascada tipográfica natural mediante CSS Custom Properties y herencia desde `body`:
  ```css
  :root {
      --theme-fonts: 'Fira Sans Extra Condensed', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
      --bs-body-font-family: 'Fira Sans Extra Condensed', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
      --bs-font-sans-serif: 'Fira Sans Extra Condensed', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
  }

  body {
      font-family: var(--theme-fonts);
      letter-spacing: 0.02em;
  }

  button, input, optgroup, select, textarea {
      font-family: inherit;
  }

  h1, h2, h3, h4, h5, h6, .card-title {
      font-family: var(--theme-fonts);
      font-weight: 600;
  }
  ```
- **Sustitución de Pseudo-elementos Tabler Icons en el Menú Colapsable:** Se mantuvieron exclusivamente las sustituciones limpias de flechas en el menú lateral:
  ```css
  .main-side-nav .main-side-menu > ul:not(.collapse) > li .another-level > a::after,
  .main-side-nav .main-side-menu > ul:not(.collapse) > li:not(.no-sub) > a::after {
      font-family: 'Font Awesome 6 Free';
      font-weight: 900;
      content: "\f054"; /* fa-chevron-right */
      ...
  }
  .main-side-nav .main-side-menu > ul:not(.collapse) > li [aria-expanded=true]::after {
      font-family: 'Font Awesome 6 Free';
      font-weight: 900;
      content: "\f078"; /* fa-chevron-down */
      ...
  }
  ```
- **Resultado:** Fira Sans Extra Condensed aplica al 100% de textos informativos de la aplicación por herencia natural, mientras que Font Awesome 6 Free aplica a los iconos sin requerir `!important` descontrolados.

---

## 4. Retiro de Cabecera CORS Redundante en `.htaccess`
- Dado que el asistente `url_base()` resuelve estrictamente recursos same-origin tanto bajo `http://localhost/app.candelaria/` como bajo `http://app.candelaria.test/`, no existe ninguna solicitud cross-origin de tipografías.
- Se retiró la directiva `<FilesMatch "\.(ttf|ttc|otf|eot|woff|woff2)$"> Header set Access-Control-Allow-Origin "*" </FilesMatch>` de `.htaccess`.
- El hardening de seguridad permanece en forzar HTTP 403 Forbidden para archivos sensibles (`.env`, `.sql`, logs, directorios internos).

---

## 5. Hardening de Host Header en `url_base()`
- Se auditó y fortaleció `nucleo/Soporte/ayudantes.php`:
  ```php
  $rawHost = (string) $_SERVER['HTTP_HOST'];
  if (preg_match('/^[a-zA-Z0-9.\-]+(?::\d+)?$/', $rawHost)) {
      $host = $rawHost;
  } else {
      $host = $_SERVER['SERVER_NAME'] ?? 'localhost';
  }
  ```
- Se previene la inyección de cabeceras de host maliciosas (Host Header Poisoning), conservando la resolución adaptativa para desarrollo en localhost y virtualhost.

---

## 6. Verificación Técnica Integral
- **PHP Lint:** 16 de 16 archivos sintácticamente impecables (`No syntax errors detected`).
- **Composer Validate:** `./composer.json is valid`.
- **Base de Datos:** 14 tablas intactas, 0 migraciones nuevas, 0 modificaciones de esquema.
- **Códigos HTTP Servidor Local:**
  - `http://localhost/app.candelaria/` -> HTTP 200 OK
  - `http://app.candelaria.test/` -> HTTP 200 OK
  - `http://localhost/app.candelaria/publico/activos/alina/fonts/fontawesome/fa-solid-900.woff2` -> HTTP 200 OK (149,908 bytes)
  - `http://app.candelaria.test/publico/activos/alina/fonts/fontawesome/fa-solid-900.woff2` -> HTTP 200 OK (149,908 bytes)
- **Capturas de Pantalla Headless (Chrome):**
  - `screenshot_f01b_localhost.png`: Glifos faltantes = 0, Cuadrados vacíos = 0.
  - `screenshot_f01b_vhost.png`: Glifos faltantes = 0, Cuadrados vacíos = 0.
  - `screenshot_f01b_dark.png`: Glifos faltantes = 0, Modo oscuro verificado.

---

## 7. Nuevo Commit Fundacional y Sincronización Remota
- **Commit Baseline F0.1A Preservado:** `d60248e` intacto en el historial.
- **Nuevo Commit F0.1B Creado:** `chore: normalize Font Awesome asset integration`.
- **Primer Push Ejecutado:** `git push origin main`.
- **Estado de Sincronización:** `Ahead 0, Behind 0`, working tree clean.
