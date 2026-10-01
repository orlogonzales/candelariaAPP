# CONVENCIONES DE NOMENCLATURA Y NORMALIZACIÓN — CANDELARIAAPP

## 1. Nomenclatura en Español Estricta
Todo el código fuente propio del proyecto debe escribirse en español técnico formal, sin mezclas innecesarias:

| Ámbito | Estilo | Ejemplos Correctos | Ejemplos Prohibidos |
|---|---|---|---|
| **Carpetas** | `PascalCase` o `snake_case` | `aplicacion/Controladores/`, `base_datos/` | `controllers/`, `database/` |
| **Namespaces** | `PascalCase` | `Aplicacion\Controladores`, `Nucleo\Http` | `App\Http\Controllers` |
| **Clases / Interfaces** | `PascalCase` | `ServicioPagos`, `RepositorioClientes` | `PaymentService`, `CustomerRepo` |
| **Métodos / Funciones** | `camelCase` | `registrarPago()`, `obtenerClientesPendientes()` | `savePayment()`, `getClientes()` |
| **Variables de dominio** | `camelCase` | `$clienteActual`, `$montoTotal` | `$currentCustomer`, `$totalAmount` |
| **Tablas de BD** | `snake_case` (plural) | `organizaciones`, `ediciones_candelaria` | `organizations`, `tbl_candelaria` |
| **Columnas de BD** | `snake_case` | `fecha_inicio`, `correo_electronico` | `startDate`, `email_address` |

### Excepciones Técnicas Permitidas
Se mantiene la denominación internacional oficial impuesta por estándares y bibliotecas externas:
`PHP`, `Composer`, `vendor`, `PSR`, `HTTP`, `JSON`, `OAuth`, `JWT`, `SDK`, métodos mágicos de PHP (`__construct`, `__destruct`), encabezados HTTP estándar.

---

## 2. Normalización de Datos Textuales (Sección 10)
- **Datos de Negocio a MAYÚSCULAS:** Nombres de personas, conjuntos folclóricos, bloques, sedes, ubicaciones, observaciones operativas y descripciones deben transformarse y almacenarse en **MAYÚSCULAS**, utilizando funciones multibyte seguras (`normalizar_mayusculas()` o `mb_strtoupper(..., 'UTF-8')`).
  - Ejemplo: `ORLANDO GONZÁLEZ`, `CAPORALES HUÁSCAR`, `PUNO`.
- **Excepciones en minúsculas:** Correos electrónicos (`normalizar_minusculas()`) y URLs.
- **Inmutables (sin transformación):** Contraseñas, secretos, tokens, claves API, hashes y datos donde la capitalización sea criptográficamente relevante.
