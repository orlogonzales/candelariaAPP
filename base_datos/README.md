# GOBERNANZA Y GESTIÓN DE BASE DE DATOS — CANDELARIAAPP

## 1. Estructura Oficial
```text
/base_datos/
├── esquema/
│   └── esquema_base.sql       # Esquema oficial reproducible para instalaciones limpias
├── migraciones/               # Migraciones versionadas incrementales
├── semillas/                  # Datos base controlados y técnicos
├── respaldos/                 # Copias de seguridad (EXCLUIDAS de Git)
└── README.md                  # Este documento
```

## 2. Reglas Vinculantes de Base de Datos
1. **Reproducibilidad:** `esquema/esquema_base.sql` debe permitir levantar una instalación limpia idéntica en cualquier momento.
2. **Trazabilidad estricta:** Está terminantemente prohibido modificar esquemas manualmente en base de datos sin registrar una migración versionada correlativa en `/base_datos/migraciones/`.
3. **Semillas controladas:** Solo se permiten datos técnicos de configuración del sistema (módulos, roles protegidos, permisos). **PROHIBIDO ingresar datos ficticios de negocio** (clientes falsos, ventas falsas, etc.).
4. **Respaldos protegidos:** El directorio `/base_datos/respaldos/` está excluido del control de versiones en `.gitignore`. Ningún volcado con datos reales o sensibles puede subirse a Git.
5. **Nomenclatura en Español:** Toda tabla, columna, restricción e índice debe utilizar español técnico en formato `snake_case` (ej. `ediciones_candelaria`, `numero_documento`).
6. **Motor y Juego de Caracteres:** `ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci`.
