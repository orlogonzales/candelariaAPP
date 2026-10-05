# INFORME DE EVIDENCIAS Y CONSOLIDACIÓN ARQUITECTÓNICA — MICROLOTE F1.2D
**CandelariaAPP &bull; Plataforma de Gestión y Producción Audiovisual**  
**Festividad de la Virgen de la Candelaria &bull; Puno, Perú**

---

## 1. RESUMEN EJECUTIVO

- **Microlote:** `F1.2D` — Integración, Catálogos Transversales, Consolidación de Configuración y Aislamiento Determinista de Pruebas.
- **Baseline de Entrada Oficial:** `3da7e92bedd90e4b2a27c776effe8b3ebc6f2a50` (F1.2C Aprobado con 173/173 PASS).
- **Estado de Cierre:** **PASS FORMAL TÉCNICO Y ARQUITECTÓNICO (100% PRUEBAS EXITOSAS)**.
- **Regresión Acumulada Total:** **193 / 193 PRUEBAS PASS (0 FALLOS)**.
  - `F1.1A` (Identidad, Actores, Canales, Auditoría): **15/15 PASS**
  - `F1.1B` (Seguridad, Sesiones, CSRF, Bloqueo): **26/26 PASS**
  - `F1.1C` (RBAC, Catálogo Permisos, Autorización Backend): **11/11 PASS**
  - `F1.1D` (Login Alina, DataTables, Modales, API Usuarios — *Aislada*): **17/17 PASS**
  - `F1.1E` (Hardening, IDOR, Roles y SEGURIDAD_AUTH): **25/25 PASS**
  - `F1.2A` (Modelo Configuración, Parámetros Tipados, Branding, Caché): **25/25 PASS**
  - `F1.2B` (Ficha Institucional, Modales Alina, Branding Seguro, Anti-IDOR): **28/28 PASS**
  - `F1.2C` (Configuración General, Parámetros Operativos, Soberanía, Concurrencia 409): **26/26 PASS**
  - `F1.2D` (Integración, Catálogos, Tenant Fail-Closed, Aislamiento y Preservación): **20/20 PASS**
- **Preservación Inviolable de la Cuenta `orlando` (ID 24):**
  - Huella Digital SHA-256 Pre-Regresión: `80e6af84e02e89e3`
  - Huella Digital SHA-256 Post-Regresión: `80e6af84e02e89e3` (**100% IDÉNTICA**)
  - Estado: `ACTIVO`
  - Intentos Fallidos: `0`
  - Bloqueo Temporal: `NULL`
  - Rol Asignado: `admin_organizacion`
- **Upstream Alina (`admin-dashboard/`):** **100% INTACTO** (cero modificaciones, working tree clean).
- **Tabler Iconos (`ti-`):** **0 efectivo en vistas del sistema** (100% Font Awesome 6).
- **Higiene de Secretos:** Cero contraseñas en texto claro, credenciales ni tokens en repositorio, seeds, SQL versionado ni documentación.

---

## 2. MATRIZ DE DOMINIO: CATÁLOGO vs PARÁMETRO vs ENTIDAD DE NEGOCIO

En estricta observancia del principio de distinción ontológica, se categorizaron y auditaron todos los componentes estructurales de la plataforma:

