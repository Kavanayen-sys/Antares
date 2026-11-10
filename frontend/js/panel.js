"use strict";

const estado = {
    usuarios: [],
    contenedores: [],
    camiones: [],
    incidencias: [],
    rutas: [],
    cuadrillas: [],
    cuadrilleros: [],
    asignaciones: [],
    miRuta: null,
    maquinaria: [],
    centros: [],
    vertederos: [],
    garajes: [],
    estacionamientos: [],
    talleres: [],
    reparaciones: [],
    enviosVertedero: [],
    cedulasBloqueadas: [],
    logsLogin: [],
    notificaciones: [],
    notificacionesLeidas: new Set(),
    usuarioActual: null
};

let mapaGeneralContenedores = null;
let mapaFormContenedor = null;
let marcadorFormContenedor = null;
let marcadoresContenedores = [];

let mapaMiRuta = null;
let capaRutaGeo = null;

let chartEstado = null;
let chartTipo = null;
let chartFlota = null;

const mensajePanel = document.getElementById("mensaje-panel");

// Navegación entre pestañas
document.querySelectorAll("[data-seccion]").forEach(boton => {
    boton.addEventListener("click", () => {
        cambiarSeccion(boton.dataset.seccion);
    });
});

function cambiarSeccion(nombreSeccion) {
    document.querySelectorAll("[data-seccion]").forEach(item => item.classList.remove("activo"));
    document.querySelectorAll(".seccion-panel").forEach(item => item.classList.remove("activa"));
    const boton = document.querySelector(`[data-seccion="${nombreSeccion}"]`);
    if (boton) boton.classList.add("activo");
    const seccion = document.getElementById(`seccion-${nombreSeccion}`);
    if (seccion) seccion.classList.add("activa");

    if (nombreSeccion === "contenedores") {
        setTimeout(() => {
            if (mapaGeneralContenedores) mapaGeneralContenedores.invalidateSize();
            if (mapaFormContenedor) mapaFormContenedor.invalidateSize();
        }, 200);
    }
    if (nombreSeccion === "mi-ruta") {
        setTimeout(() => {
            if (mapaMiRuta) mapaMiRuta.invalidateSize();
        }, 200);
    }
    if (nombreSeccion === "resumen") {
        setTimeout(() => {
            renderizarGraficos();
        }, 150);
    }
}

// Botones para abrir editores
document.querySelectorAll("[data-nuevo]").forEach(boton => {
    boton.addEventListener("click", () => abrirEditor(boton.dataset.nuevo));
});

// Botones cancelar en formularios
document.querySelectorAll("[data-cancelar]").forEach(boton => {
    boton.addEventListener("click", () => {
        const form = boton.closest("form");
        if (form) {
            form.reset();
            form.hidden = true;
        }
    });
});

// Cerrar sesión
document.getElementById("cerrar-sesion").addEventListener("click", async () => {
    try {
        await pedir(API_USUARIOS, "/logout", "POST");
    } finally {
        window.location.href = "login.html";
    }
});

// Sistema de Notificaciones y Avisos
const btnNotif = document.getElementById("btn-notificaciones");
const dropdownNotif = document.getElementById("dropdown-notificaciones");
const btnLimpiarNotif = document.getElementById("btn-limpiar-notificaciones");

if (btnNotif && dropdownNotif) {
    btnNotif.addEventListener("click", (e) => {
        e.stopPropagation();
        const estaVisible = dropdownNotif.style.display === "block";
        dropdownNotif.style.display = estaVisible ? "none" : "block";
    });

    document.addEventListener("click", (e) => {
        if (!dropdownNotif.contains(e.target) && e.target !== btnNotif) {
            dropdownNotif.style.display = "none";
        }
    });

    btnLimpiarNotif?.addEventListener("click", (e) => {
        e.stopPropagation();
        (estado.notificaciones || []).forEach(n => estado.notificacionesLeidas.add(n.id));
        dibujarNotificaciones();
    });
}

// CU-31: Modal Mi Perfil
const modalPerfil = document.getElementById("modal-mi-perfil");
document.getElementById("btn-mi-perfil")?.addEventListener("click", () => {
    if (!estado.usuarioActual) return;
    document.getElementById("perfil-nombre").value = estado.usuarioActual.priNom || "";
    document.getElementById("perfil-telefono").value = estado.usuarioActual.telUsu || "";
    document.getElementById("perfil-password").value = "";
    if (modalPerfil) modalPerfil.showModal();
});
document.getElementById("btn-cerrar-perfil")?.addEventListener("click", () => {
    if (modalPerfil) modalPerfil.close();
});
document.getElementById("form-mi-perfil")?.addEventListener("submit", async (e) => {
    e.preventDefault();
    const nombre = document.getElementById("perfil-nombre").value.trim();
    const telefono = document.getElementById("perfil-telefono").value.trim();
    const password = document.getElementById("perfil-password").value;

    const payload = { nombre, telefono };
    if (password.length > 0) payload.password = password;

    await ejecutar(async () => {
        const res = await pedir(API_USUARIOS, "/mi-perfil", "PUT", payload);
        estado.usuarioActual.priNom = nombre;
        estado.usuarioActual.telUsu = telefono;
        document.getElementById("usuario-sesion-info").textContent =
            `${nombre} (${(estado.usuarioActual.rol || '').toUpperCase()})`;
        if (modalPerfil) modalPerfil.close();
        mostrarMensaje(mensajePanel, res.message);
    });
});

// Botón refrescar Mi Ruta
const btnRecargarMiRuta = document.getElementById("btn-recargar-mi-ruta");
if (btnRecargarMiRuta) {
    btnRecargarMiRuta.addEventListener("click", async () => {
        await ejecutar(async () => {
            const res = await pedir(API_GESTION, "/mi-ruta");
            estado.miRuta = res.data;
            dibujarMiRuta();
            mostrarMensaje(mensajePanel, "Datos de tu ruta actualizados.");
        });
    });
}

// Envío de formularios
document.getElementById("form-usuario")?.addEventListener("submit", guardarUsuario);
document.getElementById("form-contenedor")?.addEventListener("submit", guardarContenedor);
document.getElementById("form-camion")?.addEventListener("submit", guardarCamion);
document.getElementById("form-ruta")?.addEventListener("submit", guardarRuta);
document.getElementById("form-cuadrilla")?.addEventListener("submit", guardarCuadrilla);
document.getElementById("form-asignacion")?.addEventListener("submit", guardarAsignacion);
document.getElementById("form-maquinaria")?.addEventListener("submit", guardarMaquinaria);
document.getElementById("form-centro")?.addEventListener("submit", guardarCentro);
document.getElementById("form-vertedero")?.addEventListener("submit", guardarVertedero);
document.getElementById("form-garaje")?.addEventListener("submit", guardarGaraje);
document.getElementById("form-estacionamiento")?.addEventListener("submit", guardarEstacionamiento);
document.getElementById("form-taller")?.addEventListener("submit", guardarTaller);
document.getElementById("form-reparacion")?.addEventListener("submit", guardarReparacion);
document.getElementById("form-envio-vertedero")?.addEventListener("submit", guardarEnvioVertedero);

// Delegación de acciones de clic en tablas
document.getElementById("tabla-usuarios")?.addEventListener("click", manejarAccion);
document.getElementById("tabla-contenedores")?.addEventListener("click", manejarAccion);
document.getElementById("tabla-camiones")?.addEventListener("click", manejarAccion);
document.getElementById("tabla-rutas")?.addEventListener("click", manejarAccion);
document.getElementById("tabla-cuadrillas")?.addEventListener("click", manejarAccion);
document.getElementById("tabla-maquinaria")?.addEventListener("click", manejarAccion);
document.getElementById("tabla-centros")?.addEventListener("click", manejarAccion);
document.getElementById("tabla-vertederos")?.addEventListener("click", manejarAccion);
document.getElementById("tabla-garajes")?.addEventListener("click", manejarAccion);
document.getElementById("tabla-talleres")?.addEventListener("click", manejarAccion);
document.getElementById("tabla-reparaciones")?.addEventListener("click", manejarAccionReparaciones);

// Acciones en tabla de incidencias (Asignar a equipo especializado / Eliminar)
document.getElementById("tabla-incidencias")?.addEventListener("click", (evento) => {
    const btnAsignar = evento.target.closest("[data-accion='abrir-asignar-incidencia']");
    if (btnAsignar) {
        const idInci = Number(btnAsignar.dataset.id);
        const inci = estado.incidencias.find(i => Number(i.idInci) === idInci);
        if (!inci) return;

        const diag = document.getElementById("dialogo-asignar-incidencia");
        const info = document.getElementById("info-asignar-incidencia");
        const inputId = document.getElementById("input-asignar-inci-id");
        const selectVehi = document.getElementById("select-asignar-vehi");

        if (diag && info && inputId && selectVehi) {
            inputId.value = idInci;
            info.innerHTML = `Asignar <b>Incidencia #${inci.idInci}</b> en Contenedor #${inci.idCon} (Tipo: <b>${escapar(inci.tipoInci)}</b>).`;

            selectVehi.innerHTML = '<option value="">Seleccionar vehículo de la flota...</option>' +
                estado.camiones.map(v => `
                    <option value="${v.idVehi}">
                        ${escapar(v.matriculaVehi)} - ${escapar(v.marcaVehi)} ${escapar(v.modeloVehi)} [${escapar(v.tipoVehi)}] (${escapar(v.estVehi)})
                    </option>
                `).join("");

            diag.showModal();
        }
        return;
    }

    manejarAccion(evento);
});

document.getElementById("btn-cerrar-asignar-incidencia")?.addEventListener("click", () => {
    document.getElementById("dialogo-asignar-incidencia")?.close();
});

