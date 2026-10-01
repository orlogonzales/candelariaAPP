# REGISTRO DE EVIDENCIAS TÉCNICAS — F0.1 FOUNDATION

**Fecha de Ejecución:** 2026-10-01  
**Entorno de Ejecución:** Windows 10/11, Laragon, Apache 2.4, PHP 8.3.30, MySQL/MariaDB 10.x.

---

## 1. Verificación de Entorno y Binarios
```powershell
# Versión de PHP
php -v
# Resultado: PHP 8.3.30 (cli) (built: Jan 13 2026 22:50:40) (ZTS Visual C++ 2019 x64)

# Versión de Composer
composer -V
# Resultado: Composer version 2.10.1 2026-06-04 10:25:59
```

---

## 2. Verificación de Conexión a Base de Datos y Creación de Esquema
```text
Base de datos local: app_candelaria
Conexión PDO: EXITOSA (127.0.0.1:3306, usuario root)
Migración ejecutada: base_datos/migraciones/2026_10_01_000001_crear_tablas_fundacionales.sql
Semilla ejecutada:   base_datos/semillas/2026_10_01_000001_semillas_fundacionales.sql

Tablas Verificadas (14 en total):
- auditoria_operaciones (0 registros)
- capacidades_plan (0 registros)
- ediciones_candelaria (0 registros)
- menu_opciones (0 registros)
- migraciones_control (1 registros - migracion inicial)
- modulos (19 registros - catalogo oficial de dominios)
- organizaciones (0 registros)
- permisos (0 registros)
- planes (1 registros - Plan V1 Estudio)
- rol_permisos (0 registros)
- roles (3 registros - Superadmin, Admin Organizacion, Operador)
- sesiones (0 registros)
- usuario_roles (0 registros)
- usuarios (0 registros)
```
*Evidencia confirmada: 0 registros ficticios de negocio en la base de datos.*

---

## 3. Verificación de Solicitudes HTTP y Endpoints
Ejecutado contra Apache en local:

| URL Probada | Código HTTP | Tamaño | Resultado |
|---|---|---|---|
| `http://localhost/app.candelaria/` | `HTTP/1.1 200 OK` | 34,790 bytes | **PASS** |
| `http://app.candelaria.test/` | `HTTP/1.1 200 OK` | 34,790 bytes | **PASS** |
| `http://localhost/app.candelaria/api/v1/estado` | `HTTP/1.1 200 OK` | 494 bytes (JSON) | **PASS** |
| `http://app.candelaria.test/api/v1/estado` | `HTTP/1.1 200 OK` | 494 bytes (JSON) | **PASS** |
| `http://localhost/.../style.css` | `HTTP/1.1 200 OK` | 727,899 bytes | **PASS** |
| `http://localhost/.../fontawesome/css/all.css` | `HTTP/1.1 200 OK` | 148,310 bytes | **PASS** |
| `http://localhost/.../candelaria.css` | `HTTP/1.1 200 OK` | 4,466 bytes | **PASS** |
| `http://localhost/.../candelaria.js` | `HTTP/1.1 200 OK` | 6,310 bytes | **PASS** |
| `http://localhost/.../skeleton.css` | `HTTP/1.1 200 OK` | 2,820 bytes | **PASS** |
| `http://localhost/.../skeleton.js` | `HTTP/1.1 200 OK` | 6,430 bytes | **PASS** |
| `http://localhost/.../sweetalert.js` | `HTTP/1.1 200 OK` | 64,345 bytes | **PASS** |
| `http://localhost/.../fa-solid-900.woff2` | `HTTP/1.1 200 OK` | 149,908 bytes | **PASS** |
| `http://localhost/.../fa-regular-400.woff2` | `HTTP/1.1 200 OK` | 24,840 bytes | **PASS** |
| `http://localhost/.../fa-brands-400.woff2` | `HTTP/1.1 200 OK` | 108,000 bytes | **PASS** |

---

## 4. Verificación Tipográfica e Iconográfica
- **Fira Sans Extra Condensed:** Cargada vía Google Fonts e inyectada globalmente mediante `publico/css/candelaria.css`.
- **Referencias residuales a Lexend Deca en código propio:** 0 usos.
- **Font Awesome Free 6.3.0:** Assets locales cargados sin dependencias externas CDN.
- **Referencias residuales a Tabler en vistas propias:** 0 usos.
- **Tooltips del Sidebar:** Funcionales vía Bootstrap 5 Tooltip nativo en `.navbar-menu-list .nav-link[title]`.
- **Alina Upstream:** Directorio `admin-dashboard/` 100% preservado sin modificaciones destructivas.

---

## 5. Verificación de UI/UX, Skeleton Loaders y SweetAlert2
- **Auditoría de Placeholders en Alina:** Se verificó que Alina incluye `placeholder.html` basado en Bootstrap 5, pero limitado a rectángulos genéricos.
- **Solución Centralizada CandelariaAPP:** Se implementó `publico/css/skeleton.css` y `publico/js/skeleton.js` con soporte para variantes semánticas:
  - `stats`, `table`, `form`, `card`, `list`, `dashboard`, `profile`.
- **Accesibilidad:** `aria-busy="true"` dinámico durante la carga y `@media (prefers-reduced-motion: reduce)`.
- **SweetAlert2 v11.7.2:** Activo localmente desde `publico/activos/alina/vendor/sweetalert/sweetalert.js` y expuesto vía `CandelariaUI`.
- **Separación de Estados:**
  - Carga de contenido $\to$ Skeleton
  - Procesamiento de acción $\to$ Botón deshabilitado con spinner (`fa-spinner fa-spin`)
  - Comunicación de resultado $\to$ SweetAlert2
  - Cero `setTimeout` artificial para simular cargas.

---

## 6. Nota de Cierre y Transición a F0.1A
Este documento certifica la fase de **construcción fundacional (F0.1)**. Tras la revisión de gobernanza, el repositorio se mantuvo en estado previo al commit (con archivos no rastreados) para someterlo a una auditoría estricta de paridad estructural, idempotencia y hardening de seguridad en el microlote **F0.1A (Homologación y Baseline Fundacional)**, documentado en detalle en `docs/evidencias/fase0_f01a_evidencias.md`.