| ELEMENTO | TIPO | ÁMBITO | CONSUMIDOR ACTUAL | CONSUMIDOR FUTURO | ESTADO | DUPLICACIÓN | NECESITA CATÁLOGO | NECESITA CONFIGURACIÓN | ACCIÓN APLICADA |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `tipos_documento` | CATÁLOGO | PLATAFORMA | `PersonaRepositorio`, `OrganizacionControlador`, UI Modal Usuarios | Módulos Clientes, Contratos | GOBERNADO | No | Existente (6 tipos) | No | Preservado como catálogo protegido y no administrable por tenant. |
| `actores_sistema` | CATÁLOGO | PLATAFORMA | `AuditoriaRepositorio`, `AutenticacionServicio` (SEGURIDAD_AUTH) | Webhooks pasarela, workers | GOBERNADO | No | Existente (6 actores) | No | Protegido de sistema; prohibido convertir en CRUD para tenants. |
| `canales` | CATÁLOGO | PLATAFORMA | `AuditoriaRepositorio`, `ContextoOperacion` | Futuras Apps móviles, WhatsApp API | GOBERNADO | No | Existente (7 canales) | No | Gobernado técnicamente; extensible sin CRUD prematuro. |
| `roles` | CATÁLOGO | PLATAFORMA / ORG | `AutorizacionServicio`, `AutorizacionMiddleware` | Subroles operativos | GOBERNADO | No | Existente (3 roles) | No | Roles de sistema protegidos; asignación filtrada por rango. |
| `permisos` | CATÁLOGO | PLATAFORMA | `AutorizacionServicio`, Controladores, UI Sidebar | Permisos granulares de negocio | GOBERNADO | No | Existente (13 permisos) | No | Inmutables a nivel de esquema; vinculados a módulos formales. |
| `modulos` | CATÁLOGO | PLATAFORMA | `permisos`, `capacidades_plan`, UI Sidebar | Fases 2 a 19 | GOBERNADO | No | Existente (19 dominios) | No | Catálogo de módulos oficiales de CandelariaAPP intacto. |
| `parametros_configuracion` | PARÁMETRO | PLATAFORMA / ORG | `AutenticacionServicio` (login), `ConfiguracionServicio` | Cotizaciones, Pagos, Reservas | GOBERNADO | No | No (usa tabla de parámetros) | Sí (9 parámetros tipados) | Catálogo cerrado sin bolsa libre `clave->valor`. |
| `organizaciones` | ENTIDAD | MULTI-TENANT | `OrganizacionRepositorio`, `ContextoOperacion`, Controladores | Fases 2 a 19 | ENTIDAD ACTIVA | No | No | No | Entidad con ciclo de vida, branding y datos fiscales propios. |
| `personas` | ENTIDAD | ORGANIZACIÓN | `PersonaRepositorio`, `UsuarioControlador` | CRM, Folcloristas, Clientes | ENTIDAD ACTIVA | No | No | No | Entidad con identidad civil y atributos de contacto. |
| `usuarios` | ENTIDAD | ORGANIZACIÓN | `UsuarioRepositorio`, `AutenticacionServicio`, Controladores | Operadores, Clientes | ENTIDAD ACTIVA | No | No | No | Cuentas de acceso autenticadas con RBAC y anti-IDOR. |
| `sesiones` | ENTIDAD | SEGURIDAD | `SesionRepositorio`, `AutenticacionMiddleware` | Token storage | ENTIDAD ACTIVA | No | No | No | Tokens con hashing SHA-256, expiración y rotación. |
| `auditoria_operaciones` | ENTIDAD | AUDITORÍA | `AuditoriaRepositorio` | Módulos de auditoría visual | ENTIDAD ACTIVA | No | No | No | Bitácora inmutable Append-Only libre de secretos. |

### Regla contra Catálogos Especulativos
Se evaluó la eventual creación de catálogos para danzas, agrupaciones, concursos, escenarios, paquetes, productos, servicios, métodos de pago, estados de reserva, tipos de cobertura, activos y galerías. **Ninguno cumplió simultáneamente los 4 criterios de elegibilidad** (transversal + consumidor real actual + evita hardcode actual + semántica definida). En consecuencia, se acató la regla estricta: **DOCUMENTAR, NO IMPLEMENTAR PREMATURAMENTE**. Su construcción queda programada para sus respectivas fases de negocio (Fase 2 en adelante).

---

## 3. INVENTARIO DE HARDCODES Y CLASIFICACIÓN

Se realizó una auditoría exhaustiva sobre el código fuente, clasificando cada hallazgo de acuerdo a las cuatro categorías autorizadas:

