# DOCUMENTACIÓN OFICIAL — CANDELARIAAPP V1
## Índice General, Arquitectura, Gobernanza y Estado de Certificación

**Versión:** 1.0.0 (Cierre y Congelamiento de Desarrollo Funcional V1)  
**Directiva de Cierre:** `DT-V1-CIERRE-001`  
**Baseline Final de V1:** [`f3ddb21`](https://github.com/orlogonzales/candelariaAPP/commit/f3ddb21)  
**Calificación Oficial:** **APTO CON RESTRICCIONES — SUJETO A ACTIVACIÓN INDIVIDUAL DE PROVEEDORES EXTERNOS**

---

> [!IMPORTANT] Declaración Expresa sobre el Estado de Despliegue
> La Dirección Técnica ha dado por **culminado el ciclo de construcción y certificación local de CandelariaAPP V1**.
> 
> **El despliegue efectivo en el servidor de producción definitivo todavía NO ha sido certificado ni auditado en vivo.**
> La presencia de directivas `.htaccess` y plantillas `.env` en el repositorio acredita la preparación técnica del proyecto, pero no sustituye la verificación de configuración, certificados TLS y pruebas de humo que deben realizarse durante la fase formal de despliegue sobre la infraestructura final.

---

## 1. Estructura de la Documentación Oficial

```text
/docs/
├── README.md                              # Índice general y estado del proyecto (este archivo)
├── manuales/
│   └── manual_administrador_servidor.md   # Manual de instalación, configuración, DRP y operación
├── gobernanza/
│   └── reglas_gobernanza.md               # Mandatos, roles, protocolo de cambios y gates
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
│   └── estrategia_api.md                  # Principios API-first, envoltura JSON y contratos
├── base-datos/
│   └── modelo_datos.md                    # Modelo SaaS-ready, ciclo de ediciones y DDL (97 tablas)
└── modulos/
    └── mapa_modulos.md                    # Catálogo canónico de los 24 dominios funcionales (104 permisos RBAC)
```

---

## 2. Resumen Métrico de CandelariaAPP V1

| Dimensión | Métrica Acreditada | Detalle de Verificación |
|---|:---:|---|
| **Módulos Oficiales** | **24** | Catálogo completo registrado en tabla `modulos` |
| **Permisos RBAC Granulares** | **104** | Gobernados bajo matriz de capacidades y autorización en backend |
| **Tablas Relacionales** | **97** | Motor InnoDB, claves foráneas `ON DELETE RESTRICT` y auditoría |
| **Migraciones Versionadas** | **23** | Numeradas cronológicamente en `/base_datos/migraciones/` |
| **Regresión Global** | **2,019 / 2,019 PASS** | 40 suites ejecutadas con código de salida `0` (log persistido) |
| **Gates de Ratificación** | **25 / 25 PASS** | Suite probatoria de instalación limpia, esquema y seguridad |
| **Verificación Operativa** | **28 / 28 PASS** | Suite DRP transaccional, DRP de archivos, workers y sandbox |
| **Huella Criptográfica** | `80e6af84e02e89e3` | Huella inmutable de identidad de Orlando (ID 24) |

---

## 3. Trazabilidad de Micro-Baselines del Ciclo de Cierre V1

| Fase / Directiva | Micro-Baseline | Hitos y Logros Acreditados |
|---|:---:|---|
| **F2.12A** | `45b6e05` | Asistente de cierre de edición, acta con hash SHA-256 y reapertura auditada. |
| **F2.12B** | `620c83e` | Reportes oficiales exportables (Ingresos, Manifiesto, Asistencia) en CSV, XLSX y PDF. |
| **F2.12B-1 / F2.12C** | `f158f44` | Saneamiento de auditoría, autorización cuatro ojos y Dashboard Ejecutivo con KPIs persistidos. |
| **F2.12D** | `5554192` | Suite integral de auditoría de alcance y seguridad transversal. |
| **DT-F212D-002** | `28d6af7` | Ratificación probatoria en primer plano, log de regresión persistente y blindaje de 25 gates. |
| **DT-V1-DESPLIEGUE-001** | `f3ddb21` | Verificación operativa completa: DRP con muestra real (BD + PDFs + Multimedia con hashes 1:1), workers y sandbox. |
| **DT-V1-CIERRE-001** | `f3ddb21` | Consolidación documental, congelamiento formal de V1 y manual del administrador. |

---

## 4. Restricciones Operativas Vigentes

1. **Catálogo de Pasarelas en Sandbox:**
   - Culqi, Niubiz, Stripe y Mercado Pago se encuentran integradas bajo contratos de interfaz limpios, pero **con credenciales de prueba y simuladores**.
   - No deben habilitarse cobros comerciales reales sin la autorización expresa de Dirección Técnica y el canje formal de llaves de producción.
2. **Meta WhatsApp Cloud API en Simulación:**
   - La mensajería saliente opera mediante el adaptador `SIMULADOR_SANDBOX`.
   - La activación de la línea oficial y plantillas en vivo requiere completar la verificación de cuenta WABA y registro de tokens de sistema.
3. **Congelamiento de Alcance Funcional:**
   - El alcance funcional de V1 queda estrictamente congelado. No se permite la apertura de nuevas fases, módulos o extensiones funcionales sin autorización de Dirección Técnica.
