# REGISTRO DE EVIDENCIAS TÉCNICAS — FASE 1.1B
## NÚCLEO DE AUTENTICACIÓN, SESIONES SEGURAS, PROTECCIÓN CONTRA SESSION FIXATION, CSRF, POLÍTICAS DE BLOQUEO Y CONTEXTO DE OPERACIÓN

**Fecha de Ejecución:** 2026-10-02  
**Entorno de Ejecución:** Windows, Laragon, Apache 2.4, PHP 8.3.30, MySQL 8.4.3 LTS, Composer 2.10.1, Git 2.x  
**Carácter del Microlote:** Implementación del motor central de autenticación y control de sesiones seguras backend para usuarios humanos de CandelariaAPP, sin interfaz visual de login ni CRUD de usuarios (reservados para F1.1D).

---

## 1. Baseline Inicial de Partida
- **Rama:** `main`
- **Hash Inicial:** `9158b3fc31cdaeda318b457c423e77b75ee8eff2` (`9158b3f`)
- **Estado de Sincronización:** `Ahead 0 / Behind 0` (`origin/main...HEAD: 0 0`)
- **Working Tree:** `CLEAN`

---

## 2. Auditoría Previa del Esquema de Base de Datos y Componentes
Se auditaron las 18 tablas existentes en la base de datos `app_candelaria`:
1. `sesiones`:
   - `id VARCHAR(128) PRIMARY KEY`
   - `usuario_id INT UNSIGNED DEFAULT NULL` (clave foránea con índice hacia `usuarios.id`)
   - `direccion_ip VARCHAR(45) NOT NULL`
   - `agente_usuario TEXT DEFAULT NULL`
   - `carga_util LONGTEXT NOT NULL`
   - `ultima_actividad INT UNSIGNED NOT NULL` (con índice `idx_sesiones_actividad`)
   - **Evaluación de Idoneidad Estructural:** La tabla fundacional `sesiones` soporta al 100% todos los requerimientos de F1.1B sin requerir alteración de columnas, claves ni creación de una migración 003. El campo `id` de 128 caracteres alberga perfectamente representaciones criptográficas no reversibles (hash SHA-256 de 64 caracteres hexadecimales), evitando exponer tokens en texto claro. La clave foránea indexada `usuario_id` permite sesiones múltiples simultáneas por usuario y consultas optimizadas.
2. `usuarios`:
   - Conserva campos fundacionales `intentos_fallidos TINYINT UNSIGNED DEFAULT 0`, `bloqueado_hasta DATETIME DEFAULT NULL`, `ultimo_acceso_en DATETIME DEFAULT NULL` y `estado ENUM('ACTIVO','INACTIVO','BLOQUEADO')`.
3. `auditoria_operaciones`:
   - Mantiene la restricción CHECK `chk_auditoria_actor` que exige consistencia mutua exclusiva entre `HUMANO` y `SISTEMA`.

---

## 3. Decisiones Clave de Arquitectura y Seguridad

### A. Criptografía y Protección de Credenciales
- Uso exclusivo de las funciones nativas de PHP 8.3: `password_hash()`, `password_verify()` y `password_needs_rehash()`.
- Prohibición estricta de MD5, SHA1 o algoritmos reversibles.
- Cero contraseñas o hashes registrados en logs o auditoría.

### B. Identificadores de Sesión No Reversibles (SHA-256)
- El cliente (navegador/API) recibe un token plano de 64 caracteres hexadecimales generado con `bin2hex(random_bytes(32))`.
- La base de datos (`sesiones.id`) almacena exclusivamente el hash SHA-256 del token (`hash('sha256', $token)`).
- En caso de una hipotética exfiltración de la base de datos de sesiones, un atacante no puede secuestrar sesiones activas porque los tokens originales son irrecuperables.

### C. Protección contra Session Fixation
- En cada autenticación exitosa se emite un nuevo token independiente y se invoca `session_regenerate_id(true)`.
- El identificador o token pre-login queda completamente desvinculado de la sesión autenticada.