document.getElementById("form-asignar-incidencia")?.addEventListener("submit", async (e) => {
    e.preventDefault();
    const idInci = document.getElementById("input-asignar-inci-id")?.value;
    const idVehi = document.getElementById("select-asignar-vehi")?.value;
    if (!idInci || !idVehi) return;

    await ejecutar(async () => {
        const res = await pedir(API_CONTENEDORES, `/incidencias/${idInci}/asignar`, "POST", { idVehi });
        mostrarMensaje(mensajePanel, res.message);
        document.getElementById("dialogo-asignar-incidencia")?.close();

        // Recargar incidencias y refrescar tabla
        const incRes = await pedir(API_CONTENEDORES, "/incidencias");
        estado.incidencias = incRes.data || [];
        dibujarIncidencias();
        actualizarIndicadores();
        dibujarNotificaciones();
    });
});

// CU1: Búsqueda rápida de entidades (Filtro en tiempo real para tablas)
document.addEventListener("input", (evento) => {
    if (evento.target.matches("input[data-filtro-tabla]")) {
        const query = evento.target.value.toLowerCase().trim();
        const tableId = evento.target.getAttribute("data-filtro-tabla");
        const tbody = document.getElementById(tableId);
        if (!tbody) return;
        const rows = tbody.querySelectorAll("tr");
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(query) ? "" : "none";
        });
    }
});

// CU-08: Bloqueo de Cédulas
document.getElementById("btn-bloquear-cedula")?.addEventListener("click", async () => {
    const input = document.getElementById("input-bloquear-cedula");
    const cedula = input ? input.value.trim() : "";
    if (!cedula) return;
    await ejecutar(async () => {
        const res = await pedir(API_CONTENEDORES, "/cedulas-bloqueadas", "POST", { cedula });
        mostrarMensaje(mensajePanel, res.message);
        if (input) input.value = "";
        const cedRes = await pedir(API_CONTENEDORES, "/cedulas-bloqueadas");
        estado.cedulasBloqueadas = cedRes.data || [];
        dibujarCedulasBloqueadas();
    });
});

// Cambios de estado en incidencias (general y mi-ruta)
document.addEventListener("change", async (evento) => {
    const selector = evento.target.closest(".selector-estado-incidencia");
    if (!selector) return;

    const id = Number(selector.dataset.id);
    const nuevoEstado = selector.value;

    await ejecutar(async () => {
        const resultado = await pedir(API_CONTENEDORES, `/incidencias/${id}`, "PUT", { estado: nuevoEstado });
        mostrarMensaje(mensajePanel, resultado.message);

        // Actualizar estado local
        const item = estado.incidencias.find(i => Number(i.idInci) === id);
        if (item) item.estado = nuevoEstado;

        if (estado.miRuta && Array.isArray(estado.miRuta.incidencias)) {
            const itemRuta = estado.miRuta.incidencias.find(i => Number(i.idInci) === id);
            if (itemRuta) itemRuta.estado = nuevoEstado;
        }

        renderizarGraficos();
    });
});

// =========================================================
// INICIALIZACIÓN DEL PANEL
// =========================================================

async function iniciarPanel() {
    try {
        const sesion = await pedir(API_USUARIOS, "/sesion");
        const rol = sesion.data.rol;
        const rolesPermitidos = ["administrador", "municipal", "cuadrilla", "operario"];

        if (!rolesPermitidos.includes(rol)) {
            window.location.href = "index.html";
            return;
        }

        estado.usuarioActual = sesion.data;
        document.getElementById("usuario-sesion-info").textContent =
            `${sesion.data.priNom} (${rol.toUpperCase()})`;

        // Filtrar botones de navegación según el rol
        document.querySelectorAll(".panel-menu button").forEach(btn => {
            const permitidos = (btn.dataset.roles || "").split(",").map(r => r.trim());
            if (!permitidos.includes(rol)) {
                btn.style.display = "none";
            } else {
                btn.style.display = "block";
            }
        });

        // Filtrar botones de acción según el rol
        document.querySelectorAll("[data-nuevo]").forEach(btn => {
            const permitidos = (btn.dataset.roles || "administrador,municipal").split(",").map(r => r.trim());
            if (!permitidos.includes(rol)) {
                btn.style.display = "none";
            } else {
                btn.style.display = "";
            }
        });

        // Establecer sección inicial según rol
        if (rol === "cuadrilla") {
            cambiarSeccion("mi-ruta");
        } else if (rol === "operario") {
            cambiarSeccion("maquinaria");
        } else if (rol === "municipal") {
            cambiarSeccion("asignaciones");
        } else {
            cambiarSeccion("resumen");
        }

        await cargarDatosSegunRol(rol);
    } catch (error) {
        if (error.estado === 401 || error.estado === 403) {
            window.location.href = "login.html";
        } else {
            mostrarMensaje(mensajePanel, "Error al iniciar sesión: " + error.message, true);
        }
    }
}

async function cargarDatosSegunRol(rol) {
    await ejecutar(async () => {
        // 1. Centros y Vertederos
        const [centrosRes, vertederosRes, enviosRes] = await Promise.all([
            pedir(API_CENTROS, "/centros"),
            pedir(API_CENTROS, "/vertederos"),
            pedir(API_CENTROS, "/envios-vertedero")
        ]);
        estado.centros = centrosRes.data || [];
        estado.vertederos = vertederosRes.data || [];
        estado.enviosVertedero = enviosRes.data || [];

        dibujarCentrosVertederos();
        dibujarEnviosVertedero();
        actualizarSelectCentros();

        // 2. Garajes y Talleres (CU-25, CU-26, CU-27, CU-28)
        const [garRes, estacRes, talRes, repRes] = await Promise.all([
            pedir(API_LOGISTICA, "/garajes"),
            pedir(API_LOGISTICA, "/estacionamientos"),
            pedir(API_LOGISTICA, "/talleres"),
            pedir(API_LOGISTICA, "/reparaciones")
        ]);
        estado.garajes = garRes.data || [];
        estado.estacionamientos = estacRes.data || [];
        estado.talleres = talRes.data || [];
        estado.reparaciones = repRes.data || [];

        dibujarGarajes();
        dibujarEstacionamientos();
        dibujarTalleres();
        dibujarReparaciones();
        actualizarSelectsGarajeYTaller();

        // 3. Cuadrilla
        if (rol === "cuadrilla") {
            const [miRutaRes, rutRes, asigRes, incRes, vehRes] = await Promise.all([
                pedir(API_LOGISTICA, "/mi-ruta"),
                pedir(API_LOGISTICA, "/rutas"),
                pedir(API_LOGISTICA, "/asignaciones"),
                pedir(API_CONTENEDORES, "/incidencias"),
                pedir(API_LOGISTICA, "/camiones")
            ]);
            estado.miRuta = miRutaRes.data;
            estado.rutas = rutRes.data || [];
            estado.asignaciones = asigRes.data || [];
            estado.incidencias = incRes.data || [];
            estado.camiones = vehRes.data || [];

            dibujarMiRuta();
            dibujarRutas();
            dibujarAsignaciones();
            dibujarIncidencias();
        }

        // 4. Operario / Maquinaria
        if (rol === "operario" || rol === "administrador" || rol === "municipal") {
            const maqRes = await pedir(API_CENTROS, "/maquinaria");
            estado.maquinaria = maqRes.data || [];
            dibujarMaquinaria();
        }

        // 5. Gestión (Admin / Municipal)
        if (rol === "administrador" || rol === "municipal") {
            const [contRes, vehRes, incRes, rutRes, cuadRes, cuadrillerosRes, asigRes] = await Promise.all([
                pedir(API_CONTENEDORES, "/contenedores"),
                pedir(API_LOGISTICA, "/camiones"),
                pedir(API_CONTENEDORES, "/incidencias"),
                pedir(API_LOGISTICA, "/rutas"),
                pedir(API_LOGISTICA, "/cuadrillas"),
                pedir(API_LOGISTICA, "/cuadrilleros"),
                pedir(API_LOGISTICA, "/asignaciones")
            ]);

            estado.contenedores = contRes.data || [];
            estado.camiones = vehRes.data || [];
            estado.incidencias = incRes.data || [];
            estado.rutas = rutRes.data || [];
            estado.cuadrillas = cuadRes.data || [];
            estado.cuadrilleros = cuadrillerosRes.data || [];
            estado.asignaciones = asigRes.data || [];

            dibujarContenedores();
            dibujarCamiones();
            dibujarIncidencias();
            dibujarRutas();
            dibujarCuadrillas();
            dibujarAsignaciones();
            actualizarSelectsAsignacion();
            actualizarSelectCuadrilleros();
            actualizarListaContenedoresRuta();
            actualizarSelectsGarajeYTaller();
        }

        // 6. Admin
        if (rol === "administrador") {
            const [usuRes, cedRes, logsRes] = await Promise.all([
                pedir(API_USUARIOS, "/usuarios"),
                pedir(API_CONTENEDORES, "/cedulas-bloqueadas"),
                pedir(API_USUARIOS, "/logs-login")
            ]);
            estado.usuarios = usuRes.data || [];
            estado.cedulasBloqueadas = cedRes.data || [];
            estado.logsLogin = logsRes.data || [];

            dibujarUsuarios();
            dibujarCedulasBloqueadas();
            dibujarLogsLogin();
        }

        actualizarIndicadores();
        renderizarGraficos();
        dibujarNotificaciones();
    });
}

// =========================================================
// RENDERIZADO DE TABLAS
// =========================================================

function dibujarUsuarios() {
    const tbody = document.getElementById("tabla-usuarios");
    if (!tbody) return;
    tbody.innerHTML = estado.usuarios.map(u => `
        <tr>
            <td>${u.idUsu}</td>
            <td>${escapar(u.priNom)}</td>
            <td>${escapar(u.email)}</td>
            <td><b>${escapar(u.rol)}</b></td>
            <td><span class="badge">${escapar(u.estUsu)}</span></td>
            <td class="acciones-tabla">
                <button class="accion-tabla editar" data-accion="editar" data-entidad="usuario" data-id="${u.idUsu}">Editar</button>
                <button class="accion-tabla eliminar" data-accion="eliminar" data-entidad="usuario" data-id="${u.idUsu}">Eliminar</button>
            </td>
        </tr>`).join("");
}

