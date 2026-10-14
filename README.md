# CANDELARIAAPP V1.2
### Plataforma de Gestión Operativa, Comercial, Folclórica y Audiovisual para la Festividad Virgen de la Candelaria

[![Versión](https://img.shields.io/badge/versi%C3%B3n-1.2.0--desarrollo%2Fv1.2-blue.svg)](https://github.com/orlogonzales/candelariaAPP)
[![Regresión Global V1.2](https://img.shields.io/badge/regresi%C3%B3n%20V1.2-2%2C397%2F2%2C397%20PASS%20(51%20suites)-brightgreen.svg)](pruebas/registro_regresion_global.log)
[![Compuertas Evolutivas](https://img.shields.io/badge/ratificaci%C3%B3n%2019%2F19-PASS-success.svg)](pruebas/ratificacion_v11.php)
[![OpenAPI 3.0.3 Certificada 1:1](https://img.shields.io/badge/OpenAPI%203.0.3-269%2F269%20CERTIFICADOS%201%3A1-informational.svg)](pruebas/matriz_certificacion_contratos_v12.json)
[![Estado](https://img.shields.io/badge/dictamen-V1.2%20COMPLETADO%20EN%20RAMA%20AISLADA-orange.svg)](docs/README.md)

---

## 📌 Estado del Proyecto: V1.2 IMPLEMENTADO EN RAMA AISLADA (`desarrollo/v1.2`)

**CandelariaAPP V1.2** ha completado su ciclo oficial de implementación (Fases 4.1 a 4.4) bajo la directiva **`DT-V12-002`** en la rama independiente **`desarrollo/v1.2`**, preservando intacta la rama **`main`** en el baseline documental de cierre V1.1 [`bc97361`](https://github.com/orlogonzales/candelariaAPP/commit/bc97361) (y su baseline técnico [`e513fdd`](https://github.com/orlogonzales/candelariaAPP/commit/e513fdd), así como la referencia histórica V1.0 [`f3ddb21`](https://github.com/orlogonzales/candelariaAPP/commit/f3ddb21) / [`1f42885`](https://github.com/orlogonzales/candelariaAPP/commit/1f42885)).

El alcance de **V1.2** ejecuta las seis decisiones vinculantes de Dirección Técnica (`DEC-01` a `DEC-06`):
1. **Analítica Multianual e Inteligencia Histórica de Ediciones (`DEC-01` - Opción 1A):** Comparativa interanual de KPIs financieros, comerciales, operativos, folclóricos y de postproducción sobre el esquema relacional existente (**108 tablas**, **29 migraciones**, **115 permisos RBAC**), con aislamiento multi-tenant estricto y exportación multianual CSV/XLSX/PDF.
2. **Certificación Contractual API 1:1 en Tiempo de Ejecución (`DEC-02` - Opción 2A):** Verificación automatizada bidireccional 1:1 entre el enrutador real (`rutas/api.php`) y la especificación OpenAPI 3.0.3 (`docs/api/openapi.json` y `docs/api/openapi.yaml`), alcanzando **269/269 endpoints certificados (`0` observados)**.
3. **Blindaje de Webhooks de Pasarelas de Pago y WhatsApp Cloud API (`DEC-03` - Opción 3A):** Validación criptográfica *fail-closed* de firmas (`HMAC-SHA256` / `X-Hub-Signature-256`) y ventanas de tiempo, bloqueo pesimista transaccional (`SELECT ... FOR UPDATE`), idempotencia estricta e invariante monotónica ante eventos fuera de orden o duplicados.

> [!WARNING] Limitaciones Obligatorias y Restricciones Vigentes
> 1. **Aislamiento de rama (`DEC-06`):** Toda la evolución V1.2 reside en `desarrollo/v1.2`. La fusión hacia `main` requiere homologación y autorización expresa posterior de Dirección Técnica.
> 2. **Pasarelas de pago y WhatsApp Cloud API sin credenciales productivas activas:** Aunque el blindaje criptográfico, transaccional y de máquina de estados está certificado (`14/14 PASS`), los conectores continúan operando en **MODO SANDBOX / SIMULADOR LOCAL** hasta la activación individual de proveedores externos en servidor productivo.
> 3. **Carácter no tributario de recibos internos:** Los recibos de caja, contratos y cotizaciones emitidos por el sistema son documentos administrativos internos; **no constituyen comprobantes tributarios electrónicos SUNAT** (`DEC-05`).
> 4. **Alcance diferido a V1.3 y exclusiones (`DEC-04` / `DEC-05`):** La profundización de Agenda (Módulo 08), Activos Retornables (Módulo 10) y Reseñas (Módulo 14) queda diferida a **V1.3**. Aplicaciones móviles nativas, facturación SUNAT, facturación SaaS y login social permanecen fuera del alcance.

---

## 📊 Indicadores Oficiales del Sistema (Comparativa V1.0 vs V1.1 vs V1.2)

| Indicador | Referencia Histórica V1.0 (`DT-V1-CIERRE-001`) | Línea Base Congelada V1.1 (`DT-V11-CIERRE-001`) | Estado Vigente V1.2 (`DT-V12-002`) |
| :--- | :---: | :---: | :---: |
| **Tablas relacionales (InnoDB)** | 97 | 108 | **108** |
| **Migraciones versionadas** | 23 | 29 | **29** |
| **Permisos RBAC granulares** | 104 | 115 | **115** |
| **Módulos oficiales** | 24 | 24 | **24** |
| **Suites automatizadas** | 40 | 48 | **51** |
| **Pruebas aprobadas (Regresión Global)** | 2,019 / 2,019 PASS | 2,342 / 2,342 PASS | **2,397 / 2,397 PASS (`0 FAIL`)** |
| **Compuertas estructurales y DRP** | 25 / 25 PASS | 19 / 19 PASS | **19 / 19 PASS** |
| **Rutas REST en OpenAPI 3.0.3** | — | 265 rutas (estructural) | **269 / 269 rutas certificadas 1:1 en runtime** |
| **Baseline / Rama Oficial** | `f3ddb21` / `1f42885` (`main`) | `e513fdd` / `bc97361` (`main`) | **Rama `desarrollo/v1.2`** |

---

## 🏛️ Fases de CandelariaAPP V1.2 y V1.1

### Ciclo V1.2 (`DT-V12-002` — Rama `desarrollo/v1.2`)

| Fase | Dominio Evolutivo V1.2 | Suite / Evidencia | Resultado | Micro-Baseline |
| :---: | :--- | :--- | :---: | :---: |
| **Fase 4.1** | Analítica Multianual e Inteligencia Histórica de Ediciones (`DEC-01`) | `pruebas/ejecutar_pruebas_f41.php` | **25 / 25 PASS** | `88becaf` |
| **Fase 4.2A** | Certificación Contractual API 1:1 en Tiempo de Ejecución (`DEC-02`) | `pruebas/ejecutar_pruebas_f42_contratos.php` | **16 / 16 PASS (269/269)** | `9f73604` |
| **Fase 4.2B** | Blindaje de Webhooks de Pasarelas de Pago y WhatsApp (`DEC-03`) | `pruebas/ejecutar_pruebas_f42_webhooks.php` | **14 / 14 PASS** | `9f73604` |
| **Fase 4.3** | Estabilización Transversal, DRP y Regresión Global Completa | `pruebas/ejecutar_regresion_completa.php` | **2,397 / 2,397 PASS (51 suites)** | Cierre V1.2 |
| **Fase 4.4** | Consolidación Documental y Entrega de Cierre V1.2 | `docs/README.md`, `README.md` | **COMPLETADA** | Cierre V1.2 |

### Ciclo V1.1 Homologado (`DT-V11-CIERRE-001` — Conservado en `main`)

| Fase | Dominio Evolutivo V1.1 | Directiva de Cierre | Micro-Baseline | Estado |
| :---: | :--- | :---: | :---: | :---: |
| **Fase 3.1** | Programación Folclórica, Principio 4 Ojos, Seguimiento Operativo y Cobertura | `DT-V11-008` | `4edcfbf` | **HOMOLOGADA** |
| **Fase 3.2** | Motor de Automatizaciones y Comunicaciones WhatsApp (Simulador/Outbox) | `DT-V11-009` | `63b4e66` | **HOMOLOGADA** |
| **Fase 3.3** | Operación Móvil, Validación QR sin Conexión y Blindaje de Base de Datos | `DT-V11-013` | `bba6d38` | **HOMOLOGADA** |
| **Fase 3.4** | Portal del Cliente, Saldos, Pagos, Selección Fotográfica y 5 Compuertas | `DT-V11-015` | `909940f` / `5c84ca7` | **HOMOLOGADA** |
| **Fase 3.5** | Especificación OpenAPI 3.0.3, Arquitectura Headless y Regularización F2.10 | `DT-V11-016` | `e513fdd` | **HOMOLOGADA** |

---

## 📚 Documentación y Recursos

- [📖 Índice General de Documentación Oficial (V1.0, V1.1 y V1.2)](docs/README.md)
- [🔌 Especificación OpenAPI 3.0.3 Certificada 1:1 (YAML)](docs/api/openapi.yaml) | [Especificación OpenAPI 3.0.3 (JSON)](docs/api/openapi.json)
- [📊 Matriz de Certificación Contractual 1:1 (269/269 Endpoints)](pruebas/matriz_certificacion_contratos_v12.json)
- [🗺️ Mapa Canónico de los 24 Módulos y 115 Permisos RBAC](docs/modulos/mapa_modulos.md)
- [🛠️ Manual del Administrador del Servidor (Instalación, Configuración y DRP)](docs/manuales/manual_administrador_servidor.md)
- [⚖️ Reglas de Gobernanza del Proyecto](docs/gobernanza/reglas_gobernanza.md)
- [🗄️ Modelo de Datos y Evolución del Esquema](docs/base-datos/modelo_datos.md)
- [📋 Plantilla de Configuración de Producción](.env.produccion.ejemplo)

---

## 🧪 Verificación y Aseguramiento de Calidad

Para reproducir las suites de certificación automatizadas de **CandelariaAPP V1.2**:

```bash
# 1. Regresión global completa de V1.2 (51 suites / 2,397 pruebas aprobadas, 0 fallos)
php pruebas/ejecutar_regresion_completa.php

# 2. Suites específicas de CandelariaAPP V1.2 (Fases 4.1 y 4.2)
php pruebas/ejecutar_pruebas_f41.php
php pruebas/ejecutar_pruebas_f42_contratos.php
php pruebas/ejecutar_pruebas_f42_webhooks.php

# 3. Compuertas estructurales y DRP (19/19 PASS: esquema limpio 108 tablas, 115 permisos, DRP y huella)
php pruebas/ratificacion_v11.php
```

---

## ⚖️ Licencia y Propiedad
Desarrollado para la gestión institucional, folclórica, comercial y de coberturas audiovisuales durante la Festividad Virgen de la Candelaria.  
Todos los derechos reservados.
