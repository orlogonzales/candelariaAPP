# ARQUITECTURA GENERAL — CANDELARIAAPP

## 1. Flujo Conceptual de Peticiones
La aplicación se rige por una arquitectura API-first con controladores delgados:

$$\text{Cliente HTTP / Navegador} \longrightarrow \text{Enrutador} \longrightarrow \text{Middleware} \longrightarrow \text{Controlador} \longrightarrow \text{Servicio} \longrightarrow \text{Repositorio / Modelo} \longrightarrow \text{PDO} \longrightarrow \text{MySQL / MariaDB}$$

### Responsabilidades por Capa:
- **Enrutador (`nucleo/Enrutamiento`):** Captura URI y método HTTP, despacha a closures o controladores.
- **Middleware (`nucleo/Middleware`):** Autenticación, verificación de sesión, validación CSRF, limitación de tasa (rate limiting).
- **Controlador (`aplicacion/Controladores`):** Delgados. Reciben el request, delegan al Servicio correspondiente y devuelven una vista o un sobre JSON.
- **Servicio (`aplicacion/Servicios`):** Alberga la **lógica de negocio**. Aplica reglas, transacciones y coordinación entre repositorios.
- **Repositorio / Modelo (`aplicacion/Repositorios`, `aplicacion/Modelos`):** Abstracción de acceso a datos mediante PDO con Prepared Statements. Cero SQL en controladores o vistas.
- **Entidades (`aplicacion/Entidades`):** Representación tipada de datos del dominio.

---

## 2. Jerarquía SaaS-Ready
```text
PLATAFORMA (Superadmin Global)
    ↓
ORGANIZACIÓN / TENANT (ej. O.G. Estudio Creativo)
    ↓
PLAN / SUSCRIPCIÓN (ej. Plan Estudio V1)
    ↓
MÓDULOS / CAPACIDADES
    ↓
USUARIOS
    ↓
ROLES (Asignados al tenant o a la plataforma)
    ↓
PERMISOS (Atómicos por módulo)
    ↓
EDICIONES CANDELARIA (ej. Candelaria 2027)
    ↓
DATOS OPERATIVOS DE NEGOCIO
```

---

## 3. Principio de Menú Dinámico vs Autorización
- El menú visual se organiza en un máximo de **3 niveles**:
  $$\text{Nivel 1} \longrightarrow \text{Nivel 2} \longrightarrow \text{Nivel 3}$$
- Cada opción especifica `capacidad_requerida` y `permiso_requerido`.
- **Regla Vincunlante:** $\text{MENÚ OCULTO} \neq \text{SEGURIDAD}$. El backend y la API autorizan de manera independiente e indeleble cada operación.