function dibujarContenedores() {
    const tbody = document.getElementById("tabla-contenedores");
    if (!tbody) return;
    tbody.innerHTML = estado.contenedores.map(c => `
        <tr>
            <td>#${c.idCon}</td>
            <td>${escapar(c.calle)} y ${escapar(c.esquina)}</td>
            <td>${escapar(c.zona)}</td>
            <td>${escapar(c.tipoCon)}</td>
            <td>${Number(c.capacidad).toLocaleString("es-UY")} L</td>
            <td><span class="badge">${escapar(c.estCon)}</span></td>
            <td class="acciones-tabla">
                <button class="accion-tabla editar" data-accion="editar" data-entidad="contenedor" data-id="${c.idCon}">Editar</button>
                <button class="accion-tabla eliminar" data-accion="eliminar" data-entidad="contenedor" data-id="${c.idCon}">Eliminar</button>
            </td>
        </tr>`).join("");

    inicializarMapaGeneralContenedores();
    actualizarMarcadoresGenerales();
}

function dibujarCamiones() {
    const tbody = document.getElementById("tabla-camiones");
    if (!tbody) return;
    tbody.innerHTML = estado.camiones.map(camion => `
        <tr>
            <td>#${camion.idVehi}</td>
            <td><b>${escapar(camion.matriculaVehi)}</b></td>
            <td>${escapar(camion.marcaVehi)} ${escapar(camion.modeloVehi)}</td>
            <td>${escapar(camion.tipoVehi)}</td>
            <td>${Number(camion.capVehi).toLocaleString("es-UY")} kg</td>
            <td><span class="badge ${camion.estVehi === 'disponible' ? 'disponible' : (camion.estVehi === 'roto' ? 'roto' : 'en-uso')}">${escapar(camion.estVehi)}</span></td>
            <td class="acciones-tabla">
                <button class="accion-tabla editar" data-accion="editar" data-entidad="camion" data-id="${camion.idVehi}">Editar</button>
                <button class="accion-tabla eliminar" data-accion="eliminar" data-entidad="camion" data-id="${camion.idVehi}">Eliminar</button>
            </td>
        </tr>`).join("");
}

function dibujarIncidencias() {
    const tbody = document.getElementById("tabla-incidencias");
    if (!tbody) return;
    const ordenadas = [...estado.incidencias].sort((a, b) => Number(a.idInci) - Number(b.idInci));
    tbody.innerHTML = ordenadas.map(incidencia => {
        const estActual = incidencia.estado || "en curso";
        const esEspecializada = ["roto", "incendiado"].includes(incidencia.tipoInci);

        let equipoTexto = "";
        if (incidencia.matriculaVehi) {
            equipoTexto = `<span style="color: var(--verde-oscuro); font-weight: bold;">🚛 ${escapar(incidencia.matriculaVehi)} (${escapar(incidencia.modeloVehi || "")})</span>`;
        } else if (esEspecializada) {
            equipoTexto = `<span style="background: #fff3cd; color: #856404; padding: 2px 8px; border-radius: 4px; font-size: 0.8rem; font-weight: bold; border: 1px solid #ffeeba;">Libre (Especializado)</span>`;
        } else {
            equipoTexto = `<span style="color: var(--gris); font-size: 0.85rem;">Ruta ordinaria</span>`;
        }

        return `
        <tr>
            <td>#${incidencia.idInci}</td>
            <td>${escapar(incidencia.fchaInci || "-")}</td>
            <td>#${incidencia.idCon} - ${escapar(incidencia.ubicacion || "")}</td>
            <td title="${escapar(incidencia.descInci || "")}"><b>${escapar(incidencia.tipoInci || "")}</b></td>
            <td>${escapar(incidencia.prioridad || "")}</td>
            <td>${equipoTexto}</td>
            <td>
                <select class="selector-estado-incidencia" data-id="${incidencia.idInci}">
                    <option value="resuelto"${estActual === "resuelto" ? " selected" : ""}>Resuelto</option>
                    <option value="en curso"${estActual === "en curso" ? " selected" : ""}>En curso</option>
                    <option value="descartado"${estActual === "descartado" ? " selected" : ""}>Descartado</option>
                </select>
            </td>
            <td class="acciones-tabla" style="white-space: nowrap;">
                <button class="boton" type="button" style="padding: 4px 8px; font-size: 0.8rem; margin-right: 4px;" data-accion="abrir-asignar-incidencia" data-id="${incidencia.idInci}">Asignar incidencias</button>
                <button class="accion-tabla eliminar" type="button" data-accion="eliminar" data-entidad="incidencia" data-id="${incidencia.idInci}">Eliminar</button>
            </td>
        </tr>`;
    }).join("");
}

function dibujarRutas() {
    const tbody = document.getElementById("tabla-rutas");
    if (!tbody) return;
    const esGestion = ["administrador", "municipal"].includes(estado.usuarioActual?.rol);
    tbody.innerHTML = estado.rutas.map(r => `
        <tr>
            <td>#${r.idRuta}</td>
            <td><b>${escapar(r.frecuencia)}</b></td>
            <td>Centro #${r.idCentro} (${escapar(r.tipoCentro || "")})</td>
            <td>${r.cantidadContenedores || 0} contenedores</td>
            <td class="acciones-tabla">
                ${esGestion ? `
                <button class="accion-tabla editar" data-accion="editar" data-entidad="ruta" data-id="${r.idRuta}">Editar</button>
                <button class="accion-tabla eliminar" data-accion="eliminar" data-entidad="ruta" data-id="${r.idRuta}">Eliminar</button>
                ` : '<span style="color: var(--gris); font-size: 0.85rem;">Solo lectura</span>'}
            </td>
        </tr>`).join("");
}

function dibujarCuadrillas() {
    const tbody = document.getElementById("tabla-cuadrillas");
    if (!tbody) return;
    tbody.innerHTML = estado.cuadrillas.map(c => `
        <tr>
            <td>#${c.idCuadrilla}</td>
            <td>${escapar(c.nomChofer)}</td>
            <td>${escapar(c.telChofer || "-")}</td>
            <td>${escapar(c.nomPeon)}</td>
            <td>${escapar(c.telPeon || "-")}</td>
            <td>${c.matriculaVehi ? `<b>${escapar(c.matriculaVehi)}</b> (${escapar(c.marcaVehi || "")} ${escapar(c.modeloVehi || "")})` : '<span style="color: var(--gris);">(Sin asignar)</span>'}</td>
            <td class="acciones-tabla">
                <button class="accion-tabla eliminar" data-accion="eliminar" data-entidad="cuadrilla" data-id="${c.idCuadrilla}">Eliminar</button>
            </td>
        </tr>`).join("");
}

