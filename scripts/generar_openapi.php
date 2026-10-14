<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * GENERADOR CANÓNICO DE ESPECIFICACIÓN OPENAPI 3.0.3 (CANDELARIAAPP V1.2)
 * DIRECTIVAS TÉCNICAS DT-V11-016 (FASE 3.5) Y DT-V12-002 (FASE 4.2A)
 * ==============================================================================
 * Genera la especificación OpenAPI 3.0.3 en sincronía 1:1 con el 100% de las
 * rutas registradas en `rutas/api.php` (269 endpoints en 13 dominios).
 */

namespace Herramientas\Documentacion;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../nucleo/Soporte/ayudantes.php';

use Nucleo\Enrutamiento\Enrutador;

class GeneradorOpenApi
{
    public static function construirEspecificacion(): array
    {
        return [
            'openapi' => '3.0.3',
            'info' => [
                'title' => 'CandelariaAPP V1.2 — API REST Headless',
                'description' => "Especificación canónica formal de las APIs REST de CandelariaAPP (Certificación Contractual 1:1 — DT-V12-002).\n\n"
                    . "Diseñada bajo arquitectura API-First para soportar la aplicación web Alina Bootstrap 5 "
                    . "y la futura aplicación móvil nativa (Android / iOS) para la Festividad Virgen de la Candelaria en Puno, Perú.\n\n"
                    . "### Dominios Principales Documentados (13 Dominios · 269 Endpoints):\n"
                    . "- **Portal del Cliente:** Autenticación sin contraseña, perfil inmutable, estado de cuenta, recibos y galerías fotográficas.\n"
                    . "- **Portal Público y Checkout:** Resolución multitenant, catálogo comercial, cotizador determinista en backend, bloqueos de cupos (TTL 15m) y checkout soberano.\n"
                    . "- **Operación Móvil y Validación QR:** Descarga de manifiestos criptográficos para validación sin conexión, validación online, sincronización de lotes y resolución de conflictos.\n"
                    . "- **Programación Folclórica:** Catálogo de danzas, conjuntos, bloques, padrón oficial, programación versionada con 4 ojos y seguimiento en vivo.\n"
                    . "- **Comunicaciones y Eventos:** Consentimientos Ley 29733, plantillas, reglas de notificación, webhooks WhatsApp blindados y bandeja de salida (Outbox).\n"
                    . "- **Ventas, Finanzas y Postproducción:** Ventas, pagos, webhooks de pasarelas blindados, cotizaciones, recibos internos y descarga de originales bajo 5 compuertas simultáneas.\n"
                    . "- **Dashboard Ejecutivo y Analítica Multianual (V1.2):** KPIs en tiempo real, comparativa interanual YoY en centavos, curvas de preventa (Booking Pace), cohortes de retención y exportaciones CSV/XLSX/PDF.\n\n"
                    . "### Convenciones de Seguridad y Respuestas:\n"
                    . "- Toda respuesta exitosa sigue el sobre JSON: `{ exito: true, codigo: 200, mensaje: string, datos: ... }`.\n"
                    . "- Todo error sigue el sobre JSON: `{ exito: false, codigo: 4xx|5xx, mensaje: string, errores: [...] }`.\n"
                    . "- Aislamiento Multitenant estricto por `organizacion_id` y `edicion_id`.\n"
                    . "- Prevención activa contra IDOR en todos los recursos de clientes y organizaciones.",
                'version' => '1.2.0',
                'contact' => [
                    'name' => 'O.G. Estudio Creativo — Equipo de Arquitectura',
                    'email' => 'contacto@ogestudiocreativo.com',
                    'url' => 'https://ogestudiocreativo.com',
                ],
                'license' => [
                    'name' => 'Propietaria — CandelariaAPP V1.2',
                ],
            ],
            'servers' => [
                [
                    'url' => 'http://localhost/app.candelaria',
                    'description' => 'Servidor de Desarrollo Local (Laragon / Apache)',
                ],
                [
                    'url' => 'http://app.candelaria.test',
                    'description' => 'Servidor Local VirtualHost (.test)',
                ],
                [
                    'url' => 'https://app.candelaria.pe',
                    'description' => 'Servidor de Producción Proyectado',
                ],
            ],
            'tags' => [
                ['name' => 'Portal del Cliente', 'description' => 'Autenticación passwordless, perfil inmutable, estado de cuenta y selección de fotografías'],
                ['name' => 'Portal Público y Checkout', 'description' => 'Catálogo comercial, cotizador determinista, bloqueo de cupos y checkout de invitado'],
                ['name' => 'Operación Móvil y QR', 'description' => 'Manifiestos offline, validación HMAC-SHA256 y sincronización de lotes de campo'],
                ['name' => 'Programación Folclórica', 'description' => 'Catálogo de danzas, conjuntos, bloques, programación oficial y tablero en vivo'],
                ['name' => 'Comunicaciones y Consentimientos', 'description' => 'Consentimientos Ley 29733, plantillas, reglas automatizadas, webhooks WhatsApp y Outbox'],
                ['name' => 'Ventas y Finanzas', 'description' => 'Gestión comercial, CRM, catálogo, cotizaciones, ventas, pagos, pasarelas y conciliación'],
                ['name' => 'Reservas y Operaciones', 'description' => 'Reservas de servicios, salidas operativas, proveedores, recursos y manifiestos de pasajeros'],
                ['name' => 'Postproducción y Galerías', 'description' => 'Proyectos fotográficos, galerías y entrega canónica de originales bajo 5 compuertas'],
                ['name' => 'Documentos y Emisión PDF', 'description' => 'Generación de cotizaciones, contratos, recibos y boletos imprimibles en A4 y Ticket 80mm'],
                ['name' => 'Identidad y Autenticación Admin', 'description' => 'Inicio y cierre de sesión, usuarios y roles administrativos RBAC'],
                ['name' => 'Organizaciones y Contexto', 'description' => 'Multi-tenant, gestión de ediciones, cierres y parámetros de plataforma'],
                ['name' => 'Dashboard Ejecutivo', 'description' => 'KPIs comerciales, operativos y motor de Analítica Multianual (YoY, Booking Pace, Cohortes)'],
                ['name' => 'Sistema y Salud', 'description' => 'Verificación de estado de la plataforma y distribución de contratos OpenAPI'],
            ],
            'paths' => self::construirRutasSincronizadas(),
            'components' => self::construirComponentes(),
        ];
    }

    /**
     * Construye el mapa completo de rutas garantizando correspondencia 1:1 exacta
     * con `rutas/api.php`, enriquecido con las especificaciones detalladas de dominio.
     */
    private static function construirRutasSincronizadas(): array
    {
        $rutasManuales = self::construirRutasDetalladas();

        $rutasRegistradas = self::cargarRutasDeApiPhp();
        $paths = [];
        $operationIdsUsados = [];

        // Primero registrar operationIds de las rutas manuales que coinciden con api.php
        foreach ($rutasRegistradas as $r) {
            $patron = $r['patron'];
            $metodo = strtolower($r['metodo']);
            if (isset($rutasManuales[$patron][$metodo]['operationId'])) {
                $operationIdsUsados[$rutasManuales[$patron][$metodo]['operationId']] = true;
            }
        }

        foreach ($rutasRegistradas as $r) {
            $patron = $r['patron'];
            $metodoHttp = strtoupper($r['metodo']);
            $metodoKey = strtolower($metodoHttp);
            $accion = $r['accion'];

            if (isset($rutasManuales[$patron][$metodoKey])) {
                $op = $rutasManuales[$patron][$metodoKey];
                // Garantizar que todos los parámetros de ruta y respuestas de error estén declarados
                $op = self::asegurarParametrosDeRuta($patron, $op);
                $op = self::asegurarRespuestasEstandar($patron, $metodoHttp, $op);
                $paths[$patron][$metodoKey] = $op;
                continue;
            }

            $opGenerada = self::generarContratoParaRuta(
                $patron,
                $metodoHttp,
                $accion,
                $operationIdsUsados
            );
            $paths[$patron][$metodoKey] = self::asegurarRespuestasEstandar($patron, $metodoHttp, $opGenerada);
        }

        return $paths;
    }

    /**
     * Garantiza que toda operación documente los códigos HTTP de error aplicables en tiempo de ejecución.
     */
    private static function asegurarRespuestasEstandar(string $patron, string $metodoHttp, array $op): array
    {
        if (in_array($patron, ['/api/v1/estado', '/api/v1/openapi.json', '/api/v1/openapi.yaml'], true)) {
            return $op;
        }

        $op['responses']['400'] ??= ['$ref' => '#/components/responses/ErrorSolicitudInvalida'];
        $op['responses']['401'] ??= ['$ref' => '#/components/responses/ErrorNoAutorizado'];
        $op['responses']['403'] ??= ['$ref' => '#/components/responses/ErrorAccesoDenegado'];
        $op['responses']['404'] ??= ['$ref' => '#/components/responses/ErrorNoEncontrado'];
        $op['responses']['422'] ??= ['$ref' => '#/components/responses/ErrorValidacion'];

        if ($metodoHttp === 'DELETE') {
            $op['responses']['405'] ??= ['$ref' => '#/components/responses/ErrorMetodoNoPermitido'];
        }

        return $op;
    }

