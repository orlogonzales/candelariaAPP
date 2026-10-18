/**
 * ==============================================================================
 * CANDELARIAAPP - JAVASCRIPT: POSTPRODUCCIÓN Y GALERÍAS (postproduccion.js)
 * FASE 2.11 — Gestión de Proyectos Audiovisuales, Álbumes y Entregas Parciales
 * ==============================================================================
 */

document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    const URL_API_POST = typeof window.construirUrlCandelaria === 'function'
        ? window.construirUrlCandelaria('api/v1/postproduccion')
        : ((window.CANDELARIA_BASE_URL || '').replace(/\/+$/, '') + '/api/v1/postproduccion');

    // Modales Bootstrap
    const modalNuevoPryEl = document.getElementById('modalNuevoProyecto');
    const modalNuevoPry = modalNuevoPryEl ? new bootstrap.Modal(modalNuevoPryEl) : null;

    const modalDetalleEl = document.getElementById('modalDetalleProyecto');
    const modalDetalle = modalDetalleEl ? new bootstrap.Modal(modalDetalleEl) : null;

    const modalGaleriaEl = document.getElementById('modalCrearGaleria');
    const modalGaleria = modalGaleriaEl ? new bootstrap.Modal(modalGaleriaEl) : null;

    const modalSubirEl = document.getElementById('modalSubirArchivo');
    const modalSubir = modalSubirEl ? new bootstrap.Modal(modalSubirEl) : null;

    const modalEntregaEl = document.getElementById('modalCrearEntrega');
    const modalEntrega = modalEntregaEl ? new bootstrap.Modal(modalEntregaEl) : null;

    let proyectosCache = [];
    let proyectoActual = null;

    // Elementos DOM
    const btnRecargar = document.getElementById('btnRecargarProyectos');
    const btnNuevoPry = document.getElementById('btnNuevoProyecto');
    const filtroBusqueda = document.getElementById('filtroBusqueda');
    const filtroEstado = document.getElementById('filtroEstado');
    const contenedorProyectos = document.getElementById('contenedorProyectos');
    const contadorResultados = document.getElementById('contadorResultados');

    // Botones dentro del detalle
    const btnCrearGaleria = document.getElementById('btnCrearGaleria');
    const btnAbrirSubida = document.getElementById('btnAbrirSubida');
    const btnCrearEntrega = document.getElementById('btnCrearEntrega');

    // Inicializar
    cargarProyectos();

    if (btnRecargar) {
        btnRecargar.addEventListener('click', () => cargarProyectos());
    }

    if (btnNuevoPry && modalNuevoPry) {
        btnNuevoPry.addEventListener('click', () => {
            document.getElementById('formNuevoProyecto').reset();
            modalNuevoPry.show();
        });
    }

    if (filtroEstado) {
        filtroEstado.addEventListener('change', () => renderizarProyectos());
    }

    if (filtroBusqueda) {
        let timer = null;
        filtroBusqueda.addEventListener('input', () => {
            clearTimeout(timer);
            timer = setTimeout(() => renderizarProyectos(), 250);
        });
    }

    if (btnCrearGaleria && modalGaleria) {
        btnCrearGaleria.addEventListener('click', () => {
            document.getElementById('formCrearGaleria').reset();
            modalGaleria.show();
        });
    }

    if (btnAbrirSubida && modalSubir) {
        btnAbrirSubida.addEventListener('click', () => {
            poblarSelectorGalerias();
            document.getElementById('formSubirArchivo').reset();
            document.getElementById('progresoSubidaBox').classList.add('d-none');
            document.getElementById('progresoSubidaTxt').classList.add('d-none');
            modalSubir.show();
        });
    }

    if (btnCrearEntrega && modalEntrega) {
        btnCrearEntrega.addEventListener('click', () => {
            poblarArchivosParaEntrega();
            document.getElementById('formCrearEntrega').reset();
            modalEntrega.show();
        });
    }

    // =========================================================================
    // 1. CARGA DE PROYECTOS Y KPIS
    // =========================================================================
    async function cargarProyectos() {
        try {
            if (contadorResultados) contadorResultados.textContent = 'Actualizando...';
            const res = await fetch(`${URL_API_POST}/proyectos`);
            const data = await res.json();

            if (data.exito) {
                proyectosCache = data.datos || [];
                actualizarKpis();
                renderizarProyectos();
            } else {
                throw new Error(data.mensaje || 'Error al obtener proyectos');
            }
        } catch (e) {
            console.error(e);
            if (contenedorProyectos) {
                contenedorProyectos.innerHTML = `
                    <div class="col-12 text-center py-5 text-muted">
                        <i class="fa-solid fa-triangle-exclamation fa-2x text-warning mb-2"></i>
                        <p>No se pudieron cargar los proyectos de postproducción.</p>
                    </div>
                `;
            }
        }
    }

    function actualizarKpis() {
        const total = proyectosCache.length;
        const enEdicion = proyectosCache.filter(p => ['PLANIFICADO', 'EN_EDICION', 'EN_REVISION'].includes(p.estado)).length;
        let totalEntregas = 0;
        let totalBytes = 0;

        proyectosCache.forEach(p => {
            totalBytes += parseInt(p.peso_total_bytes || 0, 10);
        });

        document.getElementById('kpiTotalProyectos').textContent = total;
        document.getElementById('kpiEnEdicion').textContent = enEdicion;
        
        const mb = (totalBytes / (1024 * 1024)).toFixed(1);
        const gb = (totalBytes / (1024 * 1024 * 1024)).toFixed(2);
        document.getElementById('kpiAlmacenamiento').textContent = totalBytes > 1024 * 1024 * 1024 ? `${gb} GB` : `${mb} MB`;
    }

    function renderizarProyectos() {
        if (!contenedorProyectos) return;

        const q = (filtroBusqueda ? filtroBusqueda.value : '').toLowerCase().trim();
        const est = filtroEstado ? filtroEstado.value : '';

        const filtrados = proyectosCache.filter(p => {
            const coincideTexto = !q || 
                (p.titulo || '').toLowerCase().includes(q) ||
                (p.cliente_apellidos || '').toLowerCase().includes(q) ||
                (p.cliente_nombres || '').toLowerCase().includes(q) ||
                (p.edicion_nombre || '').toLowerCase().includes(q);
            const coincideEstado = !est || p.estado === est;
            return coincideTexto && coincideEstado;
        });

        if (contadorResultados) {
            contadorResultados.textContent = `Mostrando ${filtrados.length} de ${proyectosCache.length} proyectos`;
        }

        if (filtrados.length === 0) {
            contenedorProyectos.innerHTML = `
                <div class="col-12 text-center py-5 text-muted">
                    <i class="fa-solid fa-folder-open fa-3x mb-3 text-secondary opacity-50"></i>
                    <h5 class="fw-semibold">No se encontraron proyectos</h5>
                    <p class="small">Intente ajustando los filtros de búsqueda o registre un nuevo proyecto.</p>
                </div>
            `;
            return;
        }

        contenedorProyectos.innerHTML = '';
        filtrados.forEach(p => {
            const badgeClass = {
                'PLANIFICADO': 'bg-secondary',
                'EN_EDICION': 'bg-warning text-dark',
                'EN_REVISION': 'bg-info text-dark',
                'ENTREGADO_PARCIAL': 'bg-primary',
                'ENTREGADO_TOTAL': 'bg-success',
                'CERRADO': 'bg-dark'
            }[p.estado] || 'bg-secondary';

            const card = document.createElement('div');
            card.className = 'col-xl-4 col-md-6';
            card.innerHTML = `
                <div class="card border-0 shadow-sm b-r-12 h-100 d-flex flex-column hover-shadow transition">
                    <div class="card-body p-3 d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <span class="badge ${badgeClass} f-s-11">${p.estado}</span>
                            <span class="text-muted f-s-11"><i class="fa-solid fa-calendar me-1"></i>${p.edicion_nombre}</span>
                        </div>
                        <h5 class="f-w-700 text-dark mb-1">${escaparHtml(p.titulo)}</h5>
                        <p class="text-muted f-s-12 mb-2">
                            <i class="fa-solid fa-user me-1 text-primary"></i> ${escaparHtml(p.cliente_apellidos + ' ' + p.cliente_nombres)}
                        </p>

                        <div class="p-2 bg-light b-r-8 mb-3 f-s-12 mt-auto">
                            <div class="d-flex justify-content-between mb-1">
                                <span><i class="fa-solid fa-camera me-1 text-secondary"></i> Fotógrafo:</span>
                                <strong class="text-dark">${p.fotografo_nombre ? escaparHtml(p.fotografo_nombre) : 'Sin asignar'}</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span><i class="fa-solid fa-film me-1 text-secondary"></i> Editor:</span>
                                <strong class="text-dark">${p.editor_nombre ? escaparHtml(p.editor_nombre) : 'Sin asignar'}</strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span><i class="fa-solid fa-photo-film me-1 text-secondary"></i> Archivos:</span>
                                <span class="badge bg-secondary-subtle text-secondary">${p.total_archivos || 0} items</span>
                            </div>
                        </div>

                        <div class="d-flex gap-2">
                            <button class="btn btn-outline-primary btn-sm w-100 fw-semibold btn-ver-proyecto" data-id="${p.id}">
                                <i class="fa-solid fa-eye me-1"></i> Ver Proyecto
                            </button>
                        </div>
                    </div>
                </div>
            `;
            contenedorProyectos.appendChild(card);
        });

        // Event listener ver proyecto
        contenedorProyectos.querySelectorAll('.btn-ver-proyecto').forEach(btn => {
            btn.addEventListener('click', () => abrirDetalleProyecto(btn.getAttribute('data-id')));
        });
    }

    // =========================================================================
    // 2. DETALLE DE PROYECTO
    // =========================================================================
    async function abrirDetalleProyecto(id) {
        try {
            const res = await fetch(`${URL_API_POST}/proyectos/${id}`);
            const data = await res.json();
            if (!data.exito) throw new Error(data.mensaje);

            proyectoActual = data.datos;

            document.getElementById('detTituloProyecto').textContent = proyectoActual.titulo;
            document.getElementById('detSubtituloProyecto').textContent = 
                `${proyectoActual.cliente_apellidos} ${proyectoActual.cliente_nombres} · ${proyectoActual.edicion_nombre} (${proyectoActual.estado})`;
            
            document.getElementById('detTotalArchivos').textContent = proyectoActual.total_archivos || 0;
            document.getElementById('detTotalEntregas').textContent = (proyectoActual.entregas || []).length;

            renderizarGaleriasArchivos();
            renderizarEntregas();

            modalDetalle.show();
        } catch (e) {
            alert('Error al abrir proyecto: ' + e.message);
        }
    }

    function renderizarGaleriasArchivos() {
        const cont = document.getElementById('contenedorGaleriasArchivos');
        const galerias = proyectoActual.galerias || [];

        if (galerias.length === 0) {
            cont.innerHTML = `
                <div class="text-center py-4 text-muted">
                    <i class="fa-solid fa-images fa-2x mb-2 opacity-50"></i>
                    <p class="small mb-2">No se han creado álbumes o galerías para este proyecto.</p>
                    <button class="btn btn-sm btn-outline-primary" onclick="document.getElementById('btnCrearGaleria').click()">Crear Primera Galería</button>
                </div>
            `;
            return;
        }

        cont.innerHTML = '';
        galerias.forEach(g => {
            const box = document.createElement('div');
            box.className = 'card border mb-3 b-r-12 overflow-hidden';
            
            let archivosHtml = '';
            const archivos = g.archivos || [];
            if (archivos.length === 0) {
                archivosHtml = `<div class="p-3 text-muted small text-center">No hay archivos en esta galería. Suba fotos o videos usando el botón superior.</div>`;
            } else {
                archivosHtml = `<div class="row g-2 p-3">`;
                archivos.forEach(a => {
                    const esVideo = (a.mime_type || '').startsWith('video/');
                    const pesoMb = (parseInt(a.peso_bytes || 0, 10) / (1024 * 1024)).toFixed(1);
                    archivosHtml += `
                        <div class="col-xl-3 col-md-4 col-sm-6">
                            <div class="border b-r-8 p-2 bg-white d-flex align-items-center gap-2">
                                <div class="w-35 h-35 d-flex-center b-r-8 ${esVideo ? 'bg-danger-subtle text-danger' : 'bg-primary-subtle text-primary'} f-s-16">
                                    <i class="fa-solid ${esVideo ? 'fa-video' : 'fa-image'}"></i>
                                </div>
                                <div class="overflow-hidden flex-grow-1">
                                    <div class="text-truncate fw-semibold f-s-12" title="${escaparHtml(a.nombre_original)}">${escaparHtml(a.nombre_original)}</div>
                                    <div class="text-muted f-s-11">${pesoMb} MB</div>
                                </div>
                                <a href="${URL_API_POST}/archivos/${a.id}/descargar" class="btn btn-sm btn-light text-primary" title="Descargar" target="_blank">
                                    <i class="fa-solid fa-download"></i>
                                </a>
                            </div>
                        </div>
                    `;
                });
                archivosHtml += `</div>`;
            }

            box.innerHTML = `
                <div class="card-header bg-light py-2 px-3 d-flex justify-content-between align-items-center">
                    <div>
                        <strong class="text-dark f-s-14"><i class="fa-solid fa-folder me-1 text-warning"></i> ${escaparHtml(g.titulo)}</strong>
                        <span class="badge bg-secondary-subtle text-secondary ms-2 f-s-11">${g.tipo_galeria}</span>
                        ${g.es_publica_cliente ? '<span class="badge bg-success-subtle text-success ms-1 f-s-11">Pública Cliente</span>' : '<span class="badge bg-secondary ms-1 f-s-11">Interna</span>'}
                    </div>
                    <span class="text-muted f-s-12">${archivos.length} items</span>
                </div>
                ${archivosHtml}
            `;
            cont.appendChild(box);
        });
    }

    function renderizarEntregas() {
        const cont = document.getElementById('contenedorEntregasProyecto');
        const entregas = proyectoActual.entregas || [];

        if (entregas.length === 0) {
            cont.innerHTML = `
                <div class="text-center py-4 text-muted">
                    <i class="fa-solid fa-box-archive fa-2x mb-2 opacity-50"></i>
                    <p class="small mb-2">No se han registrado entregas para el cliente aún.</p>
                    <button class="btn btn-sm btn-success" onclick="document.getElementById('btnCrearEntrega').click()">Publicar Primera Entrega</button>
                </div>
            `;
            return;
        }

        cont.innerHTML = '';
        entregas.forEach(e => {
            const estadoBadge = {
                'PUBLICADA': 'bg-info-subtle text-info',
                'APROBADA_CLIENTE': 'bg-success-subtle text-success',
                'OBSERVADA_CLIENTE': 'bg-warning-subtle text-warning'
            }[e.estado_entrega] || 'bg-secondary';

            const card = document.createElement('div');
            card.className = 'card border mb-3 b-r-12 p-3';
            card.innerHTML = `
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <h6 class="fw-bold mb-1 text-dark">
                            <span class="badge bg-primary me-2">Entrega #${e.numero_entrega}</span>
                            ${escaparHtml(e.titulo)}
                        </h6>
                        <span class="text-muted f-s-12">Publicado el: ${e.creado_en}</span>
                    </div>
                    <span class="badge ${estadoBadge} f-s-12 fw-bold">
                        ${e.estado_entrega === 'APROBADA_CLIENTE' ? '<i class="fa-solid fa-circle-check me-1"></i> APROBADA POR CLIENTE' : e.estado_entrega}
                    </span>
                </div>

                ${e.notas ? `<p class="f-s-12 text-muted mb-2"><strong>Notas:</strong> ${escaparHtml(e.notas)}</p>` : ''}
                
                ${e.observaciones_cliente ? `
                    <div class="alert alert-warning p-2 f-s-12 mb-0">
                        <i class="fa-solid fa-comment-dots me-1"></i> <strong>Observaciones del Cliente:</strong> ${escaparHtml(e.observaciones_cliente)}
                    </div>
                ` : ''}
            `;
            cont.appendChild(card);
        });
    }

    // =========================================================================
    // 3. FORMULARIOS Y ACCIONES MODALES
    // =========================================================================

    // Crear Proyecto
    document.getElementById('formNuevoProyecto').addEventListener('submit', async function(ev) {
        ev.preventDefault();
        const btn = document.getElementById('btnGuardarProyecto');
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Guardando...';

        const formData = new FormData(this);
        const body = Object.fromEntries(formData.entries());

        try {
            const res = await fetch(`${URL_API_POST}/proyectos`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body)
            });
            const data = await res.json();
            if (data.exito) {
                modalNuevoPry.hide();
                cargarProyectos();
            } else {
                throw new Error(data.mensaje);
            }
        } catch (e) {
            alert('Error al crear proyecto: ' + e.message);
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-floppy-disk me-1"></i> Guardar Proyecto';
        }
    });

    // Crear Galería
    document.getElementById('formCrearGaleria').addEventListener('submit', async function(ev) {
        ev.preventDefault();
        if (!proyectoActual) return;

        const formData = new FormData(this);
        const body = Object.fromEntries(formData.entries());
        body.es_publica_cliente = document.getElementById('galeriaEsPublica').checked;

        try {
            const res = await fetch(`${URL_API_POST}/proyectos/${proyectoActual.id}/galerias`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body)
            });
            const data = await res.json();
            if (data.exito) {
                modalGaleria.hide();
                abrirDetalleProyecto(proyectoActual.id);
            } else {
                throw new Error(data.mensaje);
            }
        } catch (e) {
            alert('Error al crear galería: ' + e.message);
        }
    });

    function poblarSelectorGalerias() {
        const sel = document.getElementById('subirGaleriaId');
        sel.innerHTML = '';
        (proyectoActual.galerias || []).forEach(g => {
            const opt = document.createElement('option');
            opt.value = g.id;
            opt.textContent = `${g.titulo} (${g.tipo_galeria})`;
            sel.appendChild(opt);
        });
    }

    // Subir Archivo por Streams con XHR Progress
    document.getElementById('formSubirArchivo').addEventListener('submit', function(ev) {
        ev.preventDefault();
        if (!proyectoActual) return;

        const galeriaId = document.getElementById('subirGaleriaId').value;
        const fileInput = document.getElementById('subirInputFile');
        if (!fileInput.files.length) return;

        const file = fileInput.files[0];
        const formData = new FormData();
        formData.append('archivo', file);

        const bar = document.getElementById('progresoSubidaBar');
        const box = document.getElementById('progresoSubidaBox');
        const txt = document.getElementById('progresoSubidaTxt');
        const btn = document.getElementById('btnEjecutarSubida');

        box.classList.remove('d-none');
        txt.classList.remove('d-none');
        btn.disabled = true;

        const xhr = new XMLHttpRequest();
        xhr.open('POST', `${URL_API_POST}/proyectos/${proyectoActual.id}/galerias/${galeriaId}/archivos`);

        xhr.upload.onprogress = function(e) {
            if (e.lengthComputable) {
                const pct = Math.round((e.loaded / e.total) * 100);
                bar.style.width = pct + '%';
                txt.textContent = `Transmitiendo stream: ${pct}% completado...`;
            }
        };

        xhr.onload = function() {
            btn.disabled = false;
            try {
                const res = JSON.parse(xhr.responseText);
                if (xhr.status === 201 && res.exito) {
                    modalSubir.hide();
                    abrirDetalleProyecto(proyectoActual.id);
                    cargarProyectos();
                } else {
                    alert('Error en subida: ' + (res.mensaje || 'Error desconocido'));
                }
            } catch (err) {
                alert('Error al procesar respuesta del servidor');
            }
        };

        xhr.onerror = function() {
            btn.disabled = false;
            alert('Error de conexión durante el streaming del archivo');
        };

        xhr.send(formData);
    });

    function poblarArchivosParaEntrega() {
        const cont = document.getElementById('listaSeleccionArchivosEntrega');
        cont.innerHTML = '';
        let total = 0;

        (proyectoActual.galerias || []).forEach(g => {
            (g.archivos || []).forEach(a => {
                total++;
                const item = document.createElement('div');
                item.className = 'form-check mb-1';
                item.innerHTML = `
                    <input class="form-check-input check-archivo-entrega" type="checkbox" value="${a.id}" id="chk_arch_${a.id}" checked>
                    <label class="form-check-label small" for="chk_arch_${a.id}">
                        <strong>${escaparHtml(a.nombre_original)}</strong> (${(parseInt(a.peso_bytes)/1024/1024).toFixed(1)} MB) - ${g.titulo}
                    </label>
                `;
                cont.appendChild(item);
            });
        });

        if (total === 0) {
            cont.innerHTML = '<span class="text-muted small">No hay archivos en el proyecto para incluir.</span>';
        }
    }

    // Crear Entrega
    document.getElementById('formCrearEntrega').addEventListener('submit', async function(ev) {
        ev.preventDefault();
        if (!proyectoActual) return;

        const formData = new FormData(this);
        const body = Object.fromEntries(formData.entries());

        const checks = document.querySelectorAll('.check-archivo-entrega:checked');
        const archivoIds = Array.from(checks).map(c => parseInt(c.value, 10));

        if (archivoIds.length === 0) {
            alert('Debe seleccionar al menos un archivo para la entrega.');
            return;
        }

        body.archivo_ids = archivoIds;

        try {
            const res = await fetch(`${URL_API_POST}/proyectos/${proyectoActual.id}/entregas`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body)
            });
            const data = await res.json();
            if (data.exito) {
                modalEntrega.hide();
                abrirDetalleProyecto(proyectoActual.id);
            } else {
                throw new Error(data.mensaje);
            }
        } catch (e) {
            alert('Error al publicar entrega: ' + e.message);
        }
    });

    function escaparHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
});