function dibujarAsignaciones() {
    const tbody = document.getElementById("tabla-asignaciones");
    if (!tbody) return;
    tbody.innerHTML = estado.asignaciones.map(a => `
        <tr>
            <td>#${a.idOperacion}</td>
            <td>${escapar(a.fchOperacion)}</td>
            <td>Cuadrilla #${a.idCuadrilla} (${escapar(a.nomChofer)} y ${escapar(a.nomPeon)})</td>
            <td>${escapar(a.matriculaVehi)} (${escapar(a.marcaVehi)} ${escapar(a.modeloVehi)})</td>
            <td>${a.idRuta ? `Ruta #${a.idRuta} (${escapar(a.frecuencia || "")})` : "Sin ruta fija"}</td>
        </tr>`).join("");
}

// CU-18: Mapa de Mi Ruta y paradas
function dibujarMiRuta() {
    const tarjetas = document.getElementById("mi-ruta-tarjetas");
    const tablaCont = document.getElementById("tabla-mi-ruta-contenedores");
    const tablaInci = document.getElementById("tabla-mi-ruta-incidencias");
    if (!tarjetas || !tablaCont || !tablaInci) return;

    const data = estado.miRuta;
    if (!data || !data.cuadrilla) {
        tarjetas.innerHTML = `<div style="grid-column: 1 / -1; padding: 18px; background: #fff3cd; border: 1px solid #ffeeba; border-radius: 6px; color: #856404;">
            No tenés un equipo de cuadrilla asignado actualmente. Contactá al encargado municipal.
        </div>`;
        tablaCont.innerHTML = `<tr><td colspan="7" style="text-align: center; color: var(--gris);">Sin contenedores asignados.</td></tr>`;
        tablaInci.innerHTML = `<tr><td colspan="7" style="text-align: center; color: var(--gris);">Sin incidencias para mostrar.</td></tr>`;
        return;
    }

    const c = data.cuadrilla;
    const v = data.vehiculo;
    const r = data.ruta;

    tarjetas.innerHTML = `
        <article>
            <span>Equipo</span>
            <b>Cuadrilla #${c.idCuadrilla}</b>
            <p>Chofer: ${escapar(c.nomChofer)} (${escapar(c.telChofer || "-")})</p>
            <p>Peón: ${escapar(c.nomPeon)} (${escapar(c.telPeon || "-")})</p>
        </article>
        <article>
            <span>Vehículo</span>
            <b>${v ? escapar(v.matriculaVehi) : "Sin vehículo"}</b>
            <p>${v ? `${escapar(v.marcaVehi)} ${escapar(v.modeloVehi)} (${escapar(v.tipoVehi)})` : "Pendiente de asignación"}</p>
            <p>${v ? `Capacidad: ${Number(v.capVehi).toLocaleString("es-UY")} kg` : ""}</p>
        </article>
        <article>
            <span>Ruta</span>
            <b>${r ? `Ruta #${r.idRuta}` : "Sin ruta"}</b>
            <p>${r ? escapar(r.frecuencia) : "Pendiente de asignación"}</p>
            <p>${r ? `Destino: Centro #${r.idCentro} (${escapar(r.tipoCentro || "")})` : ""}</p>
        </article>
    `;

    // Contenedores
    const contenedores = data.contenedores || [];
    if (contenedores.length === 0) {
        tablaCont.innerHTML = `<tr><td colspan="7" style="text-align: center; color: var(--gris);">No hay contenedores registrados en esta ruta.</td></tr>`;
    } else {
        tablaCont.innerHTML = contenedores.map(con => `
            <tr>
                <td><b>Parada #${con.orden}</b></td>
                <td>#${con.idCon}</td>
                <td>${escapar(con.calle)} y ${escapar(con.esquina)}</td>
                <td>${escapar(con.zona)}</td>
                <td>${escapar(con.tipoCon)}</td>
                <td>${Number(con.capacidad).toLocaleString("es-UY")} L</td>
                <td><span class="badge">${escapar(con.estCon)}</span></td>
            </tr>`).join("");
    }

    // Incidencias en la ruta
    const incidencias = data.incidencias || [];
    if (incidencias.length === 0) {
        tablaInci.innerHTML = `<tr><td colspan="7" style="text-align: center; color: var(--verde-oscuro); font-weight: bold;">¡Excelente! No hay incidencias pendientes en los contenedores de tu ruta.</td></tr>`;
    } else {
        tablaInci.innerHTML = incidencias.map(inci => {
            const estActual = inci.estado || "en curso";
            return `
            <tr>
                <td>#${inci.idInci}</td>
                <td><b>Parada #${inci.ordenParada || "-"}</b></td>
                <td>${escapar(inci.fchaInci || "-")}</td>
                <td>#${inci.idCon} - ${escapar(inci.ubicacion || "")}</td>
                <td title="${escapar(inci.descInci || "")}"><b>${escapar(inci.tipoInci)}</b></td>
                <td>${escapar(inci.prioridad)}</td>
                <td>
                    <select class="selector-estado-incidencia" data-id="${inci.idInci}">
                        <option value="resuelto"${estActual === "resuelto" ? " selected" : ""}>Resuelto</option>
                        <option value="en curso"${estActual === "en curso" ? " selected" : ""}>En curso</option>
                        <option value="descartado"${estActual === "descartado" ? " selected" : ""}>Descartado</option>
                    </select>
                </td>
            </tr>`;
        }).join("");
    }

    // CU-18: Renderizar mapa interactivo del recorrido
    renderizarMapaMiRuta(contenedores);
}

function renderizarMapaMiRuta(contenedores) {
    const div = document.getElementById("mapa-mi-ruta");
    if (!div || typeof L === "undefined") return;

    if (!mapaMiRuta) {
        mapaMiRuta = L.map("mapa-mi-ruta").setView([-34.9011, -56.1645], 13);
        L.tileLayer("https://tile.openstreetmap.org/{z}/{x}/{y}.png", {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap'
        }).addTo(mapaMiRuta);
    }

    if (capaRutaGeo) {
        mapaMiRuta.removeLayer(capaRutaGeo);
    }
    capaRutaGeo = L.layerGroup().addTo(mapaMiRuta);

    const puntos = [];
    contenedores.forEach(c => {
        if (c.latitud && c.longitud) {
            const lat = Number(c.latitud);
            const lng = Number(c.longitud);
            puntos.push([lat, lng]);

            const marker = L.circleMarker([lat, lng], {
                radius: 9,
                fillColor: "#2980b9",
                color: "#1b4f72",
                weight: 2,
                opacity: 1,
                fillOpacity: 0.85
            }).addTo(capaRutaGeo);

            marker.bindPopup(`<b>Parada #${c.orden}</b><br>Contenedor #${c.idCon}<br>${escapar(c.calle)} y ${escapar(c.esquina)}`);
        }
    });

    if (puntos.length > 1) {
        L.polyline(puntos, {
            color: "#e67e22",
            weight: 4,
            opacity: 0.85,
            dashArray: "6, 8"
        }).addTo(capaRutaGeo);
        mapaMiRuta.fitBounds(L.latLngBounds(puntos).pad(0.1));
    } else if (puntos.length === 1) {
        mapaMiRuta.setView(puntos[0], 15);
    }
    setTimeout(() => mapaMiRuta.invalidateSize(), 200);
}

function dibujarMaquinaria() {
    const tbody = document.getElementById("tabla-maquinaria");
    if (!tbody) return;
    const esGestion = ["administrador", "operario", "municipal"].includes(estado.usuarioActual?.rol);
    tbody.innerHTML = estado.maquinaria.map(m => `
        <tr>
            <td>#${m.idMaq}</td>
            <td><b>${escapar(m.marcaMaq)} ${escapar(m.modeloMaq)}</b></td>
            <td><code>${escapar(m.numSerie)}</code></td>
            <td>${escapar(m.propositoMaq)}</td>
            <td>${Number(m.capMaq).toLocaleString("es-UY")} kg/h</td>
            <td>Centro #${m.idCentro} (${escapar(m.tipoCentro || "")})</td>
            <td class="acciones-tabla">
                ${esGestion ? `
                <button class="accion-tabla editar" data-accion="editar" data-entidad="maquinaria" data-id="${m.idMaq}">Editar</button>
                <button class="accion-tabla eliminar" data-accion="eliminar" data-entidad="maquinaria" data-id="${m.idMaq}">Eliminar</button>
                ` : '<span style="color: var(--gris); font-size: 0.85rem;">Solo lectura</span>'}
            </td>
        </tr>`).join("");
}

function dibujarCentrosVertederos() {
    const tbodyCen = document.getElementById("tabla-centros");
    const tbodyVert = document.getElementById("tabla-vertederos");

    if (tbodyCen) {
        const esGestion = ["administrador", "municipal"].includes(estado.usuarioActual?.rol);
        tbodyCen.innerHTML = estado.centros.map(c => `
            <tr>
                <td>#${c.idCentro}</td>
                <td>${escapar(c.ubiCentro || '')}</td>
                <td><b>${escapar(c.tipoCentro)}</b></td>
                <td>${Number(c.capCentro).toLocaleString("es-UY")} kg</td>
                <td class="acciones-tabla">
                    ${esGestion ? `
                    <button class="accion-tabla editar" data-accion="editar" data-entidad="centro" data-id="${c.idCentro}">Editar</button>
                    <button class="accion-tabla eliminar" data-accion="eliminar" data-entidad="centro" data-id="${c.idCentro}">Eliminar</button>
                    ` : '<span style="color: var(--gris); font-size: 0.85rem;">-</span>'}
                </td>
            </tr>`).join("");
    }

    if (tbodyVert) {
        const esGestion = ["administrador", "municipal"].includes(estado.usuarioActual?.rol);
        tbodyVert.innerHTML = estado.vertederos.map(v => `
            <tr>
                <td>#${v.idVertedero}</td>
                <td>${escapar(v.ubicacionVertedero)}</td>
                <td class="acciones-tabla">
                    ${esGestion ? `
                    <button class="accion-tabla editar" data-accion="editar" data-entidad="vertedero" data-id="${v.idVertedero}">Editar</button>
                    <button class="accion-tabla eliminar" data-accion="eliminar" data-entidad="vertedero" data-id="${v.idVertedero}">Eliminar</button>
                    ` : '<span style="color: var(--gris); font-size: 0.85rem;">-</span>'}
                </td>
            </tr>`).join("");
    }
}

// CU-25: Garajes
function dibujarGarajes() {
    const tbody = document.getElementById("tabla-garajes");
    if (!tbody) return;
    const esGestion = ["administrador", "municipal"].includes(estado.usuarioActual?.rol);
    tbody.innerHTML = estado.garajes.map(g => `
        <tr>
            <td>#${g.idGaraje}</td>
            <td>${escapar(g.ubiGaraje)}</td>
            <td><b>${g.vehiculosEstacionados || 0} vehículos</b></td>
            <td class="acciones-tabla">
                ${esGestion ? `
                <button class="accion-tabla editar" data-accion="editar" data-entidad="garaje" data-id="${g.idGaraje}">Editar</button>
                <button class="accion-tabla eliminar" data-accion="eliminar" data-entidad="garaje" data-id="${g.idGaraje}">Eliminar</button>
                ` : '<span style="color: var(--gris); font-size: 0.85rem;">-</span>'}
            </td>
        </tr>`).join("");
}

// CU-26: Estacionamientos
function dibujarEstacionamientos() {
    const tbody = document.getElementById("tabla-estacionamientos");
    if (!tbody) return;
    tbody.innerHTML = estado.estacionamientos.map(e => `
        <tr>
            <td>#${e.idEstacion}</td>
            <td><b>${escapar(e.matriculaVehi)}</b> (${escapar(e.marcaVehi)} ${escapar(e.modeloVehi)})</td>
            <td>${escapar(e.ubiGaraje)}</td>
            <td>${escapar(e.horaEstacionamiento)}</td>
        </tr>`).join("");
}

// CU-27: Talleres
function dibujarTalleres() {
    const tbody = document.getElementById("tabla-talleres");
    if (!tbody) return;
    const esGestion = ["administrador", "municipal"].includes(estado.usuarioActual?.rol);
    tbody.innerHTML = estado.talleres.map(t => `
        <tr>
            <td>#${t.idMant}</td>
            <td>${escapar(t.ubiMant)}</td>
            <td><b>${t.vehiculosEnReparacion || 0} en taller</b></td>
            <td class="acciones-tabla">
                ${esGestion ? `
                <button class="accion-tabla editar" data-accion="editar" data-entidad="taller" data-id="${t.idMant}">Editar</button>
                <button class="accion-tabla eliminar" data-accion="eliminar" data-entidad="taller" data-id="${t.idMant}">Eliminar</button>
                ` : '<span style="color: var(--gris); font-size: 0.85rem;">-</span>'}
            </td>
        </tr>`).join("");
}

