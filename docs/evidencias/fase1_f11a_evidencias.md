# REGISTRO DE EVIDENCIAS TÉCNICAS — FASE 1.1A
## MODELO DE IDENTIDAD, PERSONAS, USUARIOS, ACTORES, CANALES Y AUDITORÍA DUAL

**Fecha de Ejecución:** 2026-10-01  
**Entorno de Ejecución:** Windows, Laragon, Apache 2.4, PHP 8.3.30, MySQL 8.4.3 LTS, Composer 2.10.1, Git 2.x  
**Carácter del Microlote:** Arquitectura del modelo de datos de identidad, personas naturales/jurídicas, cuentas de usuario, catálogo de actores de sistema, catálogo extensible de canales, contexto transversal de operación, auditoría append-only con restricciones estructurales, entidades de dominio, repositorios PDO nativos y validación de paridad al 100%.

---

## 1. Baseline Inicial de Partida
- **Rama:** `main`
- **Hash Inicial:** `d37741d79b4122b038cc07a2917def813cc7b879` (`d37741d`)
- **Estado de Sincronización:** `Ahead 0 / Behind 0` (`origin/main...HEAD: 0 0`)
- **Working Tree:** `CLEAN`

---

## 2. Auditoría de Tablas Fundacionales Existentes
Se inspeccionaron las 14 tablas previas de Fase 0 en `app_candelaria`:
- `migraciones_control` (1 registro)
- `modulos` (19 registros del catálogo oficial)
- `planes` (1 registro)
- `roles` (3 registros: Superadmin, Administrador, Operador)
- 0 registros en `usuarios`, 0 en `auditoria_operaciones` y 0 en tablas de negocio.
- Se certificó que no existía tabla `personas`, que `auditoria_operaciones` carecía de dimensiones de actor y canal, y que `usuarios` carecía de vinculación de identidad real.

---

## 3. Decisiones Clave de Modelado
1. **Desacoplamiento Persona $\longrightarrow$ Usuario:**
   - La identidad en el mundo real reside en `personas`. Un cliente, prospecto o colaborador puede existir sin tener jamás credenciales en el sistema.
   - Todo `usuario` está vinculado obligatoriamente a una persona física mediante `persona_id`.
   - Regla de cardinalidad: `PERSONA 1 ── 0..1 USUARIO` garantizada por `uk_usuarios_persona (persona_id)`.
2. **Diferenciación Estricta Natural vs Jurídica:**
   - `NATURAL`: Nombres y apellidos obligatorios; razón social estrictamente `NULL`.
   - `JURIDICA`: Razón social obligatoria; nombres y apellidos estrictamente `NULL`.
   - Ambas admiten opcionalmente `nombre_comercial`.
   - Cumplimiento forzado a nivel de motor mediante restricción `CHECK chk_personas_tipo_consistencia`.
3. **Ubicación y País:**
   - Cero hardcodeo de `ciudad DEFAULT 'PUNO'`.
   - `codigo_pais CHAR(2)` estandarizado bajo ISO 3166-1 alpha-2 (por defecto 'PE').
4. **WhatsApp y Contacto:**
   - `telefono_whatsapp` es opcional a nivel de `personas` para no imponer reglas comerciales a identidades no clientes.
5. **Actores Técnicos vs Canales (Ortogonalidad):**
   - **Actor:** Quién o qué ejecuta (`HUMANO` con `usuario_id` vs `SISTEMA` con `actor_sistema_id`).
   - **Canal:** Por dónde ingresa la solicitud (`APP`, `WEB`, `API`, `APP_MOVIL`, `WHATSAPP`, `IMPORTACION`, `API_PARTNER`).
   - Cero usuarios ficticios: Procesos automatizados o formularios web se registran con su identidad técnica real (`LANDING_CANDELARIA`, `WORKER_CONCILIACION`, etc.).
   - Validación estructural mediante `CHECK chk_auditoria_actor`: impide combinaciones contradictorias a nivel de base de datos.
6. **Auditoría Append-Only:**
   - `AuditoriaRepositorio` únicamente implementa `registrar()`. Carece por completo de métodos de modificación o borrado.
   - Sanitización preventiva automática de claves sensibles (`contrasena`, `password`, `token`, `secret`, `api_key`, `cvv`) censuradas a `[REDACTADO]`.

---

## 4. Diagramas de Relación

### A. Persona $\longrightarrow$ Usuario
```text
PERSONA (Identidad física o jurídica)
  ├── id (PK)
  ├── organizacion_id (FK)
  ├── tipo_persona (NATURAL | JURIDICA)
  ├── tipo_documento_id (FK)
  ├── numero_documento
  ├── nombres / apellidos (si NATURAL)
  ├── razon_social (si JURIDICA)
  └── nombre_comercial (opcional)
         │
         └── 0..1
              │
           USUARIO (Cuenta de acceso y seguridad)
              ├── id (PK)
              ├── persona_id (FK UNIQUE)
              ├── nombre_usuario (UNIQUE)
              ├── correo_electronico (UNIQUE)
              ├── contrasena_hash (password_hash)
              ├── estado (ACTIVO | INACTIVO | BLOQUEADO)
              ├── intentos_fallidos
              └── bloqueado_hasta
```

