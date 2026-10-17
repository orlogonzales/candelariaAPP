# DOCUMENTACIÓN OFICIAL — CANDELARIAAPP V1.2
## Índice General, Arquitectura, Gobernanza y Estado de Certificación

**Versión en Rama de Desarrollo:** 1.2.0 (`desarrollo/v1.2` — Directivas `DT-V12-002` y `DT-V12-003`)  
**Línea Base Congelada en `main` (V1.1):** `DT-V11-CIERRE-001` (Baseline técnico [`e513fdd`](https://github.com/orlogonzales/candelariaAPP/commit/e513fdd) / Documental [`bc97361`](https://github.com/orlogonzales/candelariaAPP/commit/bc97361))  
**Referencia Histórica V1.0:** `DT-V1-CIERRE-001` (Baseline técnico [`f3ddb21`](https://github.com/orlogonzales/candelariaAPP/commit/f3ddb21) / Documental [`1f42885`](https://github.com/orlogonzales/candelariaAPP/commit/1f42885))  
**Calificación Oficial en `desarrollo/v1.2`:** **IMPLEMENTACIÓN Y AUDITORÍA CONTRACTUAL V1.2 COMPLETADAS (`2,674/2,674 PASS` EN 51 SUITES, `269/269` ENDPOINTS CERTIFICADOS INDIVIDUALMENTE EN RUNTIME CON `293/293 PASS`) — PENDIENTE DE HOMOLOGACIÓN DE DIRECCIÓN TÉCNICA**

---

> [!IMPORTANT] Declaración Expresa sobre Alcance V1.2, Resoluciones de Deuda Técnica y Restricciones Vigentes
> Bajo las directivas **`DT-V12-002`** y **`DT-V12-003`**, se han ejecutado las Fases 4.1 a 4.4 y la auditoría final de cobertura contractual individual en la rama aislada **`desarrollo/v1.2`**, manteniendo intacta la rama **`main`** en `bc97361`.
>
> **Hitos técnicos resueltos en V1.2:**
> 1. **Analítica Multianual (`DEC-01` - Opción 1A):** Implementada sobre el modelo relacional existente (**108 tablas**, **29 migraciones**, **115 permisos RBAC**), agregando endpoints comparativos interanuales (`/api/v1/dashboard/analitica-multianual`, `/api/v1/dashboard/analitica-multianual/exportar`), pestaña interactiva en Dashboard Ejecutivo y exportación CSV/XLSX/PDF con sanitización anti-Formula Injection (`25/25 PASS`).
> 2. **Certificación Contractual API 1:1 Individual en Tiempo de Ejecución (`DEC-02` - Opción 2A y `DT-V12-003`):** Se reconcilió el inventario entre V1.1 (**265 rutas** de negocio 100% compatibles) y V1.2 (**269 rutas** únicas: +2 rutas de analítica multianual + 2 rutas técnicas de distribución OpenAPI) y se ejecutaron pruebas individuales de contrato (`CT-V12-001` a `CT-V12-269`) sobre cada uno de los **269 endpoints** en la base de datos aislada desechable (`app_candelaria_v12_cert_contratos`), acreditando las 4 categorías de cobertura (inventariado, documentado, validado estructuralmente y ejecutado en runtime), `253/253` rechazos sin sesión (`401`/`403`), `183/183` rechazos por capacidad RBAC (`403`), aislamiento Multi-Tenant, Anti-IDOR y `0` respuestas HTTP 500 (`293/293 PASS`, **269/269 CERTIFICADOS**, `0` observados, `0` omitidos).
> 3. **Blindaje de Webhooks de Pasarelas de Pago y WhatsApp (`DEC-03` - Opción 3A):** Se fortalecieron `WebhookPasarelaServicio` y `WebhookWhatsAppServicio` con rechazo *fail-closed* de firmas inválidas o expiradas, bloqueo pesimista por fila (`SELECT ... FOR UPDATE`), idempotencia transaccional y protección monotónica frente a eventos fuera de orden (`14/14 PASS`).
>
> **Limitaciones obligatorias vigentes:**
> - **Aislamiento de rama (`DEC-06`):** `main` permanece en `bc97361` hasta la homologación formal de Dirección Técnica.
> - **Proveedores externos en modo sandbox:** Culqi, Niubiz, Stripe, Mercado Pago y Meta WhatsApp Cloud API continúan sin credenciales productivas activas en el entorno local.
> - **Recibos internos no tributarios (`DEC-05`):** Los recibos de caja, contratos y cotizaciones son comprobantes administrativos internos y **no constituyen comprobantes tributarios SUNAT**.
> - **Módulos diferidos a V1.3 (`DEC-04`):** Agenda (`08`), Activos Retornables (`10`) y Reseñas (`14`) conservan su nivel funcional de V1.1 y su profundización queda diferida a V1.3.

---

## 1. Estructura de la Documentación Oficial

```text
/docs/
├── README.md                              # Índice general y estado consolidado V1.0 / V1.1 / V1.2 (este archivo)
├── manuales/
│   └── manual_administrador_servidor.md   # Manual de instalación, configuración, DRP y operación
├── gobernanza/
│   └── reglas_gobernanza.md               # Mandatos, roles, protocolo de cambios, fases y limitaciones
├── decisiones/
│   ├── ADR-001-cambio-frontend-retiro-vue-adopcion-alina.md
│   └── ADR-002-stack-backend-nativo-saas-ready.md
├── arquitectura/
│   ├── adaptacion_plantilla_alina.md      # Estrategia de layout, Font Awesome y Fira Sans
│   ├── arquitectura_general.md            # Flujo HTTP, MVC propio, API-first y capas
│   ├── convenciones_nomenclatura.md       # Nomenclatura estricta en español y normalización
│   └── patron_ui_ux_asincrono.md          # Estándar CRUD, modales, DataTables y Skeletons
├── seguridad/
│   └── lineamiento_seguridad.md           # Security gate, PDO, escape contextual y auditoría
├── api/
│   ├── estrategia_api.md                  # Principios API-first, envoltura JSON y contratos Headless
│   ├── openapi.json                       # Especificación canónica OpenAPI 3.0.3 (JSON - 269 rutas certificadas 1:1)
│   └── openapi.yaml                       # Especificación canónica OpenAPI 3.0.3 (YAML - 269 rutas certificadas 1:1)
├── base-datos/
│   └── modelo_datos.md                    # Modelo SaaS-ready, evolución V1.0 (97 tablas) a V1.1/V1.2 (108 tablas)
└── modulos/
    └── mapa_modulos.md                    # Catálogo canónico de los 24 dominios funcionales (115 permisos RBAC)
```

---

## 2. Indicadores Oficiales Comparados (Histórico V1.0 vs Congelado V1.1 vs Vigente V1.2)

| Indicador | Referencia Histórica V1.0 (`DT-V1-CIERRE-001`) | Línea Base Congelada V1.1 (`DT-V11-CIERRE-001`) | Estado Vigente V1.2 (`DT-V12-003`) | Detalle de Verificación V1.2 |
| :--- | :---: | :---: | :---: | :--- |
| **Tablas Relacionales** | 97 | 108 | **108** | Sin tablas nuevas en V1.2 (`DEC-01` Opción 1A); blindaje incondicional de `app_candelaria` |
| **Migraciones Versionadas** | 23 | 29 | **29** | Numeradas cronológicamente (`000001` a `000029`) en `/base_datos/migraciones/` |
| **Permisos RBAC** | 104 | 115 | **115** | Reutilización de permisos existentes (`ediciones.reportes.ver`, `ediciones.reportes.ingresos.exportar`) |
| **Módulos Oficiales** | 24 | 24 | **24** | Catálogo completo registrado en tabla `modulos` |
| **Suites Automatizadas** | 40 | 48 | **51** | 48 suites V1.1 + 3 suites nuevas V1.2 (`f41`, `f42_contratos`, `f42_webhooks`) |
| **Pruebas Aprobadas** | 2,019 / 2,019 PASS | 2,342 / 2,342 PASS | **2,674 / 2,674 PASS** | 51 suites ejecutadas con `0 FAIL` y código `0` (`EXIT_SUCCESS` en `registro_regresion_global.log`) |
| **Compuertas Evolutivas** | 25 / 25 PASS | 19 / 19 PASS | **19 / 19 PASS** | Suite `pruebas/ratificacion_v11.php` (instalación limpia 1:1, DRP y seguridad) |
| **Rutas OpenAPI 3.0.3** | — | 265 rutas (estructural) | **269 / 269 rutas (1:1 runtime)** | Matriz `pruebas/matriz_certificacion_contratos_v12.json` (`269 CERTIFICADOS`, `293/293 PASS`) |
| **Huella Criptográfica** | `80e6af84e02e89e3` | `80e6af84e02e89e3` | **`80e6af84e02e89e3`** | Huella inmutable de identidad de Orlando (ID 24) preservada |

---

## 3. Trazabilidad Oficial de Fases Implementadas en CandelariaAPP V1.2 (`DT-V12-002` y `DT-V12-003`)

| Fase / Directiva | Micro-Baseline | Alcance Técnico y Evidencia Probatoria |
| :--- | :---: | :--- |
| **Fase 4.1** (`DT-V12-002` / `DEC-01`) | `88becaf` | **COMPLETADA:** Analítica multianual e inteligencia histórica de ediciones sobre esquema de 108 tablas. Comparativa interanual de 2 a $N$ ediciones con variaciones absolutas ($\Delta$) y porcentuales ($\Delta\%$), manejo seguro de división por cero (`null`), aislamiento por `organizacion_id`, pestaña interactiva en Dashboard Ejecutivo y exportación CSV/XLSX/PDF (`25/25 PASS` en `pruebas/ejecutar_pruebas_f41.php`). |
| **Fase 4.2** (`DT-V12-002` / `DT-V12-003` / `DEC-02`, `DEC-03`) | `DT-V12-003` | **COMPLETADA:** (1) Certificación contractual 1:1 individual en tiempo de ejecución de los **269 endpoints REST** (`CT-V12-001` a `CT-V12-269`) entre `rutas/api.php` y OpenAPI 3.0.3 sobre base aislada desechable `app_candelaria_v12_cert_contratos`, matriz `pruebas/matriz_certificacion_contratos_v12.json` y corrección de 8 defectos latentes en controladores/servicios (`293/293 PASS`, `269/269 CERTIFICADOS`, `0` observados). (2) Blindaje de `WebhookPasarelaServicio` y `WebhookWhatsAppServicio` con firmas HMAC *fail-closed*, bloqueo pesimista `FOR UPDATE`, idempotencia e invariante monotónica (`14/14 PASS` en `pruebas/ejecutar_pruebas_f42_webhooks.php`). |
| **Fase 4.3** (`DT-V12-002` / `DT-V12-003`) | `DT-V12-003` | **COMPLETADA:** Estabilización transversal y ejecución completa en primer plano de las **51 suites** (`2,674/2,674 PASS`, `0 FAIL`) en `pruebas/registro_regresion_global.log` y ratificación estructural `19/19 PASS` en `pruebas/ratificacion_v11.php`. |
| **Fase 4.4** (`DT-V12-002` / `DT-V12-003`) | `DT-V12-003` | **COMPLETADA:** Sincronización documental (`README.md`, `docs/README.md`, `docs/modulos/mapa_modulos.md`) preservando los indicadores históricos de V1.0 y V1.1, y entrega del Informe de Auditoría Final de Cobertura Contractual `DT-V12-003`. |

---

## 4. Trazabilidad Histórica de Fases Homologadas en CandelariaAPP V1.1 (Referencia Conservada)

| Fase / Directiva | Micro-Baseline Homologado | Decisión de Cierre y Alcance Homologado en V1.1 |
| :--- | :---: | :--- |
| **Fase 3.1** (`DT-V11-003`, `004`, `006`, `008`) | `4edcfbf` | **HOMOLOGADA:** Catálogo de danzas y conjuntos, padrón anual por edición, programación oficial versionada con principio de cuatro ojos, control de concurrencia (`version_bloqueo`), seguimiento operativo y cobertura audiovisual (106/106 pruebas PASS). |
| **Fase 3.2** (`DT-V11-009`) | `63b4e66` | **HOMOLOGADA:** Motor de eventos de negocio, reglas configurables de WhatsApp, validación de consentimiento diferenciado, idempotencia criptográfica, bandeja de salida y simulador local (47/47 pruebas PASS). |
| **Fase 3.3** (`DT-V11-010` a `DT-V11-013`) | `bba6d38` | **HOMOLOGADA:** Operación móvil web/PWA y validación QR sin conexión con manifiesto firmado HMAC-SHA256, estados provisionales locales, conciliación por lotes (`servidor_prevalece`) y blindaje incondicional de `app_candelaria` (36/36 pruebas funcionales + 18/18 blindaje BD + 15/15 seguridad QR PASS). |
| **Fase 3.4** (`DT-V11-014`, `DT-V11-015`) | `909940f` / `5c84ca7` | **HOMOLOGADA:** Portal del cliente con autenticación passwordless blindada (expiración, rate-limit, bloqueo brute-force y single-use atómico), aislamiento anti-IDOR, recibos internos, selección fotográfica persistente y 5 compuertas simultáneas para descarga de originales (58/58 funcional + 40/40 seguridad PASS). |
| **Fase 3.5** (`DT-V11-016`) | `e513fdd` | **HOMOLOGADA:** Inventario de 265 rutas REST en 13 dominios, especificación canónica OpenAPI 3.0.3 (JSON y YAML), visualizador Swagger UI en `/documentacion` y `/docs`, y regularización determinista de F2.10 sin omisiones condicionales (36/36 F3.5 + 59/59 F2.10 PASS). |
| **Cierre V1.1** (`DT-V11-CIERRE-001`) | `bc97361` | **HOMOLOGADA:** Consolidación documental integral y congelamiento de la línea base V1.1 en `main` (`2,342/2,342 PASS` en 48 suites). |

---

## 5. Trazabilidad Histórica del Ciclo Base V1.0 (Referencia Conservada)

| Fase / Directiva V1.0 | Micro-Baseline | Hitos y Logros Acreditados en V1.0 |
| :--- | :---: | :--- |
| **F2.12A** | `45b6e05` | Asistente de cierre de edición, acta con hash SHA-256 y reapertura auditada. |
| **F2.12B** | `620c83e` | Reportes oficiales exportables (Ingresos, Manifiesto, Asistencia) en CSV, XLSX y PDF. |
| **F2.12B-1 / F2.12C** | `f158f44` | Saneamiento de auditoría, autorización cuatro ojos y Dashboard Ejecutivo con KPIs persistidos. |
| **F2.12D** | `5554192` | Suite integral de auditoría de alcance y seguridad transversal. |
| **DT-F212D-002** | `28d6af7` | Ratificación probatoria en primer plano, log de regresión persistente y blindaje de 25 gates (2,019 PASS). |
| **DT-V1-DESPLIEGUE-001** | `f3ddb21` | Verificación operativa completa: DRP con muestra real (BD + PDFs + Multimedia con hashes 1:1), workers y sandbox. |
| **DT-V1-CIERRE-001** | `1f42885` | Consolidación documental, congelamiento formal de V1.0 (97 tablas, 23 migraciones, 104 permisos) y manual del administrador. |
