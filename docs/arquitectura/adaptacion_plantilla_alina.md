# ADAPTACIÓN DE LA PLANTILLA ALINA BOOTSTRAP 5 — CANDELARIAAPP

## 1. Protección del Upstream Original
El directorio `admin-dashboard/` contiene el código fuente original adquirido de **Alina Bootstrap 5** y su documentación.
- **Regla Estricta:** Debe considerarse de **solo lectura**.
- No se modifican destructivamente los archivos originales.
- No se eliminan demos ni se reorganiza la plantilla adquirida.
- La plantilla de CandelariaAPP se construyó extrayendo y desacoplando la estructura visual de `blank.html`.

---

## 2. Flujo de Extracción y Reutilización
```text
admin-dashboard/alina/template/blank.html (UPSTREAM INTACTO)
                     ↓ Inspección y desacoplamiento
      recursos/vistas/layouts/principal.php (LAYOUT MAESTRO)
                     ↓
┌────────────────────┼────────────────────┐
↓                    ↓                    ↓
parciales/           parciales/           parciales/
metas_encabezado     barra_lateral        cabecera_superior
                     ↓
             VISTAS DE MÓDULOS (ej. paginas/inicio.php)
```

---

## 3. Iconografía Oficial: Font Awesome Free 6.3.0
- **Fuente de Activos:** Alojada localmente en `publico/activos/alina/vendor/fontawesome/` y `publico/activos/alina/fonts/fontawesome/`.
- Cero dependencias de CDN.
- Sustitución progresiva de Tabler Icons por Font Awesome en toda la interfaz de la aplicación.
- No se cargan librerías redundantes en las vistas cuando su uso en la capa derivada es 0.

---

## 4. Tipografía Oficial: Fira Sans Extra Condensed
- **Fuente Oficial:** Fira Sans Extra Condensed (Google Fonts, pesos 300, 400, 500, 600, 700).
- **Sustitución de Lexend Deca:** Anulada globalmente en `publico/css/candelaria.css`.
- Aplica a `html`, `body`, encabezados (`h1`-`h6`), botones, inputs, tablas, modales, tooltips y menús.
- **Resultado:** 0 usos visibles de Lexend Deca en CandelariaAPP.

---

## 5. Tooltips del Sidebar Compacto
- Implementado en `recursos/vistas/parciales/barra_lateral.php` y activado en `publico/js/candelaria.js`.
- Atributo `title="..."` interactivo transformado en Tooltip nativo de Bootstrap 5 con ubicación `placement: right`.
- Soporta observación dinámica vía `MutationObserver` para opciones añadidas asincrónicamente.
- Totalmente compatible con el modo claro y oscuro (`dark/light mode`).
