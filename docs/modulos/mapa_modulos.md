# MAPA DE MÓDULOS FUNCIONALES (19 DOMINIOS) — CANDELARIAAPP

En Fase 0 se establecen los límites arquitectónicos de los **19 módulos oficiales** de CandelariaAPP. Ninguno de estos módulos funcionales se desarrolla en Fase 0; aquí se documenta su alcance, responsabilidades y relaciones:

| Nº | Módulo | Código | Responsabilidad Principal |
|---|---|---|---|
| **01** | **Dashboard** | `dashboard` | Estadísticas en tiempo real, embudo de prospectos, estado de cobros, coberturas y pendientes. |
| **02** | **Pre-Candelaria / CRM** | `crm_prospectos` | Preagenda anual de prospectos: NUEVO, CONTACTADO, INTERESADO, COTIZADO, CONFIRMADO, CONVERTIDO, DESCARTADO. |
| **03** | **Ediciones Candelaria** | `ediciones` | Ciclo histórico anual: Candelaria 2027, 2028. Fases: Preoperación, Operación, Postproducción y Cerrada. |
| **04** | **Catálogo Comercial** | `catalogo` | Paquetes, prestaciones, condiciones contractuales y tarifas asociadas por edición. |
| **05** | **Clientes** | `clientes` | Padrón unificado. WhatsApp como contacto obligatorio y principal; email opcional. Separación de consentimientos. |
| **06** | **Reservas y Ventas** | `ventas_reservas` | Contratos, reservas de cobertura y vinculación de clientes a paquetes específicos. |
| **07** | **Programación Folclórica** | `programacion_folclorica` | Conjuntos, bloques, orden de presentación, paradas, veneración y coreografías. |
| **08** | **Agenda** | `agenda` | Citas previas, reuniones presenciales en Puno y puntos de encuentro logístico. |
| **09** | **Operaciones y Cobertura** | `operaciones_cobertura` | Asignación de fotógrafos/videógrafos en campo, puntos de veneración y control de cobertura. |
| **10** | **Identificación y Activos** | `identificacion_activos` | Pines, credenciales, pulseras o dispositivos electrónicos. Retornables vs no retornables. Trazabilidad multianual por tenant. |
| **11** | **Pagos y Caja** | `pagos_caja` | Catálogo de pasarelas, comisiones configurables, monto mínimo global, saldos y control de caja chica. |
| **12** | **Comunicaciones** | `comunicaciones` | Centro de mensajería individual trazable vía WhatsApp, segmentación lógica (ej. por conjunto) y plantillas. Cero spam. |
| **13** | **Postproducción y Entregas** | `postproduccion_entregas` | Flujo de edición, galerías fotográficas, URLs de descarga, entregas parciales y finales. |
| **14** | **Reseñas** | `resenas` | Solicitud, recepción, moderación y publicación de testimonios y valoraciones de clientes. |
| **15** | **Reportes** | `reportes` | Inteligencia de negocio, balances financieros, rendimiento de cobertura y métricas anuales. |
| **16** | **Administración** | `administracion` | Gestión de usuarios del tenant, asignación de roles y permisos granulares. |
| **17** | **Configuración de Organización** | `organizacion_config` | Identidad del tenant: marca corporativa, logos, membretes para PDFs y parámetros operativos. |
| **18** | **Administración de Plataforma** | `plataforma_admin` | Ámbito exclusivo del superadministrador (Orlando/Plataforma) para control global del sistema. |
| **19** | **Plataforma / SaaS Evolución** | `saas_evolucion` | Gestión de suscripciones, límites de tenants y evolución comercial multiempresa futura. |
