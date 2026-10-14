# MODELO DE DATOS Y GESTIÓN DE BASE DE DATOS — CANDELARIAAPP V1.1

**Versión Vigente:** 1.1.0 (`DT-V11-CIERRE-001` — Baseline técnico `e513fdd`)  
**Total de Tablas Relacionales:** **108 Tablas en InnoDB** (Referencia histórica V1.0: 97 tablas)  
**Total de Migraciones Versionadas:** **29 Migraciones** (Referencia histórica V1.0: 23 migraciones)  
**Total de Módulos Oficiales:** **24 Módulos** (`modulos`) | **Permisos RBAC:** **115 Permisos** (V1.0: 104)  

---

## 1. Directrices Fundacionales y de Gobernanza de Datos
- **Motor Oficial:** MySQL 8.0+ / MariaDB 10.5+ (Probado y certificado en MySQL 8.0 / 8.4 LTS).
- **Juego de Caracteres:** `utf8mb4` / Colación: `utf8mb4_unicode_ci`.
- **Motor de Almacenamiento:** `InnoDB` para soporte estricto de transacciones ACID, integridad referencial (`FOREIGN KEY`) y restricciones de validación estructural (`CHECK`).
- **Nomenclatura en Español:** Tablas en plural minúsculas (`organizaciones`, `personas`, `usuarios`, `actores_sistema`, `canales`), columnas en singular `snake_case`.
- **Blindaje Incondicional de Base Principal (`DT-V11-013`):** Queda estrictamente prohibida la ejecución de scripts de saneamiento destructivo sobre la base principal `app_candelaria`; cualquier ensayo de limpieza o restauración se ejecuta exclusivamente sobre bases temporales aisladas.

---

## 2. Evolución Estructural del Esquema (Fase 1.1A $\rightarrow$ V1.0 $\rightarrow$ V1.1)

| Hito / Versión | Migraciones | Tablas Acumuladas | Permisos RBAC | Descripción del Alcance de Datos |
| :--- | :---: | :---: | :---: | :--- |
| **Fase 1.1A (Fundacional)** | `000001`–`000003` | 18 | Base | Núcleo de identidad, multi-tenant, sesiones y auditoría dual. |
| **Cierre V1.0 (`f3ddb21`)** | `000001`–`000023` (23) | **97** | **104** | CRM, catálogo comercial, cotizaciones, ventas, reservas, pagos en centavos, recibos, postproducción y cierre de edición. |
| **Cierre V1.1 (`e513fdd`)** | `000001`–`000029` (**29**) | **108** | **115** | Programación folclórica (+6 tablas), automatizaciones WhatsApp (+2 tablas), operación QR offline (+2 tablas) y selección fotográfica en portal (+1 tabla). |

### 2.1 Nuevas Tablas Incorporadas en CandelariaAPP V1.1 (+11 Tablas)

| # | Tabla | Fase / Migración | Dominio / Finalidad |
| :---: | :--- | :---: | :--- |
| 98 | `folclore_tipos_danza` | F3.1 (`000024`) | Catálogo de modalidades y géneros de danza (traje de luces, autóctonas) |
| 99 | `conjuntos_folcloricos` | F3.1 (`000024`) | Padrón maestro de agrupaciones y conjuntos folclóricos |
| 100 | `bloques_conjunto` | F3.1 (`000024`) | Bloques, escuadras y filiales de cada conjunto folclórico |
| 101 | `folclore_participaciones_edicion` | F3.1 (`000024`) | Inscripción y puntaje de conjuntos por edición anual |
| 102 | `folclore_programaciones_oficiales` | F3.1 (`000024`, `000025`) | Versiones del rol oficial con segregación de aprobación (4 ojos) |
| 103 | `folclore_orden_presentacion` | F3.1 (`000024`, `000026`) | Turnos, seguimiento operativo, `version_bloqueo` y `retraso_minutos` |
| 104 | `whatsapp_reglas_automatizacion` | F3.2 (`000027`) | Reglas configurables de disparo automático por evento de negocio |
| 105 | `whatsapp_automatizacion_eventos` | F3.2 (`000027`) | Bitácora de ejecuciones con llave única de idempotencia |
| 106 | `operacion_offline_lotes` | F3.3 (`000028`) | Lotes de sincronización de escaneos QR desde dispositivos móviles |
| 107 | `operacion_offline_eventos` | F3.3 (`000028`) | Eventos individuales de validación QR offline y resolución de conflictos |
| 108 | `postproduccion_selecciones` | F3.4 (`000029`) | Selección persistente de fotografías realizada por el cliente en el portal |

---

### 2.2 Núcleo Fundacional (Fase 1.1A — Primeras 18 Tablas de Plataforma)

| # | Tabla | Dominio / Finalidad | Alcance SaaS-Ready |
|---|---|---|---|
| 1 | `migraciones_control` | Control de versiones y lotes de migración ejecutados (29 migraciones en V1.1) | Plataforma |
| 2 | `organizaciones` | Tenants suscriptores del sistema (ej. O.G. Estudio Creativo) | Multiempresa |
| 3 | `planes` | Catálogo de planes comerciales de la plataforma | Licenciamiento |
| 4 | `modulos` | Catálogo de los 24 dominios funcionales oficiales del sistema | Plataforma |
| 5 | `capacidades_plan` | Matriz de módulos y límites habilitados por plan | Licenciamiento |
| 6 | `roles` | Roles asignados al tenant (`organizacion_id`) o a la plataforma (`NULL`) | Autorización RBAC |
| 7 | `permisos` | Catálogo de permisos atómicos (115 permisos en V1.1; 104 en V1.0) | Autorización RBAC |
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