### D. Mitigación de Enumeración de Usuarios y Timing Attacks
- Respuestas públicas uniformes: ante usuario inexistente o contraseña incorrecta, la respuesta es idéntica (`"Credenciales de acceso incorrectas."`).
- Ante usuarios inexistentes, se ejecuta un `password_verify` simulado con un hash de referencia para equiparar los tiempos de respuesta y neutralizar ataques basados en tiempo (Timing Attacks).

### E. Centralización de Políticas de Bloqueo y Tiempos de Vida
- Se eliminaron números mágicos mediante `configuracion/seguridad.php` y la clase `Nucleo\Seguridad\ConfiguracionSeguridad`:
  - `intentos_fallidos_maximos`: 5 intentos.
  - `minutos_bloqueo`: 15 minutos de ventana temporal.
  - `timeout_inactividad_segundos`: 7200 segundos (2 horas).
  - `tiempo_vida_absoluto_segundos`: 86400 segundos (24 horas).
  - Políticas de cookie: `HttpOnly=true`, `SameSite=Lax`, `Secure` adaptativo a HTTPS/producción.

### F. Validación de Estado de Usuario en Backend
- Las sesiones activas validan en tiempo real contra la BD el estado del usuario. Si un administrador marca a un usuario como `INACTIVO` o `BLOQUEADO`, sus sesiones activas son rechazadas e invalidadas inmediatamente.

### G. Concurrencia de Sesiones
- La arquitectura permite múltiples sesiones activas del mismo usuario (ej. laptop y teléfono móvil).
- Se implementó revocación granular individual (`revocarSesionPorHash`) y masiva por usuario (`revocarTodasSesionesUsuario`).

### H. Protección CSRF
- Implementada en `Nucleo\Seguridad\ProtectorCsrf`:
  - Tokens de 32 bytes de entropía (`random_bytes(32)`).
  - Validación en tiempo constante mediante `hash_equals()`.
  - Extracción desde payload `_csrf_token` o encabezado `X-CSRF-Token`.
  - Desacoplado de la autenticación API server-to-server.

### I. Contexto de Operación y Correlación HTTP
- Integración en `Nucleo\Http\ContextoOperacion`:
  - `resolverCorrelacionId(?string $candidato)`: valida formato seguro (16 a 64 caracteres alfanuméricos y guiones `^[a-zA-Z0-9\-_]{16,64}$`). Si el encabezado recibido es válido, se propaga; si es ausente, inválido o malicioso, genera un nuevo UUID v4 criptográfico.
  - `ContextoOperacion::paraHumano(...)`: configura actor `HUMANO`, `usuario_id`, `actor_sistema_id = null`, canal `APP` (id=1).
  - `ContextoOperacion::establecerActual(?self $contexto)` y `ContextoOperacion::actual()`: exponen el contexto hidratado para el ciclo de vida de la petición.

### J. Trazabilidad de Intentos sin Violación de Invariantes
- Para resolver la auditoría de `LOGIN_FALLIDO` cuando no existe usuario autenticado, el sistema audita el evento utilizando el actor técnico `SISTEMA` (`actor_sistema_id = 1` correspondiente a `SISTEMA_CLI`), canal `APP`, registrando el identificador intentado y el motivo (`USUARIO_INEXISTENTE` o `CONTRASENA_INCORRECTA`), cumpliendo al 100% con la restricción MySQL `chk_auditoria_actor` sin inventar usuarios humanos.

---

## 4. Componentes Implementados