// CU-28: Reparaciones
function dibujarReparaciones() {
    const tbody = document.getElementById("tabla-reparaciones");
    if (!tbody) return;
    const esGestion = ["administrador", "municipal"].includes(estado.usuarioActual?.rol);
    tbody.innerHTML = estado.reparaciones.map(r => `
        <tr>
            <td>#${r.idReparacion}</td>
            <td><b>${escapar(r.matriculaVehi)}</b> (${escapar(r.marcaVehi)} ${escapar(r.modeloVehi)})</td>
            <td>${escapar(r.ubiMant)}</td>
            <td>${escapar(r.horaIngreso || "-")}</td>
            <td>${escapar(r.horaSalida || "En taller actualmente")}</td>
            <td><span class="badge ${r.horaSalida ? 'disponible' : 'roto'}">${escapar(r.estadoReparacion)}</span></td>
            <td class="acciones-tabla">
                ${esGestion && !r.horaSalida ? `
                <button class="accion-tabla" style="background:#27ae60; color:white;" data-finalizar-rep="${r.idReparacion}">Finalizar Mantenimiento</button>
                ` : '<span style="color: var(--gris); font-size: 0.85rem;">-</span>'}
            </td>
        </tr>`).join("");
}

// CU-23: Envíos a Vertedero
function dibujarEnviosVertedero() {
    const tbody = document.getElementById("tabla-envios-vertedero");
    if (!tbody) return;
    tbody.innerHTML = estado.enviosVertedero.map(env => `
        <tr>
            <td>#${env.idEnvio}</td>
            <td>Centro #${env.idCentro} - ${escapar(env.ubiCentro)}</td>
            <td><b>${escapar(env.tipoCentro)}</b></td>
            <td>Vertedero #${env.idVertedero} - ${escapar(env.ubicacionVertedero)}</td>
            <td>${escapar(env.fchVertido)}</td>
        </tr>`).join("");
}

// CU-08: Cédulas Bloqueadas
function dibujarCedulasBloqueadas() {
    const contenedor = document.getElementById("lista-cedulas-bloqueadas");
    if (!contenedor) return;
    if (estado.cedulasBloqueadas.length === 0) {
        contenedor.innerHTML = '<span style="color: var(--gris); font-size: 0.85rem;">No hay cédulas bloqueadas actualmente.</span>';
        return;
    }
    contenedor.innerHTML = estado.cedulasBloqueadas.map(c => `
        <span style="background: #fadbd8; color: #78281f; padding: 4px 10px; border-radius: 12px; font-size: 0.85rem; display: inline-flex; align-items: center; gap: 6px;">
            🔒 ${escapar(c)}
            <button type="button" data-desbloquear-cedula="${c}" style="background:none; border:none; color:#c0392b; cursor:pointer; font-weight:bold;">✕</button>
        </span>
    `).join("");

    contenedor.querySelectorAll("[data-desbloquear-cedula]").forEach(btn => {
        btn.addEventListener("click", async () => {
            const ced = btn.dataset.desbloquearCedula;
            await ejecutar(async () => {
                const res = await pedir(API_CONTENEDORES, `/cedulas-bloqueadas/${ced}`, "DELETE");
                mostrarMensaje(mensajePanel, res.message);
                const cedRes = await pedir(API_CONTENEDORES, "/cedulas-bloqueadas");
                estado.cedulasBloqueadas = cedRes.data || [];
                dibujarCedulasBloqueadas();
            });
        });
    });
}

// CU-30: Logs de Login
function dibujarLogsLogin() {
    const tbody = document.getElementById("tabla-logs-login");
    if (!tbody) return;
    if (estado.logsLogin.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; color: var(--gris);">Sin registros de acceso disponibles.</td></tr>';
        return;
    }
    tbody.innerHTML = estado.logsLogin.slice(0, 30).map(l => `
        <tr>
            <td>${escapar(l.fechaHora)}</td>
            <td><b>${escapar(l.email)}</b></td>
            <td>${escapar(l.rol)}</td>
            <td><code>${escapar(l.ip)}</code></td>
            <td><span class="badge ${l.exito ? 'disponible' : 'roto'}">${l.exito ? 'Correcto' : 'Fallido'}</span></td>
        </tr>
    `).join("");
}

// =========================================================
// CU-03: CREACIÓN DE ESTADÍSTICAS Y GRÁFICOS
// =========================================================

