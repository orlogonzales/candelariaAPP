/**
 * CandelariaAPP V1.1 — Motor de Validación Móvil y Sincronización QR Offline (Fase 3.3 / DT-V11-010)
 */

(function () {
    'use strict';

    // Constantes de almacenamiento local
    const CLAVE_PAQUETE = 'candelaria_validador_paquete_offline';
    const CLAVE_COLA = 'candelaria_validador_cola_sincronizacion';
    const CLAVE_HISTORIAL_SESION = 'candelaria_validador_historial_sesion';

    // Estado en memoria
    let estadoConexion = navigator.onLine ? 'ONLINE' : 'OFFLINE';
    let forzadoOffline = false;
    let streamCamara = null;
    let camaraFrontal = false;
    let scannerActivo = false;
    let detectorBarcode = null;
    let intervaloEscaneo = null;

    // Elementos DOM
    const contenedor = document.getElementById('contenedorValidadorMovil');
    if (!contenedor) return;

    const edicionId = parseInt(contenedor.dataset.edicionId || '1', 10);
    const tokenCsrf = contenedor.dataset.csrf || '';

    const badgeEstadoRed = document.getElementById('badgeEstadoRed');
    const textoEstadoRed = document.getElementById('textoEstadoRed');
    const btnToggleModoRed = document.getElementById('btnToggleModoRed');
    const estadoPaqueteOffline = document.getElementById('estadoPaqueteOffline');
    const detallesPaqueteOffline = document.getElementById('detallesPaqueteOffline');
    const btnDescargarPaquete = document.getElementById('btnDescargarPaquete');
    const btnSincronizarLote = document.getElementById('btnSincronizarLote');
    const badgeColaPendiente = document.getElementById('badgeColaPendiente');
    const videoScanner = document.getElementById('videoScanner');
    const canvasScanner = document.getElementById('canvasScanner');
    const visorEstadoMensaje = document.getElementById('visorEstadoMensaje');
    const inputCodigoManual = document.getElementById('inputCodigoManual');
    const btnValidarManual = document.getElementById('btnValidarManual');
    const tarjetaResultado = document.getElementById('tarjetaResultado');
    const cuerpoResultado = document.getElementById('cuerpoResultado');
    const badgeTotalSesion = document.getElementById('badgeTotalSesion');
    const tbodyBitacoraSesion = document.getElementById('tbodyBitacoraSesion');
    const tbodyHistorialLotes = document.getElementById('tbodyHistorialLotes');
    const btnConmutarCamara = document.getElementById('btnConmutarCamara');

    // Inicialización del motor
    function inicializar() {
        if ('BarcodeDetector' in window) {
            try {
                detectorBarcode = new BarcodeDetector({ formats: ['qr_code'] });
            } catch (e) {
                console.warn('[Validador] BarcodeDetector no compatible con qr_code:', e);
            }
        }

        configurarEventosRed();
        configurarEventosBotones();
        actualizarInfoPaqueteLocal();
        actualizarColaPendienteUI();
        renderizarHistorialSesion();
        iniciarCamara();
        procesarDeepLinking();
    }

    // Configuración de red reactiva
    function configurarEventosRed() {
        window.addEventListener('online', () => {
            if (!forzadoOffline) {
                estadoConexion = 'ONLINE';
                actualizarBadgeRed();
                autoSincronizarCola();
            }
        });

        window.addEventListener('offline', () => {
            if (!forzadoOffline) {
                estadoConexion = 'OFFLINE';
                actualizarBadgeRed();
            }
        });

        btnToggleModoRed.addEventListener('click', () => {
            forzadoOffline = !forzadoOffline;
            if (forzadoOffline) {
                estadoConexion = 'OFFLINE';
                btnToggleModoRed.innerHTML = '<i class="fa-solid fa-toggle-off me-1"></i> Reanudar Online';
                btnToggleModoRed.classList.replace('btn-outline-light', 'btn-danger');
            } else {
                estadoConexion = navigator.onLine ? 'ONLINE' : 'OFFLINE';
                btnToggleModoRed.innerHTML = '<i class="fa-solid fa-toggle-on me-1"></i> Forzar Offline';
                btnToggleModoRed.classList.replace('btn-danger', 'btn-outline-light');
                if (estadoConexion === 'ONLINE') {
                    autoSincronizarCola();
                }
            }
            actualizarBadgeRed();
        });

        actualizarBadgeRed();
    }

    function actualizarBadgeRed() {
        const cola = obtenerCola();
        const totalPendientes = cola.length;

        if (estadoConexion === 'ONLINE') {
            badgeEstadoRed.className = 'badge rounded-pill bg-success px-3 py-2 fs-6 shadow-sm';
            textoEstadoRed.innerHTML = '<i class="fa-solid fa-wifi me-1"></i> ONLINE';
        } else {
            badgeEstadoRed.className = 'badge rounded-pill bg-warning text-dark px-3 py-2 fs-6 shadow-sm';
            textoEstadoRed.innerHTML = `<i class="fa-solid fa-plane me-1"></i> OFFLINE (${totalPendientes})`;
        }
    }

    function configurarEventosBotones() {
        btnDescargarPaquete.addEventListener('click', descargarPaqueteOffline);
        btnSincronizarLote.addEventListener('click', sincronizarColaOffline);
        btnValidarManual.addEventListener('click', () => {
            const codigo = inputCodigoManual.value.trim();
            if (codigo) {
                procesarCodigo(codigo);
                inputCodigoManual.value = '';
            }
        });
        inputCodigoManual.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                btnValidarManual.click();
            }
        });

        if (btnConmutarCamara) {
            btnConmutarCamara.addEventListener('click', () => {
                camaraFrontal = !camaraFrontal;
                detenerCamara();
                iniciarCamara();
            });
        }

        const modalHistorial = document.getElementById('modalHistorialLotes');
        if (modalHistorial) {
            modalHistorial.addEventListener('show.bs.modal', cargarHistorialLotes);
        }
    }

    // Cámara y Escaneo en Vivo
    async function iniciarCamara() {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            mostrarMensajeVisor('La cámara no está disponible en este navegador o entorno no seguro (requiere HTTPS o localhost).', false);
            return;
        }

        try {
            mostrarMensajeVisor('Activando cámara...', true);
            const constraints = {
                video: {
                    facingMode: camaraFrontal ? 'user' : 'environment',
                    width: { ideal: 1280 },
                    height: { ideal: 720 },
                }
            };
            streamCamara = await navigator.mediaDevices.getUserMedia(constraints);
            videoScanner.srcObject = streamCamara;
            await videoScanner.play();
            ocultarMensajeVisor();
            scannerActivo = true;
            iniciarBucleEscaneo();
        } catch (e) {
            console.error('[Validador] Error al acceder a la cámara:', e);
            mostrarMensajeVisor('No se pudo acceder a la cámara. Por favor otorgue permisos o utilice el ingreso manual.', false);
        }
    }

    function detenerCamara() {
        scannerActivo = false;
        if (intervaloEscaneo) {
            clearInterval(intervaloEscaneo);
            intervaloEscaneo = null;
        }
        if (streamCamara) {
            streamCamara.getTracks().forEach(t => t.stop());
            streamCamara = null;
        }
    }

    function mostrarMensajeVisor(texto, cargando = false) {
        visorEstadoMensaje.style.display = 'block';
        visorEstadoMensaje.innerHTML = (cargando ? '<i class="fa-solid fa-circle-notch fa-spin me-2"></i>' : '<i class="fa-solid fa-triangle-exclamation me-2"></i>') + texto;
    }

    function ocultarMensajeVisor() {
        visorEstadoMensaje.style.display = 'none';
    }

    function iniciarBucleEscaneo() {
        if (intervaloEscaneo) clearInterval(intervaloEscaneo);

        intervaloEscaneo = setInterval(async () => {
            if (!scannerActivo || videoScanner.readyState !== videoScanner.HAVE_ENOUGH_DATA) return;

            if (detectorBarcode) {
                try {
                    const barcodes = await detectorBarcode.detect(videoScanner);
                    if (barcodes.length > 0) {
                        const valor = barcodes[0].rawValue;
                        if (valor) {
                            procesarCodigo(valor);
                        }
                    }
                } catch (e) {
                    // Ignorar frames intermedios sin código
                }
            }
        }, 350);
    }

    // Procesamiento central de código (Online u Offline)
    async function procesarCodigo(codigo) {
        if (!codigo) return;

        // Vibración háptica en móvil
        if ('vibrate' in navigator) {
            try { navigator.vibrate(80); } catch (_) {}
        }

        if (estadoConexion === 'ONLINE') {
            await procesarOnline(codigo);
        } else {
            procesarOffline(codigo);
        }
    }

    // Validación Online
    async function procesarOnline(codigo) {
        try {
            renderizarResultadoCargando(codigo);
            const resp = await fetch('/api/v1/operaciones/offline/validar-online', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': tokenCsrf,
                },
                body: JSON.stringify({
                    edicion_id: edicionId,
                    token_qr: codigo,
                    marcar_ingreso: true,
                }),
            });

            const data = await resp.json();
            const resultado = {
                codigo: codigo,
                valido: data.exito,
                resultado_servidor: data.datos?.resultado_codigo || (data.exito ? 'CONFIRMADO' : 'RECHAZADO'),
                motivo: data.mensaje || data.datos?.motivo || 'Validado en servidor.',
                titular: data.datos?.datos?.titular || 'Cliente Candelaria',
                correlativo: data.datos?.datos?.correlativo || codigo,
                concepto: 'Acceso Oficial Candelaria',
                es_provisional: false,
                hora: new Date().toLocaleTimeString(),
            };

            mostrarResultadoUI(resultado);
            registrarHistorialSesion(resultado);
        } catch (e) {
            console.warn('[Validador] Falla en petición online, conmutando a offline:', e);
            procesarOffline(codigo);
        }
    }

    // Validación Offline
    function procesarOffline(codigo) {
        const paquete = obtenerPaqueteLocal();
        if (!paquete) {
            mostrarResultadoUI({
                valido: false,
                codigo: codigo,
                resultado_servidor: 'ERROR_SIN_PAQUETE',
                motivo: 'No se ha descargado el paquete de validación offline. Conéctese a internet para descargar el turno.',
                titular: 'Desconocido',
                correlativo: codigo,
                es_provisional: true,
                hora: new Date().toLocaleTimeString(),
            });
            return;
        }

        // Obtener historial de escaneos de esta sesión para control anti-replay local
        const historial = obtenerHistorialSesion();
        const escaneosPrevios = historial.map(h => h.qr_hash).filter(Boolean);

        // Generar hash básico de coincidencia
        const qrHash = generarHashSimulado(codigo);

        // 1. Verificar lista de revocación
        const revocadas = paquete.lista_revocacion || [];
        const revocada = revocadas.find(r => r.qr_hash === qrHash);
        if (revocada) {
            const res = {
                valido: false,
                codigo: codigo,
                qr_hash: qrHash,
                resultado_servidor: 'RECHAZADO_REVOCADO',
                motivo: `Credencial revocada en servidor: ${revocada.motivo || 'Anulada'}`,
                titular: 'No Autorizado',
                correlativo: codigo,
                es_provisional: true,
                hora: new Date().toLocaleTimeString(),
            };
            mostrarResultadoUI(res);
            registrarHistorialSesion(res);
            return;
        }

        // 2. Control Anti-Replay Local
        if (escaneosPrevios.includes(qrHash)) {
            const res = {
                valido: false,
                codigo: codigo,
                qr_hash: qrHash,
                resultado_servidor: 'CONFLICTO_DUPLICADO',
                motivo: 'Duplicado local: Esta credencial ya fue escaneada en este dispositivo.',
                titular: 'Acceso Denegado',
                correlativo: codigo,
                es_provisional: true,
                hora: new Date().toLocaleTimeString(),
            };
            mostrarResultadoUI(res);
            registrarHistorialSesion(res);
            return;
        }

        // 3. Buscar en el catálogo local
        const credenciales = paquete.resumen_credenciales || {};
        let cred = credenciales[qrHash];

        // Búsqueda por correlativo directo
        if (!cred) {
            for (const k in credenciales) {
                if (credenciales[k].correlativo === codigo.toUpperCase()) {
                    cred = credenciales[k];
                    break;
                }
            }
        }

        if (!cred) {
            const res = {
                valido: false,
                codigo: codigo,
                qr_hash: qrHash,
                resultado_servidor: 'RECHAZADO_INEXISTENTE',
                motivo: 'Credencial no encontrada en el paquete local. Requiere verificación online en servidor.',
                titular: 'No Encontrado',
                correlativo: codigo,
                es_provisional: true,
                hora: new Date().toLocaleTimeString(),
            };
            mostrarResultadoUI(res);
            registrarHistorialSesion(res);
            return;
        }

        // Válido Provisional Offline
        const resValido = {
            valido: true,
            codigo: codigo,
            qr_hash: qrHash,
            resultado_servidor: 'VALIDO_LOCAL_PROVISIONAL',
            motivo: 'Credencial válida localmente (Confirmación provisional en cola offline).',
            titular: cred.titular_mascarado || 'Titular',
            correlativo: cred.correlativo || codigo,
            concepto: cred.concepto || 'Entrada Candelaria',
            asiento: cred.asiento || 'S/N',
            es_provisional: true,
            hora: new Date().toLocaleTimeString(),
        };

        mostrarResultadoUI(resValido);
        registrarHistorialSesion(resValido);
        encolarEventoOffline(resValido);
    }

    function mostrarResultadoUI(res) {
        tarjetaResultado.style.display = 'block';

        let colorBorde = res.valido ? 'border-success bg-light-success' : 'border-danger bg-light-danger';
        let icono = res.valido ? 'fa-circle-check text-success' : 'fa-circle-xmark text-danger';
        let titulo = res.valido ? 'ACCESO PERMITIDO' : 'ACCESO DENEGADO';

        if (res.resultado_servidor === 'CONFLICTO_DUPLICADO') {
            colorBorde = 'border-warning bg-light-warning';
            icono = 'fa-triangle-exclamation text-warning';
            titulo = 'CREDENCIAL DUPLICADA';
        }

        const badgeProvisional = res.es_provisional
            ? '<span class="badge bg-warning text-dark rounded-pill ms-2"><i class="fa-solid fa-clock me-1"></i> Provisional Offline</span>'
            : '<span class="badge bg-success rounded-pill ms-2"><i class="fa-solid fa-check-double me-1"></i> Confirmado Servidor</span>';

        cuerpoResultado.innerHTML = `
            <div class="d-flex align-items-center justify-content-center gap-2 mb-2">
                <i class="fa-solid ${icono} fs-1"></i>
            </div>
            <h4 class="fw-bold mb-1 ${res.valido ? 'text-success' : (res.resultado_servidor === 'CONFLICTO_DUPLICADO' ? 'text-warning' : 'text-danger')}">
                ${titulo} ${badgeProvisional}
            </h4>
            <div class="fs-5 fw-bold text-dark mb-1 font-monospace">${res.correlativo}</div>
            <div class="text-muted fw-semibold mb-2">${res.titular}</div>
            <div class="p-2 rounded bg-white border mb-2 text-start fs-7">
                <div><strong>Detalle:</strong> ${res.concepto || 'Acceso General'}</div>
                <div><strong>Asiento / Bloque:</strong> ${res.asiento || 'General'}</div>
                <div><strong>Diagnóstico:</strong> ${res.motivo}</div>
                <div><strong>Hora:</strong> ${res.hora}</div>
            </div>
        `;
    }

    function renderizarResultadoCargando(codigo) {
        tarjetaResultado.style.display = 'block';
        cuerpoResultado.innerHTML = `
            <div class="py-3 text-center">
                <i class="fa-solid fa-circle-notch fa-spin text-primary fs-2 mb-2"></i>
                <div class="fw-bold">Verificando en servidor central...</div>
                <small class="text-muted font-monospace">${codigo}</small>
            </div>
        `;
    }

    // Gestión del Paquete Offline
    async function descargarPaqueteOffline() {
        btnDescargarPaquete.disabled = true;
        btnDescargarPaquete.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Descargando...';

        try {
            const resp = await fetch(`/api/v1/operaciones/offline/paquete?edicion_id=${edicionId}`, {
                headers: { 'X-CSRF-Token': tokenCsrf }
            });
            const data = await resp.json();

            if (data.exito && data.datos) {
                guardarPaqueteLocal(data.datos);
                actualizarInfoPaqueteLocal();
                alert(`Paquete offline descargado correctamente.\nCredenciales en caché: ${data.datos.total_credenciales}\nRevocadas: ${data.datos.total_revocadas}`);
            } else {
                alert('No se pudo descargar el paquete offline: ' + (data.mensaje || 'Error desconocido'));
            }
        } catch (e) {
            console.error('[Validador] Error descargando paquete:', e);
            alert('Error de conexión al intentar descargar el paquete offline.');
        } finally {
            btnDescargarPaquete.disabled = false;
            btnDescargarPaquete.innerHTML = '<i class="fa-solid fa-cloud-arrow-down me-1"></i> Descargar Turno Offline';
        }
    }

    function guardarPaqueteLocal(paquete) {
        localStorage.setItem(CLAVE_PAQUETE, JSON.stringify(paquete));
    }

    function obtenerPaqueteLocal() {
        const str = localStorage.getItem(CLAVE_PAQUETE);
        if (!str) return null;
        try {
            return JSON.parse(str);
        } catch (_) {
            return null;
        }
    }

    function actualizarInfoPaqueteLocal() {
        const paquete = obtenerPaqueteLocal();
        if (paquete) {
            estadoPaqueteOffline.className = 'text-success fw-bold';
            estadoPaqueteOffline.textContent = 'Activo y Vigente';
            const total = paquete.total_credenciales || Object.keys(paquete.resumen_credenciales || {}).length;
            const exp = paquete.expira_en ? paquete.expira_en.substring(11, 16) : 'N/A';
            detallesPaqueteOffline.textContent = `Vence: ${exp} · En caché: ${total} credenciales · Lote: ${paquete.lote_uuid?.substring(0, 15)}...`;
        } else {
            estadoPaqueteOffline.className = 'text-warning fw-bold';
            estadoPaqueteOffline.textContent = 'Sin descargar';
            detallesPaqueteOffline.textContent = 'Vigencia: No iniciada · Credenciales en caché: 0';
        }
    }

    // Gestión de la Cola Offline
    function obtenerCola() {
        const str = localStorage.getItem(CLAVE_COLA);
        if (!str) return [];
        try { return JSON.parse(str); } catch (_) { return []; }
    }

    function guardarCola(cola) {
        localStorage.setItem(CLAVE_COLA, JSON.stringify(cola));
        actualizarColaPendienteUI();
        actualizarBadgeRed();
    }

    function encolarEventoOffline(res) {
        const cola = obtenerCola();
        const evento = {
            idempotencia_hash: `idemp_${Date.now()}_${Math.random().toString(36).substring(2, 9)}`,
            token_qr: res.codigo,
            resultado_local: res.resultado_servidor,
            escaneado_en: new Date().toISOString().slice(0, 19).replace('T', ' '),
            correlativo: res.correlativo,
            titular: res.titular,
        };
        cola.push(evento);
        guardarCola(cola);
    }

    function actualizarColaPendienteUI() {
        const cola = obtenerCola();
        if (cola.length > 0) {
            badgeColaPendiente.textContent = cola.length;
            badgeColaPendiente.classList.remove('d-none');
        } else {
            badgeColaPendiente.classList.add('d-none');
        }
    }

    async function autoSincronizarCola() {
        const cola = obtenerCola();
        if (cola.length > 0 && estadoConexion === 'ONLINE') {
            console.log('[Validador] Conexión recuperada. Auto-sincronizando cola de escaneos...');
            await sincronizarColaOffline();
        }
    }

    async function sincronizarColaOffline() {
        const cola = obtenerCola();
        if (cola.length === 0) {
            alert('No hay escaneos offline pendientes de sincronización.');
            return;
        }

        btnSincronizarLote.disabled = true;
        btnSincronizarLote.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Sincronizando...';

        const paquete = obtenerPaqueteLocal();
        const loteUuid = paquete ? paquete.lote_uuid : `lote-manual-${Date.now()}`;

        try {
            const resp = await fetch('/api/v1/operaciones/offline/sincronizar', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': tokenCsrf,
                },
                body: JSON.stringify({
                    edicion_id: edicionId,
                    lote_uuid: loteUuid,
                    dispositivo_info: navigator.userAgent.substring(0, 100),
                    eventos: cola,
                }),
            });

            const data = await resp.json();
            if (data.exito) {
                guardarCola([]); // Vaciar cola sincronizada
                actualizarHistorialSesionSincronizado();
                alert(`Sincronización completada exitosamente.\nTotal procesados: ${data.datos?.total_escaneos || cola.length}\nConfirmados: ${data.datos?.total_confirmados || 0}\nConflictos: ${data.datos?.total_conflictos || 0}\nRechazados: ${data.datos?.total_rechazados || 0}`);
            } else {
                alert('No se pudo sincronizar el lote: ' + (data.mensaje || 'Error desconocido'));
            }
        } catch (e) {
            console.error('[Validador] Error sincronizando lote:', e);
            alert('Error de red al intentar sincronizar el lote con el servidor.');
        } finally {
            btnSincronizarLote.disabled = false;
            btnSincronizarLote.innerHTML = '<i class="fa-solid fa-rotate me-1"></i> Sincronizar Lote';
            actualizarColaPendienteUI();
        }
    }

    // Historial y Bitácora de la Sesión
    function obtenerHistorialSesion() {
        const str = sessionStorage.getItem(CLAVE_HISTORIAL_SESION);
        if (!str) return [];
        try { return JSON.parse(str); } catch (_) { return []; }
    }

    function registrarHistorialSesion(res) {
        const historial = obtenerHistorialSesion();
        historial.unshift(res);
        sessionStorage.setItem(CLAVE_HISTORIAL_SESION, JSON.stringify(historial.slice(0, 50)));
        renderizarHistorialSesion();
    }

    function actualizarHistorialSesionSincronizado() {
        const historial = obtenerHistorialSesion();
        historial.forEach(h => {
            if (h.es_provisional) {
                h.es_provisional = false;
                h.sincronizado = true;
            }
        });
        sessionStorage.setItem(CLAVE_HISTORIAL_SESION, JSON.stringify(historial));
        renderizarHistorialSesion();
    }

    function renderizarHistorialSesion() {
        const historial = obtenerHistorialSesion();
        badgeTotalSesion.textContent = `${historial.length} escaneos`;

        if (historial.length === 0) {
            tbodyBitacoraSesion.innerHTML = `
                <tr>
                    <td colspan="4" class="text-center py-4 text-muted">
                        <i class="fa-regular fa-clipboard fs-4 d-block mb-1"></i>
                        Aún no se han registrado validaciones en esta sesión.
                    </td>
                </tr>
            `;
            return;
        }

        tbodyBitacoraSesion.innerHTML = historial.map(h => {
            const badgeClase = h.valido ? 'bg-success' : (h.resultado_servidor === 'CONFLICTO_DUPLICADO' ? 'bg-warning text-dark' : 'bg-danger');
            const estadoTexto = h.valido ? 'Válido' : (h.resultado_servidor === 'CONFLICTO_DUPLICADO' ? 'Duplicado' : 'Rechazado');
            const syncIcon = h.es_provisional
                ? '<span class="badge bg-light-warning text-warning border border-warning">Pendiente</span>'
                : '<span class="badge bg-light-success text-success border border-success"><i class="fa-solid fa-cloud-check"></i> Sync</span>';

            return `
                <tr>
                    <td class="ps-3"><small class="text-muted">${h.hora}</small></td>
                    <td>
                        <div class="fw-bold font-monospace">${h.correlativo}</div>
                        <small class="text-muted">${h.titular}</small>
                    </td>
                    <td><span class="badge ${badgeClase}">${estadoTexto}</span></td>
                    <td class="text-end pe-3">${syncIcon}</td>
                </tr>
            `;
        }).join('');
    }

    async function cargarHistorialLotes() {
        tbodyHistorialLotes.innerHTML = '<tr><td colspan="7" class="text-center py-3 text-muted"><i class="fa-solid fa-spinner fa-spin me-2"></i> Cargando historial...</td></tr>';
        try {
            const resp = await fetch(`/api/v1/operaciones/offline/lotes?edicion_id=${edicionId}`, {
                headers: { 'X-CSRF-Token': tokenCsrf }
            });
            const data = await resp.json();

            if (data.exito && data.datos?.lotes) {
                const lotes = data.datos.lotes;
                if (lotes.length === 0) {
                    tbodyHistorialLotes.innerHTML = '<tr><td colspan="7" class="text-center py-3 text-muted">No se registran lotes sincronizados para esta edición.</td></tr>';
                    return;
                }

                tbodyHistorialLotes.innerHTML = lotes.map(l => `
                    <tr>
                        <td class="font-monospace"><small>${l.lote_uuid.substring(0, 16)}...</small></td>
                        <td>${l.operador_nombre}</td>
                        <td class="fw-bold text-center">${l.total_escaneos}</td>
                        <td class="text-success text-center fw-bold">${l.total_confirmados}</td>
                        <td class="text-warning text-center fw-bold">${l.total_conflictos}</td>
                        <td><span class="badge ${l.estado === 'PROCESADO' ? 'bg-success' : 'bg-warning text-dark'}">${l.estado}</span></td>
                        <td><small class="text-muted">${l.creado_en}</small></td>
                    </tr>
                `).join('');
            }
        } catch (e) {
            tbodyHistorialLotes.innerHTML = '<tr><td colspan="7" class="text-center py-3 text-danger">Error al cargar historial de lotes.</td></tr>';
        }
    }

    function generarHashSimulado(str) {
        // Simulación sencilla de SHA-256 en cliente para emparejamiento con el paquete
        return 'CORR:1:' + str.toUpperCase();
    }

    function procesarDeepLinking() {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('modo') === 'offline') {
            btnToggleModoRed.click();
        }
        if (urlParams.get('modal') === 'historial') {
            const modalEl = document.getElementById('modalHistorialLotes');
            if (modalEl && window.bootstrap) {
                const modal = new bootstrap.Modal(modalEl);
                modal.show();
            }
        }
    }

    document.addEventListener('DOMContentLoaded', inicializar);
})();
