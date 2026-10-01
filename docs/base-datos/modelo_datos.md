# MODELO DE DATOS Y GESTIÓN DE BASE DE DATOS — CANDELARIAAPP

## 1. Directrices Fundacionales
- **Motor Oficial:** MySQL 8.0+ / MariaDB 10.5+.
- **Juego de Caracteres:** `utf8mb4` / Colación: `utf8mb4_unicode_ci`.
- **Motor de Almacenamiento:** `InnoDB` para soporte estricto de transacciones ACID y restricciones de integridad referencial (`FOREIGN KEY`).
- **Nomenclatura en Español:** Tablas en plural minúsculas (`organizaciones`, `usuarios`, `ediciones_candelaria`), columnas en singular `snake_case`.

---

## 2. Tablas Fundacionales (Fase 0)

| Tabla | Finalidad | Alcance SaaS-Ready |
|---|---|---|
| `migraciones_control` | Control de versiones y trazabilidad de cambios en BD | Plataforma |
| `organizaciones` | Tenants suscriptores del sistema (ej. O.G. Estudio Creativo) | Multiempresa |
| `planes` | Catálogo de planes comerciales de la plataforma | Plataforma |
| `modulos` | Catálogo de los 19 dominios funcionales del sistema | Plataforma |
| `capacidades_plan` | Matriz de módulos y límites habilitados por plan | Licenciamiento |
| `roles` | Roles asignados al tenant (`organizacion_id`) o a la plataforma (`NULL`) | Autorización |
| `permisos` | Permisos atómicos asociados a cada módulo | Autorización |
| `rol_permisos` | Mapeo relacional n-a-n entre roles y permisos | Autorización |
| `usuarios` | Cuentas de usuario pertenecientes a un tenant o superadmins | Usuarios |
| `usuario_roles` | Asignación de roles a usuarios | Autorización |
| `ediciones_candelaria` | Ediciones anuales de la Festividad asociadas a la organización | Negocio/Tenant |
| `menu_opciones` | Jerarquía visual de navegación (máximo 3 niveles) | Navegación |
| `sesiones` | Control y persistencia de sesiones web seguras | Seguridad |
| `auditoria_operaciones` | Pista inmutable de operaciones sensibles | Trazabilidad |

---

## 3. Ciclo de Vida de Ediciones Candelaria
El campo `fase_actual` de la tabla `ediciones_candelaria` define las etapas de operación anual:

$$\text{PREOPERACION} \longrightarrow \text{OPERACION} \longrightarrow \text{POSTPRODUCCION} \longrightarrow \text{CERRADA}$$

- Al transitar a **POSTPRODUCCIÓN**, se desactivan operaciones comerciales directas pero se preservan activas las consultas de galerías, entregas, seguimiento, pagos y reseñas.
