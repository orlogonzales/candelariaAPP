/**
 * ==============================================================================
 * CANDELARIAAPP - GESTIÓN ASÍNCRONA DE CLIENTES Y CARTERA (clientes.js)
 * ==============================================================================
 * - CRUD y consulta asíncrona vía Fetch API y DataTables Alina.
 * - Soporte para alta dual: vincular Persona existente (Select2) o registrar nueva Persona+Cliente.
 * - Transición comercial gobernada (PROSPECTO -> ACTIVO -> INACTIVO -> BLOQUEADO).
 * - Cero recargas de página completas.
 * ==============================================================================
 */

'use strict';

class ModuloClientes {
    constructor() {
        this.instanciaDataTable = null;
        this.clientesCache = [];
        this.modalCrear = null;
        this.modalEstado = null;

        this.init();
    }

    async init() {
        this.inicializarModales();
        this.vincularEventosUI();
        this.inicializarSelect2Personas();
        await this.cargarClientes();
    }

    inicializarModales() {
        const elCrear = document.getElementById('modalCrearCliente');
        if (elCrear && window.bootstrap?.Modal) {
            this.modalCrear = new window.bootstrap.Modal(elCrear);
        }

        const elEstado = document.getElementById('modalCambiarEstadoCliente');
        if (elEstado && window.bootstrap?.Modal) {
            this.modalEstado = new window.bootstrap.Modal(elEstado);
        }
    }

