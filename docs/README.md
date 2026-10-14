# DOCUMENTACIÓN OFICIAL — CANDELARIAAPP V1.1
## Índice General, Arquitectura, Gobernanza y Estado de Certificación

**Versión Vigente:** 1.1.0 (Consolidación Documental y Cierre Integral V1.1)  
**Directiva de Cierre Vigente:** `DT-V11-CIERRE-001`  
**Baseline Técnico Homologado V1.1:** [`e513fdd`](https://github.com/orlogonzales/candelariaAPP/commit/e513fdd)  
**Referencia Histórica V1.0:** `DT-V1-CIERRE-001` (Baseline técnico [`f3ddb21`](https://github.com/orlogonzales/candelariaAPP/commit/f3ddb21) / Documental [`1f42885`](https://github.com/orlogonzales/candelariaAPP/commit/1f42885))  
**Calificación Oficial:** **CERRADO INTEGRALMENTE EN ALCANCE V1.1 — SUJETO A ACTIVACIÓN INDIVIDUAL DE PROVEEDORES EXTERNOS Y CERTIFICACIÓN PREVIA A DESPLIEGUE**

---

> [!IMPORTANT] Declaración Expresa sobre Alcance, Limitaciones y Estado de Despliegue
> La Dirección Técnica ha homologado las cinco fases evolutivas de **CandelariaAPP V1.1** (Fases 3.1 a 3.5) sobre el baseline técnico [`e513fdd`](https://github.com/orlogonzales/candelariaAPP/commit/e513fdd).
>
> **Limitaciones obligatorias vigentes:**
> 1. **Certificación contractual 1:1 pendiente para integración móvil:** Los 265 endpoints están inventariados y validados estructuralmente en OpenAPI 3.0.3 (`36/36 PASS`), pero la comprobación automatizada 1:1 de esquemas de solicitud, respuesta y error frente al comportamiento en tiempo de ejecución deberá realizarse al preparar las integraciones móviles.
> 2. **Pasarelas de pago y WhatsApp Cloud API sin habilitación productiva:** Culqi, Niubiz, Stripe, Mercado Pago y Meta WhatsApp Cloud API continúan en modo sandbox/simulación local.
> 3. **Recibos internos no tributarios:** Los recibos de caja, contratos y cotizaciones emitidos por el sistema son comprobantes administrativos internos y **no constituyen comprobantes tributarios SUNAT**.
> 4. **Aplicación móvil nativa fuera del alcance implementado:** Se entrega el validador web/PWA offline y la especificación Headless OpenAPI 3.0.3; la aplicación móvil nativa permanece fuera del alcance de V1.1.
> 5. **Despliegue productivo sujeto a autorización independiente:** El despliegue efectivo en el servidor de producción definitivo requiere autorización expresa e independiente de Dirección Técnica.

---

## 1. Estructura de la Documentación Oficial

```text
/docs/
├── README.md                              # Índice general y estado consolidado V1.0 / V1.1 (este archivo)
├── manuales/
│   └── manual_administrador_servidor.md   # Manual de instalación, configuración, DRP y operación (V1.0 / V1.1)
├── gobernanza/
│   └── reglas_gobernanza.md               # Mandatos, roles, protocolo de cambios, fases V1.1 y limitaciones
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
│   ├── openapi.json                       # Especificación canónica OpenAPI 3.0.3 (JSON - 265 rutas)
│   └── openapi.yaml                       # Especificación canónica OpenAPI 3.0.3 (YAML - 265 rutas)
├── base-datos/
│   └── modelo_datos.md                    # Modelo SaaS-ready, evolución V1.0 (97 tablas) a V1.1 (108 tablas)
└── modulos/
    └── mapa_modulos.md                    # Catálogo canónico de los 24 dominios funcionales (115 permisos RBAC en V1.1)
```

---

## 2. Indicadores Oficiales Comparados (Histórico V1.0 vs Vigente V1.1)

| Indicador | Referencia Histórica V1.0 (`DT-V1-CIERRE-001`) | Estado Vigente V1.1 (`DT-V11-CIERRE-001`) | Detalle de Verificación V1.1 |
| :--- | :---: | :---: | :--- |
| **Tablas Relacionales** | 97 | **108** | Motor InnoDB, claves foráneas y blindaje incondicional de `app_candelaria` |
| **Migraciones Versionadas** | 23 | **29** | Numeradas cronológicamente (`000001` a `000029`) en `/base_datos/migraciones/` |
| **Permisos RBAC** | 104 | **115** | Gobernados bajo matriz de capacidades y autorización en backend |
| **Módulos Oficiales** | 24 | **24** | Catálogo completo registrado en tabla `modulos` |
| **Suites Automatizadas** | 40 | **48** | Ejecución determinista sin omisiones silenciosas (`[SKIP]` erradicados) |
| **Pruebas Aprobadas** | 2,019 / 2,019 PASS | **2,342 / 2,342 PASS** | 48 suites ejecutadas con código `0` (`EXIT_SUCCESS` en log persistido) |
| **Compuertas Evolutivas** | — (25 gates V1.0) | **19 / 19 PASS** | Suite `pruebas/ratificacion_v11.php` (instalación limpia 1:1, DRP y seguridad) |
| **Rutas Documentadas OpenAPI** | — | **265 rutas** | Especificación OpenAPI 3.0.3 en 13 dominios (`62 $ref` resueltos, `0` rotos) |
| **Huella Criptográfica** | `80e6af84e02e89e3` | **`80e6af84e02e89e3`** | Huella inmutable de identidad de Orlando (ID 24) preservada |

---

## 3. Trazabilidad Oficial de Fases Homologadas en CandelariaAPP V1.1

| Fase / Directiva | Micro-Baseline Homologado | Decisión de Cierre y Alcance Homologado |
| :--- | :---: | :--- |
| **Fase 3.1** (`DT-V11-003`, `004`, `006`, `008`) | `4edcfbf` | **HOMOLOGADA:** Catálogo de danzas y conjuntos, padrón anual por edición, programación oficial versionada con principio de cuatro ojos, control de concurrencia (`version_bloqueo`), seguimiento operativo y cobertura audiovisual (106/106 pruebas PASS). |
| **Fase 3.2** (`DT-V11-009`) | `63b4e66` | **HOMOLOGADA:** Motor de eventos de negocio, reglas configurables de WhatsApp, validación de consentimiento diferenciado, idempotencia criptográfica, bandeja de salida y simulador local (47/47 pruebas PASS). |
| **Fase 3.3** (`DT-V11-010` a `DT-V11-013`) | `bba6d38` | **HOMOLOGADA:** Operación móvil web/PWA y validación QR sin conexión con manifiesto firmado HMAC-SHA256, estados provisionales locales, conciliación por lotes (`servidor_prevalece`) y blindaje incondicional de `app_candelaria` (36/36 pruebas funcionales + 18/18 blindaje BD + 15/15 seguridad QR PASS). |
| **Fase 3.4** (`DT-V11-014`, `DT-V11-015`) | `909940f` / `5c84ca7` | **HOMOLOGADA:** Portal del cliente con autenticación passwordless blindada (expiración, rate-limit, bloqueo brute-force y single-use atómico), aislamiento anti-IDOR, recibos internos, selección fotográfica persistente y 5 compuertas simultáneas para descarga de originales (58/58 funcional + 40/40 seguridad PASS). |
| **Fase 3.5** (`DT-V11-016`) | `e513fdd` | **HOMOLOGADA:** Inventario de 265 rutas REST en 13 dominios, especificación canónica OpenAPI 3.0.3 (JSON y YAML), visualizador Swagger UI en `/documentacion` y `/docs`, y regularización determinista de F2.10 sin omisiones condicionales (36/36 F3.5 + 59/59 F2.10 PASS). |

---

## 4. Trazabilidad Histórica del Ciclo Base V1.0 (Referencia Conservada)

| Fase / Directiva V1.0 | Micro-Baseline | Hitos y Logros Acreditados en V1.0 |
| :--- | :---: | :--- |
| **F2.12A** | `45b6e05` | Asistente de cierre de edición, acta con hash SHA-256 y reapertura auditada. |
| **F2.12B** | `620c83e` | Reportes oficiales exportables (Ingresos, Manifiesto, Asistencia) en CSV, XLSX y PDF. |
| **F2.12B-1 / F2.12C** | `f158f44` | Saneamiento de auditoría, autorización cuatro ojos y Dashboard Ejecutivo con KPIs persistidos. |
| **F2.12D** | `5554192` | Suite integral de auditoría de alcance y seguridad transversal. |
| **DT-F212D-002** | `28d6af7` | Ratificación probatoria en primer plano, log de regresión persistente y blindaje de 25 gates (2,019 PASS). |
| **DT-V1-DESPLIEGUE-001** | `f3ddb21` | Verificación operativa completa: DRP con muestra real (BD + PDFs + Multimedia con hashes 1:1), workers y sandbox. |
| **DT-V1-CIERRE-001** | `1f42885` | Consolidación documental, congelamiento formal de V1.0 (97 tablas, 23 migraciones, 104 permisos) y manual del administrador. |

---

## 5. Restricciones Operativas y Condiciones para V1.2 / Producción

1. **Certificación Contractual 1:1 Previa a Integración Móvil:**
   - Antes de conectar clientes móviles nativos, deberá ejecutarse una suite de pruebas de contrato automatizadas sobre los 265 endpoints para verificar la correspondencia 1:1 de solicitudes, respuestas y errores frente al comportamiento en tiempo de ejecución.
2. **Catálogo de Pasarelas en Sandbox:**
   - Culqi, Niubiz, Stripe y Mercado Pago operan con credenciales de prueba y simuladores locales. Queda pendiente la certificación en vivo de eventos concurrentes y webhooks fuera de orden con proveedores reales.
3. **Meta WhatsApp Cloud API en Simulación:**
   - La mensajería saliente y el motor de automatizaciones operan mediante el adaptador `SIMULADOR_SANDBOX`. La puesta en producción requiere validación de firma del webhook real y plantillas HSM aprobadas en WABA.
4. **Carácter No Tributario de Documentos Internos:**
   - Los recibos de caja emitidos (`REC-YYYY-NNNNNN`) incluyen leyenda explícita de documento interno no tributario; no sustituyen comprobantes electrónicos SUNAT.
5. **Congelamiento Funcional V1.1:**
   - El alcance funcional de V1.1 queda congelado. Ni el backlog de V1.2 (analítica multianual, apps móviles nativas) ni el despliegue productivo pueden iniciarse sin autorización independiente de Dirección Técnica.

