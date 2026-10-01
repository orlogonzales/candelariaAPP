# ADR-001: CAMBIO ARQUITECTÓNICO DE FRONTEND — RETIRO DE VUE 3 Y ADOPCIÓN DE ALINA BOOTSTRAP 5

## Estado
**APROBADO Y EN VIGOR** (Modifica la decisión preliminar de la Instrucción Maestra).

## Contexto y Antecedentes
Inicialmente, la Instrucción Maestra contemplaba el uso obligatorio de Vue 3 para el frontend de CandelariaAPP V1. Sin embargo, se adquirió la plantilla administrativa oficial **Alina Bootstrap 5 Admin Dashboard**, la cual ya cuenta con una amplia biblioteca de componentes, layouts, utilidades visuales, responsive y estilos probados.

## Decisión
1. **Decisión Anterior (Histórica):** Vue 3 como frontend obligatorio.
2. **Decisión Actual (Vigente):** Vue 3 queda formalmente retirado como requisito para la V1.
3. **Frontend Oficial V1:** Alina Bootstrap 5 + JavaScript Moderno (ES6+) + Fetch API + HTTP/JSON + Componentes oficiales reutilizables de Alina.
4. **Protección de Upstream:** El directorio original `admin-dashboard/alina/` se declara material fuente de solo lectura. No se modifica destructivamente. La plantilla propia se construye de forma desacoplada y reutilizable a partir de la extracción estructural de `blank.html`.
5. **Iconografía Oficial:** Font Awesome Free 6.3.0 en sustitución de sistemas secundarios como Tabler en el código visual de la aplicación.
6. **Tipografía Oficial:** Fira Sans Extra Condensed aplicada globalmente en sustitución de Lexend Deca.

## Justificación
- **Reducción radical de complejidad:** Elimina dependencias pesadas de compilación en frontend (Vite/Node/Vue runtime) sin sacrificar dinamismo.
- **Aprovechamiento integral de inversión:** Alina ya proporciona componentes nativos de interfaz (tablas, modales, formularios, dropdowns, canvas).
- **Mantenimiento del principio API-First:** La lógica de negocio no queda atrapada en el cliente; el backend sigue siendo la única autoridad de validación y negocio.
- **Experiencia asincrónica:** Uso recurrente de Fetch API para flujos CRUD en modales sin recarga completa de página.

## Consecuencias
- **Positivas:** Desarrollo más ágil, menor superficie de ataque en frontend, soporte nativo de Bootstrap 5, compatibilidad inmediata con PHP 8.3.
- **A tener en cuenta:** Las operaciones reactivas se implementan mediante JavaScript modular nativo, manteniendo el código propio libre de jQuery.
