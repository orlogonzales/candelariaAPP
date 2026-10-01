# PATRÓN OFICIAL DE UI/UX ASÍNCRONO, CRUD Y SKELETON LOADERS — CANDELARIAAPP

## 1. Principio Fundamental de Experiencia de Usuario
La experiencia de usuario en CandelariaAPP V1 debe ser:

$$\text{FLUIDA} \quad\bullet\quad \text{COMPACTA} \quad\bullet\quad \text{ASÍNCRONA} \quad\bullet\quad \text{CONTEXTUAL} \quad\bullet\quad \text{RESPONSIVA}$$

El estándar de interacción descarta la navegación fragmentada y recargas completas de pantalla en operaciones convencionales, implementando el patrón:

$$\text{Listado} + \text{Acciones} + \text{Modales} + \text{Fetch API} + \text{Actualización Parcial}$$

---

## 2. La Tríada Obligatoria de Estados de Interacción (Sección 39)
Esta distinción es **estricta y vinculante** en toda la aplicación:

| Momento de la Interacción | Mecanismo Visual Obligatorio | Implementación Técnica | Comportamiento |
|---|---|---|---|
| **1. Carga de Contenido** | **Skeleton Loader** | `Skeleton.show(target, tipo)` | Refleja la silueta del contenido final (`aria-busy="true"`, shimmer adaptativo). |
| **2. Procesamiento de Acción** | **Botón Disabled + Spinner** | `CandelariaUI.procesarBoton(btn)` | Bloquea doble envío; cambia icono por `fa-spinner fa-spin`. |
| **3. Comunicación de Resultado** | **SweetAlert2** | `CandelariaUI.notificarExito/Error()` | Diálogo contextual flotante; nunca `alert()` ni volcado de errores PHP/SQL. |
| **4. Modificación de Datos** | **Actualización Parcial** | Redibujado asíncrono de componente / DataTable | Conserva página, filtros y búsquedas sin recargar la ventana. |

---

## 3. Patrón CRUD Oficial en Pantalla (Secciones 21-25)

```text
┌──────────────────────────────────────────────────────────────────────────────┐
│ [ICONO] TÍTULO DEL MÓDULO (Izquierda)             [+ NUEVO] [ACCIÓN] (Derecha)│
├──────────────────────────────────────────────────────────────────────────────┤
│ TARJETAS DE MÉTRICAS (KPIs)                                                  │
│ [Skeleton Stats mientras se reciben los datos del backend]                  │
├──────────────────────────────────────────────────────────────────────────────┤
│ BARRA DE BÚSQUEDA Y FILTROS CONTEXTUALES                                     │
├──────────────────────────────────────────────────────────────────────────────┤
│ DATATABLE ASÍNCRONA                                                          │
│ [Skeleton Table durante la petición inicial]                                 │
│                                                                              │
│ Registro 101    ORLANDO GONZÁLEZ      ACTIVO    [Ver] [Editar] [Anular]      │
│ Registro 102    CAPORALES HUÁSCAR     ACTIVO    [Ver] [Editar] [Anular]      │
└──────────────────────────────────────────────────────────────────────────────┘
```

### A. Flujo de Alta (Nuevo):
1. Clic en `[+ Nuevo]`.
2. Se abre el Modal de Alina inmediatamente (cero demora perceptiva).
3. Formulario listo para captura.
4. Envío con validación en cliente $\to$ `CandelariaUI.procesarBoton()`.
5. Petición `Fetch` a `/api/v1/...` $\to$ Backend valida y autoriza autoritativamente.
6. **Éxito:** SweetAlert2 informa resultado positivo $\to$ Cierra modal $\to$ Limpia formulario $\to$ Refresca DataTable en segundo plano.
7. **Error:** SweetAlert2 detalla la observación de negocio $\to$ Mantiene modal abierto $\to$ Conserva datos capturados para corrección inmediata.

### B. Flujo de Edición:
1. Clic en `[Editar]` sobre la fila.
2. Se abre el Modal de Alina **inmediatamente**.
3. Mientras se solicita la entidad al backend, el cuerpo del modal muestra un **Skeleton Form** (PROHIBIDO abrir modal vacío o esperar la respuesta antes de abrir).
4. Al resolver `Fetch`, se oculta el Skeleton y se inyecta el formulario precargado.
5. Al guardar $\to$ Spinner $\to$ SweetAlert2 $\to$ Cierre de modal $\to$ Actualización parcial de la fila/tabla.

### C. Flujo de Eliminación / Anulación:
1. Nunca ejecutar eliminación al primer clic.
2. Diálogo SweetAlert2 con confirmación explícita (`¿Confirmar anulación?`).
3. Al confirmar: `Fetch` hacia el backend.
4. Preferencia por **anulación lógica / desactivación** frente a borrado físico cuando exista trazabilidad comercial o contractual.
5. DataTable se actualiza asíncronamente conservando paginación y filtros.

---

## 4. Modal Primero, Página cuando esté Justificada (Sección 26)
- **Candidatos naturales a Modal / Offcanvas:** Crear, editar, asignar activo, registrar pago, observaciones operativas, cambio de estado, configuraciones secundarias.
- **Candidatos a Página Completa:** Dashboard principal, expediente integral 360 del cliente, mapa de veneración, calendario folclórico completo, galerías masivas.

---

## 5. Formularios Extensos: Patrón Wizard (Sección 27-28)
- Cuando un formulario supere la capacidad cómoda de un modal tradicional, se adoptará el componente **Wizard / Step Form** de Alina (`form_wizards.html`).
- Pasos funcionales lógicos (ej. Datos $\to$ Contacto $\to$ Paquete $\to$ Confirmación).
- **Distribución de Columnas Responsiva:**
  - Desktop: 3 a 4 columnas.
  - Tablet: 2 columnas.
  - Móvil: 1 columna.

---

## 6. Infraestructura Centralizada de Skeleton Loaders (Secciones 32-46)
- **Implementación:** [publico/css/skeleton.css](file:///d:/laragon/www/app.candelaria/publico/css/skeleton.css) y [publico/js/skeleton.js](file:///d:/laragon/www/app.candelaria/publico/js/skeleton.js).
- **Variantes Semánticas Soportadas:**
  - `stats`: Widget de métricas numéricas con icono circular.
  - `table`: Cabecera y filas tabulares con anchos variables.
  - `form`: Cuadrícula responsiva de etiquetas, inputs y botones.
  - `card`: Tarjeta con títulos, párrafos y botón de acción.
  - `list`: Lista de elementos con avatares o viñetas.
  - `dashboard`: Matriz completa de KPIs y tabla de actividad.
  - `profile`: Avatar centralizado, identificadores y datos de cuenta.
- **Accesibilidad:**
  - Inyección de atributo `aria-busy="true"` durante la carga; eliminación automática en `Skeleton.hide()`.
  - Regla `@media (prefers-reduced-motion: reduce)`: silencia animaciones shimmer para usuarios sensibles.
- **Regla Estricta:** Cero `setTimeout` artificial. El Skeleton solo existe mientras la promesa de red se encuentra pendiente (`finally { Skeleton.hide(target); }`).
- **Estado de Error:** Ante fallos de conexión, `Skeleton.error(target, mensaje, { accion })` renderiza una tarjeta accesible con botón de reintento.
