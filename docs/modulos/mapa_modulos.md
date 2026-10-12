# MAPA DE MÓDULOS FUNCIONALES (24 DOMINIOS OFICIALES) — CANDELARIAAPP V1

**Versión:** 1.0.0 (Cierre y Congelamiento V1)  
**Estado:** Catálogo Canónico Consolidado  
**Total de Módulos:** 24 Módulos Oficiales  
**Total de Permisos RBAC:** 104 Capacidades Granulares  
**Total de Tablas Relacionales:** 97 Tablas en InnoDB  

---

## 1. Catálogo Oficial de los 24 Módulos

| Nº | Módulo | Código | Responsabilidad Principal | Permisos RBAC | Tablas Clave |
|:---:|---|---|---|:---:|---|
| **01** | **Dashboard** | `dashboard` | Estadísticas en tiempo real, embudo de ventas, estado de cobros, coberturas y KPI ejecutivos persistidos. | 4 | `reportes_indicadores_cache` |
| **02** | **Pre-Candelaria / CRM** | `crm_prospectos` | Preagenda anual de prospectos: NUEVO, CONTACTADO, INTERESADO, COTIZADO, CONFIRMADO, CONVERTIDO, DESCARTADO. | 5 | `crm_prospectos`, `crm_interacciones` |
| **03** | **Ediciones Candelaria** | `ediciones` | Ciclo histórico anual: Preoperación, Operación, Postproducción y Cierre formal con acta e integridad SHA-256. | 6 | `ediciones_candelaria`, `edicion_cierres_historial` |
| **04** | **Catálogo Comercial** | `catalogo` | Paquetes, prestaciones, condiciones contractuales, ofertas y tarifas asociadas por edición. | 7 | `paquetes`, `items_comerciales`, `tarifas_item_edicion` |
| **05** | **Clientes** | `clientes` | Padrón unificado. WhatsApp como contacto obligatorio; separación de consentimientos (Tratamiento Datos vs Marketing). | 4 | `clientes`, `personas`, `cliente_consentimientos` |
| **06** | **Reservas y Ventas (Base)** | `ventas_reservas` | Dominio fundacional de vínculos contractuales y comerciales. | 4 | `ventas`, `reservas` |
| **07** | **Programación Folclórica** | `programacion_folclorica` | Conjuntos, bloques, orden de presentación, paradas, veneración y coreografías. | 3 | `conjuntos_folcloricos`, `bloques_conjunto` |
| **08** | **Agenda** | `agenda` | Citas previas, reuniones presenciales en Puno y puntos de encuentro logístico. | 3 | `agenda_eventos`, `agenda_participantes` |
| **09** | **Operaciones y Cobertura** | `operaciones_cobertura` | Asignación de fotógrafos/videógrafos en campo, puntos de veneración y control de cobertura. | 4 | `operacion_recursos`, `operacion_asignaciones` |
| **10** | **Identificación y Activos** | `identificacion_activos` | Pines, credenciales, pulseras o dispositivos electrónicos. Retornables vs no retornables. Trazabilidad multianual. | 4 | `activos_identificacion`, `activo_movimientos` |
| **11** | **Pagos y Caja** | `pagos_caja` | Catálogo de pasarelas, comisiones financieras, desglose de 4 importes, control de caja chica y libro mayor inmutable. | 8 | `pagos`, `recibos_caja`, `cuentas_bancarias_organizacion` |
| **12** | **Comunicaciones** | `comunicaciones` | Centro de mensajería individual trazable vía WhatsApp, segmentación lógica, outbox resiliente y plantillas Meta. | 6 | `comunicacion_mensajes`, `comunicacion_plantillas` |
| **13** | **Documentos e Impresión** | `documentos` | Emisión institucional de cotizaciones, contratos, recibos internos de caja y boletos imprimibles con QR de verificación. | 5 | `documentos_emitidos`, `documento_plantillas` |
| **14** | **Reseñas** | `resenas` | Solicitud, recepción, moderación y publicación de testimonios y valoraciones de clientes. | 3 | `resenas_clientes` |
| **15** | **Reportes Oficiales** | `reportes` | Inteligencia de negocio, balances financieros, padrón de asistencia y manifiesto de salidas con exportación CSV/XLSX/PDF. | 7 | `reportes_configuracion`, `edicion_cierres_historial` |
| **16** | **Administración** | `administracion` | Gestión de usuarios del tenant, asignación de roles, permisos granulares y bitácora inmutable de auditoría. | 6 | `usuarios`, `roles`, `permisos`, `auditoria_operaciones` |
| **17** | **Configuración Organización** | `organizacion_config` | Identidad del tenant: marca corporativa, logos, membretes para PDFs y parámetros operativos. | 4 | `organizaciones`, `configuraciones_organizacion` |
| **18** | **Administración Plataforma**| `plataforma_admin` | Ámbito exclusivo del superadministrador (Orlando/Plataforma) para control global del sistema. | 4 | `plataforma_ajustes`, `tenants_suscripciones` |
| **19** | **Plataforma / SaaS** | `saas_evolucion` | Gestión de suscripciones, límites de tenants y evolución comercial multiempresa futura. | 3 | `planes`, `modulos` |
| **20** | **Cotizaciones** | `cotizaciones` | Propuestas económicas formales, versiones de cotización, expiración soberana y conversión a venta. | 5 | `cotizaciones`, `cotizacion_lineas`, `cotizacion_versiones` |
| **21** | **Ventas** | `ventas` | Libro mayor comercial, control de saldos, estados financieros y bloqueo atómico contra sobreventa. | 5 | `ventas`, `venta_lineas` |
| **22** | **Reservas** | `reservas` | Compromisos de servicio, programación de turnos, participantes y reprogramaciones. | 5 | `reservas`, `reserva_participantes` |
| **23** | **Operaciones y Salidas** | `operaciones` | Salidas de campo, manifiestos de embarque, asignación de guías, transporte y check-in con código QR. | 5 | `operacion_salidas`, `salida_manifiesto_pasajeros` |
| **24** | **Postproducción Multimedia** | `postproduccion` | Proyectos audiovisuales, álbumes de fotos y video, control de descargas seguras y portal de recuerdos. | 6 | `postproduccion_proyectos`, `postproduccion_galerias`, `postproduccion_archivos` |

**Total RBAC:** **104 Permisos Activos y Auditados**
