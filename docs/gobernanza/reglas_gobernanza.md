# GOBERNANZA DEL PROYECTO — CANDELARIAAPP

## 1. Mandato General
CandelariaAPP V1 nace como el sistema oficial de gestión y producción audiovisual para la Festividad de la Candelaria, operado inicialmente por **O.G. Estudio Creativo**, pero arquitectónicamente concebido para:
- SaaS multiempresa / multitenant.
- Plataforma con planes y módulos licenciables.
- API consumible por web y futuras aplicaciones móviles (Android/iOS).
- Sistema escalable por ediciones anuales de Candelaria.

**Fórmula de V1:** Single-tenant en operación, SaaS-ready en arquitectura y API-first desde el inicio.

---

## 2. Roles y Autoridad
- **Propietario del Producto:** Orlando González / O.G. Estudio Creativo. Posee la autoridad de decisión sobre alcance, stack, negocio y cambios de fase.
- **Implementador Técnico (Gemini):** Ejecutor riguroso de mandatos técnicos. No tiene potestad unilateral para modificar el stack, alterar convenciones, introducir frameworks, eliminar requerimientos o avanzar de fase.

---

## 3. Protocolo Obligatorio ante Decisiones no Definidas
Frente a cualquier ambigüedad técnica, requerimiento no especificado o desviación imprevista:

$$\text{DETENER} \longrightarrow \text{DOCUMENTAR} \longrightarrow \text{PROPONER} \longrightarrow \text{ESPERAR AUTORIZACIÓN}$$

---

## 4. Regla de Veracidad
Está terminantemente prohibido declarar elementos como `PASS`, `COMPLETADO`, `SEGURO` o `FUNCIONANDO` sin comprobación empírica demostrable. Todo aspecto no evaluado directamente debe registrarse explícitamente como:

**NO VERIFICADO**

---

## 5. Control de Gates
Cada fase y microlote debe someterse a revisión formal de gate:
- **GATE 0 (Foundation & Arquitectura):** Requiere verificación de estructura, BD fundacional, gobernanza, ADRs, plantilla reutilizable, activos locales y Git limpio.
- **Security Gate por Módulo:** Obligatorio para cada futuro módulo antes de su cierre:
  ```text
  SQL Injection:        PASS/FAIL
  XSS:                  PASS/FAIL
  CSRF:                 PASS/FAIL/N/A
  Validación backend:   PASS/FAIL
  Autorización backend: PASS/FAIL
  Aislamiento tenant:   PASS/FAIL/N/A
  Datos sensibles:      PASS/FAIL
  Uploads:              PASS/FAIL/N/A
  Regresión:            PASS/FAIL
  ```
- **Ratificación Evolutiva V1.1 (`pruebas/ratificacion_v11.php`):** Evalúa 19 compuertas obligatorias (regresión global sin omisiones silenciosas, instalación limpia 1:1 de 108 tablas y 115 permisos, DRP en entorno aislado, huella inmutable `80e6af84e02e89e3` y protección de almacenamiento privado).

---

## 6. Estado Oficial de Gobernanza: Cierre Integral V1.1 (`DT-V11-CIERRE-001`)

### 6.1 Indicadores Oficiales (Comparativa V1.0 vs V1.1)

| Indicador | V1.0 (`DT-V1-CIERRE-001`) | V1.1 (`DT-V11-CIERRE-001`) |
| :--- | :---: | :---: |
| **Tablas** | 97 | **108** |
| **Migraciones** | 23 | **29** |
| **Permisos RBAC** | 104 | **115** |
| **Módulos oficiales** | 24 | **24** |
| **Suites automatizadas** | 40 | **48** |
| **Pruebas aprobadas** | 2,019 | **2,342** |
| **Compuertas evolutivas** | — (25 gates V1.0) | **19/19** |
| **Baseline técnico** | `f3ddb21` (Doc: `1f42885`) | **`e513fdd`** |

### 6.2 Fases Evolutivas V1.1 Homologadas

- **Fase 3.1 — Programación Folclórica (`4edcfbf`):** Homologada bajo `DT-V11-008`. Catálogo de danzas y conjuntos, padrón por edición, programación versionada, principio de cuatro ojos, concurrencia optimista (`version_bloqueo`), seguimiento operativo y cobertura audiovisual.
- **Fase 3.2 — Comunicaciones Automatizadas (`63b4e66`):** Homologada bajo `DT-V11-009`. Motor de eventos, reglas configurables, consentimiento diferenciado, idempotencia y bandeja de salida en modo simulación local.
- **Fase 3.3 — Operación Móvil y QR sin Conexión (`bba6d38`):** Homologada bajo `DT-V11-013`. Validación QR offline con firma HMAC-SHA256, estados provisionales locales, conciliación por lotes (`servidor_prevalece`) y blindaje incondicional de `app_candelaria` frente a saneamientos automatizados.
- **Fase 3.4 — Portal del Cliente (`909940f` / `5c84ca7`):** Homologada bajo `DT-V11-015`. Autenticación sin contraseña blindada, aislamiento anti-IDOR, consulta de saldos, recibos internos, selección fotográfica y 5 compuertas simultáneas para descarga de originales.
- **Fase 3.5 — OpenAPI 3.0.3 y Arquitectura Headless (`e513fdd`):** Homologada bajo `DT-V11-016`. Inventario y especificación de 265 rutas REST en 13 dominios (JSON/YAML), Swagger UI en `/documentacion` y `/docs`, y regularización determinista de la suite `F2.10` (`59/59 PASS`).

---

## 7. Limitaciones Obligatorias y Reserva de Autorización

1. **Certificación contractual 1:1 pendiente para integración móvil:** Aunque los 265 endpoints cuentan con validación estructural y suite contractual (`36/36 PASS`), la verificación automatizada 1:1 de que los esquemas de solicitudes, respuestas y errores coincidan con el comportamiento real en tiempo de ejecución queda pendiente para la etapa de integración móvil.
2. **Pasarelas de pago y WhatsApp Cloud API sin habilitación productiva:** Culqi, Niubiz, Stripe, Mercado Pago y Meta WhatsApp Cloud API continúan operando exclusivamente en modo sandbox/simulador local.
3. **Carácter no tributario de recibos internos:** Los recibos de caja, contratos y cotizaciones emitidos por el sistema son documentos de control interno y **no constituyen comprobantes tributarios SUNAT**.
4. **Aplicación móvil nativa fuera del alcance implementado:** El desarrollo de aplicaciones móviles nativas (Android/iOS) permanece fuera del alcance implementado en V1.1.
5. **Despliegue productivo sujeto a autorización independiente:** Ninguna puesta en producción ni apertura de desarrollos de V1.2 está autorizada sin mandato expreso e independiente de Dirección Técnica.

