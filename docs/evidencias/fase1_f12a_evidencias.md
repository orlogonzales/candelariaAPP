# INFORME DE EVIDENCIAS Y ARQUITECTURA — MICROLOTE F1.2A
**CandelariaAPP &bull; Plataforma de Gestión y Producción Audiovisual**  
**Festividad de la Virgen de la Candelaria &bull; Puno, Perú**

---

## 1. RESUMEN EJECUTIVO

- **Microlote:** `F1.2A` — Modelo de Configuración y Organización (Ámbitos Plataforma y Organización, Parámetros Tipados, Branding, RBAC y Auditoría).
- **Baseline de Entrada Oficial:** `0c1c5f9ae3abb1eac09c92713fcfc302c6df7891` (Gate F1.1 cerrado con 94/94 PASS, 0 P0/P1).
- **Estado de Cierre:** **PASS FORMAL TÉCNICO Y DE MODELO (100% PRUEBAS EXITOSAS)**.
- **Regresión Acumulada Total:** **119 / 119 PRUEBAS PASS (0 FALLOS)**.
  - `F1.1A` (Identidad, Actores, Auditoría): **15/15 PASS**
  - `F1.1B` (Seguridad, Sesiones, CSRF, Bloqueo): **26/26 PASS**
  - `F1.1C` (RBAC, Permisos, Autorización Backend): **11/11 PASS**
  - `F1.1D` (Login, DataTables, Modales, API Usuarios): **17/17 PASS**
  - `F1.1E` (Hardening, IDOR, Roles y SEGURIDAD_AUTH): **25/25 PASS**
  - `F1.2A` (Modelo Organización, Parámetros Tipados, Branding, Caché): **25/25 PASS**
- **PHP Lint:** 0 errores sintácticos en la totalidad del repositorio (44/44 archivos verificados).
- **Composer:** Validez estricta de `composer.json` (`./composer.json is valid`).
- **Whitespace / Git Check:** Cero errores de formato o tabulaciones espurias (`git diff --check` limpio).
- **Secret Scan:** Cero credenciales ni secretos en texto claro versionados.
- **Upstream Alina (`admin-dashboard/`):** **100% INTACTO** (cero modificaciones, working tree clean).
- **Disponibilidad HTTP:** `http://app.candelaria.test/login` (HTTP 200) y `http://localhost/app.candelaria/login` (HTTP 200).

---

## 2. AUDITORÍA PREVIA DEL ESQUEMA Y REUTILIZACIÓN

Previo al diseño e implementación, se realizó una auditoría completa del esquema de base de datos existente para evitar redundancias, tablas desconectadas o "bolsas de configuración" sin gobernanza:

1. **Tabla `organizaciones`:** Existía desde F0 con la organización soberana inicial ID `10000` (`og_estudio`, `O.G. ESTUDIO CREATIVO S.A.C.`). En lugar de crear entidades duplicadas o tablas satélites innecesarias, se determinó extender `organizaciones` mediante columnas relacionales normalizadas para su perfil institucional y branding.
2. **Módulos Fundacionales:** Los módulos `17` (`organizacion_config` - "Configuración de Organización") y `18` (`plataforma_admin` - "Administración de Plataforma") ya estaban formalmente registrados en `modulos`.
3. **Catálogo de Documentos:** `tipos_documento` ya contenía `DNI` (1) y `RUC` (2). Se vinculó formalmente `tipo_documento_id` en `organizaciones` como clave foránea con integridad referencial.
4. **Catálogo de Roles:** Roles `1` (`superadmin_plataforma`) y `2` (`admin_organizacion`) plenamente integrados en el motor RBAC.

---

## 3. SEPARACIÓN FORMAL DE LOS TRES ÁMBITOS

Se estableció y documentó la delimitación arquitectónica rigurosa e inquebrantable de los tres ámbitos del sistema:

