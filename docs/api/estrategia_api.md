# ESTRATEGIA API-FIRST — CANDELARIAAPP

## 1. Principio API-First
CandelariaAPP concibe la capa de datos y negocio como una API REST independiente y autocontenida.
- La web Alina Bootstrap 5 es el primer consumidor de esta API.
- Futuras aplicaciones móviles (Android e iOS) consumirán la misma arquitectura sin reconstruir controladores de negocio.

---

## 2. Formato Oficial del Sobre JSON (Response Envelope)
Todas las respuestas de endpoints de la API deben retornar una estructura consistente:

```json
{
    "exito": true,
    "codigo": 200,
    "mensaje": "Operación ejecutada exitosamente.",
    "datos": { ... },
    "errores": null
}
```

En caso de error:

```json
{
    "exito": false,
    "codigo": 422,
    "mensaje": "Los datos proporcionados no superaron la validación.",
    "datos": null,
    "errores": [
        { "campo": "telefono_whatsapp", "mensaje": "El número de WhatsApp es obligatorio." }
    ]
}
```

---

## 3. Versionamiento de Rutas
Todos los endpoints públicos o autenticados de negocio se versionan formalmente bajo el prefijo:

$$\text{/api/v1/...}$$

Esto previene roturas de compatibilidad con futuras versiones de apps móviles o clientes externos.

---

## 4. Códigos de Estado HTTP Estándar
- `200 OK`: Petición exitosa con datos de retorno.
- `201 Created`: Recurso creado exitosamente.
- `204 No Content`: Operación exitosa sin cuerpo de retorno.
- `400 Bad Request`: Formato de petición inválido o malformado.
- `401 Unauthorized`: No autenticado (falta token o sesión inválida).
- `403 Forbidden`: Autenticado pero sin permisos para la acción solicitada.
- `404 Not Found`: Recurso no encontrado.
- `422 Unprocessable Entity`: Error de validación de negocio en el backend.
- `429 Too Many Requests`: Límite de tasa excedido.
- `500 Internal Server Error`: Error no controlado del servidor (sin volcar detalles sensibles en producción).

---

## 5. Especificación Canónica OpenAPI 3.0.3 y Arquitectura Headless (Fase 3.5 — V1.1)

En la Fase 3.5 (`DT-V11-016`, baseline técnico `e513fdd`), la API REST de **CandelariaAPP V1.1** fue inventariada y formalizada bajo el estándar **OpenAPI 3.0.3**:
- **Rutas documentadas:** **265 rutas** organizadas en **13 dominios funcionales** (23 públicas/cliente/móvil y 242 administrativas/backoffice).
- **Artefactos de contrato:** [`docs/api/openapi.json`](openapi.json) y [`docs/api/openapi.yaml`](openapi.yaml) (sincronizados en `publico/documentacion/`).
- **Visualizador interactivo:** Swagger UI disponible en `/documentacion` y `/docs`.
- **Esquemas de seguridad documentados:** `CookieClienteAuth` (`candelaria_cliente_sesion`, `HttpOnly`), `BearerAdminAuth` (`HTTP Bearer`) y `TenantHeader` (`X-Organizacion-Slug`).

> [!IMPORTANT] Observación y Limitación Obligatoria de Certificación Contractual (`DT-V11-CIERRE-001`)
> - La suite de la Fase 3.5 (`pruebas/ejecutar_pruebas_f35.php`, `36/36 PASS`) certifica la validez sintáctica OpenAPI 3.0.3, la resolución de las 62 referencias `$ref` (`0` rotas), la unicidad de `operationId` y la correspondencia de rutas críticas.
> - **La certificación contractual 1:1 en tiempo de ejecución de los 265 endpoints** (verificando de forma automatizada que cada endpoint posea esquemas de solicitudes, respuestas y errores idénticos a su comportamiento real) **queda pendiente como requisito obligatorio previo a la integración de aplicaciones móviles nativas**.
> - El desarrollo de la aplicación móvil nativa (Android/iOS) permanece fuera del alcance implementado en V1.1.