    /**
     * Carga las rutas declaradas en `rutas/api.php` sin afectar el estado global si ya estaban cargadas.
     */
    private static function cargarRutasDeApiPhp(): array
    {
        $existentes = Enrutador::obtenerRutas();
        $rutasApi = array_values(array_filter($existentes, fn(array $r) => str_starts_with($r['patron'], '/api/')));

        if (empty($rutasApi)) {
            require __DIR__ . '/../rutas/api.php';
            $existentes = Enrutador::obtenerRutas();
            $rutasApi = array_values(array_filter($existentes, fn(array $r) => str_starts_with($r['patron'], '/api/')));
        }

        // Deduplicar por (metodo, patron) preservando orden canónico
        $unicas = [];
        $resultado = [];
        foreach ($rutasApi as $r) {
            $llave = strtoupper($r['metodo']) . ' ' . $r['patron'];
            if (!isset($unicas[$llave])) {
                $unicas[$llave] = true;
                $resultado[] = $r;
            }
        }

        return $resultado;
    }

    /**
     * Asegura que cualquier placeholder `{param}` del patrón exista en `$op['parameters']`.
     */
    private static function asegurarParametrosDeRuta(string $patron, array $op): array
    {
        if (!preg_match_all('/\{([a-zA-Z0-9_]+)\}/', $patron, $m)) {
            return $op;
        }

        $paramsExistentes = [];
        foreach ($op['parameters'] ?? [] as $p) {
            if (($p['in'] ?? '') === 'path' && !empty($p['name'])) {
                $paramsExistentes[$p['name']] = true;
            }
        }

        foreach ($m[1] as $nombreParam) {
            if (!isset($paramsExistentes[$nombreParam])) {
                $esString = in_array($nombreParam, ['codigo', 'slug'], true);
                $op['parameters'][] = [
                    'name' => $nombreParam,
                    'in' => 'path',
                    'required' => true,
                    'description' => "Identificador de ruta ({$nombreParam})",
                    'schema' => ['type' => $esString ? 'string' : 'integer'],
                ];
            }
        }

        return $op;
    }