| HALLAZGO | UBICACIÓN | TIPO ENCONTRADO | CLASIFICACIÓN | IMPACTO / RESOLUCIÓN |
| :--- | :--- | :--- | :--- | :--- |
| `$contexto->organizacionId ?? 1` | `UsuarioControlador.php` (líneas 64, 107, 163) | Fallback numérico silencioso | **HARDCODE INDEBIDO** | **CORREGIDO.** Sustituido por `resolverOrganizacionId()`, aplicando Fail-Closed (HTTP 403) ante ausencia de tenant. |
| `$contextoBarra->organizacionId ?? 10000` | `barra_lateral.php` (línea 17) | Fallback numérico a tenant | **HARDCODE INDEBIDO** | **CORREGIDO.** Reemplazado por comprobación explícita de `organizacionId !== null && organizacionId > 0` sin asumir ID mágico. |
| `max="10000.0"` | `configuracion/general.php` (línea 345) | Atributo de validación HTML | **LEGÍTIMA** | Límite superior del campo de formulario para el monto mínimo admitido. |
| `'PEN'` | `ParametroConfiguracion.php`, Semillas, SQL | Código ISO divisa nacional | **LEGÍTIMA / SEMILLA** | Bloqueado en catálogo soberano inmutable para el sistema peruano. |
| `'America/Lima'` | `ParametroConfiguracion.php`, Semillas, SQL | Huso horario oficial IANA | **LEGÍTIMA / SEMILLA** | Valor por defecto soberano para el cálculo de tiempos en Puno, Perú. |
| `max_intentos_login`, `minutos_bloqueo_login` | `AutenticacionServicio.php`, Semillas, Tests | Códigos canónicos gobernados | **LEGÍTIMA / SEMILLA** | Nombres canónicos de parámetros consumidos dinámicamente desde BD. |
| `10000` (Tenant O.G. Estudio) | Semillas SQL (`000003_semillas...`), Tests F1.2 | ID del tenant inicial | **SEMILLA / TEST** | Identificador determinista de la organización base en semillas y pruebas. |
| `admin_organizacion`, `superadmin_plataforma` | Repositorios, Middlewares, Semillas | Nombres canónicos de roles | **LEGÍTIMA** | Constantes y códigos de roles protegidos de sistema (`es_sistema = 1`). |
| `app.candelaria.test` | Apache VHost, Scripts de captura de pantalla | Host local de desarrollo | **LEGÍTIMA / TEST** | Dominio local configurado en Laragon para el entorno de pruebas. |

---

## 4. MATRIZ DE CONSUMIDORES DE PARÁMETROS DE CONFIGURACIÓN

| PARÁMETRO | ÁMBITO | CONSUMIDOR ACTUAL | CONSUMIDOR FUTURO | ESTADO |
| :--- | :--- | :--- | :--- | :--- |
| `plataforma.max_intentos_login` | PLATAFORMA | `AutenticacionServicio::autenticar()` | API Gateway / Rate limiters | **CONSUMIDO DINÁMICAMENTE** |
| `plataforma.minutos_bloqueo_login` | PLATAFORMA | `AutenticacionServicio::autenticar()` | Lockout manager | **CONSUMIDO DINÁMICAMENTE** |
| `plataforma.monto_minimo_pago_pe` | PLATAFORMA | `ConfiguracionServicio`, UI General | Fase 6: Pagos y Caja / Pasarelas | **GOBERNADO / SIN CONSUMIDOR TODAVÍA** |
| `plataforma.zona_horaria` | PLATAFORMA | `ConfiguracionServicio` (validador IANA) | Agenda, Cronograma de Edición | **GOBERNADO / SIN CONSUMIDOR TODAVÍA** |
| `plataforma.moneda_principal` | PLATAFORMA | `ConfiguracionServicio` (inmutable PEN) | Fase 4: Catálogo Comercial, Fase 6 | **GOBERNADO / SIN CONSUMIDOR TODAVÍA** |
| `plataforma.permitir_registro_publico` | PLATAFORMA | `ConfiguracionServicio`, UI General | Portales públicos y landing page | **GOBERNADO / SIN CONSUMIDOR TODAVÍA** |
| `organizacion.notificar_whatsapp` | ORGANIZACION | `ConfiguracionServicio`, UI General | Fase 11: Mensajería WhatsApp API | **GOBERNADO / SIN CONSUMIDOR TODAVÍA** |
| `organizacion.dias_validez_cotizacion` | ORGANIZACION | `ConfiguracionServicio`, UI General | Fase 3: Pre-Candelaria / Cotizaciones | **GOBERNADO / SIN CONSUMIDOR TODAVÍA** |
| `organizacion.porcentaje_reserva_minimo` | ORGANIZACION | `ConfiguracionServicio`, UI General | Fase 5: Reservas y Contratos | **GOBERNADO / SIN CONSUMIDOR TODAVÍA** |