| Ámbito | Nivel de Soberanía | Alcance | Administrador Autorizado | Persistencia / Almacenamiento |
|---|---|---|---|---|
| **PLATAFORMA** | Global / Soberano | Parámetros del sistema completo (moneda principal, zona horaria, monto mínimo de pago, ventanas de bloqueo) | `superadmin_plataforma` (Exclusivo) | `parametros_configuracion` con `organizacion_id IS NULL` |
| **ORGANIZACION** | Tenant / Empresa | Datos institucionales de la empresa, contacto, logotipo y parámetros operativos de negocio | `admin_organizacion` (y superadmin) | Columnas en `organizaciones` y `parametros_configuracion` con `organizacion_id` explícito |
| **EDICION** | Operativo Anual | Festividad/Edición específica (ej. Candelaria 2026). Arquitectura reservada para Fase 2. | Reservado para Fase 2 | No implementado en F1.2A (cero código prematuro) |

> **Mandato de Seguridad:** Queda estrictamente prohibido que un `admin_organizacion` visualice o modifique parámetros de `PLATAFORMA`. Intentos de mutación arrojan tempranamente `AccesoDenegadoExcepcion` (HTTP 403).

---

## 4. MODELO DE DATOS Y MIGRACIÓN INCREMENTAL

### 4.1. Migración Oficial 003 (`base_datos/migraciones/2026_10_02_000003_configuracion_y_organizacion.sql`)

1. **Ampliación Relacional de `organizaciones`:**
   - `tipo_documento_id`: Clave foránea referenciando `tipos_documento(id)`.
   - `direccion`: Dirección fiscal/física.
   - `codigo_pais`: Código ISO 3166-1 alpha-2 (por defecto `'PE'`).
   - `departamento`, `provincia`, `distrito`: Ubicación geográfica normalizada.
   - `telefono_whatsapp`: Teléfono canónico de mensajería comercial.
   - `sitio_web`: URL institucional.
   - `contacto_nombre`, `contacto_cargo`: Representante operativo.
   - `isotipo_url`: Ruta relativa del isotipo institucional.

2. **Creación de `parametros_configuracion`:**
   - Tabla tipada y gobernada para parámetros clave-valor controlados.
   - Campos: `id`, `organizacion_id`, `ambito` (`ENUM('PLATAFORMA', 'ORGANIZACION')`), `codigo` (`VARCHAR(80)`), `tipo_dato` (`ENUM('STRING', 'INTEGER', 'DECIMAL', 'BOOLEAN', 'DATE', 'DATETIME')`), `valor` (`TEXT`), `etiqueta`, `descripcion`, `es_editable`, `reglas_validacion_json`, `actualizado_en`.
   - Restricción de unicidad: `UNIQUE KEY uk_parametros_org_codigo (organizacion_id, codigo)`.
   - **Constraint de Integridad Estructural MySQL 8.4:**
     ```sql
     CONSTRAINT chk_param_ambito_org CHECK (
         (ambito = 'PLATAFORMA' AND organizacion_id IS NULL) OR
         (ambito = 'ORGANIZACION' AND organizacion_id IS NOT NULL)
     )
     ```
     Impide en el motor de persistencia cualquier estado inconsistente entre el ámbito declarado y la tenencia de la organización.

3. **Semillas Oficiales 003 (`base_datos/semillas/2026_10_02_000003_semillas_configuracion_y_organizacion.sql`):**
   - Actualización del perfil institucional de la organización `10000` (`O.G. ESTUDIO CREATIVO S.A.C.`, RUC `20601234567`, Puno, Perú).
   - Creación de permisos RBAC para Módulo 17 (`organizacion.ver`, `organizacion.editar`, `branding.editar`, `configuracion_organizacion.ver`, `configuracion_organizacion.editar`).
   - Creación de permisos RBAC para Módulo 18 (`configuracion_plataforma.ver`, `configuracion_plataforma.editar`).
   - Asignación estricta en matriz `rol_permisos`: Superadmin obtiene permisos 7 a 13; Admin Organización obtiene permisos 7 a 11.
   - Parámetros iniciales de Plataforma: `plataforma.monto_minimo_pago_pe` (50.00), `plataforma.zona_horaria` (`America/Lima`), `plataforma.moneda_principal` (`PEN`), `plataforma.permitir_registro_publico` (`false`), `plataforma.max_intentos_login` (5), `plataforma.minutos_bloqueo_login` (15).
   - Parámetros iniciales de Organización: `organizacion.notificar_whatsapp` (`true`), `organizacion.dias_validez_cotizacion` (15), `organizacion.porcentaje_reserva_minimo` (30.00).

