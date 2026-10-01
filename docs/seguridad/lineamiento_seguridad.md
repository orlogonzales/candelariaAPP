# LINEAMIENTO DE SEGURIDAD POR CONSTRUCCIÓN — CANDELARIAAPP

## 1. Principio Fundamental
La seguridad no es un añadido posterior; se construye en cada línea de código. La interfaz visual es únicamente para conveniencia del usuario; **el backend es la única autoridad de validación y autorización**.

$$\text{Menú Oculto} \neq \text{Seguridad}$$

---

## 2. Controles Obligatorios por Construcción

### A. Prevención de Inyección SQL (SQLi)
- Uso obligatorio de **PDO con Prepared Statements** y parámetros vinculados (`bindValue()` / `bindParam()`).
- Prohibida la interpolación directa de variables en cadenas SQL (`SELECT * FROM tabla WHERE id = $id`).

### B. Prevención de Cross-Site Scripting (XSS)
- Todo dato impreso en vistas HTML debe ser escapado contextualmente mediante `escapar_html()` (`htmlspecialchars(..., ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')`).
- En respuestas API, los encabezados deben declarar `Content-Type: application/json; charset=utf-8`.

### C. Protección contra Cross-Site Request Forgery (CSRF)
- Formularios POST/PUT/DELETE web deben requerir un token CSRF sincronizado en sesión.
- Peticiones Fetch API deben adjuntar el token mediante el encabezado `X-CSRF-TOKEN`.

### D. Sesiones Seguras
- Cookies con atributos obligatorios: `HttpOnly=true`, `SameSite=Lax` (o `Strict`), `Secure=true` en producción HTTPS.
- Regeneración de ID de sesión tras eventos de autenticación (`session_regenerate_id(true)`).

### E. Hashing Seguro de Contraseñas
- Implementación de `password_hash($contrasena, PASSWORD_ARGON2ID)` o `PASSWORD_BCRYPT`.
- Nunca almacenar contraseñas en texto claro o con algoritmos obsoletos (MD5, SHA1).

### F. Gestión de Secretos y Variables de Entorno
- Claves de cifrado, secretos API y credenciales de base de datos residen estrictamente en `.env`.
- `.env` está expresamente excluido de Git en `.gitignore`.

### G. Subida de Archivos Segura
- Verificación de tipo MIME real (`finfo_file()`), no solo de la extensión reportada por el cliente.
- Límites estrictos de tamaño.
- Nombres de archivo sanitizados y almacenamiento fuera del DocumentRoot público cuando se trate de documentos privados.

---

## 3. Pista de Auditoría Inmutable
Toda operación sensible (cambios de estado, anulación de pagos, reasignación de activos, accesos administrativos) debe registrarse en la tabla `auditoria_operaciones` capturando:
- `organizacion_id`, `usuario_id`, `modulo`, `accion`, `entidad_tipo`, `entidad_id`, `datos_previos_json`, `datos_nuevos_json`, `direccion_ip`, `agente_usuario`, `creado_en`.
- **PROHIBIDO registrar secretos, contraseñas o tokens en los registros de auditoría.**
