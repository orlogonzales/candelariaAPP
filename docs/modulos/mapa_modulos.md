# MAPA DE MÓDULOS FUNCIONALES (24 DOMINIOS OFICIALES) — CANDELARIAAPP V1.1

**Versión Vigente:** 1.1.0 (Cierre Integral V1.1 — `DT-V11-CIERRE-001`)  
**Baseline Técnico Homologado:** `e513fdd` (Referencia histórica V1.0: `f3ddb21` / `1f42885`)  
**Estado:** Catálogo Canónico Consolidado  
**Total de Módulos Oficiales:** 24 Módulos (V1.0: 24 | V1.1: 24)  
**Total de Permisos RBAC:** **115 Capacidades Granulares** (Referencia histórica V1.0: 104 | Incremento V1.1: +11)  
**Total de Tablas Relacionales:** **108 Tablas en InnoDB** (Referencia histórica V1.0: 97 | Incremento V1.1: +11)  
**Total de Migraciones Versionadas:** **29 Migraciones** (Referencia histórica V1.0: 23 | Incremento V1.1: +6)  

---

## 1. Catálogo Oficial de los 24 Módulos y Evolución RBAC (V1.0 $\rightarrow$ V1.1)

| Nº | Módulo | Código | Responsabilidad Principal | RBAC V1.0 | RBAC V1.1 | Tablas Clave (incluye extensión V1.1) |
|:---:|---|---|---|:---:|:---:|---|
| **01** | **Dashboard** | `dashboard` | Estadísticas en tiempo real, embudo de ventas, estado de cobros, coberturas y KPI ejecutivos persistidos. | 4 | **4** | `reportes_indicadores_cache` |
| **02** | **Pre-Candelaria / CRM** | `crm_prospectos` | Preagenda anual de prospectos: NUEVO, CONTACTADO, INTERESADO, COTIZADO, CONFIRMADO, CONVERTIDO, DESCARTADO. | 5 | **5** | `crm_prospectos`, `crm_interacciones` |
| **03** | **Ediciones Candelaria** | `ediciones` | Ciclo histórico anual: Preoperación, Operación, Postproducción y Cierre formal con acta e integridad SHA-256. | 6 | **6** | `ediciones_candelaria`, `edicion_cierres_historial` |
| **04** | **Catálogo Comercial** | `catalogo` | Paquetes, prestaciones, condiciones contractuales, ofertas y tarifas asociadas por edición. | 7 | **7** | `paquetes`, `items_comerciales`, `tarifas_item_edicion` |
| **05** | **Clientes** | `clientes` | Padrón unificado. WhatsApp obligatorio; consentimientos diferenciados; acceso passwordless al portal del cliente. | 4 | **4** | `clientes`, `personas`, `cliente_consentimientos` |
| **06** | **Reservas y Ventas (Base)** | `ventas_reservas` | Dominio fundacional de vínculos contractuales y comerciales. | 4 | **4** | `ventas`, `reservas` |
| **07** | **Programación Folclórica** | `programacion_folclorica` | Tipos de danza, conjuntos, bloques, inscripciones por edición, programación versionada (4 ojos, `version_bloqueo`), seguimiento en vivo y cobertura. | 3 | **11** | `folclore_tipos_danza`, `conjuntos_folcloricos`, `bloques_conjunto`, `folclore_participaciones_edicion`, `folclore_programaciones_oficiales`, `folclore_orden_presentacion` |
| **08** | **Agenda** | `agenda` | Citas previas, reuniones presenciales en Puno y puntos de encuentro logístico. | 3 | **3** | `agenda_eventos`, `agenda_participantes` |
| **09** | **Operaciones y Cobertura** | `operaciones_cobertura` | Asignación de fotógrafos/videógrafos en campo, puntos de veneración y control de cobertura audiovisual. | 4 | **4** | `operacion_recursos`, `operacion_asignaciones` |
| **10** | **Identificación y Activos** | `identificacion_activos` | Pines, credenciales, pulseras o dispositivos electrónicos. Retornables vs no retornables. Trazabilidad multianual. | 4 | **4** | `activos_identificacion`, `activo_movimientos` |
| **11** | **Pagos y Caja** | `pagos_caja` | Catálogo de pasarelas (sandbox), comisiones, desglose de 4 importes en centavos, caja chica y libro mayor inmutable. | 8 | **8** | `pagos`, `recibos_caja`, `cuentas_bancarias_organizacion` |
| **12** | **Comunicaciones** | `comunicaciones` | Centro de mensajería WhatsApp (sandbox), outbox resiliente, plantillas y motor de reglas de automatización por eventos con idempotencia. | 6 | **7** | `comunicacion_mensajes`, `comunicacion_plantillas`, `whatsapp_reglas_automatizacion`, `whatsapp_automatizacion_eventos` |
| **13** | **Documentos e Impresión** | `documentos` | Emisión institucional de cotizaciones, contratos, recibos internos no tributarios y boletos A4/Ticket 80mm con QR HMAC-SHA256. | 5 | **5** | `documentos_emitidos`, `documento_plantillas`, `contratos_venta` |
| **14** | **Reseñas** | `resenas` | Solicitud, recepción, moderación y publicación de testimonios y valoraciones de clientes. | 3 | **3** | `resenas_clientes` |
| **15** | **Reportes Oficiales** | `reportes` | Inteligencia de negocio por edición, balances financieros, padrón de asistencia y manifiesto con exportación CSV/XLSX/PDF. | 7 | **7** | `reportes_configuracion`, `edicion_cierres_historial` |
| **16** | **Administración** | `administracion` | Gestión de usuarios del tenant, asignación de roles, permisos granulares y bitácora inmutable de auditoría. | 6 | **6** | `usuarios`, `roles`, `permisos`, `auditoria_operaciones` |
| **17** | **Configuración Organización** | `organizacion_config` | Identidad del tenant: marca corporativa, logos, membretes para PDFs y parámetros operativos. | 4 | **4** | `organizaciones`, `configuraciones_organizacion` |
| **18** | **Administración Plataforma**| `plataforma_admin` | Ámbito exclusivo del superadministrador (Orlando/Plataforma) para control global del sistema. | 4 | **4** | `plataforma_ajustes`, `tenants_suscripciones` |
| **19** | **Plataforma / SaaS** | `saas_evolucion` | Gestión de suscripciones, límites de tenants y evolución comercial multiempresa futura. | 3 | **3** | `planes`, `modulos` |
| **20** | **Cotizaciones** | `cotizaciones` | Propuestas económicas formales, versiones de cotización, expiración soberana y conversión a venta. | 5 | **5** | `cotizaciones`, `cotizacion_lineas`, `cotizacion_versiones` |
| **21** | **Ventas** | `ventas` | Libro mayor comercial, control de saldos, estados financieros y bloqueo atómico contra sobreventa. | 5 | **5** | `ventas`, `venta_lineas` |
| **22** | **Reservas** | `reservas` | Compromisos de servicio, programación de turnos, participantes y reprogramaciones. | 5 | **5** | `reservas`, `reserva_participantes` |
| **23** | **Operaciones y Salidas** | `operaciones` | Salidas de campo, manifiestos, check-in QR online y validación móvil QR sin conexión con conciliación por lotes (`servidor_prevalece`). | 5 | **6** | `operacion_salidas`, `salida_manifiesto_pasajeros`, `operacion_offline_lotes`, `operacion_offline_eventos` |
| **24** | **Postproducción Multimedia** | `postproduccion` | Proyectos audiovisuales, galerías, selección fotográfica persistente del cliente y descarga de originales bajo 5 compuertas. | 6 | **7** | `postproduccion_proyectos`, `postproduccion_galerias`, `postproduccion_archivos`, `postproduccion_selecciones` |

