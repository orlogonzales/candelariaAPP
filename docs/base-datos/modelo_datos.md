# MODELO DE DATOS Y GESTIÓN DE BASE DE DATOS — CANDELARIAAPP

## 1. Directrices Fundacionales
- **Motor Oficial:** MySQL 8.0+ / MariaDB 10.5+ (Probado y certificado en MySQL 8.4 LTS).
- **Juego de Caracteres:** `utf8mb4` / Colación: `utf8mb4_unicode_ci`.
- **Motor de Almacenamiento:** `InnoDB` para soporte estricto de transacciones ACID, integridad referencial (`FOREIGN KEY`) y restricciones de validación estructural (`CHECK`).
- **Nomenclatura en Español:** Tablas en plural minúsculas (`organizaciones`, `personas`, `usuarios`, `actores_sistema`, `canales`), columnas en singular `snake_case`.

---

## 2. Padrón Oficial de Tablas (Fase 1.1A — 18 Tablas Oficiales)

| # | Tabla | Dominio / Finalidad | Alcance SaaS-Ready |
|---|---|---|---|
| 1 | `migraciones_control` | Control de versiones y lotes de migración ejecutados | Plataforma |
| 2 | `organizaciones` | Tenants suscriptores del sistema (ej. O.G. Estudio Creativo) | Multiempresa |
| 3 | `planes` | Catálogo de planes comerciales de la plataforma | Licenciamiento |
| 4 | `modulos` | Catálogo de los 19 dominios funcionales del sistema | Plataforma |
| 5 | `capacidades_plan` | Matriz de módulos y límites habilitados por plan | Licenciamiento |
| 6 | `roles` | Roles asignados al tenant (`organizacion_id`) o a la plataforma (`NULL`) | Autorización RBAC |
| 7 | `permisos` | Permisos atómicos asociados a cada módulo funcional | Autorización RBAC |
| 8 | `rol_permisos` | Mapeo relacional n-a-n entre roles y permisos | Autorización RBAC |
| 9 | `tipos_documento` | Catálogo extensible de documentos de identidad (DNI, RUC, etc.) | Identidad |
| 10 | `personas` | Padrón canónico de identidades físicas (`NATURAL`) y jurídicas (`JURIDICA`) | Identidad Central |
| 11 | `usuarios` | Cuentas de acceso vinculadas obligatoriamente a una persona | Acceso / Seguridad |
| 12 | `usuario_roles` | Asignación de roles de seguridad a usuarios | Autorización RBAC |
| 13 | `ediciones_candelaria` | Ediciones anuales de la Festividad asociadas a la organización | Negocio / Tenant |
| 14 | `menu_opciones` | Jerarquía visual de navegación (máximo 3 niveles) | Navegación |
| 15 | `sesiones` | Control y persistencia de sesiones web seguras | Seguridad HTTP |
| 16 | `actores_sistema` | Catálogo controlado de identidades técnicas / actores virtuales | Trazabilidad |
| 17 | `canales` | Catálogo extensible de medios de ingreso (APP, WEB, API, etc.) | Trazabilidad |
| 18 | `auditoria_operaciones` | Pista inmutable de auditoría dual con restricción estructural `CHECK` | Auditoría Dual |

---

## 3. Arquitectura de Identidad: Persona $\longrightarrow$ Usuario

El sistema desacopla estrictamente la identidad real del sujeto de su cuenta de credenciales de acceso:

```text
PERSONA (Identidad física o jurídica en el mundo real)
  1
  │
  └──── 0..1 USUARIO (Credencial de acceso al sistema con login y contraseña hash)
```

- **Restricción de Consistencia (`chk_personas_tipo_consistencia`):**
  * `NATURAL`: Requiere `nombres` y `apellidos` obligatorios; `razon_social` debe ser `NULL`.
  * `JURIDICA`: Requiere `razon_social` obligatoria; `nombres` y `apellidos` deben ser `NULL`.
  * Ambas pueden registrar opcionalmente `nombre_comercial`.
- **Unicidad Documental por Organización:**
  * `UNIQUE KEY uk_personas_org_doc (organizacion_id, tipo_documento_id, numero_documento)`.
- **Unicidad de Usuario por Persona:**
  * `UNIQUE KEY uk_usuarios_persona (persona_id)`.

---

## 4. Arquitectura de Operación: Actor $\times$ Canal (Trazabilidad Dual)

La auditoría de operaciones separa de forma ortogonal **quién o qué ejecuta** de **por qué medio ingresa**:

```text
                                  OPERACIÓN EN EL SISTEMA
                                             │
                     ┌───────────────────────┴───────────────────────┐
                     ▼                                               ▼
             DIMENSIÓN ACTOR                                 DIMENSIÓN CANAL
       (¿Quién o qué ejecuta?)                             (¿Por dónde ingresa?)
        ┌────────────┴────────────┐                      ┌───────────┼───────────┐
        ▼                         ▼                      ▼           ▼           ▼
     HUMANO                    SISTEMA                  APP         WEB         API
        │                         │                      │           │           │
   usuario_id               actor_sistema             (Panel      (Landing    (Webhooks,
  (FK usuarios)         (Identificador técnico:        Alina)      Pública)    Partners,
                         LANDING_CANDELARIA,                                  CRONs)
                         CHECKOUT_PASARELA,
                         WORKER_CONCILIACION...)
```

### Regla Estructural `CHECK` en `auditoria_operaciones`:
```sql
CONSTRAINT `chk_auditoria_actor` CHECK (
    (`actor_tipo` = 'HUMANO' AND `usuario_id` IS NOT NULL AND `actor_sistema_id` IS NULL)
    OR
    (`actor_tipo` = 'SISTEMA' AND `usuario_id` IS NULL AND `actor_sistema_id` IS NOT NULL)
)
```
Cualquier estado contradictorio (humano sin usuario, sistema sin actor técnico, o mezcla de ambos) es rechazado automáticamente a nivel de motor de base de datos.
