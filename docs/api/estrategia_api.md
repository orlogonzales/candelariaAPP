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