| Archivo | Responsabilidad |
|---|---|
| `configuracion/seguridad.php` | Parámetros centralizados de seguridad, cookies, CSRF y bloqueos |
| `nucleo/Seguridad/ConfiguracionSeguridad.php` | Acceso fuertemente tipado a parámetros de seguridad |
| `nucleo/Seguridad/ProtectorCsrf.php` | Generación y validación de tokens CSRF con `hash_equals` |
| `nucleo/Seguridad/ManejadorCookie.php` | Emisión e invalidación de cookies HttpOnly, SameSite y Secure |
| `aplicacion/Entidades/Sesion.php` | Entidad de sesión con evaluación de timeout y tiempo absoluto |
| `aplicacion/Repositorios/SesionRepositorio.php` | Almacenamiento de hashes SHA-256, expiración y revocación concurrente |
| `aplicacion/Seguridad/ResultadoAutenticacion.php` | Value object inmutable con resultado de intento de autenticación |
| `aplicacion/Seguridad/AutenticacionServicio.php` | Orquestación central de login, lockout, rehash, logout y validación |
| `nucleo/Http/ContextoOperacion.php` | Resuelve correlación segura HTTP y expone contexto estático actual |
| `nucleo/Http/Middleware/AutenticacionMiddleware.php` | Middleware de sesión para Cookie / Bearer y propagación de headers |
| `rutas/api.php` | Endpoints técnicos `/api/v1/auth/login`, `/api/v1/auth/logout`, `/api/v1/auth/sesion` |
| `pruebas/ejecutar_pruebas_f11b.php` | Suite de 26 pruebas automatizadas de seguridad F1.1B |

---

## 5. Resultados de Pruebas Automatizadas

### A. Suite F1.1B (`pruebas/ejecutar_pruebas_f11b.php`)
```text
==============================================================================
CANDELARIAAPP — SUITE DE PRUEBAS DE SEGURIDAD Y AUTENTICACIÓN F1.1B
AUTENTICACIÓN, SESIONES SEGURAS, FIXATION, CSRF, BLOQUEO Y AUDITORÍA
==============================================================================

 [PASS] 01. Autenticación exitosa con contraseña correcta y generación de sesión
 [PASS] 02. Contraseña incorrecta rechazada con mensaje de error uniforme
 [PASS] 03. Usuario inexistente rechazado con respuesta uniforme idéntica (Anti-Enumeración)
 [PASS] 04. Usuario con estado INACTIVO es rechazado antes de crear sesión
 [PASS] 05. Usuario con estado BLOQUEADO y ventana activa es rechazado
 [PASS] 06. Incremento consecutivo del contador de intentos fallidos (1 -> 2)
 [PASS] 07. Bloqueo automático de cuenta y asignación de bloqueado_hasta al alcanzar 5 intentos fallidos
 [PASS] 08. Autenticación exitosa restablece automáticamente intentos_fallidos a 0 y limpia bloqueado_hasta
 [PASS] 09a. Detección nativa de necesidad de rehash
 [PASS] 09. password_needs_rehash actualiza automáticamente el hash en BD con algoritmos y costos vigentes
 [PASS] 10. Prevención de Session Fixation: Generación de token fresco e independiente en cada login
 [PASS] 11. Validación de sesión activa retorna Contexto de Operación hidratado con actor humano
 [PASS] 12. Token de sesión no existente en BD es rechazado de inmediato (retorna null)
 [PASS] 13. Sesión expirada por inactividad es rechazada y purgada físicamente de la base de datos
 [PASS] 14. Sesión revocada individualmente por su identificador hash rechaza acceso futuro
 [PASS] 15. Cierre de sesión (Logout) destruye la sesión en servidor e impide nuevos accesos
 [PASS] 16. Backend valida estado de usuario en tiempo real: usuario desactivado no puede operar sesión preexistente
 [PASS] 17. Concurrencia: Múltiples sesiones activas simultáneas del mismo usuario sin interferencia
 [PASS] 18. Token CSRF criptográficamente seguro validado exitosamente en tiempo constante
 [PASS] 19. Token CSRF discrepante es rechazado de forma segura
 [PASS] 20. Token CSRF ausente o vacío es rechazado de forma segura
 [PASS] 21. Invariantes del Contexto de Operación Humano garantizadas (HUMANO + usuario_id + actor_sistema NULL)
 [PASS] 22. Canal de autenticación correctamente asignado a canal canónico APP (id=1)
 [PASS] 23. Ciclo seguro de correlación HTTP: propagación de identificadores válidos y generación UUID v4 ante inválidos
 [PASS] 24. Secretos ausentes: 0 contraseñas o credenciales en texto plano detectadas en 0 de 19 eventos auditados
 [PASS] 25. AutenticacionMiddleware resuelve sesión por Cookie o Bearer e hidrata ContextoOperacion::actual()

Transacción de prueba revertida (ROLLBACK). Base de datos app_candelaria íntegra y libre de residuos.

==============================================================================
RESULTADO FINAL F1.1B: 26 PRUEBAS EXITOSAS / 0 FALLOS
==============================================================================
```