**Totales RBAC:** **104 Permisos en V1.0 $\rightarrow$ 115 Permisos Activos y Auditados en V1.1**

---

## 2. Detalle de los 11 Permisos RBAC Incorporados en CandelariaAPP V1.1

| Fase | Migración | Módulo | Código de Permiso | Descripción |
| :---: | :---: | :--- | :--- | :--- |
| **Fase 3.1** | `000024` | `07 - programacion_folclorica` | `folclore.tipos_danza.ver` | Consultar catálogo de tipos y géneros folclóricos |
| **Fase 3.1** | `000024` | `07 - programacion_folclorica` | `folclore.tipos_danza.gestionar` | Crear y editar tipos de danza folclórica |
| **Fase 3.1** | `000024` | `07 - programacion_folclorica` | `folclore.conjuntos.ver` | Consultar agrupaciones y comparsas registradas |
| **Fase 3.1** | `000024` | `07 - programacion_folclorica` | `folclore.conjuntos.gestionar` | Crear y modificar conjuntos y sus filiales/bloques |
| **Fase 3.1** | `000024` | `07 - programacion_folclorica` | `folclore.inscripciones.gestionar` | Registrar participación de conjuntos en una edición anual |
| **Fase 3.1** | `000024` | `07 - programacion_folclorica` | `folclore.programacion.ver` | Consultar rol y orden de presentación de la festividad |
| **Fase 3.1** | `000024` | `07 - programacion_folclorica` | `folclore.programacion.gestionar` | Registrar versiones y turnos de presentación folclórica |
| **Fase 3.1** | `000024` | `07 - programacion_folclorica` | `folclore.programacion.aprobar` | Oficializar y activar versiones del rol (principio 4 ojos) |
| **Fase 3.2** | `000027` | `12 - comunicaciones` | `comunicaciones.automatizaciones.gestionar` | Crear, configurar y simular disparadores automáticos WhatsApp |
| **Fase 3.3** | `000028` | `23 - operaciones` | `operacion.offline.validar` | Acceder al validador QR móvil offline y sincronizar lotes |
| **Fase 3.4** | `000029` | `24 - postproduccion` | `postproduccion.selecciones.ver` | Consultar selecciones fotográficas realizadas por clientes en el portal |