---

## 5. AISLAMIENTO DETERMINISTA DE PRUEBAS Y PRESERVACIÓN DE LA CUENTA `orlando`

### 5.1. Causa Raíz del Incidente Operativo
Durante la auditoría de `pruebas/ejecutar_pruebas_f11d.php` se detectó que la prueba 02 mutaba directamente la cuenta real `orlando` en la base de datos de desarrollo mediante `actualizarContrasenaHash` con una clave temporal aleatoria generada fuera de una transacción. Asimismo, la prueba 17 intentaba autenticar contra esa clave aleatoria, dejando el hash criptográfico permanentemente desincronizado.

### 5.2. Solución Aplicada (Aislamiento Estricto y Fixtures Efímeros)
1. **Inicio Temprano de Transacción:** Se trasladó `$pdo->beginTransaction()` antes de la prueba 02 en `ejecutar_pruebas_f11d.php`.
2. **Fixture Administrativo Aislado:** La prueba 02 crea una persona y usuario administrativo efímero (`admin_test_f11d`) dentro de la transacción, asignándole el rol `admin_organizacion`. La prueba de autenticación, generación de token y validación de contexto se ejecuta exclusivamente sobre este fixture.
3. **Verificación de `orlando` en Solo Lectura:** La prueba 17 fue refactorizada para verificar que `orlando` existe, se encuentra `ACTIVO`, tiene `intentos_fallidos = 0`, `bloqueado_hasta = NULL` y posee el rol `admin_organizacion`, sin invocar métodos destructivos ni mutar su contraseña.
4. **Rollback Garantizado:** Al finalizar la suite en el bloque `finally`, se ejecuta `$pdo->rollBack()`, revirtiendo cualquier cambio y manteniendo intacta la base de datos de desarrollo.

### 5.3. Huella Digital Criptográfica (Fingerprint Pre/Post Regresión)
Para asegurar que ninguna suite alteró la contraseña del usuario real sin exponer el hash completo ni la clave en claro:
- **Algoritmo:** `substr(hash('sha256', $hashBcrypt), 0, 16)`
- **Fingerprint Pre-Regresión:** `80e6af84e02e89e3`
- **Fingerprint Post-Regresión (tras 193 pruebas):** `80e6af84e02e89e3`
- **Resultado:** **IDENTIDAD Y HASH INMUTABLES (INTEGRIDAD 100%)**.

---

## 6. SOBERANÍA Y BLINDAJE DE PLATAFORMA

- **Aislamiento en Backend (`ConfiguracionControlador`):**
  - Un usuario con rol `admin_organizacion` que consulte `GET /api/v1/configuracion` recibe `plataforma = []` y la bandera `puede_ver_plataforma = false`.
  - Si intenta forzar el parámetro `?ambito=PLATAFORMA`, el backend responde inmediatamente con **HTTP 403 Forbidden** (`Acceso denegado: requiere el permiso 'configuracion_plataforma.ver'`).
  - Si intenta emitir un `PUT /api/v1/configuracion` para modificar un parámetro soberano, el backend verifica tanto el permiso como el rango de superadministrador, respondiendo con **HTTP 403 Forbidden**.
- **Aislamiento en Frontend (`recursos/vistas/paginas/configuracion/general.php`):**
  - La pestaña *"Soberanía de Plataforma"* no se renderiza en el DOM para operadores sin permiso de plataforma (`<?php if ($puedeVerPlat): ?>`).
  - El panel de parámetros soberanos se omite completamente, impidiendo cualquier interacción o visibilidad indebida.