4. **Sincronización de Esquema Canónico (`base_datos/esquema/esquema_base.sql`):**
   - El esquema base completo fue sincronizado y verificado mediante una instalación limpia en una base de datos temporal, confirmando total consistencia con las migraciones históricas 001, 002 y 003.

---

## 5. CAPA DE DOMINIO Y GOBERNANZA

### 5.1. Entidades de Dominio
- **`Organizacion` (`aplicacion/Entidades/Organizacion.php`):**
  - Normalización automática en construcción: textos de negocio (nombres, razones sociales, direcciones, cargos) a `MAYÚSCULAS`; correos electrónicos y URLs a minúsculas limpias.
  - Validación de invariantes: slug válido para el código, código ISO de país de 2 caracteres, validación estricta de sintaxis de correo electrónico.
- **`ParametroConfiguracion` (`aplicacion/Entidades/ParametroConfiguracion.php`):**
  - Tipado fuerte con casteo nativo (`obtenerValorCasteado()` devuelve `bool`, `int`, `float` o `string` según el catálogo).
  - Reglas de validación numérica (`min`, `max`) y listas blancas (`opciones`).
  - **Prohibición Estricta de Secretos:** Lista negra de patrones (`password`, `secret`, `api_key`, `app_key`, `token`) que arroja `InvalidArgumentException` si se pretende registrar un secreto técnico como parámetro de configuración. Los secretos residen exclusivamente en `.env`.

### 5.2. Repositorios y Servicios
- **`OrganizacionRepositorio` (`aplicacion/Repositorios/OrganizacionRepositorio.php`):** Consultas por ID, código slug, listado y actualización relacional de datos de perfil y branding.
- **`ConfiguracionRepositorio` (`aplicacion/Repositorios/ConfiguracionRepositorio.php`):** Búsqueda por ámbito y código, actualización de valores con control de existencia.
- **`ConfiguracionServicio` (`aplicacion/Configuracion/ConfiguracionServicio.php`):**
  - Control RBAC en backend: solo `esSuperadmin` puede modificar plataforma; operadores de organización gestionan únicamente su respectivo tenant.
  - Caché en memoria de proceso para lecturas ultrarrápidas, con invalidación atómica inmediata tras actualización.
  - Pista de auditoría inmutable (`auditoria_operaciones`) registrando datos previos y nuevos, con censura activa de cualquier posible secreto.
- **`BrandingServicio` (`aplicacion/Configuracion/BrandingServicio.php`):**
  - Políticas de branding: Tipos MIME permitidos (`image/webp`, `image/png`, `image/jpeg`, `image/svg+xml`), tamaño máximo de 2 MB.
  - Rutas relativas seguras: `/recursos/subidas/organizaciones/{id}/branding/` con nombres de archivo parametrizados y timestamp para evitar colisiones y caché agresiva de navegador.
  - **Mandato Cero BLOBs:** Se prohíbe el almacenamiento de imágenes o binarios en MySQL.

---

## 6. RESULTADOS DE LA SUITE DE REGRESIÓN COMPLETA

```text
==============================================================================
CANDELARIAAPP — SUITE DE INTEGRACIÓN F1.1A (Identidad, Actores y Auditoría)
15 PRUEBAS EXITOSAS / 0 FALLOS [PASS]
==============================================================================
CANDELARIAAPP — SUITE DE SEGURIDAD Y AUTENTICACIÓN F1.1B (Sesiones y CSRF)
26 PRUEBAS EXITOSAS / 0 FALLOS [PASS]
==============================================================================
CANDELARIAAPP — SUITE DE AUTORIZACIÓN Y RBAC F1.1C (Roles y Permisos)
11 PRUEBAS EXITOSAS / 0 FALLOS [PASS]
==============================================================================
CANDELARIAAPP — SUITE DE LOGIN Y CRUD DE USUARIOS F1.1D (Vistas y API)
17 PRUEBAS EXITOSAS / 0 FALLOS [PASS]
==============================================================================
CANDELARIAAPP — SUITE DE HARDENING Y AUDITORÍA F1.1E (IDOR, Roles, SEGURIDAD_AUTH)
25 PRUEBAS EXITOSAS / 0 FALLOS [PASS]
==============================================================================
CANDELARIAAPP — SUITE DE MODELO DE CONFIGURACIÓN Y ORGANIZACIÓN F1.2A
25 PRUEBAS EXITOSAS / 0 FALLOS [PASS]
==============================================================================
TOTAL REGRESIÓN ACUMULADA: 119 / 119 PRUEBAS PASS (100% EXITOSO)
==============================================================================
```

