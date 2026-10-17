<?php
declare(strict_types=1);

function resolverConfiguracionEndpointPositiva(
    string $patron,
    string $metodoHttp,
    array $f,
    string $sufijo,
    string $tmpLogoPath
): array {
    $reemplazos = [
        'id'             => $f['usuarioAId'],
        'lineaId'        => $f['cotizacionLineaId'],
        'ventaId'        => $f['ventaDocId'],
        'participanteId' => $f['participanteId'],
        'prestacionId'   => $f['prestacionId'],
        'asignacionId'   => $f['asignacionRecursoId'],
        'codigo'         => 'CULQI',
        'edicionId'      => $f['edicionAId'],
        'actividadId'    => $f['actividadId'],
        'galeriaId'      => $f['galeriaId'],
        'reservaId'      => $f['reservaDocId'],
    ];

    $query = [];
    $post = [];
    $files = [];
    $headers = [];
    $rawBody = null;
    $esperado = 200;

    // -------------------------------------------------------------
    // Ajuste contextual de ID según el recurso
    // -------------------------------------------------------------
    if (str_starts_with($patron, '/api/v1/usuarios/')) {
        if ($patron === '/api/v1/usuarios/{id}' && $metodoHttp === 'DELETE') {
            $reemplazos['id'] = $f['usuarioModificableId'];
        } elseif (in_array($patron, ['/api/v1/usuarios/{id}', '/api/v1/usuarios/{id}/estado', '/api/v1/usuarios/{id}/roles', '/api/v1/usuarios/{id}/restablecer-clave'], true)) {
            $reemplazos['id'] = $f['usuarioModificableId'];
        } else {
            $reemplazos['id'] = $f['usuarioAId'];
        }
    } elseif (str_starts_with($patron, '/api/v1/ediciones/')) {
        if (str_contains($patron, '/cierre') || str_contains($patron, '/reabrir')) {
            $reemplazos['id'] = ($patron === '/api/v1/ediciones/{id}/reabrir') ? $f['edicionParaReabrirId'] : $f['edicionCerradaId'];
        } elseif (str_contains($patron, '/cerrar')) {
            $reemplazos['id'] = $f['edicionParaCerrarId'];
        } elseif (str_contains($patron, '/estado')) {
            $reemplazos['id'] = $f['edicionCambioEstadoId'];
        } else {
            $reemplazos['id'] = $f['edicionAId'];
        }
    } elseif (str_starts_with($patron, '/api/v1/clientes/')) {
        if ($patron === '/api/v1/clientes/{id}/estado') {
            $reemplazos['id'] = $f['clienteCambioEstadoId'];
        } elseif ($patron === '/api/v1/clientes/{id}' && in_array($metodoHttp, ['PUT', 'POST', 'PATCH'], true)) {
            $reemplazos['id'] = $f['clienteModificableId'];
        } else {
            $reemplazos['id'] = $f['clienteAId'];
        }
    } elseif (str_starts_with($patron, '/api/v1/crm/oportunidades/')) {
        $reemplazos['id'] = $f['oportunidadId'];
    } elseif (str_starts_with($patron, '/api/v1/crm/interacciones/')) {
        $reemplazos['id'] = $f['interaccionId'];
    } elseif (str_starts_with($patron, '/api/v1/crm/origenes/')) {
        $reemplazos['id'] = $f['origenId'];
    } elseif (str_starts_with($patron, '/api/v1/catalogo/categorias/')) {
        $reemplazos['id'] = $f['categoriaId'];
    } elseif (str_starts_with($patron, '/api/v1/catalogo/items/')) {
        $reemplazos['id'] = $f['itemId'];
    } elseif (str_starts_with($patron, '/api/v1/catalogo/paquetes/')) {
        $reemplazos['id'] = $f['paqueteId'];
    } elseif (str_starts_with($patron, '/api/v1/catalogo/ofertas/items/')) {
        $reemplazos['id'] = $f['ofertaItemId'];
    } elseif (str_starts_with($patron, '/api/v1/catalogo/ofertas/paquetes/')) {
        $reemplazos['id'] = $f['ofertaPaqueteId'];
    } elseif (str_starts_with($patron, '/api/v1/catalogo/tarifas/items/')) {
        $reemplazos['id'] = $f['tarifaItemId'];
    } elseif (str_starts_with($patron, '/api/v1/catalogo/tarifas/paquetes/')) {
        $reemplazos['id'] = $f['tarifaPaqueteId'];
    } elseif (str_starts_with($patron, '/api/v1/cotizaciones/')) {
        if ($patron === '/api/v1/cotizaciones/{id}/rechazar') {
            $reemplazos['id'] = $f['cotizacionParaRechazarId'];
        } elseif ($patron === '/api/v1/cotizaciones/{id}/anular') {
            $reemplazos['id'] = $f['cotizacionParaAnularId'];
        } elseif ($patron === '/api/v1/cotizaciones/{id}/aceptar') {
            $reemplazos['id'] = $f['cotizacionParaAceptarId'];
        } elseif ($patron === '/api/v1/cotizaciones/{id}/revision') {
            $reemplazos['id'] = $f['cotizacionParaRevisionId'];
        } elseif ($patron === '/api/v1/cotizaciones/{id}/emitir') {
            $reemplazos['id'] = $f['cotizacionParaEmitirId'];
        } elseif ($patron === '/api/v1/cotizaciones/{id}/lineas/{lineaId}') {
            $reemplazos['id'] = $f['cotizacionBorradorId'];
            $reemplazos['lineaId'] = $f['cotizacionLineaEliminarId'];
        } elseif (in_array($patron, ['/api/v1/cotizaciones/{id}', '/api/v1/cotizaciones/{id}/lineas', '/api/v1/cotizaciones/{id}/lineas/{lineaId}/descuento', '/api/v1/cotizaciones/{id}/descuento-global'], true)) {
            $reemplazos['id'] = $f['cotizacionBorradorId'];
        } else {
            $reemplazos['id'] = $f['cotizacionDocId'];
        }
    } elseif (str_starts_with($patron, '/api/v1/ventas/')) {
        if ($patron === '/api/v1/ventas/{id}/cancelar') {
            $reemplazos['id'] = $f['ventaParaCancelarId'];
        } elseif ($patron === '/api/v1/ventas/{id}/anular') {
            $reemplazos['id'] = $f['ventaParaAnularId'];
        } elseif ($patron === '/api/v1/ventas/{id}/liquidar') {
            $reemplazos['id'] = $f['ventaParaLiquidarId'];
        } else {
            $reemplazos['id'] = $f['ventaDocId'];
        }
    } elseif (str_starts_with($patron, '/api/v1/reservas/')) {
        if ($patron === '/api/v1/reservas/{id}/cancelar') {
            $reemplazos['id'] = $f['reservaParaCancelarId'];
        } elseif ($patron === '/api/v1/reservas/desde-venta/{ventaId}') {
            $reemplazos['ventaId'] = $f['ventaSinReservaId'];
        } elseif ($patron === '/api/v1/reservas/{id}/participantes/{participanteId}') {
            $reemplazos['id'] = $f['reservaDocId'];
            $reemplazos['participanteId'] = $f['participanteEliminarId'];
        } elseif ($patron === '/api/v1/reservas/prestaciones/{prestacionId}/programar') {
            $reemplazos['prestacionId'] = $f['prestacionParaProgramarId'];
        } elseif (str_contains($patron, '/prestaciones/{prestacionId}/reprogramar')) {
            $reemplazos['prestacionId'] = $f['prestacionParaReprogramarId'];
        } else {
            $reemplazos['id'] = $f['reservaDocId'];
            $reemplazos['reservaId'] = $f['reservaDocId'];
        }
    } elseif (str_starts_with($patron, '/api/v1/operaciones/salidas/')) {
        if ($patron === '/api/v1/operaciones/salidas/{id}/checkin') {
            $reemplazos['id'] = $f['salidaId'];
        } elseif ($patron === '/api/v1/operaciones/salidas/{id}/despachar') {
            $reemplazos['id'] = $f['salidaDespacharId'];
        } elseif ($patron === '/api/v1/operaciones/salidas/{id}/finalizar') {
            $reemplazos['id'] = $f['salidaFinalizarId'];
        } elseif ($patron === '/api/v1/operaciones/salidas/{id}/interrumpir') {
            $reemplazos['id'] = $f['salidaInterrumpirId'];
        } elseif ($patron === '/api/v1/operaciones/salidas/{id}/cancelar') {
            $reemplazos['id'] = $f['salidaCancelarId'];
        } else {
            $reemplazos['id'] = $f['salidaDocId'];
        }
    } elseif (str_starts_with($patron, '/api/v1/operaciones/proveedores/')) {
        $reemplazos['id'] = $f['proveedorId'];
    } elseif (str_starts_with($patron, '/api/v1/operaciones/recursos/')) {
        $reemplazos['id'] = $f['recursoId'];
    } elseif (str_starts_with($patron, '/api/v1/operaciones/entregas/')) {
        if ($patron === '/api/v1/operaciones/entregas/{id}/despachar') {
            $reemplazos['id'] = $f['entregaDespacharId'];
        } else {
            $reemplazos['id'] = $f['entregaId'];
        }
    } elseif (str_starts_with($patron, '/api/v1/pagos/')) {
        if ($patron === '/api/v1/pagos/{id}/verificar') {
            $reemplazos['id'] = $f['pagoParaVerificarId'];
        } elseif ($patron === '/api/v1/pagos/{id}/reembolsar') {
            $reemplazos['id'] = $f['pagoParaReembolsarId'];
        } else {
            $reemplazos['id'] = $f['pagoDocId'];
        }
    } elseif (str_starts_with($patron, '/api/v1/cuentas-bancarias/')) {
        $reemplazos['id'] = $f['cuentaBancariaId'];
    } elseif (str_starts_with($patron, '/api/v1/postproduccion/proyectos/')) {
        $reemplazos['id'] = $f['proyectoId'];
    } elseif (str_starts_with($patron, '/api/v1/postproduccion/archivos/')) {
        $reemplazos['id'] = $f['archivoId'];
    } elseif (str_starts_with($patron, '/api/v1/folclore/tipos-danza/')) {
        $reemplazos['id'] = $f['tipoDanzaId'];
    } elseif (str_starts_with($patron, '/api/v1/folclore/conjuntos/')) {
        $reemplazos['id'] = $f['conjuntoId'];
    } elseif (str_starts_with($patron, '/api/v1/folclore/bloques/')) {
        $reemplazos['id'] = $f['bloqueId'];
    } elseif (str_starts_with($patron, '/api/v1/folclore/inscripciones/')) {
        $reemplazos['id'] = $f['inscripcionId'];
    } elseif (str_starts_with($patron, '/api/v1/folclore/actividades/')) {
        $reemplazos['id'] = $f['actividadId'];
    } elseif (str_starts_with($patron, '/api/v1/folclore/programaciones/')) {
        $reemplazos['id'] = $f['programacionId'];
    } elseif (str_starts_with($patron, '/api/v1/folclore/turnos/')) {
        $reemplazos['id'] = ($patron === '/api/v1/folclore/turnos/{id}' && $metodoHttp === 'DELETE')
            ? $f['turnoEliminarId']
            : $f['turnoId'];
    } elseif (str_starts_with($patron, '/api/v1/folclore/seguimiento/turnos/')) {
        $reemplazos['id'] = $f['turnoParaSeguimientoId'];
    } elseif (str_starts_with($patron, '/api/v1/publico/portal/')) {
        if (str_contains($patron, '/recibo/')) {
            $reemplazos['id'] = $f['pagoDocId'];
        } elseif (str_contains($patron, '/reserva/')) {
            $reemplazos['id'] = $f['reservaDocId'];
        } elseif (str_contains($patron, '/venta/')) {
            $reemplazos['id'] = $f['ventaDocId'];
        } elseif (str_contains($patron, '/mis-galerias/{id}')) {
            $reemplazos['id'] = $f['proyectoId'];
        } elseif (str_contains($patron, '/galerias/')) {
            $reemplazos['id'] = $f['galeriaId'];
        } elseif (str_contains($patron, '/descargar-multimedia/')) {
            $reemplazos['id'] = $f['archivoId'];
        } elseif (str_contains($patron, '/entregas/')) {
            $reemplazos['id'] = $f['entregaMultimediaId'];
        }
    } elseif (str_starts_with($patron, '/api/v1/documentos/')) {
        if (str_contains($patron, '/cotizacion/')) {
            $reemplazos['id'] = $f['cotizacionDocId'];
        } elseif (str_contains($patron, '/contrato/')) {
            $reemplazos['id'] = $f['ventaDocId'];
        } elseif (str_contains($patron, '/recibo/')) {
            $reemplazos['id'] = $f['pagoDocId'];
        } elseif (str_contains($patron, '/boleto/')) {
            $reemplazos['reservaId'] = $f['reservaDocId'];
        }
    } elseif (str_starts_with($patron, '/api/v1/comunicaciones/')) {
        $reemplazos['id'] = $f['conversacionId'];
        if (str_contains($patron, '/mensajes/{id}/intentos')) {
            $reemplazos['id'] = $f['mensajeId'];
        } elseif (str_contains($patron, '/campanas/{id}')) {
            $reemplazos['id'] = ($patron === '/api/v1/comunicaciones/campanas/{id}/aprobar') ? $f['campanaSoDId'] : $f['campanaId'];
        } elseif (str_contains($patron, '/automatizaciones/{id}')) {
            $reemplazos['id'] = $f['automatizacionId'];
        }
    }

    // -------------------------------------------------------------
    // 1. DELETE con HTTP 405 de gobernanza
    // -------------------------------------------------------------
    if (in_array($patron, [
        '/api/v1/usuarios/{id}',
        '/api/v1/clientes/{id}',
        '/api/v1/crm/oportunidades/{id}',
        '/api/v1/crm/origenes/{id}',
        '/api/v1/crm/interacciones/{id}',
    ], true) && $metodoHttp === 'DELETE') {
        $esperado = 405;
        return compact('reemplazos', 'query', 'post', 'files', 'headers', 'rawBody', 'esperado');
    }

    // -------------------------------------------------------------
    // 2. POST con HTTP 201 según OpenAPI
    // -------------------------------------------------------------
    if ($patron === '/api/v1/folclore/tipos-danza' && $metodoHttp === 'POST') {
        $esperado = 201;
        $post = ['codigo' => 'TD_DIAB_' . $sufijo, 'nombre' => 'DIABLADA PUNEÑA ' . $sufijo, 'activo' => 1];
        return compact('reemplazos', 'query', 'post', 'files', 'headers', 'rawBody', 'esperado');
    }
    if ($patron === '/api/v1/folclore/conjuntos' && $metodoHttp === 'POST') {
        $esperado = 201;
        $post = ['tipo_danza_id' => $f['tipoDanzaId'], 'codigo' => 'CJ_DIAB_' . $sufijo, 'nombre' => 'DIABLADA CENTRAL ' . $sufijo, 'activo' => 1];
        return compact('reemplazos', 'query', 'post', 'files', 'headers', 'rawBody', 'esperado');
    }
    if ($patron === '/api/v1/postproduccion/proyectos' && $metodoHttp === 'POST') {
        $esperado = 201;
        $post = [
            'edicion_id'           => $f['edicionAId'],
            'cliente_id'          => $f['clienteAId'],
            'reserva_id'          => $f['reservaDocId'],
            'prestacion_id'       => $f['prestacionDocId'],
            'codigo_proyecto'     => 'PRY-POS-' . $sufijo,
            'titulo'              => 'Proyecto Positivo ' . $sufijo,
            'fecha_limite_entrega' => date('Y-m-d', strtotime('+30 days')),
        ];
        return compact('reemplazos', 'query', 'post', 'files', 'headers', 'rawBody', 'esperado');
    }

    // -------------------------------------------------------------
    // 3. Casos con Query Parameters
    // -------------------------------------------------------------
    if ($patron === '/api/v1/personas/buscar') {
        $query['q'] = 'Auditor';
    } elseif ($patron === '/api/v1/catalogo/items/buscar') {
        $query['q'] = 'Butaca';
    } elseif ($patron === '/api/v1/catalogo/ofertas') {
        $query['edicion_id'] = (string) $f['edicionAId'];
    } elseif ($patron === '/api/v1/crm/interacciones') {
        $query['cliente_id'] = (string) $f['clienteAId'];
        $query['oportunidad_id'] = (string) $f['oportunidadId'];
    } elseif ($patron === '/api/v1/crm/clientes/{id}/timeline') {
        $reemplazos['id'] = $f['clienteAId'];
    } elseif ($patron === '/api/v1/ediciones/{id}/reportes/exportar') {
        $query['tipo'] = 'LIBRO_INGRESOS';
        $query['formato'] = 'csv';
    } elseif ($patron === '/api/v1/dashboard/analitica-multianual') {
        $query['edicion_ids'] = (string) $f['edicionAId'];
    } elseif ($patron === '/api/v1/dashboard/analitica-multianual/exportar') {
        $query['formato'] = 'csv';
        $query['edicion_ids'] = (string) $f['edicionAId'];
    } elseif ($patron === '/api/v1/webhooks/whatsapp' && $metodoHttp === 'GET') {
        $query['org_id'] = (string) $f['orgAId'];
        $query['hub_mode'] = 'subscribe';
        $query['hub_verify_token'] = 'candelaria_webhook_token_2026';
        $query['hub_challenge'] = 'challenge_123';
    } elseif ($patron === '/api/v1/operaciones/offline/paquete') {
        $query['edicion_id'] = (string) $f['edicionAId'];
    }

    // -------------------------------------------------------------
    // 4. Cargas de Archivos (Uploads)
    // -------------------------------------------------------------
    if ($patron === '/api/v1/organizacion/branding/logo') {
        $files['logo'] = ['name' => 'logo.png', 'type' => 'image/png', 'tmp_name' => $tmpLogoPath, 'error' => UPLOAD_ERR_OK, 'size' => (int)filesize($tmpLogoPath)];
    } elseif ($patron === '/api/v1/organizacion/branding/isotipo') {
        $files['isotipo'] = ['name' => 'iso.png', 'type' => 'image/png', 'tmp_name' => $tmpLogoPath, 'error' => UPLOAD_ERR_OK, 'size' => (int)filesize($tmpLogoPath)];
    } elseif ($patron === '/api/v1/postproduccion/proyectos/{id}/galerias/{galeriaId}/archivos') {
        $files['archivo'] = ['name' => 'foto.jpg', 'type' => 'image/jpeg', 'tmp_name' => $tmpLogoPath, 'error' => UPLOAD_ERR_OK, 'size' => (int)filesize($tmpLogoPath)];
    }

    // -------------------------------------------------------------
    // 5. Payloads Específicos para POST, PUT y PATCH
    // -------------------------------------------------------------
    switch ($patron) {
        case '/api/v1/auth/login':
            $post = ['login' => 'auditor_a_' . $sufijo, 'password' => 'CertV12Secret!'];
            break;
        case '/api/v1/usuarios':
            if ($metodoHttp === 'POST') {
                $post = [
                    'organizacion_id'    => $f['orgAId'],
                    'persona_id'         => $f['personaDisponibleId'],
                    'nombre_usuario'     => 'usr_pos_' . $sufijo,
                    'nombre_completo'    => 'Usuario Positivo ' . $sufijo,
                    'correo_electronico' => 'usr_pos_' . $sufijo . '@candelaria.pe',
                    'contrasena'         => 'Clave123!@#',
                    'rol_ids'            => [1],
                ];
            }
            break;
        case '/api/v1/usuarios/{id}':
            if ($metodoHttp === 'PUT') {
                $post = [
                    'nombre_completo'    => 'Usuario Modificado',
                    'correo_electronico' => 'modificado.' . $sufijo . '@candelaria.pe',
                ];
            }
            break;
        case '/api/v1/usuarios/{id}/estado':
            $post = ['estado' => 'ACTIVO'];
            break;
        case '/api/v1/usuarios/{id}/roles':
            $post = ['rol_ids' => [1]];
            break;
        case '/api/v1/usuarios/{id}/restablecer-clave':
            $post = ['nueva_contrasena' => 'NuevaClave123!@#'];
            break;
        case '/api/v1/organizacion':
            if ($metodoHttp === 'PUT') {
                $post = [
                    'nombre_comercial' => 'Candelaria Cert A Mod',
                    'razon_social'     => 'Org Certificación A SAC',
                ];
            }
            break;
        case '/api/v1/configuracion':
            if ($metodoHttp === 'PUT') {
                $post = [
                    'ambito' => 'ORGANIZACION',
                    'codigo' => 'sistema.moneda_defecto',
                    'valor'  => 'PEN',
                ];
            }
            break;
        case '/api/v1/ediciones':
            if ($metodoHttp === 'POST') {
                $esperado = 201;
                $post = [
                    'codigo'       => 'ed-pos-' . $sufijo,
                    'nombre'       => 'Edicion Positiva ' . $sufijo,
                    'anio'         => 2027,
                    'fecha_inicio' => '2027-02-01',
                    'fecha_fin'    => '2027-02-15',
                ];
            }
            break;
        case '/api/v1/ediciones/{id}':
            if ($metodoHttp === 'PUT') {
                $post = [
                    'nombre'       => 'Edicion A 2026 Modificada',
                    'anio'         => 2026,
                    'fecha_inicio' => '2026-02-01',
                    'fecha_fin'    => '2026-02-15',
                ];
            }
            break;
        case '/api/v1/ediciones/{id}/estado':
            $post = ['nuevo_estado' => 'OPERACION'];
            break;
        case '/api/v1/ediciones/{id}/seleccionar-actual':
            $post = [];
            break;
        case '/api/v1/ediciones/{id}/cerrar':
            $esperado = 201;
            $post = [
                'tipo_cierre'     => 'DEFINITIVO',
                'version_bloqueo' => 1,
            ];
            break;
        case '/api/v1/ediciones/{id}/reabrir':
            $post = [
                'motivo_excepcional'             => 'Ajuste contable justificado de auditoria integral',
                'autorizado_por_usuario_id'      => $f['usuarioAutorizadorId'],
                'clave_confirmacion_autorizador' => 'CertV12Secret!',
                'nuevo_estado'                   => 'POSTPRODUCCION_ENTREGA',
            ];
            break;
        case '/api/v1/clientes':
            if ($metodoHttp === 'POST') {
                $post = [
                    'tipo_persona'       => 'NATURAL',
                    'tipo_documento_id'  => 1,
                    'numero_documento'   => '88990011',
                    'nombres'            => 'Cliente',
                    'apellidos'          => 'Nuevo',
                    'correo_electronico' => 'cli_nuevo_' . $sufijo . '@candelaria.pe',
                    'telefono_movil'     => '+51951990011',
                    'telefono_whatsapp'  => '+51951990011',
                ];
            }
            break;
        case '/api/v1/clientes/{id}':
            if ($metodoHttp === 'PUT') {
                $post = [
                    'nombres'           => 'Cliente Modificado',
                    'apellidos'         => 'Nuevo',
                    'telefono_movil'    => '+51951990011',
                    'telefono_whatsapp' => '+51951990011',
                ];
            }
            break;
        case '/api/v1/clientes/{id}/estado':
            $post = ['estado_comercial' => 'CLIENTE'];
            break;
        case '/api/v1/clientes/{id}/consentimientos':
            $post = [
                'tipo'   => 'OPERATIVO',
                'accion' => 'OTORGAR',
                'canal'  => 'WEB',
            ];
            break;
        case '/api/v1/crm/oportunidades':
            if ($metodoHttp === 'POST') {
                $post = [
                    'cliente_id'          => $f['clienteAId'],
                    'edicion_id'          => $f['edicionAId'],
                    'origen_comercial_id' => $f['origenId'],
                    'titulo'              => 'Nueva Oportunidad ' . $sufijo,
                    'valor_estimado'      => 1000.00,
                    'moneda'              => 'PEN',
                ];
            }
            break;
        case '/api/v1/crm/oportunidades/{id}':
            if ($metodoHttp === 'PUT') {
                $post = [
                    'titulo'         => 'Oportunidad Actualizada',
                    'valor_estimado' => 1500.00,
                ];
            }
            break;
        case '/api/v1/crm/oportunidades/{id}/etapa':
            $post = ['etapa' => 'NEGOCIACION'];
            break;
        case '/api/v1/crm/oportunidades/{id}/asignar':
            $post = ['usuario_asignado_id' => $f['usuarioAId']];
            break;
        case '/api/v1/crm/interacciones':
            if ($metodoHttp === 'POST') {
                $post = [
                    'oportunidad_id' => $f['oportunidadId'],
                    'cliente_id'     => $f['clienteAId'],
                    'canal_id'       => 1,
                    'tipo'           => 'LLAMADA',
                    'direccion'      => 'SALIENTE',
                    'resumen'        => 'Interaccion ' . $sufijo,
                    'detalle'        => 'Detalle llamada de prueba',
                ];
            }
            break;
        case '/api/v1/crm/origenes':
            if ($metodoHttp === 'POST') {
                $post = [
                    'codigo' => 'ORIGEN_' . $sufijo,
                    'nombre' => 'Origen ' . $sufijo,
                    'activo' => 1,
                ];
            }
            break;
        case '/api/v1/crm/origenes/{id}':
            if ($metodoHttp === 'PUT') {
                $post = [
                    'nombre' => 'Origen Modificado',
                    'activo' => 1,
                ];
            }
            break;
        case '/api/v1/crm/origenes/{id}/estado':
            $post = ['activo' => 1];
            break;
        case '/api/v1/catalogo/categorias':
            if ($metodoHttp === 'POST') {
                $post = [
                    'codigo'      => 'CAT_NUEVA_' . $sufijo,
                    'nombre'      => 'Categoria Nueva',
                    'descripcion' => 'Desc',
                    'orden'       => 2,
                ];
            }
            break;
        case '/api/v1/catalogo/categorias/{id}':
            if ($metodoHttp === 'PUT') {
                $post = [
                    'nombre'      => 'Categoria Mod',
                    'descripcion' => 'Desc Mod',
                    'orden'       => 1,
                ];
            }
            break;
        case '/api/v1/catalogo/categorias/{id}/estado':
            $post = ['estado' => 'ACTIVO'];
            break;
        case '/api/v1/catalogo/items':
            if ($metodoHttp === 'POST') {
                $post = [
                    'categoria_id'  => $f['categoriaId'],
                    'codigo'        => 'ITM_NUEVO_' . $sufijo,
                    'nombre'        => 'Item Nuevo',
                    'tipo'          => 'PRODUCTO',
                    'unidad_medida' => 'UNIDAD',
                    'descripcion'   => 'Desc',
                ];
            }
            break;
        case '/api/v1/catalogo/items/{id}':
            if ($metodoHttp === 'PUT') {
                $post = [
                    'categoria_id'  => $f['categoriaId'],
                    'nombre'        => 'Item Modificado',
                    'tipo'          => 'PRODUCTO',
                    'unidad_medida' => 'UNIDAD',
                    'descripcion'   => 'Desc',
                ];
            }
            break;
        case '/api/v1/catalogo/items/{id}/estado':
            $post = ['estado' => 'ACTIVO'];
            break;
        case '/api/v1/catalogo/paquetes':
            if ($metodoHttp === 'POST') {
                $post = [
                    'codigo'      => 'PK_NUEVO_' . $sufijo,
                    'nombre'      => 'Paquete Nuevo',
                    'descripcion' => 'Desc',
                ];
            }
            break;
        case '/api/v1/catalogo/paquetes/{id}':
            if ($metodoHttp === 'PUT') {
                $post = [
                    'nombre'      => 'Paquete Modificado',
                    'descripcion' => 'Desc Mod',
                ];
            }
            break;
        case '/api/v1/catalogo/paquetes/{id}/estado':
            $post = ['estado' => 'ACTIVO'];
            break;
        case '/api/v1/catalogo/paquetes/{id}/composicion':
            $post = [
                'items' => [
                    ['item_comercial_id' => $f['itemId'], 'cantidad' => 1.0, 'orden' => 1],
                ],
            ];
            break;
        case '/api/v1/catalogo/ofertas/items':
            $post = [
                'edicion_id'        => $f['edicionAId'],
                'item_comercial_id' => $f['itemParaOfertarId'],
            ];
            break;
        case '/api/v1/catalogo/ofertas/items/{id}/estado':
            $post = ['estado' => 'ACTIVO'];
            break;
        case '/api/v1/catalogo/ofertas/paquetes':
            $post = [
                'edicion_id' => $f['edicionAId'],
                'paquete_id' => $f['paqueteParaOfertarId'],
            ];
            break;
        case '/api/v1/catalogo/ofertas/paquetes/{id}/estado':
            $post = ['estado' => 'ACTIVO'];
            break;
        case '/api/v1/catalogo/tarifas/items/inicial':
            $post = [
                'oferta_item_id' => $f['ofertaItemSinTarifaId'],
                'moneda'         => 'PEN',
                'precio'         => 280.00,
            ];
            break;
        case '/api/v1/catalogo/tarifas/items/{id}':
            $post = [
                'nuevo_precio'    => 290.00,
                'version_bloqueo' => 1,
                'motivo'          => 'Ajuste anual de tarifa',
            ];
            break;
        case '/api/v1/catalogo/tarifas/paquetes/inicial':
            $post = [
                'oferta_paquete_id' => $f['ofertaPaqueteSinTarifaId'],
                'moneda'            => 'PEN',
                'precio'            => 490.00,
            ];
            break;
        case '/api/v1/catalogo/tarifas/paquetes/{id}':
            $post = [
                'nuevo_precio'    => 510.00,
                'version_bloqueo' => 1,
                'motivo'          => 'Ajuste anual de tarifa paquete',
            ];
            break;
        case '/api/v1/cotizaciones':
            if ($metodoHttp === 'POST') {
                $post = [
                    'edicion_id'     => $f['edicionAId'],
                    'cliente_id'     => $f['clienteAId'],
                    'oportunidad_id' => $f['oportunidadId'],
                    'titulo'         => 'Cotizacion Nueva ' . $sufijo,
                    'moneda'         => 'PEN',
                ];
            }
            break;
        case '/api/v1/cotizaciones/{id}':
            if ($metodoHttp === 'PUT') {
                $post = ['titulo' => 'Cotizacion Modificada'];
            }
            break;
        case '/api/v1/cotizaciones/{id}/lineas':
            $post = [
                'tipo_linea'        => 'ITEM',
                'item_comercial_id' => $f['itemId'],
                'oferta_item_id'    => $f['ofertaItemId'],
                'cantidad'          => 1.0,
                'precio_unitario'   => 250.00,
            ];
            break;
        case '/api/v1/cotizaciones/{id}/lineas/{lineaId}/descuento':
            $post = ['porcentaje_descuento' => 5.0];
            break;
        case '/api/v1/cotizaciones/{id}/descuento-global':
            $post = ['porcentaje_descuento' => 5.0];
            break;
        case '/api/v1/cotizaciones/{id}/emitir':
            $post = ['valido_hasta' => date('Y-m-d', strtotime('+15 days'))];
            break;
        case '/api/v1/cotizaciones/{id}/revision':
            $post = ['motivo' => 'Revision comercial'];
            break;
        case '/api/v1/cotizaciones/{id}/rechazar':
            $post = ['motivo' => 'CLIENTE_DESISTIO'];
            break;
        case '/api/v1/cotizaciones/{id}/anular':
            $post = ['motivo' => 'ERROR_DATOS'];
            break;
        case '/api/v1/ventas/desde-cotizacion':
            $post = ['cotizacion_id' => $f['cotizacionAceptadaId']];
            break;
        case '/api/v1/ventas/{id}/cancelar':
            $post = [
                'motivo'          => 'DESISTIMIENTO_CLIENTE',
                'version_bloqueo' => 1,
            ];
            break;
        case '/api/v1/ventas/{id}/anular':
            $post = [
                'motivo'          => 'ERROR_REGISTRO',
                'version_bloqueo' => 1,
            ];
            break;
        case '/api/v1/reservas/{id}/cancelar':
            $post = ['motivo' => 'Cancelación solicitada por cliente'];
            break;
        case '/api/v1/reservas/{id}/participantes':
            $post = [
                'tipo_documento_id' => 1,
                'numero_documento'  => '88554433',
                'nombres'           => 'Juan',
                'apellidos'         => 'Perez',
                'es_titular'        => false,
            ];
            break;
        case '/api/v1/reservas/prestaciones/{prestacionId}/programar':
            $post = ['fecha_servicio' => '2026-02-06'];
            break;
        case '/api/v1/reservas/prestaciones/{prestacionId}/reprogramar':
            $post = [
                'nueva_fecha'      => '2026-02-07',
                'motivo_categoria' => 'SOLICITUD_CLIENTE',
                'motivo_detalle'   => 'Cambio de fecha solicitado por cliente',
            ];
            break;
        case '/api/v1/operaciones/salidas':
            if ($metodoHttp === 'POST') {
                $post = [
                    'edicion_id'        => $f['edicionAId'],
                    'item_comercial_id' => $f['itemId'],
                    'titulo'            => 'Salida Nueva ' . $sufijo,
                    'fecha_salida'      => '2026-02-06',
                    'hora_citacion'     => '07:00:00',
                    'hora_salida'       => '07:30:00',
                    'punto_encuentro'   => 'Plaza de Armas',
                    'tipo_capacidad'    => 'COLECTIVA',
                    'capacidad_maxima'  => 20,
                ];
            }
            break;
        case '/api/v1/operaciones/salidas/{id}':
            if ($metodoHttp === 'PUT') {
                $post = [
                    'titulo'           => 'Salida Modificada',
                    'capacidad_maxima' => 35,
                ];
            }
            break;
        case '/api/v1/operaciones/salidas/{id}/prestaciones':
            $post = [
                'prestacion_id'      => $f['prestacionDocId'],
                'cantidad_pasajeros' => 1,
            ];
            break;
        case '/api/v1/operaciones/salidas/{id}/recursos':
            $post = [
                'recurso_fisico_id' => $f['recursoId'],
                'rol_operativo'     => 'CONDUCTOR',
            ];
            break;
        case '/api/v1/operaciones/salidas/{id}/checkin':
            if ($metodoHttp === 'POST') {
                $post = [
                    'participante_id'   => $f['participanteId'],
                    'estado_asistencia' => 'PRESENTE',
                ];
            }
            break;
        case '/api/v1/operaciones/salidas/{id}/despachar':
            $post = [];
            break;
        case '/api/v1/operaciones/salidas/{id}/finalizar':
            $post = [];
            break;
        case '/api/v1/operaciones/salidas/{id}/interrumpir':
            $post = ['motivo' => 'Falla mecánica'];
            break;
        case '/api/v1/operaciones/salidas/{id}/cancelar':
            $post = ['motivo' => 'Cancelación por clima'];
            break;
        case '/api/v1/operaciones/salidas/{id}/incidencias':
            if ($metodoHttp === 'POST') {
                $post = [
                    'tipo_incidencia' => 'OTRO',
                    'descripcion'     => 'Demora menor',
                ];
            }
            break;
        case '/api/v1/operaciones/proveedores':
            if ($metodoHttp === 'POST') {
                $post = [
                    'persona_id'               => $f['personaDisponibleId'],
                    'tipo_servicio_principal' => 'TRANSPORTE',
                    'estado'                   => 'ACTIVO',
                ];
            }
            break;
        case '/api/v1/operaciones/proveedores/{id}/suspender':
            $post = ['motivo' => 'Suspensión temporal'];
            break;
        case '/api/v1/operaciones/recursos':
            if ($metodoHttp === 'POST') {
                $post = [
                    'tipo_recurso'   => 'VEHICULO_TERRESTRE',
                    'codigo_interno' => 'BUS-02-' . $sufijo,
                    'nombre'         => 'Bus Sprinter 02',
                    'propiedad_tipo' => 'PROPIO',
                ];
            }
            break;
        case '/api/v1/operaciones/recursos/{id}/estado':
            $post = ['estado' => 'DISPONIBLE'];
            break;
        case '/api/v1/operaciones/entregas/{id}/despachar':
            $post = ['observaciones' => 'Entrega despachada'];
            break;
        case '/api/v1/operaciones/offline/validar-online':
            $post = [
                'token_qr' => 'TOKEN_VALIDO',
                'tipo'     => 'BOLETO',
            ];
            break;
        case '/api/v1/operaciones/offline/sincronizar':
            $post = [
                'lote_uuid' => '550e8400-e29b-41d4-a716-446655440000',
                'eventos'   => [],
            ];
            break;
        case '/api/v1/pagos/manual':
            $post = [
                'venta_id'                  => $f['ventaAId'],
                'monto'                     => 100.00,
                'moneda'                    => 'PEN',
                'metodo_pago'               => 'TRANSFERENCIA_BANCARIA',
                'cuenta_bancaria_id'        => $f['cuentaBancariaId'],
                'numero_operacion_bancaria' => 'OP-998877',
            ];
            break;
        case '/api/v1/pagos/{id}/boucher':
            $post = ['boucher_url' => 'https://candelaria.pe/vouchers/v1.png'];
            break;
        case '/api/v1/pagos/{id}/verificar':
            $post = [
                'aprobar'         => true,
                'version_bloqueo' => 1,
            ];
            break;
        case '/api/v1/pagos/{id}/reembolsar':
            $esperado = 201;
            $post = [
                'monto'          => 50.00,
                'motivo'         => 'DESISTIMIENTO_CLIENTE',
                'motivo_detalle' => 'Cliente desiste de butaca preferencial',
            ];
            break;
        case '/api/v1/ventas/{id}/liquidar':
            $post = ['confirmacion' => true];
            break;
        case '/api/v1/cuentas-bancarias':
            if ($metodoHttp === 'POST') {
                $post = [
                    'banco_nombre'   => 'BBVA',
                    'tipo_cuenta'    => 'CORRIENTE',
                    'moneda'         => 'PEN',
                    'titular_nombre' => 'Candelaria SAC',
                    'numero_cuenta'  => '0011-0000-0000-99',
                    'activo'         => 1,
                ];
            }
            break;
        case '/api/v1/cuentas-bancarias/{id}':
            if ($metodoHttp === 'PUT') {
                $post = [
                    'titular_nombre' => 'Candelaria SAC Mod',
                    'activo'         => 1,
                ];
            }
            break;
        case '/api/v1/pasarelas/{codigo}':
            $post = [
                'modo'          => 'TEST',
                'llave_publica' => 'pk_test_123',
                'llave_privada' => 'sk_test_123',
                'activo'        => 1,
            ];
            break;
        case '/api/v1/webhooks/pasarelas/{codigo}':
            $payloadWh = json_encode([
                'id'   => 'evt_culqi_ok_' . $sufijo,
                'type' => 'charge.succeeded',
                'data' => [
                    'id'            => 'chr_culqi_tx_' . $sufijo,
                    'amount'        => 50000,
                    'currency_code' => 'PEN',
                    'metadata'      => [
                        'venta_id'        => $f['ventaAId'],
                        'organizacion_id' => $f['orgAId'],
                    ],
                    'source' => [
                        'last_four' => '4242',
                        'iin'       => ['card_brand' => 'Visa'],
                    ],
                ],
            ]);
            $rawBody = $payloadWh;
            $post = json_decode($payloadWh, true);
            $query['org_id'] = (string) $f['orgAId'];
            $headers['X-Culqi-Signature'] = hash_hmac('sha256', $payloadWh, $f['secretoCulqi'] ?? ('whsec_culqi_' . $sufijo));
            break;
        case '/api/v1/webhooks/whatsapp':
            if ($metodoHttp === 'POST') {
                $payloadWa = json_encode([
                    'object' => 'whatsapp_business_account',
                    'entry'  => [
                        [
                            'id'      => 'WABA_12345',
                            'changes' => [
                                [
                                    'value' => [
                                        'messaging_product' => 'whatsapp',
                                        'metadata'          => [
                                            'display_phone_number' => '+51999888777',
                                            'phone_number_id'      => '123456789',
                                        ],
                                        'messages'          => [],
                                    ],
                                    'field' => 'messages',
                                ],
                            ],
                        ],
                    ],
                ]);
                $rawBody = $payloadWa;
                $post = json_decode($payloadWa, true);
                $query['org_id'] = (string) $f['orgAId'];
                $headers['X-Hub-Signature-256'] = 'sha256=' . hash_hmac('sha256', $payloadWa, $f['secretoWa']);
            }
            break;
        case '/api/v1/comunicaciones/mensajes/enviar':
            $post = [
                'telefono'   => '+51951111111',
                'plantilla'  => 'notificacion_general',
                'nombre'     => 'Auditor',
                'parametros' => ['Auditor', '123456'],
            ];
            break;
        case '/api/v1/comunicaciones/conversaciones/{id}/responder':
            $post = ['texto' => 'Respuesta de prueba para el cliente'];
            break;
        case '/api/v1/comunicaciones/conversaciones/{id}/cerrar':
            $post = [];
            break;
        case '/api/v1/comunicaciones/conversaciones/{id}/asignar':
            $post = ['operador_id' => $f['usuarioAId']];
            break;
        case '/api/v1/comunicaciones/plantillas':
            if ($metodoHttp === 'POST') {
                $post = [
                    'nombre'      => 'plantilla_' . $sufijo,
                    'categoria'   => 'UTILITY',
                    'idioma'      => 'es_PE',
                    'cuerpo_texto' => 'Hola {{1}}, su turno es {{2}}',
                ];
            }
            break;
        case '/api/v1/comunicaciones/consentimientos':
            if ($metodoHttp === 'POST') {
                $post = [
                    'cliente_id' => $f['clienteAId'],
                    'telefono'   => '+51951111111',
                    'canal'      => 'WHATSAPP',
                    'finalidad'  => 'TRANSACCIONAL_OPERATIVO',
                    'accion'     => 'OTORGAR',
                    'origen'     => 'WEB_CHECKBOX',
                ];
            }
            break;
        case '/api/v1/comunicaciones/campanas':
            if ($metodoHttp === 'POST') {
                $post = [
                    'nombre'       => 'Campana Candelaria ' . $sufijo,
                    'plantilla_id' => $f['plantillaId'],
                ];
            }
            break;
        case '/api/v1/comunicaciones/configuracion':
            if ($metodoHttp === 'POST') {
                $post = [
                    'proveedor_codigo' => 'SIMULADOR_SANDBOX',
                    'modo'             => 'SIMULADOR',
                    'telefono'         => '+51999888777',
                ];
            }
            break;
        case '/api/v1/comunicaciones/simulador/recibir':
            $post = [
                'telefono_remitente' => '+51951111111',
                'mensaje_texto'      => 'Hola deseo información',
            ];
            break;
        case '/api/v1/comunicaciones/outbox/procesar':
            $post = [];
            break;
        case '/api/v1/comunicaciones/automatizaciones':
            if ($metodoHttp === 'POST') {
                $post = [
                    'codigo'            => 'REG_AUT_NEW_' . $sufijo,
                    'nombre'            => 'Aut Candelaria ' . $sufijo,
                    'evento_disparador' => 'PAGO_CONFIRMADO',
                    'plantilla_id'      => $f['plantillaId'],
                    'activo'            => 1,
                ];
            }
            break;
        case '/api/v1/comunicaciones/automatizaciones/{id}':
            if ($metodoHttp === 'PUT') {
                $post = ['nombre' => 'Aut Modificada'];
            }
            break;
        case '/api/v1/comunicaciones/automatizaciones/{id}/estado':
            $post = ['activo' => 1];
            break;
        case '/api/v1/comunicaciones/automatizaciones/probar':
            $post = [
                'regla_id' => $f['automatizacionId'],
                'payload'  => ['monto' => 500, 'cliente_nombre' => 'Auditor'],
            ];
            break;
        case '/api/v1/comunicaciones/automatizaciones/disparar':
            $post = [
                'evento'  => 'PAGO_CONFIRMADO',
                'payload' => ['monto' => 500, 'cliente_nombre' => 'Auditor'],
            ];
            break;
        case '/api/v1/publico/cotizar':
            $post = [
                'lineas' => [
                    ['tipo' => 'PAQUETE', 'id' => $f['paqueteId'], 'cantidad' => 1],
                ],
            ];
            break;
        case '/api/v1/publico/cupos/bloquear':
            $post = [
                'item_id'   => $f['itemId'],
                'cantidad'  => 1,
                'sesion_id' => 'ses_' . $sufijo,
            ];
            break;
        case '/api/v1/publico/checkout':
            $post = [
                'token_bloqueo'   => $f['tokenBloqueo'],
                'cliente'         => [
                    'tipo_documento_id' => 1,
                    'numero_documento'  => '88111111',
                    'nombres'           => 'Auditor',
                    'apellidos'         => 'Contratos A',
                    'correo'            => 'auditor.a.' . $sufijo . '@candelaria.pe',
                    'telefono'          => '+51951111111',
                ],
                'consentimientos' => [
                    'terminos_condiciones' => true,
                    'politica_privacidad'  => true,
                ],
                'metodo_pago'     => 'TRANSFERENCIA_BANCARIA',
                'pago_manual'     => [
                    'numero_operacion'   => 'OP-9988',
                    'banco'              => 'BCP',
                    'fecha_operacion'    => '2026-02-05',
                    'cuenta_bancaria_id' => $f['cuentaBancariaId'],
                ],
                'lineas'          => [
                    ['tipo' => 'ITEM', 'id' => $f['itemId'], 'cantidad' => 1],
                ],
            ];
            $query['org_id'] = (string) $f['orgAId'];
            $headers['X-Tenant-Id'] = (string) $f['orgAId'];
            break;
        case '/api/v1/publico/portal/solicitar-acceso':
            $post = [
                'canal'  => 'EMAIL',
                'correo' => 'auditor.a.' . $sufijo . '@candelaria.pe',
            ];
            break;
        case '/api/v1/publico/portal/verificar-acceso':
            $post = [
                'canal'  => 'EMAIL',
                'correo' => 'auditor.a.' . $sufijo . '@candelaria.pe',
                'token'  => $f['codigoAccesoPortal'],
            ];
            break;
        case '/api/v1/publico/portal/perfil':
            if ($metodoHttp === 'PUT') {
                $post = [
                    'nombres'           => 'Auditor',
                    'apellidos'         => 'Contratos Mod',
                    'telefono_movil'    => '+51951111111',
                    'telefono_whatsapp' => '+51951111111',
                ];
            }
            break;
        case '/api/v1/postproduccion/proyectos/{id}/galerias':
            $post = [
                'titulo' => 'Galeria Nueva',
                'slug'   => 'gal-nueva-' . $sufijo,
            ];
            break;
        case '/api/v1/postproduccion/proyectos/{id}/entregas':
            $post = [
                'titulo'         => 'Entrega 2',
                'numero_entrega' => 2,
                'tipo_entrega'   => 'PARCIAL',
            ];
            break;
        case '/api/v1/publico/portal/galerias/{id}/seleccionar':
            $post = [
                'archivo_id'  => $f['archivoId'],
                'seleccionado' => true,
            ];
            break;
        case '/api/v1/publico/portal/entregas/{id}/responder':
            $post = [
                'aprobada'        => true,
                'observaciones'   => 'Conforme con la entrega',
                'version_bloqueo' => 1,
            ];
            break;
        case '/api/v1/folclore/tipos-danza/{id}':
            if ($metodoHttp === 'PUT') {
                $post = [
                    'nombre' => 'MORENADA MODIFICADA',
                    'activo' => 1,
                ];
            }
            break;
        case '/api/v1/folclore/conjuntos/{id}':
            if ($metodoHttp === 'PUT') {
                $post = [
                    'tipo_danza_id' => $f['tipoDanzaId'],
                    'nombre'        => 'CONJUNTO MORENADA MOD',
                    'activo'        => 1,
                ];
            }
            break;
        case '/api/v1/folclore/conjuntos/{id}/bloques':
            if ($metodoHttp === 'POST') {
                $post = [
                    'codigo' => 'BLQ-02-' . $sufijo,
                    'nombre' => 'BLOQUE MORENOS',
                    'activo' => 1,
                ];
            }
            break;
        case '/api/v1/folclore/bloques/{id}':
            if ($metodoHttp === 'PUT') {
                $post = [
                    'nombre' => 'BLOQUE CHOLITAS MOD',
                    'activo' => 1,
                ];
            }
            break;
        case '/api/v1/folclore/ediciones/{edicionId}/inscripciones':
            if ($metodoHttp === 'POST') {
                $post = [
                    'conjunto_id'                  => $f['conjuntoParaInscribirId'],
                    'cantidad_estimada_danzarines' => 200,
                    'estado'                       => 'CONFIRMADO',
                ];
            }
            break;
        case '/api/v1/folclore/inscripciones/{id}':
            if ($metodoHttp === 'PUT') {
                $post = [
                    'cantidad_estimada_danzarines' => 220,
                    'estado'                       => 'CONFIRMADO',
                ];
            }
            break;
        case '/api/v1/folclore/ediciones/{edicionId}/actividades':
            if ($metodoHttp === 'POST') {
                $post = [
                    'codigo'          => 'ACT-PARADA-' . $sufijo,
                    'titulo'          => 'Gran Parada y Veneracion',
                    'fecha_inicio'    => '2026-02-09',
                    'fecha_fin'       => '2026-02-10',
                    'lugar_principal' => 'Avenida Costanera',
                ];
            }
            break;
        case '/api/v1/folclore/actividades/{id}':
            if ($metodoHttp === 'PUT') {
                $post = [
                    'titulo'          => 'Concurso de Danzas Modificado',
                    'fecha_inicio'    => '2026-02-08',
                    'fecha_fin'       => '2026-02-08',
                    'lugar_principal' => 'Estadio E.T.B.',
                    'estado'          => 'PROGRAMADA',
                ];
            }
            break;
        case '/api/v1/folclore/actividades/{actividadId}/versiones':
            if ($metodoHttp === 'POST') {
                $post = [
                    'edicion_id'           => $f['edicionAId'],
                    'etiqueta_version'     => 'V1.1',
                    'fuente_origen'        => 'Federacion',
                    'fecha_emision_fuente' => date('Y-m-d'),
                ];
            }
            break;
        case '/api/v1/folclore/programaciones/{id}/turnos':
            if ($metodoHttp === 'POST') {
                $post = [
                    'conjunto_edicion_id'   => $f['inscripcionParaTurnoId'],
                    'orden_sorteo'          => 2,
                    'hora_estimada_partida' => '09:00:00',
                ];
            }
            break;
        case '/api/v1/folclore/turnos/{id}':
            if ($metodoHttp === 'PUT') {
                $post = [
                    'orden_sorteo'          => 1,
                    'hora_estimada_partida' => '08:45:00',
                ];
            }
            break;
        case '/api/v1/folclore/turnos/{id}/vincular-salida':
            $post = ['salida_id' => $f['salidaDocId']];
            break;
        case '/api/v1/folclore/seguimiento/turnos/{id}/estado':
            $post = [
                'estado_turno'    => 'EN_PREPARACION',
                'estado'          => 'EN_PREPARACION',
                'observaciones'   => 'Turno en preparación',
                'version_bloqueo' => 1,
            ];
            break;
    }

    return compact('reemplazos', 'query', 'post', 'files', 'headers', 'rawBody', 'esperado');
}