### B. Trazabilidad Dual: Actor $\times$ Canal
```text
                                OPERACIÓN REGISTRADA
                                         │
                 ┌───────────────────────┴───────────────────────┐
                 ▼                                               ▼
         DIMENSIÓN ACTOR                                 DIMENSIÓN CANAL
   (¿Quién o qué ejecuta?)                             (¿Por dónde ingresa?)
    ┌────────────┴────────────┐                      ┌───────────┼───────────┐
    ▼                         ▼                      ▼           ▼           ▼
 HUMANO                    SISTEMA                  APP         WEB         API
    │                         │                      │           │           │
usuario_id             actor_sistema_id            (Panel     (Landing    (Webhooks,
                       (LANDING_CANDELARIA,         Alina)     Pública)    Partners,
                        CHECKOUT_PASARELA,                                 CRONs)
                        WORKER_CONCILIACION...)
```

---

## 5. Tablas Creadas y Modificadas (Total: 18 Tablas Oficiales)

### Tablas Nuevas Creadas (4):
1. `tipos_documento`: Catálogo de tipos de documento oficiales.
2. `personas`: Padrón unificado de identidades naturales y jurídicas.
3. `actores_sistema`: Catálogo de identidades técnicas / actores virtuales.
4. `canales`: Catálogo extensible de medios de ingreso de solicitudes.

### Tablas Modificadas (2):
1. `usuarios`:
   - Agregada columna `persona_id` (FK a `personas` con `ON DELETE RESTRICT`).
   - Agregada columna `nombre_usuario` (UNIQUE login).
   - Agregadas columnas `intentos_fallidos` y `bloqueado_hasta`.
   - Modificada columna `telefono_whatsapp` a nullable.
   - Agregada restricción de unicidad `uk_usuarios_persona (persona_id)`.
2. `auditoria_operaciones`:
   - Agregadas columnas `actor_tipo`, `actor_sistema_id`, `canal_id`, `correlacion_id`.
   - Renombrada columna `direccion_ip` a `origen_ip` (nullable para procesos CLI/CRON).
   - Agregadas restricciones de clave foránea `fk_auditoria_usuario`, `fk_auditoria_actor_sistema`, `fk_auditoria_canal`.
   - Agregada restricción `CHECK chk_auditoria_actor`.

---

## 6. Verificación de Migración, Clean Install y Paridad
1. **Migración 002 Ejecutada:** `2026_10_02_000002_identidad_actores_auditoria.sql` aplicada sobre `app_candelaria`.
2. **Semillas Técnicas 002 Ejecutadas:** `2026_10_02_000002_semillas_identidad_actores.sql` insertadas (6 tipos de documento, 5 actores técnicos, 7 canales, 6 permisos atómicos de usuarios asignados a roles de administración).
3. **Prueba Clean Install en BD Temporal (`candelaria_test_clean_f11a`):**
   - Se levantó la base de datos temporal limpia.
   - Se ejecutó `esquema_base.sql` y las semillas fundacionales + semillas F1.1A.
   - Se compararon **225 comprobaciones estructurales** (columnas, tipos, nulos, índices y llaves primarias/foráneas) contra la base real `app_candelaria`.
   - **Resultado:** **PARIDAD ESTRUCTURAL 100% PASS (0 diferencias detectadas)**.
   - Base de datos temporal eliminada al concluir.

---

## 7. Batería de Pruebas Automatizadas de Dominio (`pruebas/ejecutar_pruebas_f11a.php`)
Se implementó y ejecutó la suite de pruebas bajo transacción con rollback:
- `[PASS]` Persona Natural válida se crea y normaliza a MAYÚSCULAS sin default hardcodeado.
- `[PASS]` Persona Natural con razón social es bloqueada por invariante de entidad.
- `[PASS]` Persona Jurídica válida se crea con razón social y nombres estrictamente NULL.
- `[PASS]` Persona Jurídica con nombres personales es bloqueada por invariante de entidad.
- `[PASS]` Persona existe de forma independiente sin requerir usuario.
- `[PASS]` Usuario vinculado a Persona verifica contraseña nativamente con `password_verify()` y `password_needs_rehash()`.
- `[PASS]` Unicidad 1 Persona $\rightarrow$ 0..1 Usuario garantizada por restricción `uk_usuarios_persona`.
- `[PASS]` Políticas de seguridad: 5 intentos fallidos activan bloqueo temporal y restablecimiento opera correctamente.
- `[PASS]` Auditoría con Actor Humano válido registrada exitosamente.
- `[PASS]` Auditoría con Actor Sistema válido (LANDING_CANDELARIA / WEB) registrada exitosamente.
- `[PASS]` Estado contradictorio (HUMANO + actor_sistema) bloqueado por invariante de Contexto.
- `[PASS]` Estado contradictorio (SISTEMA + usuario_id) bloqueado por invariante de Contexto.
- `[PASS]` Restricción CHECK `chk_auditoria_actor` de MySQL rechaza estructuralmente estados contradictorios en BD.
- `[PASS]` Sanitización automática en Auditoría: contraseñas y secretos censurados a `[REDACTADO]`.
- `[PASS]` `AuditoriaRepositorio` es inmutable (Append-Only): 0 métodos de modificación o eliminación.
- **Resultado:** **15 / 15 Pruebas Exitosas (0 Fallos)**.

---

## 8. Verificaciones Técnicas y Regresión
1. **PHP Lint:** **26/26 PASS** (`No syntax errors detected`).
2. **Composer Validate:** **PASS** (`./composer.json is valid`).
3. **Regresión HTTP Fase 0:**
   - `http://localhost/app.candelaria/` $\longrightarrow$ **HTTP 200 OK**.
   - `http://app.candelaria.test/` $\longrightarrow$ **HTTP 200 OK**.
4. **Upstream Alina:** `admin-dashboard/alina/` **100% INTACTO**.
5. **Git Whitespace:** `git diff --check` **PASS**.
