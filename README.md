# CANDELARIAAPP V1.1
### Plataforma de Gestión Operativa, Comercial, Folclórica y Audiovisual para la Festividad Virgen de la Candelaria

[![Versión](https://img.shields.io/badge/versi%C3%B3n-1.1.0--v1.1.frozen-blue.svg)](https://github.com/orlogonzales/candelariaAPP)
[![Regresión Global V1.1](https://img.shields.io/badge/regresi%C3%B3n%20V1.1-2%2C342%2F2%2C342%20PASS-brightgreen.svg)](pruebas/registro_regresion_global.log)
[![Compuertas Evolutivas V1.1](https://img.shields.io/badge/ratificaci%C3%B3n%20V1.1-19%2F19%20PASS-success.svg)](pruebas/ratificacion_v11.php)
[![OpenAPI 3.0.3](https://img.shields.io/badge/OpenAPI%203.0.3-265%20rutas-informational.svg)](docs/api/openapi.yaml)
[![Estado](https://img.shields.io/badge/dictamen-CERRADO%20CON%20RESTRICCIONES-orange.svg)](docs/README.md)

---

## 📌 Estado del Proyecto: V1.1 CONSOLIDADO Y CONGELADO

**CandelariaAPP V1.1** ha culminado formalmente su **ciclo de evolución funcional (Fases 3.1 a 3.5) y consolidación documental** bajo la directiva **`DT-V11-CIERRE-001`**, tomando como baseline técnico homologado el commit [`e513fdd`](https://github.com/orlogonzales/candelariaAPP/commit/e513fdd) (y conservando [`f3ddb21`](https://github.com/orlogonzales/candelariaAPP/commit/f3ddb21) / [`1f42885`](https://github.com/orlogonzales/candelariaAPP/commit/1f42885) como referencia histórica del cierre V1.0).

El alcance funcional de V1.1 se encuentra **estrictamente congelado**. No se autorizan desarrollos de V1.2 ni despliegue productivo sin directiva independiente de Dirección Técnica.

> [!WARNING] Limitaciones Obligatorias y Restricciones Vigentes
> 1. **Certificación contractual 1:1 pendiente para integración móvil:** La especificación OpenAPI 3.0.3 documenta las 265 rutas en 13 dominios y supera las validaciones estructurales (`36/36 PASS`), pero la verificación automatizada 1:1 de esquemas de solicitud, respuesta y error frente al comportamiento real en tiempo de ejecución queda pendiente como requisito previo para las integraciones móviles.
> 2. **Pasarelas de pago y WhatsApp Cloud API sin habilitación productiva:** Culqi, Niubiz, Stripe, Mercado Pago y Meta WhatsApp Cloud API continúan operando exclusivamente en **MODO SANDBOX / SIMULADOR LOCAL**.
> 3. **Carácter no tributario de recibos internos:** Los recibos de caja, contratos y cotizaciones emitidos por el sistema son documentos de control administrativo y contractual interno; **no constituyen comprobantes tributarios electrónicos SUNAT**.
> 4. **Aplicación móvil nativa fuera del alcance implementado:** V1.1 entrega la operación móvil web/PWA offline y la arquitectura Headless OpenAPI 3.0.3; el desarrollo de aplicaciones móviles nativas (Android/iOS) permanece fuera del alcance implementado.
> 5. **Despliegue productivo sujeto a autorización independiente:** La certificación actual corresponde al entorno local controlado (`PHP 8.3`, MySQL 8.0+). El despliegue en el servidor de producción definitivo requiere autorización y auditoría en vivo independiente.

---

## 📊 Indicadores Oficiales del Sistema (Comparativa Histórica V1.0 vs Vigente V1.1)

| Indicador | Referencia Histórica V1.0 (`DT-V1-CIERRE-001`) | Estado Vigente V1.1 (`DT-V11-CIERRE-001`) |
| :--- | :---: | :---: |
| **Tablas relacionales (InnoDB)** | 97 | **108** |
| **Migraciones versionadas** | 23 | **29** |
| **Permisos RBAC granulares** | 104 | **115** |
| **Módulos oficiales** | 24 | **24** |
| **Suites automatizadas** | 40 | **48** |
| **Pruebas aprobadas (Regresión Global)** | 2,019 / 2,019 PASS | **2,342 / 2,342 PASS** |
| **Compuertas evolutivas V1.1** | — | **19 / 19 PASS** |
| **Rutas REST en OpenAPI 3.0.3** | — | **265 rutas (13 dominios)** |
| **Baseline Técnico Homologado** | `f3ddb21` (Doc: `1f42885`) | **`e513fdd`** |

---

## 🏛️ Fases de CandelariaAPP V1.1 Homologadas

| Fase | Dominio Evolutivo | Directiva de Cierre | Micro-Baseline | Estado |
| :---: | :--- | :---: | :---: | :---: |
| **Fase 3.1** | Programación Folclórica, Principio 4 Ojos, Seguimiento Operativo y Cobertura | `DT-V11-008` | `4edcfbf` | **HOMOLOGADA** |
| **Fase 3.2** | Motor de Automatizaciones y Comunicaciones WhatsApp (Simulador/Outbox) | `DT-V11-009` | `63b4e66` | **HOMOLOGADA** |
| **Fase 3.3** | Operación Móvil, Validación QR sin Conexión y Blindaje de Base de Datos | `DT-V11-013` | `bba6d38` | **HOMOLOGADA** |
| **Fase 3.4** | Portal del Cliente, Saldos, Pagos, Selección Fotográfica y 5 Compuertas | `DT-V11-015` | `909940f` / `5c84ca7` | **HOMOLOGADA** |
| **Fase 3.5** | Especificación OpenAPI 3.0.3, Arquitectura Headless y Regularización F2.10 | `DT-V11-016` | `e513fdd` | **HOMOLOGADA** |

---

## 🚀 Arquitectura y Capacidades Consolidada

- **Backend:** PHP 8.3 Nativo (Versión oficial de referencia certificada), Arquitectura Limpia Multicapa (Controladores, Servicios, Repositorios, Entidades).
- **Base de Datos:** MySQL 8.0+ en InnoDB con **108 tablas relacionales** y **29 migraciones versionadas** (con bloqueo incondicional contra saneamientos destructivos en la base principal).
- **Seguridad y Autorización:** RBAC granular con **115 capacidades** en **24 módulos oficiales**, autenticación passwordless blindada en el portal del cliente, protección contra SQLi, XSS, CSRF, IDOR y formula injection.
- **Integridad Transaccional y Multimedia:** Cálculo monetario en centavos, desglose de 4 importes, libro mayor inmutable, actas de cierre SHA-256 y regla de 5 compuertas simultáneas para descarga de fotografías originales.
- **Interoperabilidad Headless:** Contrato formal OpenAPI 3.0.3 en [`docs/api/openapi.json`](docs/api/openapi.json) y [`docs/api/openapi.yaml`](docs/api/openapi.yaml) con visualizador Swagger UI en `/documentacion` y `/docs`.

---

## 📚 Documentación y Recursos

- [📖 Índice General de Documentación Oficial (V1.0 & V1.1)](docs/README.md)
- [🔌 Especificación OpenAPI 3.0.3 (YAML)](docs/api/openapi.yaml) | [Especificación OpenAPI 3.0.3 (JSON)](docs/api/openapi.json)
- [🗺️ Mapa Canónico de los 24 Módulos y 115 Permisos RBAC](docs/modulos/mapa_modulos.md)
- [🛠️ Manual del Administrador del Servidor (Instalación, Configuración y DRP)](docs/manuales/manual_administrador_servidor.md)
- [⚖️ Reglas de Gobernanza del Proyecto](docs/gobernanza/reglas_gobernanza.md)
- [🗄️ Modelo de Datos y Evolución del Esquema](docs/base-datos/modelo_datos.md)
- [📋 Plantilla de Configuración de Producción](.env.produccion.ejemplo)

---

## 🧪 Verificación y Aseguramiento de Calidad

Para reproducir las suites de certificación automatizadas de **CandelariaAPP V1.1**:

```bash
# 1. Regresión global completa de V1.1 (48 suites / 2,342 pruebas aprobadas)
php pruebas/ejecutar_regresion_global.php

# 2. Ratificación evolutiva V1.1 (19/19 compuertas: esquema limpio 108 tablas, 115 permisos, DRP y huella)
php pruebas/ratificacion_v11.php

# 3. Suite contractual OpenAPI 3.0.3 Fase 3.5 (36/36 pruebas)
php pruebas/ejecutar_pruebas_f35.php

# 4. Suites de referencia histórica V1.0 (Ratificación y DRP Operativo)
php pruebas/ratificacion_dt_f212d_002.php
php pruebas/verificacion_operativa_despliegue.php
```

---

## ⚖️ Licencia y Propiedad
Desarrollado para la gestión institucional, folclórica, comercial y de coberturas audiovisuales durante la Festividad Virgen de la Candelaria.  
Todos los derechos reservados.

