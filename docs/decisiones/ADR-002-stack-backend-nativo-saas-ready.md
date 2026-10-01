# ADR-002: STACK BACKEND NATIVO Y ARQUITECTURA SAAS-READY

## Estado
**APROBADO Y EN VIGOR**

## Contexto
El desarrollo de CandelariaAPP requiere alta mantenibilidad, rendimiento óptimo, independencia tecnológica frente a frameworks masivos y capacidad de evolucionar desde una operación monopuesto (O.G. Estudio Creativo) hacia una plataforma SaaS multitenant para futuras ediciones y empresas del sector audiovisual y folclórico.

## Decisión
1. **PHP 8.3 Nativo:** MVC propio, tipado estricto (`declare(strict_types=1);`), programación orientada a objetos moderna.
2. **Prohibición Expresa de Frameworks Backend:** Sin Laravel, sin Symfony como framework, sin CodeIgniter, sin frameworks introducidos indirectamente vía Composer.
3. **Persistencia con PDO:** Acceso a base de datos exclusivamente mediante PDO y Prepared Statements (prevención por diseño de SQL Injection).
4. **Nomenclatura en Español:** Todo código propio (namespaces, clases, métodos, variables de dominio, tablas y columnas) debe redactarse en español técnico.
5. **Arquitectura SaaS-Ready:**
   - Estructuración de tablas con columna `organizacion_id` (tenant).
   - Separación estricta entre **Licenciamiento** (qué contrató la organización) y **Autorización** (qué permisos tiene el usuario).
   - Ámbito exclusivo para Superadministrador de Plataforma (`organizacion_id IS NULL`).
6. **Composer Justificado:** No se instalan librerías de terceros sin documentación previa de necesidad, alternativa, compatibilidad y mantenimiento.

## Consecuencias
- **Positivas:** Control total del código, cero sobrecarga o vulnerabilidades heredadas de frameworks, portabilidad total en servidores PHP estándar.
- **A tener en cuenta:** Los componentes transversales (enrutador, renderizador de vistas, cargador de entorno) deben ser construidos e implementados con estándares estrictos de calidad y seguridad.