### B. Suite de Regresión F1.1A (`pruebas/ejecutar_pruebas_f11a.php`)
```text
==============================================================================
CANDELARIAAPP — SUITE DE PRUEBAS DE INTEGRACIÓN F1.1A
MODELO DE IDENTIDAD, USUARIOS, ACTORES, CANALES Y AUDITORÍA
==============================================================================

 [PASS] Persona Natural válida se crea y normaliza a MAYÚSCULAS sin default hardcodeado
 [PASS] Persona Natural con razón social es bloqueada por invariante de entidad
 [PASS] Persona Jurídica válida se crea con razón social y nombres estrictamente NULL
 [PASS] Persona Jurídica con nombres personales es bloqueada por invariante de entidad
 [PASS] Persona existe de forma independiente sin requerir usuario
 [PASS] Usuario vinculado a Persona verifica contraseña nativamente con password_verify() y password_needs_rehash()
 [PASS] Unicidad 1 Persona -> 0..1 Usuario garantizada por restricción uk_usuarios_persona
 [PASS] Políticas de seguridad: 5 intentos fallidos activan bloqueo temporal y restablecimiento opera correctamente
 [PASS] Auditoría con Actor Humano válido registrada exitosamente
 [PASS] Auditoría con Actor Sistema válido (LANDING_CANDELARIA / WEB) registrada exitosamente
 [PASS] Estado contradictorio (HUMANO + actor_sistema) bloqueado por invariante de Contexto
 [PASS] Estado contradictorio (SISTEMA + usuario_id) bloqueado por invariante de Contexto
 [PASS] Restricción CHECK chk_auditoria_actor de MySQL rechaza estructuralmente estados contradictorios
 [PASS] Sanitización automática en Auditoría: contraseñas y secretos censurados a [REDACTADO]
 [PASS] AuditoriaRepositorio es inmutable (Append-Only): 0 métodos de modificación o eliminación

Transacción de prueba revertida (ROLLBACK). Base de datos app_candelaria libre de datos de prueba.

==============================================================================
RESULTADO FINAL: 15 PRUEBAS EXITOSAS / 0 FALLOS
==============================================================================
```

---

## 6. Verificaciones de Calidad, Integración y Regresión

1. **PHP Lint:**
   - 45 archivos analizados (36 archivos propios de la aplicación): **100% PASS** (0 errores de sintaxis).
2. **Composer:**
   - `composer validate`: `./composer.json is valid` (**PASS**).
   - `composer dump-autoload`: Generó mapa optimizado con 22 clases registradas (**PASS**).
3. **Git Diff Check:**
   - `git diff --check`: Sin espacios en blanco finales ni conflictos (**PASS**).
4. **Verificación HTTP:**
   - `http://localhost/app.candelaria/api/v1/estado` -> HTTP 200 OK (**PASS**).
   - `http://app.candelaria.test/api/v1/estado` -> HTTP 200 OK (**PASS**).
   - `http://localhost/app.candelaria/` -> HTTP 200 OK (**PASS**).
   - `http://app.candelaria.test/` -> HTTP 200 OK (**PASS**).
5. **Verificación Visual de Hotfix Sidebar:**
   - Capturas con Chrome Headless (`screenshot_f11b_localhost.png` y `screenshot_f11b_vhost.png`):
     - Cuadrados vacíos / glifos faltantes: **0**.
     - Tabler icons en vistas de la aplicación: **0**.
     - Font Awesome 6.3.0: **PASS**.
     - "Dashboard": elemento sin hijos y sin chevrons residuales (**PASS**).
6. **Integridad de Upstream Alina:**
   - Directorio `admin-dashboard/`: **100% INTACTO**, cero modificaciones.
