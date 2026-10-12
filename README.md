# CANDELARIAAPP V1
### Plataforma de Gestión Operativa, Comercial y Audiovisual para la Festividad Virgen de la Candelaria

[![Versión](https://img.shields.io/badge/versi%C3%B3n-1.0.0--v1.frozen-blue.svg)](https://github.com/orlogonzales/candelariaAPP)
[![Regresión Global](https://img.shields.io/badge/regresi%C3%B3n-2%2C019%2F2%2C019%20PASS-brightgreen.svg)](pruebas/registro_regresion_global.log)
[![Gates Ratificación](https://img.shields.io/badge/ratificaci%C3%B3n-25%2F25%20PASS-success.svg)](pruebas/ratificacion_dt_f212d_002.php)
[![Verificación Operativa](https://img.shields.io/badge/operativa-28%2F28%20PASS-success.svg)](pruebas/verificacion_operativa_despliegue.php)
[![Estado](https://img.shields.io/badge/dictamen-APTO%20CON%20RESTRICCIONES-orange.svg)](docs/README.md)

---

## 📌 Estado del Proyecto: V1 CONGELADO

CandelariaAPP V1 ha culminado formalmente su **ciclo de desarrollo funcional y certificación local** bajo la directiva **DT-V1-CIERRE-001**.
El alcance funcional se encuentra **estrictamente congelado** a fin de garantizar la máxima estabilidad previa al despliegue.

> [!WARNING] Advertencia de Producción y Despliegue
> - **El despliegue en el servidor de producción definitivo todavía NO ha sido certificado en vivo.**
> - Todas las pasarelas de pago (Culqi, Niubiz, Stripe, Mercado Pago) y la mensajería de WhatsApp operan en **MODO SANDBOX / SIMULADOR**.
> - Queda prohibido el procesamiento de cobros reales o el envío masivo de mensajes hasta completar el checklist de activación de proveedores externos y recibir la autorización de Dirección Técnica.

---

## 🚀 Arquitectura y Capacidades

- **Backend:** PHP 8.2+ Nativo, Arquitectura Limpia Multicapa (Controladores, Servicios, Repositorios, Entidades).
- **Base de Datos:** MySQL 8.0+ en InnoDB con 97 tablas relacionales y 23 migraciones versionadas.
- **Seguridad:** RBAC granular con 104 capacidades, protección estricta contra inyección SQL, CSRF, IDOR y fórmula injection en exportaciones OpenXML.
- **Integridad Transaccional:** Desglose financiero estricto de 4 importes, libro mayor inmutable y actas de cierre con checksums criptográficos SHA-256.
- **Defensa en Profundidad:** Raíz pública obligatoria en `publico/index.php`, denegación total mediante `.htaccess` en `almacenamiento/privado/` y `base_datos/`.

---

## 📚 Documentación y Recursos

- [📖 Documentación Oficial del Proyecto](docs/README.md)
- [🛠️ Manual del Administrador del Servidor (Instalación, Configuración y DRP)](docs/manuales/manual_administrador_servidor.md)
- [🗺️ Mapa Canónico de los 24 Módulos y 104 Permisos](docs/modulos/mapa_modulos.md)
- [📋 Plantilla de Configuración de Producción](.env.produccion.ejemplo)
- [🛡️ Esquema Base Canónico de Base de Datos](base_datos/esquema/esquema_base.sql)

---

## 🧪 Verificación y Aseguramiento de Calidad

Para reproducir las suites de pruebas automatizadas:

```bash
# 1. Regresión global completa (40 suites / 2,019 aserciones)
php pruebas/ejecutar_regresion_global.php

# 2. Ratificación de 25 gates de esquema, RBAC, XLSX y seguridad
php pruebas/ratificacion_dt_f212d_002.php

# 3. Verificación operativa de DRP (BD + PDFs + Multimedia 1:1) y workers
php pruebas/verificacion_operativa_despliegue.php
```

---

## ⚖️ Licencia y Propiedad
Desarrollado para la gestión institucional y comercial de coberturas audiovisuales durante la Festividad Virgen de la Candelaria.  
Todos los derechos reservados.