    /**
     * Genera el contrato OpenAPI 3.0.3 formal para una ruta registrada en `rutas/api.php`.
     */
    private static function generarContratoParaRuta(
        string $patron,
        string $metodoHttp,
        mixed $accion,
        array &$operationIdsUsados
    ): array {
        $tag = self::resolverTagPorPatron($patron);
        $opId = self::generarOperationIdUnico($patron, $metodoHttp, $accion, $operationIdsUsados);
        [$summary, $description] = self::generarResumenYDescripcion($patron, $metodoHttp, $accion, $tag);

        $op = [
            'tags' => [$tag],
            'summary' => $summary,
            'description' => $description,
            'operationId' => $opId,
        ];

        // Seguridad contractual según dominio de ruta
        $seguridad = self::resolverSeguridadPorPatron($patron);
        if (!empty($seguridad)) {
            $op['security'] = $seguridad;
        }

        // Parámetros de ruta ({id}, {codigo}, etc.)
        $op = self::asegurarParametrosDeRuta($patron, $op);

        // Cuerpo de petición para POST, PUT, PATCH
        if (in_array($metodoHttp, ['POST', 'PUT', 'PATCH'], true)) {
            $op['requestBody'] = [
                'required' => false,
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'additionalProperties' => true,
                        ],
                    ],
                ],
            ];
        }

        // Respuestas estándar según naturaleza del endpoint
        $esPdf = str_ends_with($patron, '/pdf') || str_contains($patron, '/recibo/') || str_contains($patron, '-pdf');
        $esExportacion = str_ends_with($patron, '/exportar');
        $esDescargaBinaria = str_ends_with($patron, '/descargar') || str_contains($patron, '/descargar-multimedia/');

        if ($esPdf) {
            $op['responses'] = [
                '200' => [
                    'description' => 'Documento PDF generado exitosamente',
                    'content' => [
                        'application/pdf' => [
                            'schema' => ['type' => 'string', 'format' => 'binary'],
                        ],
                    ],
                ],
                '401' => ['$ref' => '#/components/responses/ErrorNoAutorizado'],
                '403' => ['$ref' => '#/components/responses/ErrorAccesoDenegado'],
                '404' => ['$ref' => '#/components/responses/ErrorNoEncontrado'],
            ];
        } elseif ($esExportacion) {
            $op['responses'] = [
                '200' => [
                    'description' => 'Archivo de reporte exportado (CSV, XLSX o PDF)',
                    'content' => [
                        'text/csv' => ['schema' => ['type' => 'string', 'format' => 'binary']],
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => ['schema' => ['type' => 'string', 'format' => 'binary']],
                        'application/pdf' => ['schema' => ['type' => 'string', 'format' => 'binary']],
                    ],
                ],
                '401' => ['$ref' => '#/components/responses/ErrorNoAutorizado'],
                '403' => ['$ref' => '#/components/responses/ErrorAccesoDenegado'],
                '422' => ['$ref' => '#/components/responses/ErrorValidacion'],
            ];
        } elseif ($esDescargaBinaria) {
            $op['responses'] = [
                '200' => [
                    'description' => ' Flujo binario multimedia transmitido de forma segura',
                    'content' => [
                        'application/octet-stream' => ['schema' => ['type' => 'string', 'format' => 'binary']],
                    ],
                ],
                '401' => ['$ref' => '#/components/responses/ErrorNoAutorizado'],
                '403' => ['$ref' => '#/components/responses/ErrorAccesoDenegado'],
                '404' => ['$ref' => '#/components/responses/ErrorNoEncontrado'],
            ];
        } else {
            $respuestas = [
                '200' => [
                    'description' => 'Operación procesada con éxito',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/SobreRespuestaExito'],
                        ],
                    ],
                ],
            ];
            if ($metodoHttp === 'POST') {
                $respuestas['201'] = [
                    'description' => 'Recurso creado o procesado exitosamente',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/SobreRespuestaExito'],
                        ],
                    ],
                ];
            }
            $respuestas['401'] = ['$ref' => '#/components/responses/ErrorNoAutorizado'];
            $respuestas['403'] = ['$ref' => '#/components/responses/ErrorAccesoDenegado'];
            $respuestas['404'] = ['$ref' => '#/components/responses/ErrorNoEncontrado'];
            $respuestas['422'] = ['$ref' => '#/components/responses/ErrorValidacion'];

            $op['responses'] = $respuestas;
        }

        return $op;
    }

    private static function resolverTagPorPatron(string $patron): string
    {
        if (str_starts_with($patron, '/api/v1/publico/portal/')) {
            return 'Portal del Cliente';
        }
        if (str_starts_with($patron, '/api/v1/publico/')) {
            return 'Portal Público y Checkout';
        }
        if (str_starts_with($patron, '/api/v1/operaciones/offline/')) {
            return 'Operación Móvil y QR';
        }
        if (str_starts_with($patron, '/api/v1/folclore/')) {
            return 'Programación Folclórica';
        }
        if (str_starts_with($patron, '/api/v1/comunicaciones/') || str_starts_with($patron, '/api/v1/webhooks/whatsapp')) {
            return 'Comunicaciones y Consentimientos';
        }
        if (
            str_starts_with($patron, '/api/v1/clientes') ||
            str_starts_with($patron, '/api/v1/personas/buscar') ||
            str_starts_with($patron, '/api/v1/crm/') ||
            str_starts_with($patron, '/api/v1/catalogo/') ||
            str_starts_with($patron, '/api/v1/cotizaciones') ||
            str_starts_with($patron, '/api/v1/ventas') ||
            str_starts_with($patron, '/api/v1/pagos') ||
            str_starts_with($patron, '/api/v1/cuentas-bancarias') ||
            str_starts_with($patron, '/api/v1/pasarelas') ||
            str_starts_with($patron, '/api/v1/webhooks/pasarelas')
        ) {
            return 'Ventas y Finanzas';
        }
        if (str_starts_with($patron, '/api/v1/reservas') || str_starts_with($patron, '/api/v1/operaciones/')) {
            return 'Reservas y Operaciones';
        }
        if (str_starts_with($patron, '/api/v1/postproduccion/')) {
            return 'Postproducción y Galerías';
        }
        if (str_starts_with($patron, '/api/v1/documentos/')) {
            return 'Documentos y Emisión PDF';
        }
        if (
            str_starts_with($patron, '/api/v1/auth/') ||
            str_starts_with($patron, '/api/v1/usuarios') ||
            str_starts_with($patron, '/api/v1/personas/disponibles') ||
            str_starts_with($patron, '/api/v1/tipos-documento') ||
            str_starts_with($patron, '/api/v1/roles')
        ) {
            return 'Identidad y Autenticación Admin';
        }
        if (
            str_starts_with($patron, '/api/v1/organizacion') ||
            str_starts_with($patron, '/api/v1/configuracion') ||
            str_starts_with($patron, '/api/v1/ediciones') ||
            str_starts_with($patron, '/api/v1/contexto/')
        ) {
            return 'Organizaciones y Contexto';
        }
        if (str_starts_with($patron, '/api/v1/dashboard/')) {
            return 'Dashboard Ejecutivo';
        }
        return 'Sistema y Salud';
    }

    private static function resolverSeguridadPorPatron(string $patron): array
    {
        $publicosSinAuth = [
            '/api/v1/estado',
            '/api/v1/openapi.json',
            '/api/v1/openapi.yaml',
            '/api/v1/auth/login',
            '/api/v1/webhooks/whatsapp',
            '/api/v1/webhooks/pasarelas/{codigo}',
        ];
        if (in_array($patron, $publicosSinAuth, true)) {
            return [];
        }

        $publicosTenant = [
            '/api/v1/publico/edicion-actual',
            '/api/v1/publico/catalogo',
            '/api/v1/publico/cotizar',
            '/api/v1/publico/cupos/bloquear',
            '/api/v1/publico/checkout',
            '/api/v1/publico/portal/solicitar-acceso',
            '/api/v1/publico/portal/verificar-acceso',
        ];
        if (in_array($patron, $publicosTenant, true)) {
            return [['TenantHeader' => []]];
        }

        if (str_starts_with($patron, '/api/v1/publico/portal/')) {
            return [['CookieClienteAuth' => []]];
        }

        return [['BearerAdminAuth' => []]];
    }

    private static function generarOperationIdUnico(
        string $patron,
        string $metodoHttp,
        mixed $accion,
        array &$operationIdsUsados
    ): string {
        $base = '';
        if (is_array($accion) && count($accion) === 2) {
            [$clase, $metodo] = $accion;
            $partesClase = explode('\\', (string) $clase);
            $corto = str_replace('Controlador', '', end($partesClase));
            $base = lcfirst($corto) . ucfirst((string) $metodo);
        } else {
            $limpio = preg_replace('/[^a-zA-Z0-9]+/', ' ', str_replace('/api/v1/', '', $patron));
            $palabras = array_filter(explode(' ', trim((string) $limpio)));
            $camel = strtolower($metodoHttp);
            foreach ($palabras as $p) {
                $camel .= ucfirst(strtolower($p));
            }
            $base = $camel;
        }

        if (!isset($operationIdsUsados[$base])) {
            $operationIdsUsados[$base] = true;
            return $base;
        }

        $conVerbo = $base . ucfirst(strtolower($metodoHttp));
        if (!isset($operationIdsUsados[$conVerbo])) {
            $operationIdsUsados[$conVerbo] = true;
            return $conVerbo;
        }

        $i = 2;
        while (isset($operationIdsUsados[$conVerbo . $i])) {
            $i++;
        }
        $final = $conVerbo . $i;
        $operationIdsUsados[$final] = true;
        return $final;
    }

    private static function generarResumenYDescripcion(
        string $patron,
        string $metodoHttp,
        mixed $accion,
        string $tag
    ): array {
        if (is_array($accion) && count($accion) === 2) {
            [$clase, $metodo] = $accion;
            $partesClase = explode('\\', (string) $clase);
            $corto = end($partesClase);
            $palabras = preg_replace('/([a-z])([A-Z])/', '$1 $2', (string) $metodo);
            $resumen = ucfirst(strtolower((string) $palabras)) . " ({$tag})";
            $descripcion = "Endpoint REST `{$metodoHttp} {$patron}` despachado por `{$corto}::{$metodo}()`. Aplica aislamiento multi-tenant por `organizacion_id` y controles de seguridad RBAC.";
            return [$resumen, $descripcion];
        }

        return [
            "Endpoint {$metodoHttp} {$patron}",
            "Contrato oficial para `{$metodoHttp} {$patron}` dentro del dominio {$tag}."
        ];
    }

    /**
     * Especificaciones ricas detalladas para los endpoints estratégicos de V1.1 y V1.2.
     */
    private static function construirRutasDetalladas(): array
    {
        $rutas = [];

        // 1. SISTEMA Y SALUD + ESPECIFICACIÓN OPENAPI
        $rutas['/api/v1/estado'] = [
            'get' => [
                'tags' => ['Sistema y Salud'],
                'summary' => 'Estado operativo de la API',
                'description' => 'Retorna metadatos de salud, versión, entorno y hora del servidor.',
                'operationId' => 'obtenerEstadoSistema',
                'responses' => [
                    '200' => [
                        'description' => 'Estado del sistema disponible',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/SobreRespuestaExito'],
                                'example' => [
                                    'exito' => true,
                                    'codigo' => 200,
                                    'mensaje' => 'API CandelariaAPP V1 en funcionamiento.',
                                    'datos' => [
                                        'plataforma' => 'CandelariaAPP',
                                        'version' => '1.2.0',
                                        'fase' => 'V1.2 Analítica Multianual y Certificación Contractual',
                                        'entorno' => 'desarrollo',
                                        'php' => '8.3.30',
                                        'hora_servidor' => '2026-10-14 00:00:00'
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $rutas['/api/v1/openapi.json'] = [
            'get' => [
                'tags' => ['Sistema y Salud'],
                'summary' => 'Especificación OpenAPI 3.0.3 en formato JSON',
                'description' => 'Sirve el contrato formal OpenAPI 3.0.3 en formato JSON con cabeceras CORS habilitadas.',
                'operationId' => 'obtenerEspecificacionOpenApiJson',
                'responses' => [
                    '200' => [
                        'description' => 'Documento JSON OpenAPI 3.0.3',
                        'content' => [
                            'application/json' => [
                                'schema' => ['type' => 'object']
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $rutas['/api/v1/openapi.yaml'] = [
            'get' => [
                'tags' => ['Sistema y Salud'],
                'summary' => 'Especificación OpenAPI 3.0.3 en formato YAML',
                'description' => 'Sirve el contrato formal OpenAPI 3.0.3 en formato YAML con cabeceras CORS habilitadas.',
                'operationId' => 'obtenerEspecificacionOpenApiYaml',
                'responses' => [
                    '200' => [
                        'description' => 'Documento YAML OpenAPI 3.0.3',
                        'content' => [
                            'application/yaml' => [
                                'schema' => ['type' => 'string']
                            ]
                        ]
                    ]
                ]
            ]
        ];

        // 2. PORTAL PÚBLICO Y CHECKOUT
        $rutas['/api/v1/publico/edicion-actual'] = [
            'get' => [
                'tags' => ['Portal Público y Checkout'],
                'summary' => 'Resolución de tenant y edición activa',
                'description' => 'Resuelve la organización y su edición folclórica activa a partir del slug o subdominio. Respuesta fail-closed si la organización no existe o está inactiva.',
                'operationId' => 'resolverEdicionActualPublico',
                'parameters' => [
                    [
                        'name' => 'slug',
                        'in' => 'query',
                        'required' => false,
                        'description' => 'Slug único de la organización (ej: og_estudio)',
                        'schema' => ['type' => 'string', 'example' => 'og_estudio']
                    ]
                ],
                'responses' => [
                    '200' => [
                        'description' => 'Organización y festividad resuelta con éxito',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/SobreRespuestaExito'],
                            ]
                        ]
                    ],
                    '404' => ['$ref' => '#/components/responses/ErrorNoEncontrado']
                ]
            ]
        ];

        $rutas['/api/v1/publico/catalogo'] = [
            'get' => [
                'tags' => ['Portal Público y Checkout'],
                'summary' => 'Catálogo comercial público',
                'description' => 'Lista los ítems y paquetes comercializables para la festividad activa del tenant.',
                'operationId' => 'listarCatalogoPublico',
                'responses' => [
                    '200' => [
                        'description' => 'Catálogo de ítems disponibles',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/SobreRespuestaExito'],
                            ]
                        ]
                    ],
                    '404' => ['$ref' => '#/components/responses/ErrorNoEncontrado']
                ]
            ]
        ];

        $rutas['/api/v1/publico/cotizar'] = [
            'post' => [
                'tags' => ['Portal Público y Checkout'],
                'summary' => 'Cotizador determinista en backend',
                'description' => 'Calcula subtotales, base imponible e IGV del 18% garantizando el cómputo exacto en centavos desde el backend.',
                'operationId' => 'cotizarPublico',
                'requestBody' => [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/CotizacionRequest']
                        ]
                    ]
                ],
                'responses' => [
                    '200' => [
                        'description' => 'Cotización calculada de forma determinista',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/SobreRespuestaExito']
                            ]
                        ]
                    ],
                    '422' => ['$ref' => '#/components/responses/ErrorValidacion']
                ]
            ]
        ];

        $rutas['/api/v1/publico/cupos/bloquear'] = [
            'post' => [
                'tags' => ['Portal Público y Checkout'],
                'summary' => 'Bloqueo temporal de cupos (TTL 15 minutos)',
                'description' => 'Reserva cupos temporalmente de forma atómica para prevenir sobreventa concurrente durante el proceso de pago.',
                'operationId' => 'bloquearCupos',
                'requestBody' => [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/BloquearCuposRequest']
                        ]
                    ]
                ],
                'responses' => [
                    '200' => [
                        'description' => 'Cupos bloqueados temporalmente con token criptográfico',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/SobreRespuestaExito']
                            ]
                        ]
                    ],
                    '409' => ['$ref' => '#/components/responses/ErrorConflictoAforo'],
                    '422' => ['$ref' => '#/components/responses/ErrorValidacion']
                ]
            ]
        ];

        $rutas['/api/v1/publico/checkout'] = [
            'post' => [
                'tags' => ['Portal Público y Checkout'],
                'summary' => 'Checkout soberano de cliente / invitado',
                'description' => 'Procesa la compra con voucher manual o pasarela sandbox, validando consentimiento expreso Ley 29733 y token de cupos activo.',
                'operationId' => 'procesarCheckout',
                'requestBody' => [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/CheckoutRequest']
                        ]
                    ]
                ],
                'responses' => [
                    '200' => [
                        'description' => 'Checkout procesado exitosamente',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/SobreRespuestaExito']
                            ]
                        ]
                    ],
                    '422' => ['$ref' => '#/components/responses/ErrorValidacion']
                ]
            ]
        ];

        // 3. PORTAL DEL CLIENTE (ESPACIO DEL CLIENTE & APP MÓVIL)
        $rutas['/api/v1/publico/portal/solicitar-acceso'] = [
            'post' => [
                'tags' => ['Portal del Cliente'],
                'summary' => 'Solicitud de acceso passwordless (OTP / Magic Link)',
                'description' => "Envía un código OTP de 6 dígitos por WhatsApp (TTL 15m) o un Magic Link por correo (TTL 24h).\n\n"
                    . "**Blindaje de Seguridad DT-V11-015:**\n"
                    . "- Respuesta homogénea que previene enumeración de cuentas.\n"
                    . "- Rate limiting activo (máximo 3 solicitudes por ventana de 15 minutos).",
                'operationId' => 'solicitarAccesoCliente',
                'requestBody' => [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/SolicitarAccesoRequest']
                        ]
                    ]
                ],
                'responses' => [
                    '200' => [
                        'description' => 'Solicitud procesada (mensaje homogéneo que previene enumeración)',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/SobreRespuestaExito']
                            ]
                        ]
                    ],
                    '429' => ['$ref' => '#/components/responses/ErrorRateLimit']
                ]
            ]
        ];

        $rutas['/api/v1/publico/portal/verificar-acceso'] = [
            'post' => [
                'tags' => ['Portal del Cliente'],
                'summary' => 'Verificación de acceso y emisión de sesión',
                'description' => "Valida el OTP o Magic Link.\n\n"
                    . "**Blindaje DT-V11-015:**\n"
                    . "- Consumo de un solo uso mediante operación atómica (`UPDATE ... WHERE utilizado = 0`).\n"
                    . "- Bloqueo por fuerza bruta al 3er intento fallido.\n"
                    . "- Emisión de token CSPRNG de 256 bits (`cli_ses_*`) con cookie HttpOnly y SameSite.\n"
                    . "- Rotación completa anti-fijación de sesión.",
                'operationId' => 'verificarAccesoCliente',
                'requestBody' => [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/VerificarAccesoRequest']
                        ]
                    ]
                ],
                'responses' => [
                    '200' => [
                        'description' => 'Autenticación exitosa y cookie de sesión emitida',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/SobreRespuestaExito']
                            ]
                        ]
                    ],
                    '401' => ['$ref' => '#/components/responses/ErrorNoAutorizado']
                ]
            ]
        ];

        $rutas['/api/v1/publico/portal/perfil'] = [
            'get' => [
                'tags' => ['Portal del Cliente'],
                'summary' => 'Perfil del cliente autenticado',
                'description' => 'Retorna la información de la persona, cliente, y festividad activa. Requiere sesión activa del cliente.',
                'operationId' => 'obtenerPerfilCliente',
                'security' => [['CookieClienteAuth' => []]],
                'responses' => [
                    '200' => [
                        'description' => 'Perfil del cliente',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/SobreRespuestaExito']
                            ]
                        ]
                    ],
                    '401' => ['$ref' => '#/components/responses/ErrorNoAutorizado']
                ]
            ],
            'put' => [
                'tags' => ['Portal del Cliente'],
                'summary' => 'Actualización del perfil de contacto',
                'description' => "Permite actualizar WhatsApp, correo y consentimiento promocional. El documento de identidad es estrictamente inmutable a nivel de backend.",
                'operationId' => 'actualizarPerfilCliente',
                'security' => [['CookieClienteAuth' => []]],
                'requestBody' => [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/ActualizarPerfilRequest']
                        ]
                    ]
                ],
                'responses' => [
                    '200' => [
                        'description' => 'Perfil actualizado exitosamente',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/SobreRespuestaExito']
                            ]
                        ]
                    ],
                    '401' => ['$ref' => '#/components/responses/ErrorNoAutorizado'],
                    '422' => ['$ref' => '#/components/responses/ErrorValidacion']
                ]
            ]
        ];

        $rutas['/api/v1/publico/portal/estado-cuenta'] = [
            'get' => [
                'tags' => ['Portal del Cliente'],
                'summary' => 'Estado de cuenta, contratos y saldos',
                'description' => 'Retorna el resumen consolidado de contratos, pagos verificados y saldo pendiente.',
                'operationId' => 'obtenerEstadoCuentaCliente',
                'security' => [['CookieClienteAuth' => []]],
                'responses' => [
                    '200' => [
                        'description' => 'Estado de cuenta consolidado',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/SobreRespuestaExito']
                            ]
                        ]
                    ],
                    '401' => ['$ref' => '#/components/responses/ErrorNoAutorizado']
                ]
            ]
        ];

        $rutas['/api/v1/publico/portal/recibo/{id}'] = [
            'get' => [
                'tags' => ['Portal del Cliente'],
                'summary' => 'Descarga de recibo de caja interno en PDF',
                'description' => "Transmite el binario PDF del recibo interno de caja. Incorpora validación Anti-IDOR: rechaza con HTTP 403 si el recibo no pertenece al cliente autenticado.",
                'operationId' => 'descargarReciboPdfCliente',
                'security' => [['CookieClienteAuth' => []]],
                'parameters' => [
                    [
                        'name' => 'id',
                        'in' => 'path',
                        'required' => true,
                        'description' => 'ID del pago o del recibo',
                        'schema' => ['type' => 'integer']
                    ]
                ],
                'responses' => [
                    '200' => [
                        'description' => 'Archivo binario PDF del recibo',
                        'content' => [
                            'application/pdf' => [
                                'schema' => ['type' => 'string', 'format' => 'binary']
                            ]
                        ]
                    ],
                    '401' => ['$ref' => '#/components/responses/ErrorNoAutorizado'],
                    '403' => ['$ref' => '#/components/responses/ErrorAccesoDenegado'],
                    '404' => ['$ref' => '#/components/responses/ErrorNoEncontrado']
                ]
            ]
        ];

        $rutas['/api/v1/publico/portal/mis-reservas'] = [
            'get' => [
                'tags' => ['Portal del Cliente'],
                'summary' => 'Listado de reservas del cliente',
                'description' => 'Lista las reservas confirmadas del titular con vinculación a conjunto folclórico y bloque.',
                'operationId' => 'listarMisReservasCliente',
                'security' => [['CookieClienteAuth' => []]],
                'responses' => [
                    '200' => [
                        'description' => 'Listado de reservas asociadas',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/SobreRespuestaExito']
                            ]
                        ]
                    ],
                    '401' => ['$ref' => '#/components/responses/ErrorNoAutorizado']
                ]
            ]
        ];

        $rutas['/api/v1/publico/portal/reserva/{id}/boleto-qr'] = [
            'get' => [
                'tags' => ['Portal del Cliente'],
                'summary' => 'Token criptográfico y boleto QR para la reserva',
                'description' => 'Genera el token HMAC-SHA256 y payload QR verificable en campo sin conexión.',
                'operationId' => 'obtenerBoletoQrCliente',
                'security' => [['CookieClienteAuth' => []]],
                'parameters' => [
                    ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]
                ],
                'responses' => [
                    '200' => [
                        'description' => 'Token QR y firma generados',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/SobreRespuestaExito']
                            ]
                        ]
                    ],
                    '401' => ['$ref' => '#/components/responses/ErrorNoAutorizado'],
                    '403' => ['$ref' => '#/components/responses/ErrorAccesoDenegado']
                ]
            ]
        ];

        $rutas['/api/v1/publico/portal/mis-galerias'] = [
            'get' => [
                'tags' => ['Portal del Cliente'],
                'summary' => 'Galerías fotográficas asignadas al cliente',
                'description' => 'Lista las galerías y proyectos de postproducción pertenecientes al cliente.',
                'operationId' => 'listarMisGaleriasCliente',
                'security' => [['CookieClienteAuth' => []]],
                'responses' => [
                    '200' => [
                        'description' => 'Listado de galerías fotográficas',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/SobreRespuestaExito']
                            ]
                        ]
                    ],
                    '401' => ['$ref' => '#/components/responses/ErrorNoAutorizado']
                ]
            ]
        ];

        $rutas['/api/v1/publico/portal/galerias/{id}/fotos'] = [
            'get' => [
                'tags' => ['Portal del Cliente'],
                'summary' => 'Fotografías de una galería para selección (*photo proofing*)',
                'description' => 'Lista las fotografías en formato web con marca de agua y estado de selección favorito.',
                'operationId' => 'listarFotosGaleriaCliente',
                'security' => [['CookieClienteAuth' => []]],
                'parameters' => [
                    ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]
                ],
                'responses' => [
                    '200' => [
                        'description' => 'Fotografías y estado de selección del cliente',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/SobreRespuestaExito']
                            ]
                        ]
                    ],
                    '401' => ['$ref' => '#/components/responses/ErrorNoAutorizado'],
                    '403' => ['$ref' => '#/components/responses/ErrorAccesoDenegado']
                ]
            ]
        ];

        $rutas['/api/v1/publico/portal/galerias/{id}/seleccionar'] = [
            'post' => [
                'tags' => ['Portal del Cliente'],
                'summary' => 'Marcar o desmarcar fotografía favorita',
                'description' => 'Persiste la selección del cliente y notas de retoque en la tabla `postproduccion_selecciones`.',
                'operationId' => 'guardarSeleccionFotoCliente',
                'security' => [['CookieClienteAuth' => []]],
                'parameters' => [
                    ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]
                ],
                'requestBody' => [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/SeleccionarFotoRequest']
                        ]
                    ]
                ],
                'responses' => [
                    '200' => [
                        'description' => 'Selección persistida exitosamente',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/SobreRespuestaExito']
                            ]
                        ]
                    ],
                    '401' => ['$ref' => '#/components/responses/ErrorNoAutorizado'],
                    '403' => ['$ref' => '#/components/responses/ErrorAccesoDenegado']
                ]
            ]
        ];

        $rutas['/api/v1/publico/portal/mis-selecciones'] = [
            'get' => [
                'tags' => ['Portal del Cliente'],
                'summary' => 'Listado global consolidado de fotos favoritas seleccionadas',
                'description' => 'Retorna todas las fotografías marcadas por el cliente a través de sus distintos proyectos.',
                'operationId' => 'listarMisSeleccionesCliente',
                'security' => [['CookieClienteAuth' => []]],
                'responses' => [
                    '200' => [
                        'description' => 'Listado de selecciones favoritas del cliente',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/SobreRespuestaExito']
                            ]
                        ]
                    ],
                    '401' => ['$ref' => '#/components/responses/ErrorNoAutorizado']
                ]
            ]
        ];

        $rutas['/api/v1/publico/portal/descargar-multimedia/{id}'] = [
            'get' => [
                'tags' => ['Portal del Cliente'],
                'summary' => 'Descarga de archivo multimedia / fotografía original',
                'description' => "Transmite el archivo de imagen.\n\n"
                    . "**Regla de Seguridad Canónica DT-V11-015 (5 Compuertas Simultáneas):**\n"
                    . "La descarga de originales de alta resolución exige autorización positiva simultánea:\n"
                    . "1. Sesión activa del cliente.\n"
                    . "2. Titularidad estricta sobre el proyecto.\n"
                    . "3. Correspondencia de tenant y edición.\n"
                    . "4. Hito formal de entrega publicado y vigente (`postproduccion_entregas`). No basta salir de `EN_EDICION`.\n"
                    . "5. Archivo en estado `ACTIVO` (no revocado ni suprimido por Ley 29733) y ausencia de bloqueos operativos.",
                'operationId' => 'descargarMultimediaCliente',
                'security' => [['CookieClienteAuth' => []]],
                'parameters' => [
                    ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']],
                    ['name' => 'tipo', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string', 'enum' => ['preview', 'original'], 'default' => 'preview']]
                ],
                'responses' => [
                    '200' => [
                        'description' => 'Archivo multimedia transmitido de forma segura',
                        'content' => [
                            'image/jpeg' => ['schema' => ['type' => 'string', 'format' => 'binary']]
                        ]
                    ],
                    '401' => ['$ref' => '#/components/responses/ErrorNoAutorizado'],
                    '403' => ['$ref' => '#/components/responses/ErrorAccesoDenegado']
                ]
            ]
        ];

        $rutas['/api/v1/publico/portal/cerrar-sesion'] = [
            'post' => [
                'tags' => ['Portal del Cliente'],
                'summary' => 'Cierre de sesión del cliente',
                'description' => 'Invalida inmediatamente el token en la base de datos y destruye la cookie de sesión.',
                'operationId' => 'cerrarSesionCliente',
                'security' => [['CookieClienteAuth' => []]],
                'responses' => [
                    '200' => [
                        'description' => 'Sesión cerrada exitosamente',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/SobreRespuestaExito']
                            ]
                        ]
                    ]
                ]
            ]
        ];

        // 4. OPERACIÓN MÓVIL Y VALIDACIÓN QR
        $rutas['/api/v1/operaciones/offline/paquete'] = [
            'get' => [
                'tags' => ['Operación Móvil y QR'],
                'summary' => 'Descarga de manifiesto criptográfico para validación offline',
                'description' => "Descarga el paquete de boletos emitidos y hashes criptográficos de la edición activa para que los dispositivos móviles de campo puedan validar accesos sin conexión a Internet.",
                'operationId' => 'descargarPaqueteOffline',
                'security' => [['BearerAdminAuth' => []]],
                'parameters' => [
                    ['name' => 'edicion_id', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'integer']]
                ],
                'responses' => [
                    '200' => [
                        'description' => 'Paquete de datos offline descargado exitosamente',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/SobreRespuestaExito']
                            ]
                        ]
                    ],
                    '401' => ['$ref' => '#/components/responses/ErrorNoAutorizado']
                ]
            ]
        ];

        $rutas['/api/v1/operaciones/offline/validar-online'] = [
            'post' => [
                'tags' => ['Operación Móvil y QR'],
                'summary' => 'Validación inmediata de credencial QR en modo online',
                'description' => 'Verifica la autenticidad, firma HMAC-SHA256 y vigencia de un boleto QR directamente contra el backend cuando el dispositivo tiene conectividad.',
                'operationId' => 'validarBoletoQrOnline',
                'security' => [['BearerAdminAuth' => []]],
                'requestBody' => [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/ValidarQrOnlineRequest']
                        ]
                    ]
                ],
                'responses' => [
                    '200' => [
                        'description' => 'Resultado de la validación del boleto',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/SobreRespuestaExito']
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $rutas['/api/v1/operaciones/offline/sincronizar'] = [
            'post' => [
                'tags' => ['Operación Móvil y QR'],
                'summary' => 'Sincronización por lotes de lecturas de campo sin conexión',
                'description' => "Procesa un lote de validaciones tomadas en modo offline por un dispositivo móvil.\n\n"
                    . "**Gobernanza DT-V11-013:**\n"
                    . "- Conciliación idempotente por identificador de lectura.\n"
                    . "- Detección y registro de conflictos por doble check-in o concurrencia entre dispositivos.\n"
                    . "- Persistencia en el log de auditoría sin corrupción de datos.",
                'operationId' => 'sincronizarLoteOffline',
                'security' => [['BearerAdminAuth' => []]],
                'requestBody' => [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/SincronizarLoteRequest']
                        ]
                    ]
                ],
                'responses' => [
                    '200' => [
                        'description' => 'Lote conciliado y resultados procesados',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/SobreRespuestaExito']
                            ]
                        ]
                    ],
                    '422' => ['$ref' => '#/components/responses/ErrorValidacion']
                ]
            ]
        ];

        $rutas['/api/v1/operaciones/offline/lotes'] = [
            'get' => [
                'tags' => ['Operación Móvil y QR'],
                'summary' => 'Historial y auditoría de lotes sincronizados',
                'description' => 'Consulta el historial de lotes procesados por dispositivo móvil con conteo de aprobados, duplicados y conflictos.',
                'operationId' => 'listarHistorialLotesOffline',
                'security' => [['BearerAdminAuth' => []]],
                'responses' => [
                    '200' => [
                        'description' => 'Historial de lotes offline',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/SobreRespuestaExito']
                            ]
                        ]
                    ]
                ]
            ]
        ];

        // 5. PROGRAMACIÓN FOLCLÓRICA (FASE 3.1)
        $rutas['/api/v1/folclore/tipos-danza'] = [
            'get' => [
                'tags' => ['Programación Folclórica'],
                'summary' => 'Catálogo oficial de danzas folclóricas',
                'description' => 'Lista las danzas registradas (Morenadas, Caporales, Diabladas, Sikuris, Tinkus, etc.) categorizadas por categoría oficial.',
                'operationId' => 'listarTiposDanza',
                'security' => [['BearerAdminAuth' => []]],
                'responses' => [
                    '200' => [
                        'description' => 'Lista de danzas folclóricas',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/SobreRespuestaExito']
                            ]
                        ]
                    ]
                ]
            ],
            'post' => [
                'tags' => ['Programación Folclórica'],
                'summary' => 'Crear tipo de danza',
                'operationId' => 'crearTipoDanza',
                'security' => [['BearerAdminAuth' => []]],
                'requestBody' => [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'required' => ['codigo', 'nombre', 'categoria'],
                                'properties' => [
                                    'codigo' => ['type' => 'string', 'example' => 'MORENADA'],
                                    'nombre' => ['type' => 'string', 'example' => 'Morenada'],
                                    'categoria' => ['type' => 'string', 'enum' => ['LUCES', 'AUTÓCTONOS'], 'example' => 'LUCES']
                                ]
                            ]
                        ]
                    ]
                ],
                'responses' => [
                    '201' => ['description' => 'Tipo de danza creado']
                ]
            ]
        ];

        $rutas['/api/v1/folclore/conjuntos'] = [
            'get' => [
                'tags' => ['Programación Folclórica'],
                'summary' => 'Catálogo maestro de conjuntos y comparsas',
                'description' => 'Lista las agrupaciones folclóricas del tenant con su danza vinculada.',
                'operationId' => 'listarConjuntosFolcloricos',
                'security' => [['BearerAdminAuth' => []]],
                'responses' => [
                    '200' => [
                        'description' => 'Listado de conjuntos',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/SobreRespuestaExito']
                            ]
                        ]
                    ]
                ]
            ],
            'post' => [
                'tags' => ['Programación Folclórica'],
                'summary' => 'Registrar nuevo conjunto folclórico',
                'operationId' => 'crearConjuntoFolclorico',
                'security' => [['BearerAdminAuth' => []]],
                'requestBody' => [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'required' => ['nombre', 'tipo_danza_id'],
                                'properties' => [
                                    'nombre' => ['type' => 'string', 'example' => 'Poderosa y Espectacular Morenada Bellavista'],
                                    'tipo_danza_id' => ['type' => 'integer', 'example' => 1],
                                    'siglas' => ['type' => 'string', 'example' => 'MB'],
                                    'anio_fundacion' => ['type' => 'integer', 'example' => 1980]
                                ]
                            ]
                        ]
                    ]
                ],
                'responses' => [
                    '201' => ['description' => 'Conjunto registrado']
                ]
            ]
        ];

        $rutas['/api/v1/folclore/seguimiento/tablero'] = [
            'get' => [
                'tags' => ['Programación Folclórica'],
                'summary' => 'Tablero de seguimiento y control de paso en vivo',
                'description' => 'Expone el estado operativo en tiempo real de los conjuntos en la parada o concurso (EN_ESPERA, EN_ESCENA, CULMINADO, OBSERVADO).',
                'operationId' => 'obtenerTableroOperativoFolclore',
                'security' => [['BearerAdminAuth' => []]],
                'responses' => [
                    '200' => [
                        'description' => 'Tablero operativo en vivo',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/SobreRespuestaExito']
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $rutas['/api/v1/folclore/seguimiento/turnos/{id}/estado'] = [
            'put' => [
                'tags' => ['Programación Folclórica'],
                'summary' => 'Actualizar estado operativo del turno de un conjunto',
                'description' => 'Actualiza el paso del conjunto en el recorrido oficial con control de concurrencia optimista (`version_bloqueo`).',
                'operationId' => 'actualizarEstadoTurnoFolclore',
                'security' => [['BearerAdminAuth' => []]],
                'parameters' => [
                    ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]
                ],
                'requestBody' => [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'required' => ['estado', 'version_bloqueo'],
                                'properties' => [
                                    'estado' => ['type' => 'string', 'enum' => ['EN_ESPERA', 'EN_ESCENA', 'CULMINADO', 'OBSERVADO']],
                                    'version_bloqueo' => ['type' => 'integer', 'example' => 1],
                                    'incidencia' => ['type' => 'string', 'nullable' => true]
                                ]
                            ]
                        ]
                    ]
                ],
                'responses' => [
                    '200' => ['description' => 'Estado actualizado'],
                    '409' => ['$ref' => '#/components/responses/ErrorConflictoConcurrencia']
                ]
            ]
        ];

        // 6. COMUNICACIONES Y OUTBOX
        $rutas['/api/v1/comunicaciones/consentimientos'] = [
            'get' => [
                'tags' => ['Comunicaciones y Consentimientos'],
                'summary' => 'Listar consentimientos de comunicación (Ley 29733)',
                'description' => 'Lista los consentimientos transaccionales y promocionales registrados por canal en la organización.',
                'operationId' => 'listarConsentimientosComunicacion',
                'security' => [['BearerAdminAuth' => []]],
                'responses' => [
                    '200' => [
                        'description' => 'Listado de consentimientos',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/SobreRespuestaExito']
                            ]
                        ]
                    ]
                ]
            ],
            'post' => [
                'tags' => ['Comunicaciones y Consentimientos'],
                'summary' => 'Registro de consentimiento diferenciado (Ley 29733)',
                'description' => 'Registra el otorgamiento o revocación de consentimientos transaccionales y promocionales por canal.',
                'operationId' => 'registrarConsentimientoComunicacion',
                'security' => [['BearerAdminAuth' => []]],
                'requestBody' => [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'required' => ['cliente_id', 'canal', 'tipo', 'accion'],
                                'properties' => [
                                    'cliente_id' => ['type' => 'integer'],
                                    'canal' => ['type' => 'string', 'enum' => ['WHATSAPP', 'EMAIL', 'SMS']],
                                    'tipo' => ['type' => 'string', 'enum' => ['OPERATIVO', 'PROMOCIONAL']],
                                    'accion' => ['type' => 'string', 'enum' => ['OTORGAR', 'REVOCAR']],
                                    'origen' => ['type' => 'string', 'example' => 'WEB_PORTAL']
                                ]
                            ]
                        ]
                    ]
                ],
                'responses' => [
                    '200' => ['description' => 'Consentimiento registrado']
                ]
            ]
        ];

        $rutas['/api/v1/comunicaciones/outbox/procesar'] = [
            'post' => [
                'tags' => ['Comunicaciones y Consentimientos'],
                'summary' => 'Procesar bandeja de salida (Outbox) de mensajes encolados',
                'description' => 'Despacha los mensajes transaccionales generados por eventos de negocio encolados en la bandeja Outbox.',
                'operationId' => 'procesarOutboxComunicaciones',
                'security' => [['BearerAdminAuth' => []]],
                'responses' => [
                    '200' => [
                        'description' => 'Bandeja Outbox procesada',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/SobreRespuestaExito']
                            ]
                        ]
                    ]
                ]
            ]
        ];

        // 7. POSTPRODUCCIÓN Y GALERÍAS
        $rutas['/api/v1/postproduccion/proyectos'] = [
            'get' => [
                'tags' => ['Postproducción y Galerías'],
                'summary' => 'Listar proyectos fotográficos',
                'operationId' => 'listarProyectosPostproduccion',
                'security' => [['BearerAdminAuth' => []]],
                'responses' => [
                    '200' => ['description' => 'Lista de proyectos']
                ]
            ],
            'post' => [
                'tags' => ['Postproducción y Galerías'],
                'summary' => 'Crear proyecto fotográfico',
                'operationId' => 'crearProyectoPostproduccion',
                'security' => [['BearerAdminAuth' => []]],
                'requestBody' => [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'required' => ['cliente_id', 'titulo'],
                                'properties' => [
                                    'cliente_id' => ['type' => 'integer'],
                                    'reserva_id' => ['type' => 'integer', 'nullable' => true],
                                    'titulo' => ['type' => 'string', 'example' => 'Sesión Morenada Bellavista Bloque A']
                                ]
                            ]
                        ]
                    ]
                ],
                'responses' => [
                    '201' => ['description' => 'Proyecto creado']
                ]
            ]
        ];

        // 8. IDENTIDAD Y AUTENTICACIÓN ADMINISTRATIVA
        $rutas['/api/v1/auth/login'] = [
            'post' => [
                'tags' => ['Identidad y Autenticación Admin'],
                'summary' => 'Autenticación administrativa (Login)',
                'description' => 'Inicia sesión para operadores, fotógrafos y administradores. Emite cookie `candelaria_sesion`.',
                'operationId' => 'loginAdmin',
                'requestBody' => [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'required' => ['usuario', 'password'],
                                'properties' => [
                                    'usuario' => ['type' => 'string', 'example' => 'admin@candelaria.pe'],
                                    'password' => ['type' => 'string', 'format' => 'password', 'example' => 'Secret2026!']
                                ]
                            ]
                        ]
                    ]
                ],
                'responses' => [
                    '200' => [
                        'description' => 'Autenticación exitosa',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/SobreRespuestaExito']
                            ]
                        ]
                    ],
                    '400' => ['$ref' => '#/components/responses/ErrorValidacion'],
                    '401' => ['$ref' => '#/components/responses/ErrorNoAutorizado']
                ]
            ]
        ];

        $rutas['/api/v1/auth/logout'] = [
            'post' => [
                'tags' => ['Identidad y Autenticación Admin'],
                'summary' => 'Cierre de sesión administrativa (Logout)',
                'operationId' => 'logoutAdmin',
                'security' => [['BearerAdminAuth' => []]],
                'responses' => [
                    '200' => ['description' => 'Sesión cerrada exitosamente']
                ]
            ]
        ];

        // 9. DASHBOARD EJECUTIVO Y ANALÍTICA MULTIANUAL (FASE 4.1 — DT-V12-002)
        $rutas['/api/v1/dashboard/analitica-multianual'] = [
            'get' => [
                'tags' => ['Dashboard Ejecutivo'],
                'summary' => 'Motor de Analítica Multianual e Inteligencia Histórica de Ediciones',
                'description' => "Calcula en centavos las series financieras interanuales (YoY), curvas de preventa (*Booking Pace*), cohortes de retención de clientes, participación folclórica y métricas operativas/postproducción para todas las ediciones de la organización.\n\n"
                    . "**Gobernanza DT-V12-002 (DEC-01 Opción 1A):**\n"
                    . "- Exige permiso `dashboard.ejecutivo.ver`.\n"
                    . "- Aislamiento estricto por `organizacion_id`.\n"
                    . "- Prevención de división por cero cuando la edición base registra 0 centavos.",
                'operationId' => 'obtenerAnaliticaMultianualDashboard',
                'security' => [['BearerAdminAuth' => []]],
                'parameters' => [
                    ['name' => 'edicion_ids', 'in' => 'query', 'required' => false, 'description' => 'Lista de IDs de edición separados por coma', 'schema' => ['type' => 'string']],
                    ['name' => 'anio_desde', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'integer']],
                    ['name' => 'anio_hasta', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'integer']],
                    ['name' => 'estado_edicion', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string']],
                    ['name' => 'canal_origen', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string']],
                ],
                'responses' => [
                    '200' => [
                        'description' => 'Analítica multianual consolidada por edición',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/SobreRespuestaExito']
                            ]
                        ]
                    ],
                    '401' => ['$ref' => '#/components/responses/ErrorNoAutorizado'],
                    '403' => ['$ref' => '#/components/responses/ErrorAccesoDenegado']
                ]
            ]
        ];

        $rutas['/api/v1/dashboard/analitica-multianual/exportar'] = [
            'get' => [
                'tags' => ['Dashboard Ejecutivo'],
                'summary' => 'Exportación oficial de Analítica Multianual (CSV, XLSX, PDF)',
                'description' => "Genera y transmite el archivo de Analítica Multianual en formato CSV (con BOM UTF-8 y sanitización anti-Formula Injection), XLSX (OpenXML ECMA-376) o PDF institucional horizontal.\n\n"
                    . "Exige explícitamente el permiso `ediciones.reportes.ingresos.exportar`.",
                'operationId' => 'exportarAnaliticaMultianualDashboard',
                'security' => [['BearerAdminAuth' => []]],
                'parameters' => [
                    ['name' => 'formato', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string', 'enum' => ['csv', 'xlsx', 'pdf'], 'default' => 'csv']],
                    ['name' => 'edicion_ids', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string']],
                    ['name' => 'anio_desde', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'integer']],
                    ['name' => 'anio_hasta', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'integer']],
                ],
                'responses' => [
                    '200' => [
                        'description' => 'Archivo binario o CSV de analítica multianual',
                        'content' => [
                            'text/csv' => ['schema' => ['type' => 'string', 'format' => 'binary']],
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => ['schema' => ['type' => 'string', 'format' => 'binary']],
                            'application/pdf' => ['schema' => ['type' => 'string', 'format' => 'binary']],
                        ]
                    ],
                    '401' => ['$ref' => '#/components/responses/ErrorNoAutorizado'],
                    '403' => ['$ref' => '#/components/responses/ErrorAccesoDenegado'],
                    '422' => ['$ref' => '#/components/responses/ErrorValidacion']
                ]
            ]
        ];

        return $rutas;
    }

    private static function construirComponentes(): array
    {
        return [
            'securitySchemes' => [
                'CookieClienteAuth' => [
                    'type' => 'apiKey',
                    'in' => 'cookie',
                    'name' => 'candelaria_cliente_sesion',
                    'description' => 'Cookie HttpOnly que almacena el token de sesión seguro del cliente (`cli_ses_*`) emitido tras validación passwordless OTP/Magic Link.'
                ],
                'BearerAdminAuth' => [
                    'type' => 'http',
                    'scheme' => 'bearer',
                    'bearerFormat' => 'CSPRNG-Token',
                    'description' => 'Token de autenticación administrativa transmitido en encabezado `Authorization: Bearer <token>` o cookie `candelaria_sesion`.'
                ],
                'TenantHeader' => [
                    'type' => 'apiKey',
                    'in' => 'header',
                    'name' => 'X-Organizacion-Slug',
                    'description' => 'Identificador del tenant para peticiones con resolución multitenant por encabezado.'
                ]
            ],
            'schemas' => [
                'SobreRespuestaExito' => [
                    'type' => 'object',
                    'required' => ['exito', 'codigo', 'mensaje'],
                    'properties' => [
                        'exito' => ['type' => 'boolean', 'example' => true],
                        'codigo' => ['type' => 'integer', 'example' => 200],
                        'mensaje' => ['type' => 'string', 'example' => 'Operación completada con éxito.'],
                        'datos' => ['type' => 'object', 'nullable' => true],
                        'errores' => ['type' => 'array', 'nullable' => true, 'items' => ['type' => 'string']]
                    ]
                ],
                'SobreRespuestaError' => [
                    'type' => 'object',
                    'required' => ['exito', 'codigo', 'mensaje'],
                    'properties' => [
                        'exito' => ['type' => 'boolean', 'example' => false],
                        'codigo' => ['type' => 'integer', 'example' => 422],
                        'mensaje' => ['type' => 'string', 'example' => 'Los datos de la solicitud no son válidos.'],
                        'datos' => ['type' => 'object', 'nullable' => true],
                        'errores' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'campo' => ['type' => 'string', 'example' => 'telefono_whatsapp'],
                                    'mensaje' => ['type' => 'string', 'example' => 'El número de teléfono es obligatorio.']
                                ]
                            ]
                        ]
                    ]
                ],
                'SolicitarAccesoRequest' => [
                    'type' => 'object',
                    'required' => ['tipo', 'destino'],
                    'properties' => [
                        'tipo' => ['type' => 'string', 'enum' => ['WHATSAPP', 'EMAIL'], 'example' => 'WHATSAPP'],
                        'destino' => ['type' => 'string', 'example' => '+51951999888']
                    ]
                ],
                'VerificarAccesoRequest' => [
                    'type' => 'object',
                    'required' => ['tipo', 'destino', 'codigo'],
                    'properties' => [
                        'tipo' => ['type' => 'string', 'enum' => ['WHATSAPP', 'EMAIL'], 'example' => 'WHATSAPP'],
                        'destino' => ['type' => 'string', 'example' => '+51951999888'],
                        'codigo' => ['type' => 'string', 'example' => '482910']
                    ]
                ],
                'ActualizarPerfilRequest' => [
                    'type' => 'object',
                    'properties' => [
                        'telefono_whatsapp' => ['type' => 'string', 'example' => '+51951999888'],
                        'correo_electronico' => ['type' => 'string', 'format' => 'email', 'example' => 'cliente.nuevo@ejemplo.pe'],
                        'consentimiento_promocional' => ['type' => 'boolean', 'example' => true]
                    ]
                ],
                'CotizacionRequest' => [
                    'type' => 'object',
                    'required' => ['lineas'],
                    'properties' => [
                        'lineas' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'required' => ['item_id', 'cantidad'],
                                'properties' => [
                                    'item_id' => ['type' => 'integer', 'example' => 1],
                                    'cantidad' => ['type' => 'integer', 'minimum' => 1, 'example' => 1]
                                ]
                            ]
                        ]
                    ]
                ],
                'BloquearCuposRequest' => [
                    'type' => 'object',
                    'required' => ['item_id', 'cantidad'],
                    'properties' => [
                        'item_id' => ['type' => 'integer', 'example' => 1],
                        'cantidad' => ['type' => 'integer', 'minimum' => 1, 'example' => 1]
                    ]
                ],
                'CheckoutRequest' => [
                    'type' => 'object',
                    'required' => ['token_bloqueo', 'cliente', 'lineas', 'metodo_pago', 'consentimientos'],
                    'properties' => [
                        'token_bloqueo' => ['type' => 'string', 'example' => 'blk_7f8a9b0c1d2e3f'],
                        'cliente' => [
                            'type' => 'object',
                            'required' => ['tipo_documento_id', 'numero_documento', 'nombres', 'apellidos', 'telefono', 'correo'],
                            'properties' => [
                                'tipo_documento_id' => ['type' => 'integer', 'example' => 1],
                                'numero_documento' => ['type' => 'string', 'example' => '44882211'],
                                'nombres' => ['type' => 'string', 'example' => 'Juan'],
                                'apellidos' => ['type' => 'string', 'example' => 'Pérez'],
                                'telefono' => ['type' => 'string', 'example' => '+51951999888'],
                                'correo' => ['type' => 'string', 'format' => 'email', 'example' => 'juan.perez@ejemplo.pe']
                            ]
                        ],
                        'metodo_pago' => ['type' => 'string', 'enum' => ['TRANSFERENCIA_BANCARIA', 'YAPE', 'PLIN', 'PASARELA'], 'example' => 'TRANSFERENCIA_BANCARIA'],
                        'consentimientos' => [
                            'type' => 'object',
                            'required' => ['terminos_condiciones', 'politica_privacidad'],
                            'properties' => [
                                'terminos_condiciones' => ['type' => 'boolean', 'example' => true],
                                'politica_privacidad' => ['type' => 'boolean', 'example' => true]
                            ]
                        ]
                    ]
                ],
                'SeleccionarFotoRequest' => [
                    'type' => 'object',
                    'required' => ['archivo_id', 'seleccionada'],
                    'properties' => [
                        'archivo_id' => ['type' => 'integer', 'example' => 10],
                        'seleccionada' => ['type' => 'boolean', 'example' => true],
                        'notas' => ['type' => 'string', 'nullable' => true, 'example' => 'Por favor retocar iluminación en el rostro']
                    ]
                ],
                'ValidarQrOnlineRequest' => [
                    'type' => 'object',
                    'required' => ['token_qr', 'dispositivo_id'],
                    'properties' => [
                        'token_qr' => ['type' => 'string', 'example' => 'cnd_qr_8f9a0b1c2d3e4f'],
                        'dispositivo_id' => ['type' => 'string', 'example' => 'MOVIL-PUNO-TERMINAL-01']
                    ]
                ],
                'SincronizarLoteRequest' => [
                    'type' => 'object',
                    'required' => ['id_lote', 'dispositivo_id', 'lecturas'],
                    'properties' => [
                        'id_lote' => ['type' => 'string', 'example' => 'LOTE-20261013-001'],
                        'dispositivo_id' => ['type' => 'string', 'example' => 'MOVIL-PUNO-TERMINAL-01'],
                        'lecturas' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'required' => ['id_lectura', 'token_qr', 'timestamp_lectura', 'resultado_offline'],
                                'properties' => [
                                    'id_lectura' => ['type' => 'string', 'example' => 'LEC-001'],
                                    'token_qr' => ['type' => 'string', 'example' => 'cnd_qr_8f9a0b1c2d3e4f'],
                                    'timestamp_lectura' => ['type' => 'string', 'example' => '2026-10-13 10:15:30'],
                                    'resultado_offline' => ['type' => 'string', 'example' => 'PERMITIDO']
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'responses' => [
                'ErrorSolicitudInvalida' => [
                    'description' => 'Solicitud inválida o parámetros faltantes/malformados.',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/SobreRespuestaError'],
                            'example' => [
                                'exito' => false,
                                'codigo' => 400,
                                'mensaje' => 'Parámetros de entrada inválidos o incompletos.'
                            ]
                        ]
                    ]
                ],
                'ErrorNoAutorizado' => [
                    'description' => 'No autorizado. Se requiere token o sesión válida.',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/SobreRespuestaError'],
                            'example' => [
                                'exito' => false,
                                'codigo' => 401,
                                'mensaje' => 'No autenticado. Inicie sesión para acceder a este recurso.'
                            ]
                        ]
                    ]
                ],
                'ErrorAccesoDenegado' => [
                    'description' => 'Acceso denegado (Anti-IDOR, CSRF o falta de permisos RBAC).',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/SobreRespuestaError'],
                            'example' => [
                                'exito' => false,
                                'codigo' => 403,
                                'mensaje' => 'Acceso denegado: El recurso solicitado no pertenece a su cuenta.'
                            ]
                        ]
                    ]
                ],
                'ErrorNoEncontrado' => [
                    'description' => 'Recurso no encontrado.',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/SobreRespuestaError'],
                            'example' => [
                                'exito' => false,
                                'codigo' => 404,
                                'mensaje' => 'El recurso solicitado no fue encontrado.'
                            ]
                        ]
                    ]
                ],
                'ErrorMetodoNoPermitido' => [
                    'description' => 'Operación o eliminación física prohibida por gobernanza de integridad.',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/SobreRespuestaError'],
                            'example' => [
                                'exito' => false,
                                'codigo' => 405,
                                'mensaje' => 'Eliminación física prohibida por gobernanza. Utilice desactivación lógica.'
                            ]
                        ]
                    ]
                ],
                'ErrorConflictoAforo' => [
                    'description' => 'Conflicto de aforo o cupos agotados.',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/SobreRespuestaError'],
                            'example' => [
                                'exito' => false,
                                'codigo' => 409,
                                'mensaje' => 'No hay cupos disponibles suficientes para completar la operación.'
                            ]
                        ]
                    ]
                ],
                'ErrorConflictoConcurrencia' => [
                    'description' => 'Conflicto de concurrencia optimista (versión de registro desfasada).',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/SobreRespuestaError'],
                            'example' => [
                                'exito' => false,
                                'codigo' => 409,
                                'mensaje' => 'El registro fue modificado concurrentemente por otro operador. Recargue los datos.'
                            ]
                        ]
                    ]
                ],
                'ErrorValidacion' => [
                    'description' => 'Error de validación semántica de datos en el backend.',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/SobreRespuestaError']
                        ]
                    ]
                ],
                'ErrorRateLimit' => [
                    'description' => 'Límite de solicitudes excedido (Rate Limiting).',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/SobreRespuestaError'],
                            'example' => [
                                'exito' => false,
                                'codigo' => 429,
                                'mensaje' => 'Ha excedido el límite de solicitudes permitidas. Por favor espere 15 minutos.'
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Serializador recursivo a formato YAML estricto en PHP puro.
     */
    public static function arrayToYaml(mixed $data, int $indent = 0): string
    {
        $espacios = str_repeat('  ', $indent);
        $salida = '';

        if (!is_array($data)) {
            return self::formatearValorEscalar($data);
        }

        $esSecuencial = array_is_list($data);

        foreach ($data as $clave => $valor) {
            if ($esSecuencial) {
                if (is_array($valor)) {
                    $salida .= $espacios . "- " . ltrim(self::arrayToYaml($valor, $indent + 1));
                } else {
                    $salida .= $espacios . "- " . self::formatearValorEscalar($valor) . "\n";
                }
            } else {
                $claveStr = (string) $clave;
                if (is_array($valor)) {
                    if (empty($valor)) {
                        $salida .= $espacios . $claveStr . ": {}\n";
                    } else {
                        $salida .= $espacios . $claveStr . ":\n" . self::arrayToYaml($valor, $indent + 1);
                    }
                } else {
                    $salida .= $espacios . $claveStr . ": " . self::formatearValorEscalar($valor) . "\n";
                }
            }
        }

        return $salida;
    }

    private static function formatearValorEscalar(mixed $val): string
    {
        if ($val === null) return 'null';
        if ($val === true) return 'true';
        if ($val === false) return 'false';
        if (is_int($val) || is_float($val)) return (string) $val;

        $str = (string) $val;
        if (str_contains($str, "\n")) {
            $lineas = explode("\n", $str);
            $res = "|\n";
            foreach ($lineas as $l) {
                $res .= "    " . $l . "\n";
            }
            return rtrim($res);
        }

        if (
            preg_match('/[:#\{\}\[\],&\*\?\|\-<>=!%@`]/', $str)
            || is_numeric($str)
            || in_array(strtolower($str), ['true', 'false', 'null', 'yes', 'no'], true)
            || str_starts_with($str, ' ') || str_ends_with($str, ' ')
            || empty($str)
        ) {
            return '"' . addcslashes($str, '"\\') . '"';
        }

        return $str;
    }
}

// Ejecución directa cuando se invoca por CLI
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    $spec = GeneradorOpenApi::construirEspecificacion();

    $dirDocs = __DIR__ . '/../docs/api';
    if (!is_dir($dirDocs)) {
        mkdir($dirDocs, 0755, true);
    }
    $jsonOutput = json_encode($spec, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    file_put_contents($dirDocs . '/openapi.json', $jsonOutput);

    $yamlOutput = GeneradorOpenApi::arrayToYaml($spec);
    file_put_contents($dirDocs . '/openapi.yaml', $yamlOutput);

    $dirPubDoc = __DIR__ . '/../publico/documentacion';
    if (!is_dir($dirPubDoc)) {
        mkdir($dirPubDoc, 0755, true);
    }
    file_put_contents($dirPubDoc . '/openapi.json', $jsonOutput);
    file_put_contents($dirPubDoc . '/openapi.yaml', $yamlOutput);

    $totalOperaciones = 0;
    foreach ($spec['paths'] as $metodos) {
        $totalOperaciones += count($metodos);
    }

    echo "Especificación OpenAPI 3.0.3 (V1.2) generada exitosamente ({$totalOperaciones} operaciones en " . count($spec['paths']) . " rutas URI):\n";
    echo " - docs/api/openapi.json\n";
    echo " - docs/api/openapi.yaml\n";
    echo " - publico/documentacion/openapi.json\n";
    echo " - publico/documentacion/openapi.yaml\n";
}
