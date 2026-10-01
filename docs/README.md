# DOCUMENTACIÓN OFICIAL — CANDELARIAAPP

Bienvenido a la documentación técnica, arquitectónica y de gobernanza de **CandelariaAPP**.

Git es la **fuente de verdad progresiva** del proyecto. Toda decisión, convención, esquema y directriz debe mantenerse actualizada y trazable en este directorio.

---

## Estructura de Documentación

```text
/docs/
├── README.md                              # Índice general de documentación
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
│   └── modelo_datos.md                    # Modelo SaaS-ready, ciclo de ediciones y DDL
├── modulos/
│   └── mapa_modulos.md                    # Catálogo de los 19 dominios funcionales
└── evidencias/
    └── fase0_f01_evidencias.md            # Pruebas verificables, comandos y resultados
```

---

## Principios Fundacionales
1. **Veracidad Absoluta:** Nada se declara terminado, seguro o PASS sin evidencia empírica verificable. Si algo no fue probado, se etiqueta como **NO VERIFICADO**.
2. **Nomenclatura en Español:** Todo el código propio, clases, métodos, variables, tablas y rutas utilizan español técnico formal.
3. **SaaS-Ready & Single-Tenant Inicial:** Operación inicial para O.G. Estudio Creativo con aislamiento de tenant y diseño preparado para multitenancy sin rehacer la arquitectura.
4. **Protección Upstream:** La plantilla adquirida `admin-dashboard/alina` se mantiene intacta como referencia de solo lectura.
5. **Seguridad por Construcción:** Validación y autorización 100% en backend; el menú no es autorización.