function renderizarGraficos() {
    if (typeof Chart === "undefined") return;

    // 1. Incidencias por Estado
    const ctxEstado = document.getElementById("grafico-incidencias-estado")?.getContext("2d");
    if (ctxEstado) {
        const resueltas = estado.incidencias.filter(i => i.estado === "resuelto").length;
        const enCurso = estado.incidencias.filter(i => (i.estado || "en curso") === "en curso").length;
        const descartadas = estado.incidencias.filter(i => i.estado === "descartado").length;

        if (chartEstado) chartEstado.destroy();
        chartEstado = new Chart(ctxEstado, {
            type: "doughnut",
            data: {
                labels: ["En curso", "Resueltas", "Descartadas"],
                datasets: [{
                    data: [enCurso, resueltas, descartadas],
                    backgroundColor: ["#f39c12", "#27ae60", "#95a5a6"]
                }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });
    }

    // 2. Incidencias por Tipo
    const ctxTipo = document.getElementById("grafico-incidencias-tipo")?.getContext("2d");
    if (ctxTipo) {
        const tipos = {};
        estado.incidencias.forEach(i => {
            const t = i.tipoInci || "otros";
            tipos[t] = (tipos[t] || 0) + 1;
        });

        if (chartTipo) chartTipo.destroy();
        chartTipo = new Chart(ctxTipo, {
            type: "bar",
            data: {
                labels: Object.keys(tipos).map(t => t.toUpperCase()),
                datasets: [{
                    label: "Cantidad",
                    data: Object.values(tipos),
                    backgroundColor: "#3498db"
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
        });
    }

    // 3. Flota Vehicular
    const ctxFlota = document.getElementById("grafico-flota-estado")?.getContext("2d");
    if (ctxFlota) {
        const disponibles = estado.camiones.filter(v => v.estVehi === "disponible").length;
        const enUso = estado.camiones.filter(v => v.estVehi === "en uso").length;
        const rotos = estado.camiones.filter(v => v.estVehi === "roto").length;

        if (chartFlota) chartFlota.destroy();
        chartFlota = new Chart(ctxFlota, {
            type: "doughnut",
            data: {
                labels: ["Disponibles", "En Servicio", "En Taller"],
                datasets: [{
                    data: [disponibles, enUso, rotos],
                    backgroundColor: ["#2ecc71", "#3498db", "#e74c3c"]
                }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });
    }
}

function actualizarIndicadores() {
    const setTxt = (id, val) => {
        const el = document.getElementById(id);
        if (el) el.textContent = val;
    };
    setTxt("cantidad-usuarios", estado.usuarios.length);
    setTxt("cantidad-contenedores", estado.contenedores.length);
    setTxt("cantidad-camiones", estado.camiones.length);
    setTxt("cantidad-incidencias", estado.incidencias.length);
    setTxt("cantidad-rutas", estado.rutas.length);
    setTxt("cantidad-cuadrillas", estado.cuadrillas.length);
    setTxt("cantidad-maquinaria", estado.maquinaria.length);
    setTxt("cantidad-centros", estado.centros.length);
}

function dibujarNotificaciones() {
    const lista = document.getElementById("lista-notificaciones");
    const contador = document.getElementById("contador-notificaciones");
    if (!lista || !contador) return;

    const notifs = [];

    // 1. Incidencias de alta prioridad activas
    (estado.incidencias || []).forEach(i => {
        const est = (i.descEstado || "").toLowerCase();
        if (i.prioridad === "alta" && (est === "en curso" || est === "abierto" || !est)) {
            notifs.push({
                id: `inci-${i.idInci}`,
                icono: "🚨",
                titulo: `Incidencia urgente #${i.idInci}`,
                desc: `${i.tipoInci || "Problema"} en contenedor #${i.idCon} (${i.calle || "Zona"}).`,
                hora: i.fchInci ? i.fchInci.substring(0, 16) : ""
            });
        }
    });

    // 2. Incidencias especializadas (roto / incendiado) sin vehículo asignado
    (estado.incidencias || []).forEach(i => {
        const t = (i.tipoInci || "").toLowerCase();
        if ((t === "roto" || t === "incendiado") && (!i.idVehi && !i.atiende_matricula)) {
            notifs.push({
                id: `esp-${i.idInci}`,
                icono: "⚠️",
                titulo: `Incidencia ${i.tipoInci} sin equipo`,
                desc: `Incidencia #${i.idInci} espera asignación de equipo especializado.`,
                hora: i.fchInci ? i.fchInci.substring(0, 16) : ""
            });
        }
    });

    // 3. Vehículos averiados en la flota
    (estado.camiones || []).forEach(v => {
        if (v.estVehi === "roto") {
            notifs.push({
                id: `vehi-${v.idVehi}`,
                icono: "🛠️",
                titulo: `Vehículo averiado: ${v.matriculaVehi || ('#' + v.idVehi)}`,
                desc: `${v.tipoVehi || "Vehículo"} requiere reparación en taller.`,
                hora: ""
            });
        }
    });

    // 4. Notificación para cuadrilla si tiene ruta
    if (estado.miRuta && estado.miRuta.ruta) {
        notifs.push({
            id: `ruta-${estado.miRuta.ruta.idRuta}`,
            icono: "📍",
            titulo: `Ruta asignada #${estado.miRuta.ruta.idRuta}`,
            desc: `${(estado.miRuta.contenedores || []).length} paradas en tu recorrido de hoy.`,
            hora: ""
        });
    }

    estado.notificaciones = notifs;
    const noLeidas = notifs.filter(n => !estado.notificacionesLeidas.has(n.id));

    contador.textContent = noLeidas.length;
    contador.style.display = noLeidas.length > 0 ? "inline-block" : "none";

    if (notifs.length === 0) {
        lista.innerHTML = '<div style="padding: 16px; text-align: center; color: var(--gris); font-size: 0.85rem;">No hay avisos ni alertas pendientes</div>';
        return;
    }

    lista.innerHTML = notifs.map(n => {
        const leida = estado.notificacionesLeidas.has(n.id);
        return `
            <div style="padding: 10px 14px; border-bottom: 1px solid #f1f5f9; background: ${leida ? '#ffffff' : '#f0fdf4'}; display: flex; gap: 10px; align-items: flex-start;">
                <span style="font-size: 1.2rem; line-height: 1.2;">${n.icono}</span>
                <div style="flex: 1; font-size: 0.82rem;">
                    <div style="font-weight: 600; color: var(--texto);">${escapar(n.titulo)}</div>
                    <div style="color: var(--gris); margin-top: 2px;">${escapar(n.desc)}</div>
                    ${n.hora ? `<div style="color: #94a3b8; font-size: 0.72rem; margin-top: 4px;">${escapar(n.hora)}</div>` : ''}
                </div>
            </div>
        `;
    }).join("");
}

// =========================================================
// HELPERS PARA SELECTS Y FORMULARIOS
// =========================================================

function actualizarSelectCentros() {
    const selects = ["select-ruta-centro", "select-maq-centro", "select-envio-centro"];
    selects.forEach(id => {
        const el = document.getElementById(id);
        if (!el) return;
        el.innerHTML = '<option value="">Seleccionar centro de acopio</option>' +
            estado.centros.map(c => `
                <option value="${c.idCentro}">#${c.idCentro} - ${escapar(c.ubiCentro)} (${escapar(c.tipoCentro)})</option>
            `).join("");
    });

    const selVert = document.getElementById("select-envio-vertedero");
    if (selVert) {
        selVert.innerHTML = '<option value="">Seleccionar vertedero</option>' +
            estado.vertederos.map(v => `
                <option value="${v.idVertedero}">#${v.idVertedero} - ${escapar(v.ubicacionVertedero)}</option>
            `).join("");
    }
}

function actualizarSelectCuadrilleros() {
    const choferSelect = document.getElementById("select-cuadrilla-chofer");
    const peonSelect = document.getElementById("select-cuadrilla-peon");
    const vehiculoSelect = document.getElementById("select-cuadrilla-vehiculo");
    if (!choferSelect || !peonSelect) return;

    const asignados = new Set();
    estado.cuadrillas.forEach(c => {
        asignados.add(Number(c.idChofer));
        asignados.add(Number(c.idPeon));
    });

    const disponibles = estado.cuadrilleros.filter(u => !asignados.has(Number(u.idUsu)));

    const opciones = '<option value="">Seleccionar trabajador</option>' +
        disponibles.map(u => `
            <option value="${u.idUsu}">${escapar(u.priNom)} (${escapar(u.email)})</option>
        `).join("");

    choferSelect.innerHTML = opciones;
    peonSelect.innerHTML = opciones;

    if (vehiculoSelect) {
        const disponiblesVehi = estado.camiones.filter(v => v.estVehi === 'disponible');
        vehiculoSelect.innerHTML = '<option value="">(Sin vehículo asignado)</option>' +
            disponiblesVehi.map(v => `
                <option value="${v.idVehi}">${escapar(v.matriculaVehi)} - ${escapar(v.marcaVehi)} ${escapar(v.modeloVehi)} (${escapar(v.tipoVehi)})</option>
            `).join("");
    }
}

function actualizarSelectsAsignacion() {
    const cuadSelect = document.getElementById("select-asig-cuadrilla");
    const vehSelect = document.getElementById("select-asig-vehiculo");
    const rutSelect = document.getElementById("select-asig-ruta");
    if (!cuadSelect || !vehSelect || !rutSelect) return;

    cuadSelect.innerHTML = '<option value="">Seleccionar cuadrilla</option>' +
        estado.cuadrillas.map(c => `
            <option value="${c.idCuadrilla}">Cuadrilla #${c.idCuadrilla} (${escapar(c.nomChofer)} y ${escapar(c.nomPeon)})</option>
        `).join("");

    vehSelect.innerHTML = '<option value="">Seleccionar vehículo</option>' +
        estado.camiones.map(v => `
            <option value="${v.idVehi}">#${v.idVehi} - ${escapar(v.matriculaVehi)} (${escapar(v.marcaVehi)} ${escapar(v.modeloVehi)} - ${v.estVehi})</option>
        `).join("");

    rutSelect.innerHTML = '<option value="">(Opcional / Sin ruta fija)</option>' +
        estado.rutas.map(r => `
            <option value="${r.idRuta}">Ruta #${r.idRuta} - ${escapar(r.frecuencia)}</option>
        `).join("");

    const hoy = new Date().toISOString().split("T")[0];
    const fechaInput = document.getElementById("input-asig-fecha");
    if (fechaInput && !fechaInput.value) {
        fechaInput.value = hoy;
    }
}

function actualizarSelectsGarajeYTaller() {
    const selEstVehi = document.getElementById("select-estac-vehiculo");
    const selEstGar = document.getElementById("select-estac-garaje");
    if (selEstVehi && selEstGar) {
        selEstVehi.innerHTML = '<option value="">Seleccionar vehículo</option>' +
            estado.camiones.map(v => `<option value="${v.idVehi}">${escapar(v.matriculaVehi)} (${v.estVehi})</option>`).join("");
        selEstGar.innerHTML = '<option value="">Seleccionar garaje</option>' +
            estado.garajes.map(g => `<option value="${g.idGaraje}">#${g.idGaraje} - ${escapar(g.ubiGaraje)}</option>`).join("");
    }

    const selRepVehi = document.getElementById("select-rep-vehiculo");
    const selRepTal = document.getElementById("select-rep-taller");
    if (selRepVehi && selRepTal) {
        const rotos = (estado.camiones || []).filter(v => (v.estVehi || "").toLowerCase().trim() === "roto");
        selRepVehi.innerHTML = '<option value="">Seleccionar vehículo averiado</option>' +
            (rotos.length > 0
                ? rotos.map(v => `<option value="${v.idVehi}">#${v.idVehi} - ${escapar(v.matriculaVehi)} (${escapar(v.marcaVehi || '')} ${escapar(v.modeloVehi || '')} - roto)</option>`).join("")
                : '<option value="" disabled>No hay vehículos con estado roto actualmente</option>');
        selRepTal.innerHTML = '<option value="">Seleccionar taller</option>' +
            estado.talleres.map(t => `<option value="${t.idMant}">#${t.idMant} - ${escapar(t.ubiMant)}</option>`).join("");
    }
}

function actualizarSelectUsarVehiculo() {
    const sel = document.getElementById("select-usar-vehiculo");
    if (!sel) return;
    const disponibles = estado.camiones.filter(v => v.estVehi === "disponible" || v.estVehi === "en uso");
    sel.innerHTML = '<option value="">Seleccionar vehículo disponible</option>' +
        disponibles.map(v => `
            <option value="${v.idVehi}">#${v.idVehi} - ${escapar(v.matriculaVehi)} (${escapar(v.marcaVehi)} ${escapar(v.modeloVehi)} - ${v.estVehi})</option>
        `).join("");
}

function actualizarListaContenedoresRuta(seleccionados = []) {
    const contenedor = document.getElementById("lista-seleccion-contenedores");
    if (!contenedor) return;

    if (estado.contenedores.length === 0) {
        contenedor.innerHTML = '<span style="color: var(--gris); font-size: 0.9rem;">No hay contenedores registrados.</span>';
        return;
    }

    const seleccionSet = new Set(seleccionados);
    contenedor.innerHTML = estado.contenedores.map(c => `
        <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px; font-size: 0.9rem; cursor: pointer;">
            <input type="checkbox" name="contenedores" value="${c.idCon}" ${seleccionSet.has(Number(c.idCon)) ? "checked" : ""}>
            <span><b>#${c.idCon}</b> - ${escapar(c.calle)} y ${escapar(c.esquina)} (${escapar(c.zona)} - ${escapar(c.tipoCon)})</span>
        </label>
    `).join("");
}

// =========================================================
// ABRIR EDITORES
// =========================================================

async function abrirEditor(entidad, registro = null) {
    const formulario = document.getElementById(`form-${entidad}`);
    if (!formulario) return;
    formulario.reset();
    if (formulario.elements.id) {
        formulario.elements.id.value = registro ? obtenerId(entidad, registro) : "";
    }
    const titulo = document.getElementById(`titulo-form-${entidad}`);
    if (titulo) {
        titulo.textContent = registro ? `Editar ${entidad}` : `Nuevo ${entidad}`;
    }

    if (entidad === "usuario" && registro) {
        formulario.elements.nombre.value = registro.priNom;
        formulario.elements.telefono.value = registro.telUsu || "";
        formulario.elements.email.value = registro.email;
        formulario.elements.rol.value = registro.rol;
        formulario.elements.estado.value = registro.estUsu;
    }
    if (entidad === "contenedor") {
        inicializarMapaFormContenedor();
        if (registro) {
            formulario.elements.capacidad.value = registro.capacidad;
            formulario.elements.calle.value = registro.calle;
            formulario.elements.esquina.value = registro.esquina;
            formulario.elements.zona.value = registro.zona;
            formulario.elements.tipo.value = registro.tipoCon;
            formulario.elements.estado.value = registro.estCon;
            formulario.elements.repuesto.checked = Number(registro.repuesto) === 1;

            const lat = parseFloat(registro.latitud);
            const lng = parseFloat(registro.longitud);
            if (lat && lng && mapaFormContenedor) {
                mapaFormContenedor.setView([lat, lng], 15);
                if (marcadorFormContenedor) {
                    marcadorFormContenedor.setLatLng([lat, lng]);
                } else {
                    marcadorFormContenedor = L.marker([lat, lng]).addTo(mapaFormContenedor);
                }
                document.getElementById("input-con-lat").value = lat.toFixed(7);
                document.getElementById("input-con-lng").value = lng.toFixed(7);
            }
        } else {
            if (marcadorFormContenedor && mapaFormContenedor) {
                mapaFormContenedor.removeLayer(marcadorFormContenedor);
                marcadorFormContenedor = null;
            }
            document.getElementById("input-con-lat").value = "";
            document.getElementById("input-con-lng").value = "";
            if (mapaFormContenedor) mapaFormContenedor.setView([-34.9011, -56.1645], 13);
        }
        setTimeout(() => {
            if (mapaFormContenedor) mapaFormContenedor.invalidateSize();
        }, 300);
    }
    if (entidad === "camion" && registro) {
        formulario.elements.matricula.value = registro.matriculaVehi;
        formulario.elements.marca.value = registro.marcaVehi;
        formulario.elements.modelo.value = registro.modeloVehi;
        formulario.elements.capacidad.value = registro.capVehi;
        formulario.elements.tipo.value = registro.tipoVehi;
        formulario.elements.estado.value = registro.estVehi;
    }
    if (entidad === "ruta") {
        actualizarSelectCentros();
        if (registro) {
            formulario.elements.frecuencia.value = registro.frecuencia;
            formulario.elements.idCentro.value = registro.idCentro;
            const detalle = await pedir(API_GESTION, `/rutas/${registro.idRuta}`);
            const seleccionados = (detalle.data.contenedores || []).map(c => Number(c.idCon));
            actualizarListaContenedoresRuta(seleccionados);
        } else {
            actualizarListaContenedoresRuta([]);
        }
    }
    if (entidad === "cuadrilla") {
        actualizarSelectCuadrilleros();
    }
    if (entidad === "asignacion") {
        actualizarSelectsAsignacion();
    }
    if (entidad === "maquinaria") {
        actualizarSelectCentros();
        if (registro) {
            formulario.elements.idCentro.value = registro.idCentro;
            formulario.elements.proposito.value = registro.propositoMaq;
            formulario.elements.capacidad.value = registro.capMaq;
            formulario.elements.marca.value = registro.marcaMaq;
            formulario.elements.modelo.value = registro.modeloMaq;
            formulario.elements.numSerie.value = registro.numSerie;
        }
    }
    if (entidad === "centro" && registro) {
        formulario.elements.ubiCentro.value = registro.ubiCentro || "";
        formulario.elements.tipoCentro.value = registro.tipoCentro || "comun";
        formulario.elements.capCentro.value = registro.capCentro || "";
    }
    if (entidad === "vertedero" && registro) {
        formulario.elements.ubicacion.value = registro.ubicacionVertedero || "";
    }
    if (entidad === "garaje" && registro) {
        formulario.elements.ubiGaraje.value = registro.ubiGaraje || "";
    }
    if (entidad === "taller" && registro) {
        formulario.elements.ubiMant.value = registro.ubiMant || "";
    }
    if (entidad === "estacionamiento" || entidad === "reparacion" || entidad === "envio-vertedero") {
        actualizarSelectsGarajeYTaller();
        actualizarSelectCentros();
    }

    formulario.hidden = false;
    formulario.scrollIntoView({ behavior: "smooth", block: "start" });
}

function obtenerId(entidad, registro) {
    if (entidad === "usuario") return registro.idUsu;
    if (entidad === "contenedor") return registro.idCon;
    if (entidad === "camion") return registro.idVehi;
    if (entidad === "ruta") return registro.idRuta;
    if (entidad === "cuadrilla") return registro.idCuadrilla;
    if (entidad === "maquinaria") return registro.idMaq;
    if (entidad === "centro") return registro.idCentro;
    if (entidad === "vertedero") return registro.idVertedero;
    if (entidad === "garaje") return registro.idGaraje;
    if (entidad === "taller") return registro.idMant;
    if (entidad === "reparacion") return registro.idReparacion;
    return registro.id;
}

// =========================================================
// GUARDADO DE FORMULARIOS
// =========================================================

async function guardarUsuario(evento) {
    evento.preventDefault();
    const formulario = evento.currentTarget;
    const datos = Object.fromEntries(new FormData(formulario).entries());
    const id = datos.id;
    delete datos.id;

    if (!id && datos.password.length < 6) {
        mostrarMensaje(mensajePanel, "La contraseña es obligatoria al crear un usuario.", true);
        return;
    }

    await ejecutar(async () => {
        const resultado = await pedir(
            API_USUARIOS,
            id ? `/usuarios/${id}` : "/usuarios",
            id ? "PUT" : "POST",
            datos
        );
        formulario.hidden = true;
        formulario.reset();
        mostrarMensaje(mensajePanel, resultado.message);
        estado.usuarios = (await pedir(API_USUARIOS, "/usuarios")).data;
        dibujarUsuarios();
        actualizarIndicadores();
    });
}

async function guardarContenedor(evento) {
    evento.preventDefault();
    const formulario = evento.currentTarget;
    const datos = Object.fromEntries(new FormData(formulario).entries());
    const id = datos.id;
    delete datos.id;
    datos.capacidad = Number(datos.capacidad);
    datos.repuesto = Boolean(datos.repuesto);
    datos.latitud = datos.latitud ? parseFloat(datos.latitud) : null;
    datos.longitud = datos.longitud ? parseFloat(datos.longitud) : null;

    await ejecutar(async () => {
        const resultado = await pedir(
            API_CONTENEDORES,
            id ? `/contenedores/${id}` : "/contenedores",
            id ? "PUT" : "POST",
            datos
        );
        formulario.hidden = true;
        formulario.reset();
        mostrarMensaje(mensajePanel, resultado.message);
        await recargarGestion();
    });
}

async function guardarCamion(evento) {
    evento.preventDefault();
    const formulario = evento.currentTarget;
    const datos = Object.fromEntries(new FormData(formulario).entries());
    const id = datos.id;
    delete datos.id;
    datos.capacidad = Number(datos.capacidad);

    await ejecutar(async () => {
        const resultado = await pedir(
            API_LOGISTICA,
            id ? `/camiones/${id}` : "/camiones",
            id ? "PUT" : "POST",
            datos
        );
        formulario.hidden = true;
        formulario.reset();
        mostrarMensaje(mensajePanel, resultado.message);
        await recargarGestion();
    });
}

async function guardarRuta(evento) {
    evento.preventDefault();
    const formulario = evento.currentTarget;
    const formData = new FormData(formulario);
    const id = formData.get("id");

    const contenedores = formData.getAll("contenedores").map(Number);
    const datos = {
        frecuencia: formData.get("frecuencia"),
        idCentro: Number(formData.get("idCentro")),
        contenedores: contenedores
    };

    await ejecutar(async () => {
        const resultado = await pedir(
            API_LOGISTICA,
            id ? `/rutas/${id}` : "/rutas",
            id ? "PUT" : "POST",
            datos
        );
        formulario.hidden = true;
        formulario.reset();
        mostrarMensaje(mensajePanel, resultado.message);
        await recargarGestion();
    });
}

async function guardarCuadrilla(evento) {
    evento.preventDefault();
    const formulario = evento.currentTarget;
    const datos = Object.fromEntries(new FormData(formulario).entries());

    await ejecutar(async () => {
        const resultado = await pedir(API_LOGISTICA, "/cuadrillas", "POST", datos);
        formulario.hidden = true;
        formulario.reset();
        mostrarMensaje(mensajePanel, resultado.message);
        await recargarGestion();
    });
}

async function guardarAsignacion(evento) {
    evento.preventDefault();
    const formulario = evento.currentTarget;
    const datos = Object.fromEntries(new FormData(formulario).entries());

    await ejecutar(async () => {
        const resultado = await pedir(API_LOGISTICA, "/asignaciones", "POST", datos);
        formulario.hidden = true;
        formulario.reset();
        mostrarMensaje(mensajePanel, resultado.message);
        await recargarGestion();
    });
}

async function guardarMaquinaria(evento) {
    evento.preventDefault();
    const formulario = evento.currentTarget;
    const datos = Object.fromEntries(new FormData(formulario).entries());
    const id = datos.id;
    delete datos.id;
    datos.idCentro = Number(datos.idCentro);
    datos.capacidad = Number(datos.capacidad);

    await ejecutar(async () => {
        const resultado = await pedir(
            API_CENTROS,
            id ? `/maquinaria/${id}` : "/maquinaria",
            id ? "PUT" : "POST",
            datos
        );
        formulario.hidden = true;
        formulario.reset();
        mostrarMensaje(mensajePanel, resultado.message);
        await recargarGestion();
    });
}

async function guardarCentro(evento) {
    evento.preventDefault();
    const formulario = evento.currentTarget;
    const datos = Object.fromEntries(new FormData(formulario).entries());
    const id = datos.id;
    delete datos.id;
    datos.capCentro = Number(datos.capCentro);

    await ejecutar(async () => {
        const resultado = await pedir(
            API_CENTROS,
            id ? `/centros/${id}` : "/centros",
            id ? "PUT" : "POST",
            datos
        );
        formulario.hidden = true;
        formulario.reset();
        mostrarMensaje(mensajePanel, resultado.message);
        await recargarGestion();
    });
}

async function guardarVertedero(evento) {
    evento.preventDefault();
    const formulario = evento.currentTarget;
    const datos = Object.fromEntries(new FormData(formulario).entries());
    const id = datos.id;
    delete datos.id;

    await ejecutar(async () => {
        const resultado = await pedir(
            API_CENTROS,
            id ? `/vertederos/${id}` : "/vertederos",
            id ? "PUT" : "POST",
            datos
        );
        formulario.hidden = true;
        formulario.reset();
        mostrarMensaje(mensajePanel, resultado.message);
        await recargarGestion();
    });
}

// CU-25: Guardar Garaje
async function guardarGaraje(evento) {
    evento.preventDefault();
    const formulario = evento.currentTarget;
    const datos = Object.fromEntries(new FormData(formulario).entries());
    const id = datos.id;
    delete datos.id;

    await ejecutar(async () => {
        const resultado = await pedir(
            API_LOGISTICA,
            id ? `/garajes/${id}` : "/garajes",
            id ? "PUT" : "POST",
            datos
        );
        formulario.hidden = true;
        formulario.reset();
        mostrarMensaje(mensajePanel, resultado.message);
        await recargarGestion();
    });
}

// CU-26: Estacionar Vehículo
async function guardarEstacionamiento(evento) {
    evento.preventDefault();
    const formulario = evento.currentTarget;
    const datos = Object.fromEntries(new FormData(formulario).entries());

    await ejecutar(async () => {
        const resultado = await pedir(API_LOGISTICA, "/estacionamientos", "POST", datos);
        formulario.hidden = true;
        formulario.reset();
        mostrarMensaje(mensajePanel, resultado.message);
        await recargarGestion();
    });
}

// CU-27: Guardar Taller
async function guardarTaller(evento) {
    evento.preventDefault();
    const formulario = evento.currentTarget;
    const datos = Object.fromEntries(new FormData(formulario).entries());
    const id = datos.id;
    delete datos.id;

    await ejecutar(async () => {
        const resultado = await pedir(
            API_LOGISTICA,
            id ? `/talleres/${id}` : "/talleres",
            id ? "PUT" : "POST",
            datos
        );
        formulario.hidden = true;
        formulario.reset();
        mostrarMensaje(mensajePanel, resultado.message);
        await recargarGestion();
    });
}

// CU-28: Registrar Reparación
async function guardarReparacion(evento) {
    evento.preventDefault();
    const formulario = evento.currentTarget;
    const datos = Object.fromEntries(new FormData(formulario).entries());

    await ejecutar(async () => {
        const resultado = await pedir(API_LOGISTICA, "/reparaciones", "POST", datos);
        formulario.hidden = true;
        formulario.reset();
        mostrarMensaje(mensajePanel, resultado.message);
        await recargarGestion();
    });
}

// CU-23: Registrar Envío a Vertedero
async function guardarEnvioVertedero(evento) {
    evento.preventDefault();
    const formulario = evento.currentTarget;
    const datos = Object.fromEntries(new FormData(formulario).entries());

    await ejecutar(async () => {
        const resultado = await pedir(API_CENTROS, "/envios-vertedero", "POST", datos);
        formulario.hidden = true;
        formulario.reset();
        mostrarMensaje(mensajePanel, resultado.message);
        await recargarGestion();
    });
}

// CU-15 / Narrativa 7: Usar Vehículo por Cuadrilla
async function guardarUsarVehiculo(evento) {
    evento.preventDefault();
    const formulario = evento.currentTarget;
    const datos = Object.fromEntries(new FormData(formulario).entries());

    await ejecutar(async () => {
        const resultado = await pedir(API_LOGISTICA, "/usar-vehiculo", "POST", datos);
        formulario.hidden = true;
        mostrarMensaje(mensajePanel, resultado.message);
        const res = await pedir(API_LOGISTICA, "/mi-ruta");
        estado.miRuta = res.data;
        dibujarMiRuta();
        await recargarGestion();
    });
}

// =========================================================
// ACCIONES EN TABLAS (EDITAR / ELIMINAR)
// =========================================================

async function manejarAccion(evento) {
    const boton = evento.target.closest("[data-accion]");
    if (!boton) return;

    const entidad = boton.dataset.entidad;
    const id = Number(boton.dataset.id);

    if (boton.dataset.accion === "editar") {
        const plural = {
            usuario: "usuarios",
            contenedor: "contenedores",
            camion: "camiones",
            ruta: "rutas",
            maquinaria: "maquinaria",
            centro: "centros",
            vertedero: "vertederos",
            garaje: "garajes",
            taller: "talleres"
        }[entidad];
        const registro = estado[plural].find(item => Number(obtenerId(entidad, item)) === id);
        abrirEditor(entidad, registro);
        return;
    }

    if (!window.confirm("¿Seguro que querés eliminar este registro?")) return;

    await ejecutar(async () => {
        const base = entidad === "usuario" ? API_USUARIOS :
            (["centro", "maquinaria", "vertedero"].includes(entidad) ? API_CENTROS :
            (["contenedor", "incidencia"].includes(entidad) ? API_CONTENEDORES : API_LOGISTICA));
        const recurso = {
            usuario: "usuarios",
            contenedor: "contenedores",
            incidencia: "incidencias",
            camion: "camiones",
            ruta: "rutas",
            cuadrilla: "cuadrillas",
            maquinaria: "maquinaria",
            centro: "centros",
            vertedero: "vertederos",
            garaje: "garajes",
            taller: "talleres"
        }[entidad];

        const resultado = await pedir(base, `/${recurso}/${id}`, "DELETE");
        mostrarMensaje(mensajePanel, resultado.message);

        if (entidad === "usuario") {
            estado.usuarios = (await pedir(API_USUARIOS, "/usuarios")).data;
            dibujarUsuarios();
            actualizarIndicadores();
        } else if (entidad === "incidencia") {
            const incRes = await pedir(API_CONTENEDORES, "/incidencias");
            estado.incidencias = incRes.data || [];
            dibujarIncidencias();
            actualizarIndicadores();
            dibujarNotificaciones();
            dibujarGraficos();
        } else {
            await recargarGestion();
        }
    });
}

async function manejarAccionReparaciones(evento) {
    const btn = evento.target.closest("[data-finalizar-rep]");
    if (!btn) return;
    const idRep = Number(btn.dataset.finalizarRep);

    await ejecutar(async () => {
        const res = await pedir(API_LOGISTICA, `/reparaciones/${idRep}/finalizar`, "POST");
        mostrarMensaje(mensajePanel, res.message);
        await recargarGestion();
    });
}

async function recargarGestion() {
    if (!estado.usuarioActual) return;
    await cargarDatosSegunRol(estado.usuarioActual.rol);
}

// =========================================================
// UTILIDADES Y MAPAS
// =========================================================

function escapar(texto) {
    const div = document.createElement("div");
    div.textContent = texto ?? "";
    return div.innerHTML;
}

function mostrarMensaje(elemento, texto, esError = false) {
    if (!elemento) return;
    elemento.textContent = texto;
    elemento.className = `mensaje panel-mensaje ${esError ? "error" : "exito"}`;
    if (texto) {
        elemento.scrollIntoView({ behavior: "smooth", block: "nearest" });
    }
}

async function ejecutar(accion) {
    try {
        await accion();
    } catch (error) {
        mostrarMensaje(mensajePanel, error.message, true);
    }
}

function inicializarMapaGeneralContenedores() {
    const div = document.getElementById("mapa-general-contenedores");
    if (!div || mapaGeneralContenedores || typeof L === "undefined") return;

    mapaGeneralContenedores = L.map("mapa-general-contenedores").setView([-34.9011, -56.1645], 13);
    L.tileLayer("https://tile.openstreetmap.org/{z}/{x}/{y}.png", {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap'
    }).addTo(mapaGeneralContenedores);
}

function actualizarMarcadoresGenerales() {
    if (!mapaGeneralContenedores) return;

    marcadoresContenedores.forEach(m => mapaGeneralContenedores.removeLayer(m));
    marcadoresContenedores = [];

    estado.contenedores.forEach(c => {
        if (c.latitud && c.longitud) {
            const lat = Number(c.latitud);
            const lng = Number(c.longitud);
            const m = L.marker([lat, lng]).addTo(mapaGeneralContenedores);
            m.bindPopup(`
                <b>Contenedor #${c.idCon}</b><br>
                ${escapar(c.calle)} y ${escapar(c.esquina)}<br>
                <small>Zona: ${escapar(c.zona)} | Tipo: ${escapar(c.tipoCon)} | Estado: ${escapar(c.estCon)}</small>
            `);
            marcadoresContenedores.push(m);
        }
    });

    if (marcadoresContenedores.length > 0) {
        setTimeout(() => mapaGeneralContenedores.invalidateSize(), 200);
    }
}

function inicializarMapaFormContenedor() {
    const div = document.getElementById("mapa-form-contenedor");
    if (!div || mapaFormContenedor || typeof L === "undefined") return;

    mapaFormContenedor = L.map("mapa-form-contenedor").setView([-34.9011, -56.1645], 13);
    L.tileLayer("https://tile.openstreetmap.org/{z}/{x}/{y}.png", {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap'
    }).addTo(mapaFormContenedor);

    mapaFormContenedor.on("click", async function (e) {
        const lat = e.latlng.lat;
        const lng = e.latlng.lng;

        if (marcadorFormContenedor) {
            marcadorFormContenedor.setLatLng(e.latlng);
        } else {
            marcadorFormContenedor = L.marker(e.latlng).addTo(mapaFormContenedor);
        }

        document.getElementById("input-con-lat").value = lat.toFixed(7);
        document.getElementById("input-con-lng").value = lng.toFixed(7);

        await autocompletarUbicacion(lat, lng);
    });
}

async function autocompletarUbicacion(lat, lng) {
    try {
        const url = `https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=18&addressdetails=1`;
        const res = await fetch(url, { headers: { "Accept-Language": "es" } });
        const data = await res.json();

        if (data && data.address) {
            const addr = data.address;
            const calleInput = document.querySelector("#form-contenedor input[name='calle']");
            const esqInput = document.querySelector("#form-contenedor input[name='esquina']");

            const calle = addr.road || addr.pedestrian || addr.street || "";
            if (calle && calleInput && !calleInput.value) {
                calleInput.value = calle;
            }
            const esquina = addr.neighbourhood || addr.suburb || addr.quarter || "";
            if (esquina && esqInput && !esqInput.value) {
                esqInput.value = esquina;
            }
        }
    } catch (err) {
        // Silencioso
    }
}

iniciarPanel();