---

## 7. AUDITORÍA DE NAVEGACIÓN Y CONSISTENCIA VISUAL ALINA

- **Jerarquía de Navegación:**
  - Estructura validada en 3 niveles máximos:
    1. Barra Compacta Lateral (`semi-side-nav`): Iconos principales y tooltips funcionales Alina.
    2. Barra Principal Desplegable (`main-side-nav`): Módulos organizados temáticamente.
    3. Acordeones / Submenús colapsables (`ul.collapse`): Vínculos a vistas específicas.
  - El módulo de Configuración se agrupa coherentemente:
    - *Configuración &rarr; Organización* (`configuracion/organizacion`)
    - *Configuración &rarr; Configuración General* (`configuracion/general`)
    - *Configuración &rarr; Control de Acceso &rarr; Padrón de Usuarios* (`usuarios`)
- **Alina Upstream (`admin-dashboard/`):**
  - El directorio de referencia de la plantilla permanece 100% limpio y libre de modificaciones locales.
- **Sustitución de Tabler:**
  - En todas las vistas propias del aplicativo (`recursos/vistas/`), el recuento de clases Tabler (`ti-`) es exactamente **0**. Todos los iconos operan exclusivamente bajo Font Awesome 6.

---

## 8. RESULTADOS DE LA REGRESIÓN COMPLETA (193/193 PASS)

```text
==============================================================================
RESUMEN DE REGRESIÓN ACUMULADA — CANDELARIAAPP F1.2D
==============================================================================
F1.1A  Modelo de Identidad, Personas, Actores, Canales y Auditoría : 15/15 PASS
F1.1B  Seguridad, Autenticación, Sesiones, CSRF, Bloqueo Dinámico  : 26/26 PASS
F1.1C  RBAC, Roles de Sistema, Permisos Atómicos y Autorización    : 11/11 PASS
F1.1D  Login Alina, DataTables, Modales CRUD y API Usuarios        : 17/17 PASS
F1.1E  Auditoría, Hardening, Anti-IDOR y SEGURIDAD_AUTH            : 25/25 PASS
F1.2A  Modelo de Configuración, Parámetros Tipados y Branding      : 25/25 PASS
F1.2B  Ficha Institucional, Modales Alina y Carga Segura Archivos   : 28/28 PASS
F1.2C  Configuración General, Soberanía y Concurrencia Optimista 409: 26/26 PASS
F1.2D  Integración, Catálogos, Fail-Closed y Aislamiento Pruebas   : 20/20 PASS
------------------------------------------------------------------------------
TOTAL GENERAL ACUMULADO                                            : 193/193 PASS (100%)
FALLOS / ERRORES                                                   : 0
==============================================================================
```

---

## 9. CHECKLIST DE CUMPLIMIENTO DEL GATE F1.2D

- [x] **P0 / P1 abiertos:** 0.
- [x] **Regresión acumulada:** 193 / 193 PASS (100% éxito).
- [x] **Aislamiento de pruebas:** Ninguna suite muta el estado funcional de desarrollo.
- [x] **Preservación de Orlando:** Fingerprint idéntico pre/post (`80e6af84e02e89e3`), intentos 0, sin bloqueo, estado ACTIVO, rol `admin_organizacion`.
- [x] **Tenant Fail-Closed:** Controladores responden 403 estricto ante ausencia de contexto organizacional.
- [x] **Soberanía Plataforma / Organización:** Privilegios estrictamente diferenciados en backend y frontend.
- [x] **Catálogos Transversales:** Auditados, gobernados y libres de CRUD especulativo.
- [x] **Parámetros sin consumidor:** Marcados formalmente como GOBERNADOS sin invención de consumidores artificiales.
- [x] **Alina Upstream:** `admin-dashboard/` 100% intacto y limpio.
- [x] **Tabler efectivo:** 0 en vistas propias.
- [x] **Higiene de Secretos:** 0 credenciales ni secretos en repositorio, semillas ni auditoría.
- [x] **Working tree y Git:** Sincronizado con origin/main y limpio.