    vincularEventosUI() {
        // Recargar
        document.getElementById('btnRecargarTabla')?.addEventListener('click', () => this.cargarClientes());

        // Abrir modal crear
        document.getElementById('btnAbrirModalCrear')?.addEventListener('click', () => {
            document.getElementById('formCrearCliente')?.reset();
            this.cambiarModoCliente('existente');
            const preview = document.getElementById('previewPersonaSeleccionada');
            if (preview) preview.classList.add('d-none');
            if (window.jQuery && window.jQuery('#buscarPersonaId').length) {
                window.jQuery('#buscarPersonaId').val(null).trigger('change');
            }
            this.modalCrear?.show();
        });

        // Toggle modo cliente (existente vs nueva)
        document.querySelectorAll('input[name="modo_cliente"]').forEach(radio => {
            radio.addEventListener('change', (e) => this.cambiarModoCliente(e.target.value));
        });

        // Toggle tipo persona (NATURAL vs JURIDICA)
        document.getElementById('crearTipoPersona')?.addEventListener('change', (e) => {
            const esJuridica = e.target.value === 'JURIDICA';
            document.querySelectorAll('.campo-natural').forEach(el => el.classList.toggle('d-none', esJuridica));
            document.querySelectorAll('.campo-juridica').forEach(el => el.classList.toggle('d-none', !esJuridica));
        });

        // Filtros
        document.getElementById('filtroEstado')?.addEventListener('change', () => this.cargarClientes());
        
        let debounceTimer = null;
        document.getElementById('filtroBusqueda')?.addEventListener('input', () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => this.cargarClientes(), 350);
        });

        document.getElementById('btnLimpiarFiltros')?.addEventListener('click', () => {
            const fEstado = document.getElementById('filtroEstado');
            const fBusqueda = document.getElementById('filtroBusqueda');
            if (fEstado) fEstado.value = '';
            if (fBusqueda) fBusqueda.value = '';
            this.cargarClientes();
        });

        // Submits
        document.getElementById('formCrearCliente')?.addEventListener('submit', (e) => this.guardarCrear(e));
        document.getElementById('formCambiarEstadoCliente')?.addEventListener('submit', (e) => this.guardarEstado(e));
    }

    cambiarModoCliente(modo) {
        const secExistente = document.getElementById('seccionPersonaExistente');
        const secNueva = document.getElementById('seccionPersonaNueva');

        if (modo === 'existente') {
            secExistente?.classList.remove('d-none');
            secNueva?.classList.add('d-none');
        } else {
            secExistente?.classList.add('d-none');
            secNueva?.classList.remove('d-none');
        }
    }

    inicializarSelect2Personas() {
        if (!window.jQuery || !window.jQuery.fn.select2) return;

        const selectEl = window.jQuery('#buscarPersonaId');
        if (!selectEl.length) return;

        selectEl.select2({
            dropdownParent: window.jQuery('#modalCrearCliente'),
            placeholder: 'Escriba nombre, documento o WhatsApp...',
            minimumInputLength: 2,
            ajax: {
                url: `${window.CANDELARIA_BASE_URL}/api/v1/personas/buscar`,
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return { q: params.term, limite: 20 };
                },
                processResults: function (data) {
                    const personas = data?.datos?.personas || [];
                    return {
                        results: personas.map(p => ({
                            id: p.id,
                            text: `${p.nombre_completo} (${p.numero_documento || 'Sin doc'} - WA: ${p.telefono_whatsapp || 'Sin WA'})`,
                            persona: p
                        }))
                    };
                },
                cache: true
            }
        });

        selectEl.on('select2:select', (e) => {
            const p = e.params.data.persona;
            if (!p) return;

            const preview = document.getElementById('previewPersonaSeleccionada');
            const pNombre = document.getElementById('previewNombre');
            const pDoc = document.getElementById('previewDoc');
            const pCorreo = document.getElementById('previewCorreo');
            const pWa = document.getElementById('previewWhatsapp');

            if (p.ya_es_cliente) {
                CandelariaUI.notificarError('Esta persona ya se encuentra registrada como cliente en la cartera.', 'Cliente Existente');
                selectEl.val(null).trigger('change');
                if (preview) preview.classList.add('d-none');
                return;
            }

            if (preview && pNombre) {
                pNombre.textContent = p.nombre_completo;
                if (pDoc) pDoc.textContent = `Doc: ${p.numero_documento || 'No registrado'}`;
                if (pCorreo) pCorreo.textContent = `Email: ${p.correo_electronico || 'No registrado'}`;
                if (pWa) pWa.textContent = `WA: ${p.telefono_whatsapp || 'No registrado'}`;
                preview.classList.remove('d-none');
            }
        });
    }

    async cargarClientes() {
        const contenedor = document.getElementById('contenedorTablaClientes');
        if (!contenedor) return;

        try {
            Skeleton.show(contenedor, 'table', { filas: 5, columnas: 8 });

            const estado = document.getElementById('filtroEstado')?.value || '';
            const busqueda = document.getElementById('filtroBusqueda')?.value || '';

            let query = 'clientes?limite=100';
            if (estado) query += `&estado=${encodeURIComponent(estado)}`;
            if (busqueda) query += `&busqueda=${encodeURIComponent(busqueda)}`;

            const resp = await window.CandelariaApi.get(query);
            const clientes = resp?.datos?.clientes || [];
            this.clientesCache = clientes;

            this.actualizarKpis(clientes);

            if (this.instanciaDataTable) {
                this.instanciaDataTable.destroy();
                this.instanciaDataTable = null;
            }

            Skeleton.hide(contenedor);
            this.renderizarTabla(contenedor, clientes);

            if (window.jQuery && window.jQuery.fn.DataTable) {
                this.instanciaDataTable = window.jQuery('#tablaClientes').DataTable({
                    language: {
                        search: "Buscar en tabla:",
                        lengthMenu: "Mostrar _MENU_ clientes",
                        info: "Mostrando _START_ a _END_ de _TOTAL_ clientes",
                        infoEmpty: "Mostrando 0 clientes",
                        infoFiltered: "(filtrado de _MAX_ totales)",
                        zeroRecords: "No se encontraron clientes registrados",
                        paginate: {
                            first: "Primero",
                            previous: "Anterior",
                            next: "Siguiente",
                            last: "Último"
                        }
                    },
                    order: [[0, 'desc']], // ID descendente
                    pageLength: 10,
                    responsive: true,
                    dom: '<"d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2"lf>rt<"d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2"ip>'
                });
            }

            this.vincularAccionesFilas();

        } catch (err) {
            console.error('[ModuloClientes] Error cargando cartera de clientes:', err);
            Skeleton.error(contenedor, 'No fue posible cargar los clientes: ' + (err.message || 'Error del servidor'), {
                texto: 'Reintentar',
                accion: () => this.cargarClientes()
            });
        }
    }

    actualizarKpis(clientes) {
        const total = clientes.length;
        const prospectos = clientes.filter(c => c.estado_comercial === 'PROSPECTO').length;
        const activos = clientes.filter(c => c.estado_comercial === 'ACTIVO').length;
        const inactivos = clientes.filter(c => ['INACTIVO', 'BLOQUEADO'].includes(c.estado_comercial)).length;

        const elTotal = document.getElementById('kpiTotalClientes');
        const elProsp = document.getElementById('kpiProspectos');
        const elActivos = document.getElementById('kpiClientesActivos');
        const elInactivos = document.getElementById('kpiClientesInactivos');

        if (elTotal) elTotal.innerText = total.toString();
        if (elProsp) elProsp.innerText = prospectos.toString();
        if (elActivos) elActivos.innerText = activos.toString();
        if (elInactivos) elInactivos.innerText = inactivos.toString();
    }

    renderizarTabla(contenedor, clientes) {
        let filasHtml = '';

        if (clientes.length === 0) {
            filasHtml = `
                <tr>
                    <td colspan="8" class="text-center py-4 text-muted">
                        <i class="fa-solid fa-users-slash f-s-32 d-block mb-2 text-secondary"></i>
                        No se encontraron clientes que coincidan con los filtros aplicados.
                    </td>
                </tr>
            `;
        } else {
            clientes.forEach(c => {
                const badgeEstado = `<span class="badge bg-${this.obtenerClaseEstado(c.estado_comercial)}">${c.estado_comercial}</span>`;
                const docTexto = c.numero_documento 
                    ? `<code>${c.numero_documento}</code> <span class="text-muted f-s-11">(${c.tipo_documento_codigo || 'DOC'})</span>` 
                    : '<span class="text-muted f-s-11">(Sin Documento)</span>';

                const waTexto = c.telefono_whatsapp
                    ? `<a href="https://wa.me/${c.telefono_whatsapp.replace(/\D/g, '')}" target="_blank" class="text-success text-decoration-none f-w-600"><i class="fa-brands fa-whatsapp me-1"></i>${c.telefono_whatsapp}</a>`
                    : '<span class="text-muted f-s-11">(Sin WA)</span>';

                const consentimientosBadge = `
                    <span class="badge bg-${c.consentimiento_operativo ? 'success' : 'secondary'} me-1" title="Consentimiento Operativo">OP: ${c.consentimiento_operativo ? 'SÍ' : 'NO'}</span>
                    <span class="badge bg-${c.consentimiento_promocional ? 'info' : 'secondary'}" title="Consentimiento Promocional">PRO: ${c.consentimiento_promocional ? 'SÍ' : 'NO'}</span>
                `;

                const urlFicha = `${window.CANDELARIA_BASE_URL}/clientes/${c.id}`;

                filasHtml += `
                    <tr>
                        <td class="f-w-600 text-muted">#${c.id}</td>
                        <td>
                            <a href="${urlFicha}" class="f-w-700 text-primary text-decoration-none d-block">${c.nombre_completo}</a>
                            <span class="f-s-11 text-muted text-uppercase">${c.tipo_persona} · Persona #${c.persona_id}</span>
                        </td>
                        <td>${docTexto}</td>
                        <td>${waTexto}</td>
                        <td>${badgeEstado}</td>
                        <td>${consentimientosBadge}</td>
                        <td class="text-center">
                            <span class="badge bg-light text-dark border">${c.total_oportunidades || 0}</span>
                        </td>
                        <td>
                            <div class="d-inline-flex gap-1">
                                <a href="${urlFicha}" class="btn btn-sm btn-outline-primary" title="Ver Ficha 360">
                                    <i class="fa-solid fa-eye"></i>
                                </a>
                                <button type="button" class="btn btn-sm btn-outline-warning btn-cambiar-estado" data-id="${c.id}" data-nombre="${c.nombre_completo}" data-estado="${c.estado_comercial}" title="Cambiar Estado">
                                    <i class="fa-solid fa-arrows-spin"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            });
        }

        contenedor.innerHTML = `
            <table class="table table-hover align-middle w-100" id="tablaClientes">
                <thead class="bg-light">
                    <tr>
                        <th style="width: 50px;">ID</th>
                        <th>Cliente / Titular</th>
                        <th>Documento</th>
                        <th>WhatsApp</th>
                        <th>Estado</th>
                        <th>Consentimiento</th>
                        <th class="text-center">Oportunidades</th>
                        <th style="width: 90px;" class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>${filasHtml}</tbody>
            </table>
        `;
    }

    obtenerClaseEstado(estado) {
        return {
            'PROSPECTO': 'info',
            'ACTIVO': 'success',
            'INACTIVO': 'secondary',
            'BLOQUEADO': 'danger'
        }[estado] || 'primary';
    }

    vincularAccionesFilas() {
        document.querySelectorAll('.btn-cambiar-estado').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const target = e.currentTarget;
                const id = target.dataset.id;
                const nombre = target.dataset.nombre;
                const estado = target.dataset.estado;

                document.getElementById('estadoClienteId').value = id;
                document.getElementById('estadoClienteNombre').textContent = nombre;
                document.getElementById('nuevoEstadoComercial').value = estado;

                this.modalEstado?.show();
            });
        });
    }

    async guardarCrear(e) {
        e.preventDefault();
        const btn = document.getElementById('btnGuardarCliente');
        const modo = document.querySelector('input[name="modo_cliente"]:checked')?.value || 'existente';

        const payload = {
            estado_comercial: document.getElementById('crearEstadoComercial')?.value || 'PROSPECTO',
            notas_comerciales: document.getElementById('crearNotasComerciales')?.value || null
        };

        if (modo === 'existente') {
            const personaId = document.getElementById('buscarPersonaId')?.value;
            if (!personaId) {
                CandelariaUI.notificarError('Debe buscar y seleccionar una persona existente.', 'Dato Requerido');
                return;
            }
            payload.persona_id = parseInt(personaId, 10);
        } else {
            const tipoPersona = document.getElementById('crearTipoPersona')?.value || 'NATURAL';
            payload.tipo_persona = tipoPersona;
            payload.tipo_documento_id = document.getElementById('crearTipoDoc')?.value || null;
            payload.numero_documento = document.getElementById('crearNumDoc')?.value || null;
            payload.correo_electronico = document.getElementById('crearCorreo')?.value || null;
            payload.telefono_whatsapp = document.getElementById('crearWhatsapp')?.value || null;

            if (tipoPersona === 'NATURAL') {
                const nombres = document.getElementById('crearNombres')?.value?.trim();
                const apellidos = document.getElementById('crearApellidos')?.value?.trim();
                if (!nombres || !apellidos) {
                    CandelariaUI.notificarError('Nombres y apellidos son obligatorios para persona natural.', 'Datos Requeridos');
                    return;
                }
                payload.nombres = nombres;
                payload.apellidos = apellidos;
            } else {
                const razonSocial = document.getElementById('crearRazonSocial')?.value?.trim();
                if (!razonSocial) {
                    CandelariaUI.notificarError('La razón social es obligatoria para persona jurídica.', 'Dato Requerido');
                    return;
                }
                payload.razon_social = razonSocial;
            }
        }

        try {
            CandelariaUI.procesarBoton(btn, 'Registrando...');
            const resp = await window.CandelariaApi.post('clientes', payload);

            CandelariaUI.notificarExito(resp?.mensaje || 'Cliente incorporado exitosamente a la cartera.', 'Cliente Registrado');
            this.modalCrear?.hide();
            await this.cargarClientes();
        } catch (err) {
            console.error('[ModuloClientes] Error al crear cliente:', err);
            CandelariaUI.notificarError(err.message || 'No fue posible registrar el cliente.', 'Error de Alta');
        } finally {
            CandelariaUI.restaurarBoton(btn);
        }
    }

    async guardarEstado(e) {
        e.preventDefault();
        const btn = document.getElementById('btnGuardarEstadoCliente');
        const id = document.getElementById('estadoClienteId')?.value;
        const nuevoEstado = document.getElementById('nuevoEstadoComercial')?.value;

        if (!id || !nuevoEstado) return;

        try {
            CandelariaUI.procesarBoton(btn, 'Actualizando...');
            const resp = await window.CandelariaApi.patch(`clientes/${id}/estado`, {
                estado_comercial: nuevoEstado
            });

            CandelariaUI.notificarExito(resp?.mensaje || 'Estado comercial actualizado con éxito.', 'Actualizado');
            this.modalEstado?.hide();
            await this.cargarClientes();
        } catch (err) {
            console.error('[ModuloClientes] Error al cambiar estado:', err);
            CandelariaUI.notificarError(err.message || 'No fue posible actualizar el estado comercial.', 'Error');
        } finally {
            CandelariaUI.restaurarBoton(btn);
        }
    }
}

document.addEventListener('DOMContentLoaded', () => {
    window.moduloClientes = new ModuloClientes();
});
