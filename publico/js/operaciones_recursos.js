/**
 * ==============================================================================
 * CANDELARIAAPP - JAVASCRIPT: PROVEEDORES Y RECURSOS FÍSICOS (operaciones_recursos.js)
 * FASE 2.6E — Catálogo de Recursos Físicos y Proveedores Aliados
 * ==============================================================================
 */

document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    const URL_API_OPERACIONES = window.CANDELARIA_BASE_URL + 'api/v1/operaciones';
    const CSRF_TOKEN = window.CANDELARIA_CSRF_TOKEN || '';

    const modalRecursoEl = document.getElementById('modalNuevoRecurso');
    const modalRecurso = modalRecursoEl ? new bootstrap.Modal(modalRecursoEl) : null;
    const modalProvEl = document.getElementById('modalNuevoProveedor');
    const modalProv = modalProvEl ? new bootstrap.Modal(modalProvEl) : null;

    let dtRecursos = null;
    let dtProveedores = null;
    let proveedoresCache = [];

    cargarRecursosFisicos();
    cargarProveedores();

    const btnRecargar = document.getElementById('btnRecargarDatos');
    if (btnRecargar) {
        btnRecargar.addEventListener('click', () => {
            cargarRecursosFisicos();
            cargarProveedores();
        });
    }

    // 1. Cargar y Renderizar Recursos Físicos
    function cargarRecursosFisicos() {
        fetch(`${URL_API_OPERACIONES}/recursos`, { headers: { 'Accept': 'application/json' } })
        .then(res => res.json())
        .then(data => {
            renderizarTablaRecursos(data.datos.recursos || []);
        })
        .catch(err => console.error('Error cargando recursos', err));
    }

    function renderizarTablaRecursos(recursos) {
        const tabla = document.getElementById('tablaRecursosFisicos');
        if (dtRecursos) {
            dtRecursos.destroy();
            dtRecursos = null;
        }

        const tbody = tabla.querySelector('tbody');
        tbody.innerHTML = '';

        recursos.forEach(r => {
            const tr = document.createElement('tr');
            let badgeProp = r.propiedad_tipo === 'PROPIO' ? '<span class="badge bg-primary">PROPIO</span>' : '<span class="badge bg-warning text-dark">EXTERNO</span>';
            let badgeEst = r.estado === 'DISPONIBLE' ? '<span class="badge bg-success">DISPONIBLE</span>' : (r.estado === 'EN_MANTENIMIENTO' ? '<span class="badge bg-warning text-dark">MANTENIMIENTO</span>' : '<span class="badge bg-danger">DE BAJA</span>');

            tr.innerHTML = `
                <td><strong class="font-monospace text-primary">${escaparHtml(r.codigo_interno)}</strong></td>
                <td>
                    <div class="d-flex flex-column">
                        <strong class="text-dark">${escaparHtml(r.nombre)}</strong>
                        <small class="text-muted">${escaparHtml(r.identificacion_oficial || 'Sin matrícula')}</small>
                    </div>
                </td>
                <td><span class="badge bg-light text-dark border">${escaparHtml(r.tipo_recurso)}</span></td>
                <td>${badgeProp}</td>
                <td>${escaparHtml(r.proveedor_nombre || 'N/A (Propio)')}</td>
                <td><span class="badge bg-light-info text-info font-monospace">${r.capacidad_maxima} pax</span></td>
                <td>${badgeEst}</td>
                <td class="text-center">
                    <select class="form-select form-select-sm selectCambiarEstadoRecurso" data-id="${r.id}" style="width: auto; display: inline-block;">
                        <option value="DISPONIBLE" ${r.estado === 'DISPONIBLE' ? 'selected' : ''}>Disponible</option>
                        <option value="EN_MANTENIMIENTO" ${r.estado === 'EN_MANTENIMIENTO' ? 'selected' : ''}>Mantenimiento</option>
                        <option value="BAJA" ${r.estado === 'BAJA' ? 'selected' : ''}>De Baja</option>
                    </select>
                </td>
            `;
            tbody.appendChild(tr);
        });

        if (typeof $.fn.DataTable !== 'undefined') {
            dtRecursos = $(tabla).DataTable({
                responsive: true,
                language: { search: "_INPUT_", searchPlaceholder: "Buscar recurso...", lengthMenu: "Mostrar _MENU_", info: "Mostrando _TOTAL_ recursos", paginate: { previous: "Ant", next: "Sig" } },
                pageLength: 10
            });
        }
    }

    // Cambiar estado de recurso
    document.addEventListener('change', function (e) {
        if (e.target.classList.contains('selectCambiarEstadoRecurso')) {
            const id = e.target.getAttribute('data-id');
            const nuevoEstado = e.target.value;

            fetch(`${URL_API_OPERACIONES}/recursos/${id}/estado`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': CSRF_TOKEN
                },
                body: JSON.stringify({ _csrf_token: CSRF_TOKEN, estado: nuevoEstado })
            })
            .then(res => res.json())
            .then(data => {
                if (!data.exito) throw new Error(data.mensaje || 'Error al actualizar estado');
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'success', title: 'Estado Actualizado', timer: 1200, showConfirmButton: false });
                }
                cargarRecursosFisicos();
            })
            .catch(err => {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'error', title: 'Error', text: err.message });
                }
            });
        }
    });

    // 2. Cargar y Renderizar Proveedores
    function cargarProveedores() {
        fetch(`${URL_API_OPERACIONES}/proveedores`, { headers: { 'Accept': 'application/json' } })
        .then(res => res.json())
        .then(data => {
            proveedoresCache = data.datos.proveedores || [];
            renderizarTablaProveedores(proveedoresCache);
            actualizarSelectProveedoresEnRecursos(proveedoresCache);
        })
        .catch(err => console.error('Error cargando proveedores', err));
    }

    function renderizarTablaProveedores(proveedores) {
        const tabla = document.getElementById('tablaProveedores');
        if (dtProveedores) {
            dtProveedores.destroy();
            dtProveedores = null;
        }

        const tbody = tabla.querySelector('tbody');
        tbody.innerHTML = '';

        proveedores.forEach((p, idx) => {
            const tr = document.createElement('tr');
            let badgeEst = p.estado === 'ACTIVO' ? '<span class="badge bg-success">ACTIVO</span>' : '<span class="badge bg-danger">SUSPENDIDO</span>';

            tr.innerHTML = `
                <td>${idx + 1}</td>
                <td><strong class="text-dark">${escaparHtml(p.nombre_completo)}</strong></td>
                <td>${escaparHtml(p.numero_documento)}</td>
                <td><span class="badge bg-light text-dark border">${escaparHtml(p.tipo_servicio_principal || 'General')}</span></td>
                <td>
                    <div class="d-flex flex-column f-s-12">
                        <span>${escaparHtml(p.telefono || '-')}</span>
                        <small class="text-muted">${escaparHtml(p.email || '')}</small>
                    </div>
                </td>
                <td><span class="badge bg-light-primary text-primary">${p.total_recursos} recursos</span></td>
                <td>${badgeEst}</td>
                <td class="text-center">
                    ${p.estado === 'ACTIVO' ? `
                    <button type="button" class="btn btn-sm btn-outline-danger btnSuspenderProveedor" data-id="${p.id}" data-nombre="${escaparHtml(p.nombre_completo)}" title="Suspender Proveedor">
                        <i class="fa-solid fa-ban"></i>
                    </button>
                    ` : '<span class="text-muted f-s-12">Suspendido</span>'}
                </td>
            `;
            tbody.appendChild(tr);
        });

        if (typeof $.fn.DataTable !== 'undefined') {
            dtProveedores = $(tabla).DataTable({
                responsive: true,
                language: { search: "_INPUT_", searchPlaceholder: "Buscar proveedor...", lengthMenu: "Mostrar _MENU_", info: "Mostrando _TOTAL_ proveedores", paginate: { previous: "Ant", next: "Sig" } },
                pageLength: 10
            });
        }
    }

    function actualizarSelectProveedoresEnRecursos(proveedores) {
        const sel = document.getElementById('recProveedorId');
        if (!sel) return;
        sel.innerHTML = '<option value="">Seleccione proveedor aliado</option>';
        proveedores.filter(p => p.estado === 'ACTIVO').forEach(p => {
            const opt = document.createElement('option');
            opt.value = p.id;
            opt.textContent = `${p.nombre_completo} (${p.tipo_servicio_principal || 'Aliado'})`;
            sel.appendChild(opt);
        });
    }

    // Modal Nuevo Recurso Físico
    const btnAbrirRec = document.getElementById('btnAbrirModalNuevoRecurso');
    if (btnAbrirRec) {
        btnAbrirRec.addEventListener('click', function () {
            document.getElementById('recCodigoInterno').value = '';
            document.getElementById('recNombre').value = '';
            document.getElementById('recCapacidadMax').value = '25';
            document.getElementById('recIdentOficial').value = '';
            document.getElementById('recNotas').value = '';
            document.getElementById('recPropiedadTipo').value = 'PROPIO';
            toggleCampoProveedor('PROPIO');

            if (modalRecurso) modalRecurso.show();
        });
    }

    const selPropiedad = document.getElementById('recPropiedadTipo');
    if (selPropiedad) {
        selPropiedad.addEventListener('change', function () {
            toggleCampoProveedor(this.value);
        });
    }

    function toggleCampoProveedor(propiedad) {
        const cont = document.getElementById('contenedorSelectProveedor');
        const ast = document.getElementById('reqProvAsterisco');
        const sel = document.getElementById('recProveedorId');

        if (propiedad === 'EXTERNO') {
            cont.style.opacity = '1';
            ast.style.display = 'inline';
            sel.required = true;
        } else {
            cont.style.opacity = '0.5';
            ast.style.display = 'none';
            sel.required = false;
            sel.value = '';
        }
    }

    const formRecurso = document.getElementById('formNuevoRecurso');
    if (formRecurso) {
        formRecurso.addEventListener('submit', function (e) {
            e.preventDefault();
            const btnGuardar = document.getElementById('btnGuardarRecurso');
            const prop = document.getElementById('recPropiedadTipo').value;
            const provId = document.getElementById('recProveedorId').value;

            if (prop === 'EXTERNO' && !provId) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'warning', title: 'Atención', text: 'Debe seleccionar un proveedor para un recurso externo.' });
                }
                return;
            }

            const payload = {
                _csrf_token: CSRF_TOKEN,
                tipo_recurso: document.getElementById('recTipoRecurso').value,
                codigo_interno: document.getElementById('recCodigoInterno').value,
                nombre: document.getElementById('recNombre').value,
                propiedad_tipo: prop,
                proveedor_id: provId ? parseInt(provId) : null,
                capacidad_maxima: parseInt(document.getElementById('recCapacidadMax').value || 1),
                identificacion_oficial: document.getElementById('recIdentOficial').value,
                notas: document.getElementById('recNotas').value,
            };

            btnGuardar.disabled = true;
            btnGuardar.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Guardando...';

            fetch(`${URL_API_OPERACIONES}/recursos`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': CSRF_TOKEN
                },
                body: JSON.stringify(payload)
            })
            .then(res => res.json())
            .then(data => {
                if (!data.exito) throw new Error(data.mensaje || 'Error al registrar recurso');
                if (modalRecurso) modalRecurso.hide();
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'success', title: 'Recurso Guardado', text: 'El recurso físico fue registrado exitosamente.' });
                }
                cargarRecursosFisicos();
            })
            .catch(err => {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'error', title: 'Error', text: err.message });
                }
            })
            .finally(() => {
                btnGuardar.disabled = false;
                btnGuardar.innerHTML = '<i class="fa-solid fa-save me-1"></i> Guardar Recurso';
            });
        });
    }

    // Modal Nuevo Proveedor
    const btnAbrirProv = document.getElementById('btnAbrirModalNuevoProveedor');
    if (btnAbrirProv) {
        btnAbrirProv.addEventListener('click', function () {
            document.getElementById('provTipoServicio').value = '';
            document.getElementById('provNotas').value = '';

            const selPer = document.getElementById('provPersonaId');
            selPer.innerHTML = '<option value="">Cargando personas...</option>';

            fetch(`${URL_API_OPERACIONES}/aux/personas`, { headers: { 'Accept': 'application/json' } })
            .then(res => res.json())
            .then(data => {
                selPer.innerHTML = '<option value="">Seleccione una persona existente</option>';
                (data.datos.personas || []).forEach(p => {
                    const opt = document.createElement('option');
                    opt.value = p.id;
                    opt.textContent = `${p.text} (${p.numero_documento || 'Sin doc'})`;
                    selPer.appendChild(opt);
                });
            });

            if (modalProv) modalProv.show();
        });
    }

    const formProv = document.getElementById('formNuevoProveedor');
    if (formProv) {
        formProv.addEventListener('submit', function (e) {
            e.preventDefault();
            const btnGuardar = document.getElementById('btnGuardarProveedor');
            const pId = document.getElementById('provPersonaId').value;

            if (!pId) {
                if (typeof Swal !== 'undefined') Swal.fire({ icon: 'warning', title: 'Atención', text: 'Debe seleccionar una persona.' });
                return;
            }

            const payload = {
                _csrf_token: CSRF_TOKEN,
                persona_id: parseInt(pId),
                tipo_servicio_principal: document.getElementById('provTipoServicio').value,
                notas_contacto: document.getElementById('provNotas').value,
            };

            btnGuardar.disabled = true;
            btnGuardar.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Guardando...';

            fetch(`${URL_API_OPERACIONES}/proveedores`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': CSRF_TOKEN
                },
                body: JSON.stringify(payload)
            })
            .then(res => res.json())
            .then(data => {
                if (!data.exito) throw new Error(data.mensaje || 'Error al registrar proveedor');
                if (modalProv) modalProv.hide();
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'success', title: 'Proveedor Registrado', text: 'El proveedor aliado fue incorporado exitosamente.' });
                }
                cargarProveedores();
            })
            .catch(err => {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'error', title: 'Error', text: err.message });
                }
            })
            .finally(() => {
                btnGuardar.disabled = false;
                btnGuardar.innerHTML = '<i class="fa-solid fa-save me-1"></i> Registrar Proveedor';
            });
        });
    }

    // Suspender Proveedor
    document.addEventListener('click', function (e) {
        const btnSusp = e.target.closest('.btnSuspenderProveedor');
        if (btnSusp) {
            const id = btnSusp.getAttribute('data-id');
            const nombre = btnSusp.getAttribute('data-nombre');

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: '¿Suspender Proveedor?',
                    text: `¿Confirma suspender al proveedor ${nombre}? Sus recursos no podrán ser asignados a nuevas salidas.`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, suspender',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#d33'
                }).then(result => {
                    if (result.isConfirmed) {
                        fetch(`${URL_API_OPERACIONES}/proveedores/${id}/suspender`, {
                            method: 'PATCH',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': CSRF_TOKEN
                            },
                            body: JSON.stringify({ _csrf_token: CSRF_TOKEN })
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (!data.exito) throw new Error(data.mensaje || 'Error al suspender');
                            cargarProveedores();
                        })
                        .catch(err => {
                            Swal.fire({ icon: 'error', title: 'Error', text: err.message });
                        });
                    }
                });
            }
        }
    });

    function escaparHtml(texto) {
        if (!texto) return '';
        const mapa = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return String(texto).replace(/[&<>"']/g, m => mapa[m]);
    }
});