### Detalle de las 25 Pruebas de F1.2A (`pruebas/ejecutar_pruebas_f12a.php`):
1. `[PASS]` Organización existente (10000 O.G. ESTUDIO CREATIVO) preservada con campos relacionales extendidos
2. `[PASS]` Normalización de Organización: textos de negocio a MAYÚSCULAS, emails/URLs a minúsculas
3. `[PASS]` Entidad Organizacion valida invariantes de código slug, código ISO de país y formato de email
4. `[PASS]` OrganizacionRepositorio resuelve correctamente por ID y código canónico
5. `[PASS]` Actualización relacional de datos institucionales de organización persistida exitosamente
6. `[PASS]` Gestión de branding almacena rutas relativas seguras y paleta institucional (sin BLOBs en MySQL)
7. `[PASS]` Separación formal de ámbitos: PLATAFORMA (organizacion_id IS NULL) y ORGANIZACION (organizacion_id obligatorio)
8. `[PASS]` MySQL CHECK constraint `chk_param_ambito_org` bloquea estructuralmente estados cruzados de ámbito
9. `[PASS]` Parámetros tipados BOOLEAN retornan tipos nativos booleanos (false / true) convertidos por backend
10. `[PASS]` Parámetros tipados DECIMAL retornan flotantes nativos validados con precisión adecuada
11. `[PASS]` Parámetros tipados INTEGER retornan enteros nativos
12. `[PASS]` Parámetros tipados STRING retornan cadenas de texto validadas
13. `[PASS]` Tipado DATE (YYYY-MM-DD) y DATETIME (YYYY-MM-DD HH:MM:SS) estrictamente validado
14. `[PASS]` Reglas de validación numérica (mínimo, máximo) aplicadas rigurosamente
15. `[PASS]` Reglas de lista blanca de opciones permitidas aplicadas rigurosamente
16. `[PASS]` Mandato de Seguridad: Prohibido almacenar contraseñas, API keys o secretos en tablas de configuración
17. `[PASS]` Catálogo Gobernado: Código en runtime no puede inventar nuevas claves de configuración arbitrarias
18. `[PASS]` Caché de proceso optimiza lecturas frecuentes evitando roundtrips a la base de datos
19. `[PASS]` Actualización formal mediante servicio invalida inmediatamente la caché y entrega el nuevo valor
20. `[PASS]` Aislamiento Multi-Tenant: Parámetros del Tenant A no interfieren con los parámetros del Tenant B
21. `[PASS]` RBAC: Superadministrador de Plataforma cuenta con autorización plena para actualizar parámetros soberanos
22. `[PASS]` RBAC: Administrador de Organización es rechazado (`AccesoDenegadoExcepcion`) al intentar modificar parámetros de Plataforma
23. `[PASS]` Pista de auditoría inmutable registra cambios de configuración con valores anteriores y nuevos
24. `[PASS]` Auditoría de configuración se encuentra 100% libre de secretos técnicos
25. `[PASS]` Branding: Políticas de tipos MIME, tamaño máximo (2MB), nomenclatura segura y rutas relativas verificadas

---

## 7. VERIFICACIONES DE GOBERNANZA Y CIERRE

1. **PHP Syntax Lint:** 44/44 archivos evaluados sin errores de sintaxis.
2. **Composer Json:** Validado estrictamente (`composer validate` exitoso).
3. **Control de Espacios en Blanco:** `git diff --check` limpio sin errores.
4. **Secret Scanning:** `git grep -i "Cand26!"` no reporta secretos estáticos; solo lógica procedural de pruebas/semillas.
5. **Upstream Alina:** Directorio `admin-dashboard/` 100% limpio y protegido.
6. **MANDATORY STOP:** Implementación técnica, modelo y persistencia de F1.2A concluidos. No se han iniciado interfaces ni controladores de F1.2B a la espera de la autorización formal.
