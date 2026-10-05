/**
 * ==============================================================================
 * CANDELARIAAPP - GESTIÓN ASÍNCRONA DE FICHA DE ORGANIZACIÓN Y BRANDING (F1.2B)
 * ==============================================================================
 * - Consumo de la API REST: /api/v1/organizacion y /api/v1/organizacion/branding/*
 * - Patrón: Skeleton -> Fetch -> Render parcial sin recarga de página
 * - Botones disabled + spinner durante peticiones asíncronas
 * - Alertas y confirmaciones mediante SweetAlert2 integrado con Alina
 * - Validación exhaustiva de archivos (PNG, JPG, WEBP, <= 2MB, rechazo de SVG)
 * - CERO location.reload()
 * ==============================================================================
 */

'use strict';

document.addEventListener('DOMContentLoaded', () => {
    // 1. Estado local de la organización
    let organizacionActual = null;
    let modalEditarInstancia = null;
    let modalBrandingInstancia = null;

    // 2. Elementos DOM principales
    const skeletonOrg = document.getElementById('skeletonOrganizacion');
    const contenidoOrg = document.getElementById('contenidoOrganizacion');
    const alertaErrorCarga = document.getElementById('alertaErrorCarga');
    const btnRecargarFicha = document.getElementById('btnRecargarFicha');
    const btnReintentarCarga = document.getElementById('btnReintentarCarga');
    const btnAbrirModalEditar = document.getElementById('btnAbrirModalEditar');

    // Modales y formularios
    const modalEditarEl = document.getElementById('modalEditarOrganizacion');
    const formEditarOrg = document.getElementById('formEditarOrganizacion');
    const btnGuardarOrg = document.getElementById('btnGuardarOrganizacion');
    const alertaErrorModalOrg = document.getElementById('alertaErrorModalOrg');

    const modalBrandingEl = document.getElementById('modalSubirBranding');
    const formSubirBranding = document.getElementById('formSubirBranding');
    const btnConfirmarBranding = document.getElementById('btnConfirmarSubidaBranding');
    const alertaErrorModalBranding = document.getElementById('alertaErrorModalBranding');
    const inputArchivoBranding = document.getElementById('inputArchivoBranding');
    const inputTipoBranding = document.getElementById('inputTipoBrandingModal');
    const labelArchivoBranding = document.getElementById('labelArchivoBranding');
    const imgModalPreview = document.getElementById('imgModalPreview');
    const placeholderModalPreview = document.getElementById('placeholderModalPreview');
    const infoArchivoSeleccionado = document.getElementById('infoArchivoSeleccionado');

    const btnCambiarLogo = document.getElementById('btnCambiarLogo');
    const btnCambiarIsotipo = document.getElementById('btnCambiarIsotipo');

    // Inicializar instancias Bootstrap de modales
    if (modalEditarEl && typeof bootstrap !== 'undefined') {
        modalEditarInstancia = bootstrap.Modal.getOrCreateInstance(modalEditarEl);
    }
    if (modalBrandingEl && typeof bootstrap !== 'undefined') {
        modalBrandingInstancia = bootstrap.Modal.getOrCreateInstance(modalBrandingEl);
    }

    /**
     * Carga y renderiza los datos de la organización desde la API REST.
     */
    async function cargarOrganizacion() {
        mostrarSkeleton();

        try {
            const respuesta = await fetch(obtenerUrlApi('/api/v1/organizacion'), {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                },
                credentials: 'same-origin'
            });

            const datos = await respuesta.json();

            if (!respuesta.ok || !datos.exito) {
                throw new Error(datos.mensaje || 'Error al obtener datos de la organización.');
            }

            organizacionActual = datos.datos.organizacion;
            renderizarFicha(organizacionActual);
            ocultarSkeleton();
        } catch (error) {
            console.error('Error al cargar organización:', error);
            mostrarErrorCarga(error.message);
        }
    }

    /**
     * Renderiza en el DOM la información institucional y activos visuales.
     */
    function renderizarFicha(org) {
        if (!org) return;

        // Identidad principal
        setTexto('displayNombreComercial', org.nombre_comercial);
        setTexto('displayRazonSocial', org.razon_social || 'Razón social no registrada');
        setTexto('displayCodigoTenant', org.codigo || 'og_estudio');
        setTexto('displayEstadoBadge', org.estado || 'ACTIVO');

        // Sección 1: Datos Institucionales y Fiscales
        setTexto('displayFichaRazonSocial', org.razon_social || 'NO REGISTRADA');
        setTexto('displayFichaNombreComercial', org.nombre_comercial || '-');
        setTexto('displayFichaTipoDocumento', org.tipo_documento_id === 1 ? 'DNI (Documento Nacional de Identidad)' : 'RUC (Registro Único de Contribuyentes)');
        setTexto('displayFichaNumeroDocumento', org.numero_documento || 'NO REGISTRADO');

        // Sección 2: Ubicación
        setTexto('displayFichaDireccion', org.direccion || 'NO REGISTRADA');
        setTexto('displayFichaPais', org.codigo_pais ? `${org.codigo_pais === 'PE' ? 'PERÚ' : org.codigo_pais} (${org.codigo_pais})` : 'PERÚ (PE)');
        setTexto('displayFichaDepartamento', org.departamento || 'NO ESPECIFICADO');
        setTexto('displayFichaProvincia', org.provincia || 'NO ESPECIFICADA');
        setTexto('displayFichaDistrito', org.distrito || 'NO ESPECIFICADO');

        // Sección 3: Contacto y Representación
        setTexto('valFichaWhatsapp', org.telefono_whatsapp || 'No registrado');
        setTexto('valFichaCorreo', org.correo_contacto || 'No registrado');

        const linkWeb = document.getElementById('linkFichaSitioWeb');
        if (linkWeb) {
            if (org.sitio_web) {
                linkWeb.href = org.sitio_web;
                linkWeb.textContent = org.sitio_web;
                linkWeb.classList.remove('d-none');
            } else {
                linkWeb.removeAttribute('href');
                linkWeb.textContent = 'No registrado';
            }
        }

        setTexto('displayFichaContactoNombre', org.contacto_nombre || 'NO ESPECIFICADO');
        setTexto('displayFichaContactoCargo', org.contacto_cargo || 'NO ESPECIFICADO');

        // Sección 4: Branding (Logotipo e Isotipo)
        actualizarVisorImagen('Logo', org.logo_url_completa);
        actualizarVisorImagen('Isotipo', org.isotipo_url_completa);
    }

    /**
     * Actualiza el visor gráfico de logotipo o isotipo.
     */
    function actualizarVisorImagen(tipo, url) {
        const img = document.getElementById(`img${tipo}Display`);
        const placeholder = document.getElementById(`placeholder${tipo}Display`);

        if (!img || !placeholder) return;

        if (url) {
            img.src = `${url}?t=${Date.now()}`;
            img.classList.remove('d-none');
            placeholder.classList.add('d-none');
        } else {
            img.src = '';
            img.classList.add('d-none');
            placeholder.classList.remove('d-none');
        }
    }

    /**
     * Muestra el Skeleton loader y oculta la ficha real.
     */
    function mostrarSkeleton() {
        if (skeletonOrg) skeletonOrg.classList.remove('d-none');
        if (contenidoOrg) contenidoOrg.classList.add('d-none');
        if (alertaErrorCarga) alertaErrorCarga.classList.add('d-none');
    }

    /**
     * Oculta el Skeleton loader y despliega la ficha real.
     */
    function ocultarSkeleton() {
        if (skeletonOrg) skeletonOrg.classList.add('d-none');
        if (contenidoOrg) contenidoOrg.classList.remove('d-none');
        if (alertaErrorCarga) alertaErrorCarga.classList.add('d-none');
    }

    /**
     * Muestra alerta de error de carga de Alina con opción de reintentar.
     */
    function mostrarErrorCarga(mensaje) {
        if (skeletonOrg) skeletonOrg.classList.add('d-none');
        if (contenidoOrg) contenidoOrg.classList.add('d-none');
        if (alertaErrorCarga) {
            const detalle = document.getElementById('textoErrorCargaDetalle');
            if (detalle) detalle.textContent = mensaje || 'Ocurrió un error al cargar la información.';
            alertaErrorCarga.classList.remove('d-none');
            alertaErrorCarga.classList.add('d-flex');
        }
    }

    // ==============================================================================
    // GESTIÓN DEL MODAL DE EDICIÓN INSTITUCIONAL
    // ==============================================================================
    if (btnAbrirModalEditar && formEditarOrg) {
        btnAbrirModalEditar.addEventListener('click', () => {
            if (!organizacionActual) return;

            // Poblar campos del formulario
            setValorInput('inputEditNombreComercial', organizacionActual.nombre_comercial || '');
            setValorInput('inputEditRazonSocial', organizacionActual.razon_social || '');
            setValorInput('selectEditTipoDocumento', organizacionActual.tipo_documento_id || '2');
            setValorInput('inputEditNumeroDocumento', organizacionActual.numero_documento || '');
            setValorInput('inputEditDireccion', organizacionActual.direccion || '');
            setValorInput('inputEditPais', organizacionActual.codigo_pais || 'PE');
            setValorInput('inputEditDepartamento', organizacionActual.departamento || '');
            setValorInput('inputEditProvincia', organizacionActual.provincia || '');
            setValorInput('inputEditDistrito', organizacionActual.distrito || '');
            setValorInput('inputEditWhatsapp', organizacionActual.telefono_whatsapp || '');
            setValorInput('inputEditTelefono', organizacionActual.telefono_contacto || '');
            setValorInput('inputEditCorreo', organizacionActual.correo_contacto || '');
            setValorInput('inputEditSitioWeb', organizacionActual.sitio_web || '');
            setValorInput('inputEditContactoNombre', organizacionActual.contacto_nombre || '');
            setValorInput('inputEditContactoCargo', organizacionActual.contacto_cargo || '');

            ocultarErrorModal(alertaErrorModalOrg);

            if (modalEditarInstancia) {
                modalEditarInstancia.show();
            }
        });

        formEditarOrg.addEventListener('submit', async (e) => {
            e.preventDefault();

            const nombreComercial = document.getElementById('inputEditNombreComercial')?.value.trim();
            if (!nombreComercial) {
                mostrarErrorModal(alertaErrorModalOrg, 'El nombre comercial de la organización es obligatorio.');
                return;
            }

            const correo = document.getElementById('inputEditCorreo')?.value.trim();
            if (correo && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(correo)) {
                mostrarErrorModal(alertaErrorModalOrg, 'El formato del correo electrónico es inválido.');
                return;
            }

            const sitioWeb = document.getElementById('inputEditSitioWeb')?.value.trim();
            if (sitioWeb && !/^https?:\/\/.+/i.test(sitioWeb)) {
                mostrarErrorModal(alertaErrorModalOrg, 'El sitio web debe comenzar con http:// o https://.');
                return;
            }

            const csrfToken = document.getElementById('csrfTokenOrgForm')?.value || '';

            const payload = {
                _csrf_token: csrfToken,
                nombre_comercial: nombreComercial,
                razon_social: document.getElementById('inputEditRazonSocial')?.value.trim() || null,
                tipo_documento_id: document.getElementById('selectEditTipoDocumento')?.value || 2,
                numero_documento: document.getElementById('inputEditNumeroDocumento')?.value.trim() || null,
                direccion: document.getElementById('inputEditDireccion')?.value.trim() || null,
                codigo_pais: (document.getElementById('inputEditPais')?.value.trim() || 'PE').toUpperCase(),
                departamento: document.getElementById('inputEditDepartamento')?.value.trim() || null,
                provincia: document.getElementById('inputEditProvincia')?.value.trim() || null,
                distrito: document.getElementById('inputEditDistrito')?.value.trim() || null,
                telefono_whatsapp: document.getElementById('inputEditWhatsapp')?.value.trim() || null,
                telefono_contacto: document.getElementById('inputEditTelefono')?.value.trim() || null,
                correo_contacto: correo || null,
                sitio_web: sitioWeb || null,
                contacto_nombre: document.getElementById('inputEditContactoNombre')?.value.trim() || null,
                contacto_cargo: document.getElementById('inputEditContactoCargo')?.value.trim() || null,
            };

            // Botón disabled + spinner
            bloquearBoton(btnGuardarOrg, 'Guardando...');
            ocultarErrorModal(alertaErrorModalOrg);

            try {
                const resp = await fetch(obtenerUrlApi('/api/v1/organizacion'), {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-Token': csrfToken,
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify(payload)
                });

                const resultado = await resp.json();

                if (!resp.ok || !resultado.exito) {
                    throw new Error(resultado.mensaje || 'Error al actualizar información.');
                }

                // Actualizar estado local y renderizar sin recargar
                organizacionActual = resultado.datos.organizacion;
                renderizarFicha(organizacionActual);

                if (modalEditarInstancia) {
                    modalEditarInstancia.hide();
                }

                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Información Actualizada!',
                        text: 'Los datos institucionales han sido guardados con éxito.',
                        timer: 2000,
                        showConfirmButton: false,
                        customClass: {
                            popup: 'b-r-16'
                        }
                    });
                }
            } catch (err) {
                console.error('Error al guardar organización:', err);
                mostrarErrorModal(alertaErrorModalOrg, err.message || 'Error de conexión con el servidor.');
            } finally {
                desbloquearBoton(btnGuardarOrg, '<i class="fa-solid fa-floppy-disk me-1"></i> Guardar Cambios');
            }
        });
    }

    // ==============================================================================
    // GESTIÓN DEL MODAL DE CARGA DE BRANDING (LOGOTIPO E ISOTIPO)
    // ==============================================================================
    function prepararModalBranding(tipo) {
        if (!inputTipoBranding || !modalBrandingEl) return;

        inputTipoBranding.value = tipo;
        const tituloEl = document.getElementById('modalSubirBrandingLabel');
        const labelEl = document.getElementById('labelArchivoBranding');

        if (tituloEl) {
            tituloEl.innerHTML = tipo === 'logo'
                ? '<i class="fa-solid fa-image text-primary me-2"></i> Actualizar Logotipo Institucional'
                : '<i class="fa-solid fa-shapes text-primary me-2"></i> Actualizar Isotipo / Símbolo';
        }

        if (labelEl) {
            labelEl.textContent = tipo === 'logo' ? 'Seleccionar Logotipo' : 'Seleccionar Isotipo';
        }

        // Resetear formulario y previsualización
        if (formSubirBranding) formSubirBranding.reset();
        if (imgModalPreview) {
            imgModalPreview.src = '';
            imgModalPreview.classList.add('d-none');
        }
        if (placeholderModalPreview) placeholderModalPreview.classList.remove('d-none');
        if (infoArchivoSeleccionado) infoArchivoSeleccionado.classList.add('d-none');
        if (btnConfirmarBranding) btnConfirmarBranding.disabled = true;

        ocultarErrorModal(alertaErrorModalBranding);

        if (modalBrandingInstancia) {
            modalBrandingInstancia.show();
        }
    }

    if (btnCambiarLogo) {
        btnCambiarLogo.addEventListener('click', () => prepararModalBranding('logo'));
    }
    if (btnCambiarIsotipo) {
        btnCambiarIsotipo.addEventListener('click', () => prepararModalBranding('isotipo'));
    }

    // Previsualización y validación client-side de archivo seleccionado
    if (inputArchivoBranding) {
        inputArchivoBranding.addEventListener('change', () => {
            ocultarErrorModal(alertaErrorModalBranding);

            const archivo = inputArchivoBranding.files ? inputArchivoBranding.files[0] : null;
            if (!archivo) {
                if (imgModalPreview) imgModalPreview.classList.add('d-none');
                if (placeholderModalPreview) placeholderModalPreview.classList.remove('d-none');
                if (btnConfirmarBranding) btnConfirmarBranding.disabled = true;
                return;
            }

            const nombre = archivo.name.toLowerCase();

            // 1. Detección estricta de SVG
            if (nombre.endsWith('.svg') || archivo.type === 'image/svg+xml') {
                mostrarErrorModal(
                    alertaErrorModalBranding,
                    'El formato SVG se encuentra temporalmente deshabilitado por políticas de sanitización y seguridad vectorial. Utilice PNG, JPG o WEBP.'
                );
                inputArchivoBranding.value = '';
                if (btnConfirmarBranding) btnConfirmarBranding.disabled = true;
                return;
            }

            // 2. Formatos autorizados
            const extensionesValidas = ['.png', '.jpg', '.jpeg', '.webp'];
            const extensionValida = extensionesValidas.some(ext => nombre.endsWith(ext));
            if (!extensionValida) {
                mostrarErrorModal(
                    alertaErrorModalBranding,
                    'Formato no permitido. Solo se admiten archivos PNG, JPG, JPEG o WEBP.'
                );
                inputArchivoBranding.value = '';
                if (btnConfirmarBranding) btnConfirmarBranding.disabled = true;
                return;
            }

            // 3. Tamaño máximo de 2 MB (2,097,152 bytes)
            const MAX_BYTES = 2097152;
            if (archivo.size > MAX_BYTES) {
                mostrarErrorModal(
                    alertaErrorModalBranding,
                    `El archivo supera el límite de 2 MB (${(archivo.size / (1024 * 1024)).toFixed(2)} MB seleccionados).`
                );
                inputArchivoBranding.value = '';
                if (btnConfirmarBranding) btnConfirmarBranding.disabled = true;
                return;
            }

            // Previsualización FileReader
            const lector = new FileReader();
            lector.onload = (e) => {
                if (imgModalPreview) {
                    imgModalPreview.src = e.target.result;
                    imgModalPreview.classList.remove('d-none');
                }
                if (placeholderModalPreview) {
                    placeholderModalPreview.classList.add('d-none');
                }
                if (btnConfirmarBranding) {
                    btnConfirmarBranding.disabled = false;
                }
            };
            lector.readAsDataURL(archivo);

            // Mostrar metadata
            if (infoArchivoSeleccionado) {
                setTexto('valNombreArchivo', archivo.name);
                setTexto('valTamanoArchivo', `${(archivo.size / 1024).toFixed(1)} KB`);
                setTexto('valTipoArchivo', archivo.type || 'image');
                infoArchivoSeleccionado.classList.remove('d-none');
            }
        });
    }

    // Envío del archivo de branding
    if (formSubirBranding) {
        formSubirBranding.addEventListener('submit', async (e) => {
            e.preventDefault();

            const tipo = inputTipoBranding?.value || 'logo';
            const archivo = inputArchivoBranding?.files ? inputArchivoBranding.files[0] : null;

            if (!archivo) {
                mostrarErrorModal(alertaErrorModalBranding, 'Debe seleccionar un archivo válido.');
                return;
            }

            const csrfToken = document.getElementById('csrfTokenBrandingForm')?.value || '';
            const formData = new FormData();
            formData.append(tipo, archivo);
            formData.append('archivo', archivo);
            formData.append('_csrf_token', csrfToken);

            bloquearBoton(btnConfirmarBranding, 'Subiendo...');
            ocultarErrorModal(alertaErrorModalBranding);

            const endpoint = tipo === 'logo'
                ? '/api/v1/organizacion/branding/logo'
                : '/api/v1/organizacion/branding/isotipo';

            try {
                const resp = await fetch(obtenerUrlApi(endpoint), {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-Token': csrfToken,
                    },
                    credentials: 'same-origin',
                    body: formData
                });

                const resultado = await resp.json();

                if (!resp.ok || !resultado.exito) {
                    throw new Error(resultado.mensaje || 'Error al subir activo de branding.');
                }

                // Actualizar imagen en la ficha sin recargar
                if (tipo === 'logo') {
                    organizacionActual.logo_url = resultado.datos.ruta_relativa;
                    organizacionActual.logo_url_completa = resultado.datos.url_completa;
                    actualizarVisorImagen('Logo', resultado.datos.url_completa);
                } else {
                    organizacionActual.isotipo_url = resultado.datos.ruta_relativa;
                    organizacionActual.isotipo_url_completa = resultado.datos.url_completa;
                    actualizarVisorImagen('Isotipo', resultado.datos.url_completa);
                }

                if (modalBrandingInstancia) {
                    modalBrandingInstancia.hide();
                }

                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Branding Actualizado!',
                        text: `El ${tipo} ha sido guardado exitosamente.`,
                        timer: 2000,
                        showConfirmButton: false,
                        customClass: {
                            popup: 'b-r-16'
                        }
                    });
                }
            } catch (err) {
                console.error('Error al subir branding:', err);
                mostrarErrorModal(alertaErrorModalBranding, err.message || 'Error de transferencia con el servidor.');
            } finally {
                desbloquearBoton(btnConfirmarBranding, '<i class="fa-solid fa-cloud-arrow-up me-1"></i> Subir y Aplicar');
            }
        });
    }

    // ==============================================================================
    // EVENTOS AUXILIARES
    // ==============================================================================
    if (btnRecargarFicha) {
        btnRecargarFicha.addEventListener('click', cargarOrganizacion);
    }
    if (btnReintentarCarga) {
        btnReintentarCarga.addEventListener('click', cargarOrganizacion);
    }

    // ==============================================================================
    // FUNCIONES AUXILIARES
    // ==============================================================================
    function obtenerUrlApi(ruta) {
        const base = window.location.origin;
        const path = window.location.pathname;
        let prefijo = '';

        if (path.includes('/app.candelaria')) {
            prefijo = '/app.candelaria';
        }

        return `${base}${prefijo}${ruta}`;
    }

    function setTexto(id, valor) {
        const el = document.getElementById(id);
        if (el) el.textContent = valor;
    }

    function setValorInput(id, valor) {
        const el = document.getElementById(id);
        if (el) el.value = valor;
    }

    function mostrarErrorModal(alertaEl, mensaje) {
        if (!alertaEl) return;
        alertaEl.textContent = mensaje;
        alertaEl.classList.remove('d-none');
    }

    function ocultarErrorModal(alertaEl) {
        if (!alertaEl) return;
        alertaEl.textContent = '';
        alertaEl.classList.add('d-none');
    }

    function bloquearBoton(btn, texto) {
        if (!btn) return;
        btn.disabled = true;
        btn.dataset.textoOriginal = btn.innerHTML;
        btn.innerHTML = `<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>${texto}`;
    }

    function desbloquearBoton(btn, textoOriginalFallback) {
        if (!btn) return;
        btn.disabled = false;
        btn.innerHTML = btn.dataset.textoOriginal || textoOriginalFallback;
    }

    // Disparar carga inicial de la organización
    cargarOrganizacion();
});
