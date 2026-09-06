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
    usuarioActual: null
};

let mapaGeneralContenedores = null;
let mapaFormContenedor = null;
let marcadorFormContenedor = null;
let marcadoresContenedores = [];

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
document.getElementById("form-usuario").addEventListener("submit", guardarUsuario);
document.getElementById("form-contenedor").addEventListener("submit", guardarContenedor);
document.getElementById("form-camion").addEventListener("submit", guardarCamion);
document.getElementById("form-ruta").addEventListener("submit", guardarRuta);
document.getElementById("form-cuadrilla").addEventListener("submit", guardarCuadrilla);
document.getElementById("form-asignacion").addEventListener("submit", guardarAsignacion);
document.getElementById("form-maquinaria").addEventListener("submit", guardarMaquinaria);
document.getElementById("form-centro").addEventListener("submit", guardarCentro);
document.getElementById("form-vertedero").addEventListener("submit", guardarVertedero);

// Delegación de acciones de clic en tablas
document.getElementById("tabla-usuarios").addEventListener("click", manejarAccion);
document.getElementById("tabla-contenedores").addEventListener("click", manejarAccion);
document.getElementById("tabla-camiones").addEventListener("click", manejarAccion);
document.getElementById("tabla-rutas").addEventListener("click", manejarAccion);
document.getElementById("tabla-cuadrillas").addEventListener("click", manejarAccion);
document.getElementById("tabla-maquinaria").addEventListener("click", manejarAccion);
document.getElementById("tabla-centros")?.addEventListener("click", manejarAccion);

// Cambios de estado en incidencias (general y mi-ruta)
document.addEventListener("change", async (evento) => {
    const selector = evento.target.closest(".selector-estado-incidencia");
    if (!selector) return;

    const id = Number(selector.dataset.id);
    const nuevoEstado = selector.value;

    await ejecutar(async () => {
        const resultado = await pedir(API_GESTION, `/incidencias/${id}`, "PUT", { estado: nuevoEstado });
        mostrarMensaje(mensajePanel, resultado.message);

        // Actualizar estado local
        const item = estado.incidencias.find(i => Number(i.idInci) === id);
        if (item) item.estado = nuevoEstado;

        if (estado.miRuta && Array.isArray(estado.miRuta.incidencias)) {
            const itemRuta = estado.miRuta.incidencias.find(i => Number(i.idInci) === id);
            if (itemRuta) itemRuta.estado = nuevoEstado;
        }
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
        // Cargar centros y vertederos para selects y vistas
        const [centrosRes, vertederosRes] = await Promise.all([
            pedir(API_GESTION, "/centros"),
            pedir(API_GESTION, "/vertederos")
        ]);
        estado.centros = centrosRes.data;
        estado.vertederos = vertederosRes.data;
        dibujarCentrosVertederos();
        actualizarSelectCentros();

        if (rol === "cuadrilla") {
            const [miRutaRes, rutRes, asigRes, incRes] = await Promise.all([
                pedir(API_GESTION, "/mi-ruta"),
                pedir(API_GESTION, "/rutas"),
                pedir(API_GESTION, "/asignaciones"),
                pedir(API_GESTION, "/incidencias")
            ]);
            estado.miRuta = miRutaRes.data;
            estado.rutas = rutRes.data;
            estado.asignaciones = asigRes.data;
            estado.incidencias = incRes.data;

            dibujarMiRuta();
            dibujarRutas();
            dibujarAsignaciones();
            dibujarIncidencias();
        }

        if (rol === "operario" || rol === "administrador" || rol === "municipal") {
            const maqRes = await pedir(API_GESTION, "/maquinaria");
            estado.maquinaria = maqRes.data;
            dibujarMaquinaria();
        }

        if (rol === "administrador" || rol === "municipal") {
            const [contRes, vehRes, incRes, rutRes, cuadRes, cuadrillerosRes, asigRes] = await Promise.all([
                pedir(API_GESTION, "/contenedores"),
                pedir(API_GESTION, "/camiones"),
                pedir(API_GESTION, "/incidencias"),
                pedir(API_GESTION, "/rutas"),
                pedir(API_GESTION, "/cuadrillas"),
                pedir(API_GESTION, "/cuadrilleros"),
                pedir(API_GESTION, "/asignaciones")
            ]);

            estado.contenedores = contRes.data;
            estado.camiones = vehRes.data;
            estado.incidencias = incRes.data;
            estado.rutas = rutRes.data;
            estado.cuadrillas = cuadRes.data;
            estado.cuadrilleros = cuadrillerosRes.data;
            estado.asignaciones = asigRes.data;

            dibujarContenedores();
            dibujarCamiones();
            dibujarIncidencias();
            dibujarRutas();
            dibujarCuadrillas();
            dibujarAsignaciones();
            actualizarSelectsAsignacion();
            actualizarSelectCuadrilleros();
            actualizarListaContenedoresRuta();
        }

        if (rol === "administrador") {
            const usuRes = await pedir(API_USUARIOS, "/usuarios");
            estado.usuarios = usuRes.data;
            dibujarUsuarios();
        }

        actualizarIndicadores();
    });
}

// =========================================================
// RENDERIZADO DE VISTAS
// =========================================================

function dibujarUsuarios() {
    const tbody = document.getElementById("tabla-usuarios");
    if (!tbody) return;
    tbody.innerHTML = estado.usuarios.map(usuario => `
        <tr>
            <td>${usuario.idUsu}</td>
            <td>${escapar(usuario.priNom)}</td>
            <td>${escapar(usuario.email)}</td>
            <td><span class="badge badge-${usuario.rol}">${escapar(usuario.rol)}</span></td>
            <td>${escapar(usuario.estUsu)}</td>
            <td class="acciones-tabla">
                <button class="accion-tabla" data-accion="editar" data-entidad="usuario" data-id="${usuario.idUsu}">Editar</button>
                <button class="accion-tabla eliminar" data-accion="eliminar" data-entidad="usuario" data-id="${usuario.idUsu}">Eliminar</button>
            </td>
        </tr>`).join("");
}

function dibujarContenedores() {
    const tbody = document.getElementById("tabla-contenedores");
    if (!tbody) return;
    tbody.innerHTML = estado.contenedores.map(contenedor => `
        <tr>
            <td>${contenedor.idCon}</td>
            <td>${escapar(contenedor.calle)} y ${escapar(contenedor.esquina)}</td>
            <td>${escapar(contenedor.zona)}</td>
            <td>${escapar(contenedor.tipoCon)}</td>
            <td>${Number(contenedor.capacidad).toLocaleString("es-UY")} L</td>
            <td>${escapar(contenedor.estCon)}</td>
            <td class="acciones-tabla">
                <button class="accion-tabla" data-accion="editar" data-entidad="contenedor" data-id="${contenedor.idCon}">Editar</button>
                <button class="accion-tabla eliminar" data-accion="eliminar" data-entidad="contenedor" data-id="${contenedor.idCon}">Eliminar</button>
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
            <td>${camion.idVehi}</td>
            <td>${escapar(camion.matriculaVehi)}</td>
            <td>${escapar(camion.marcaVehi)} ${escapar(camion.modeloVehi)}</td>
            <td>${escapar(camion.tipoVehi)}</td>
            <td>${Number(camion.capVehi).toLocaleString("es-UY")} kg</td>
            <td>${escapar(camion.estVehi)}</td>
            <td class="acciones-tabla">
                <button class="accion-tabla" data-accion="editar" data-entidad="camion" data-id="${camion.idVehi}">Editar</button>
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
        return `
        <tr>
            <td>${incidencia.idInci}</td>
            <td>${escapar(incidencia.fchaInci || "-")}</td>
            <td>#${incidencia.idCon} - ${escapar(incidencia.ubicacion || "")}</td>
            <td title="${escapar(incidencia.descInci || "")}">${escapar(incidencia.tipoInci || "")}</td>
            <td>${escapar(incidencia.prioridad || "")}</td>
            <td>
                <select class="selector-estado-incidencia" data-id="${incidencia.idInci}">
                    <option value="resuelto"${estActual === "resuelto" ? " selected" : ""}>Resuelto</option>
                    <option value="en curso"${estActual === "en curso" ? " selected" : ""}>En curso</option>
                    <option value="descartado"${estActual === "descartado" ? " selected" : ""}>Descartado</option>
                </select>
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
            <td>${r.idRuta}</td>
            <td><b>${escapar(r.frecuencia)}</b></td>
            <td>#${r.idCentro} (${escapar(r.tipoCentro || "Centro")})</td>
            <td>${r.cantidadContenedores || 0} contenedores</td>
            <td class="acciones-tabla">
                ${esGestion ? `
                <button class="accion-tabla" data-accion="editar" data-entidad="ruta" data-id="${r.idRuta}">Editar</button>
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
            <td>${c.idCuadrilla}</td>
            <td>${escapar(c.nomChofer)}</td>
            <td>${escapar(c.telChofer || "-")}</td>
            <td>${escapar(c.nomPeon)}</td>
            <td>${escapar(c.telPeon || "-")}</td>
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
            <td>${a.idRuta ? `#${a.idRuta} - ${escapar(a.frecuencia)}` : '<span style="color: var(--gris);">Sin ruta fija</span>'}</td>
        </tr>`).join("");
}

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
}

function dibujarMaquinaria() {
    const tbody = document.getElementById("tabla-maquinaria");
    if (!tbody) return;
    const esGestion = ["administrador", "municipal", "operario"].includes(estado.usuarioActual?.rol);
    tbody.innerHTML = estado.maquinaria.map(m => `
        <tr>
            <td>${m.idMaq}</td>
            <td><b>${escapar(m.marcaMaq)} ${escapar(m.modeloMaq)}</b></td>
            <td><code>${escapar(m.numSerie)}</code></td>
            <td>${escapar(m.propositoMaq)}</td>
            <td>${Number(m.capMaq).toLocaleString("es-UY")} kg/h</td>
            <td>#${m.idCentro} (${escapar(m.tipoCentro || "Centro")})</td>
            <td class="acciones-tabla">
                ${esGestion ? `
                <button class="accion-tabla editar" data-accion="editar" data-entidad="maquinaria" data-id="${m.idMaq}">Editar</button>
                <button class="accion-tabla eliminar" data-accion="eliminar" data-entidad="maquinaria" data-id="${m.idMaq}">Eliminar</button>
                ` : '<span style="color: var(--gris); font-size: 0.85rem;">-</span>'}
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
        tbodyVert.innerHTML = estado.vertederos.map(v => `
            <tr>
                <td>#${v.idVertedero}</td>
                <td>${escapar(v.ubicacionVertedero)}</td>
            </tr>`).join("");
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

// =========================================================
// HELPERS PARA SELECTS Y FORMULARIOS
// =========================================================

function actualizarSelectCentros() {
    const selects = ["select-ruta-centro", "select-maq-centro"];
    selects.forEach(id => {
        const el = document.getElementById(id);
        if (!el) return;
        el.innerHTML = '<option value="">Seleccionar centro</option>' +
            estado.centros.map(c => `<option value="${c.idCentro}">#${c.idCentro} - ${escapar(c.ubiCentro || '')} - ${escapar(c.tipoCentro)} (${c.capCentro} kg)</option>`).join("");
    });
}

function actualizarSelectCuadrilleros() {
    const selects = ["select-cuadrilla-chofer", "select-cuadrilla-peon"];
    const asignados = new Set();
    (estado.cuadrillas || []).forEach(c => {
        if (c.idChofer) asignados.add(Number(c.idChofer));
        if (c.idPeon) asignados.add(Number(c.idPeon));
    });

    selects.forEach(id => {
        const el = document.getElementById(id);
        if (!el) return;
        const disponibles = (estado.cuadrilleros || []).filter(u => !asignados.has(Number(u.idUsu)));
        el.innerHTML = '<option value="">Seleccionar integrante</option>' +
            disponibles.map(u => `<option value="${u.idUsu}">${escapar(u.priNom)} (${escapar(u.email)})</option>`).join("");
    });
}

function actualizarSelectsAsignacion() {
    const selCuad = document.getElementById("select-asig-cuadrilla");
    const selVehi = document.getElementById("select-asig-vehiculo");
    const selRuta = document.getElementById("select-asig-ruta");
    const inputFecha = document.getElementById("input-asig-fecha");

    if (inputFecha && !inputFecha.value) {
        inputFecha.value = new Date().toISOString().split("T")[0];
    }

    if (selCuad) {
        selCuad.innerHTML = '<option value="">Seleccionar cuadrilla</option>' +
            estado.cuadrillas.map(c => `<option value="${c.idCuadrilla}">Cuadrilla #${c.idCuadrilla} (${escapar(c.nomChofer)} y ${escapar(c.nomPeon)})</option>`).join("");
    }

    if (selVehi) {
        selVehi.innerHTML = '<option value="">Seleccionar vehículo</option>' +
            estado.camiones.map(v => `<option value="${v.idVehi}">${escapar(v.matriculaVehi)} - ${escapar(v.marcaVehi)} ${escapar(v.modeloVehi)} (${escapar(v.estVehi)})</option>`).join("");
    }

    if (selRuta) {
        selRuta.innerHTML = '<option value="">(Sin ruta fija / Libre)</option>' +
            estado.rutas.map(r => `<option value="${r.idRuta}">Ruta #${r.idRuta} - ${escapar(r.frecuencia)}</option>`).join("");
    }
}

function actualizarListaContenedoresRuta(seleccionados = []) {
    const contenedorLista = document.getElementById("lista-seleccion-contenedores");
    if (!contenedorLista) return;
    contenedorLista.innerHTML = estado.contenedores.map(c => {
        const isChecked = seleccionados.includes(Number(c.idCon)) ? " checked" : "";
        return `
        <label style="display: block; font-weight: normal; margin: 4px 0; cursor: pointer;">
            <input type="checkbox" name="contenedores" value="${c.idCon}"${isChecked}>
            #${c.idCon} - ${escapar(c.calle)} y ${escapar(c.esquina)} (${escapar(c.zona)}, ${escapar(c.tipoCon)})
        </label>`;
    }).join("");
}

// =========================================================
// APERTURA DE EDITORES
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
            // Cargar composición de la ruta
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
            API_GESTION,
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
            API_GESTION,
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
            API_GESTION,
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
        const resultado = await pedir(API_GESTION, "/cuadrillas", "POST", datos);
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
        const resultado = await pedir(API_GESTION, "/asignaciones", "POST", datos);
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
            API_GESTION,
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
            API_GESTION,
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

    await ejecutar(async () => {
        const resultado = await pedir(API_GESTION, "/vertederos", "POST", datos);
        formulario.hidden = true;
        formulario.reset();
        mostrarMensaje(mensajePanel, resultado.message);
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
            centro: "centros"
        }[entidad];
        const registro = estado[plural].find(item => Number(obtenerId(entidad, item)) === id);
        abrirEditor(entidad, registro);
        return;
    }

    if (!window.confirm("¿Seguro que querés eliminar este registro?")) return;

    await ejecutar(async () => {
        const base = entidad === "usuario" ? API_USUARIOS : API_GESTION;
        const recurso = {
            usuario: "usuarios",
            contenedor: "contenedores",
            camion: "camiones",
            ruta: "rutas",
            cuadrilla: "cuadrillas",
            maquinaria: "maquinaria",
            centro: "centros"
        }[entidad];

        const resultado = await pedir(base, `/${recurso}/${id}`, "DELETE");
        mostrarMensaje(mensajePanel, resultado.message);

        if (entidad === "usuario") {
            estado.usuarios = (await pedir(API_USUARIOS, "/usuarios")).data;
            dibujarUsuarios();
            actualizarIndicadores();
        } else {
            await recargarGestion();
        }
    });
}

async function recargarGestion() {
    if (!estado.usuarioActual) return;
    await cargarDatosSegunRol(estado.usuarioActual.rol);
}

// =========================================================
// UTILIDADES
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

    mapaGeneralContenedores = L.map(div).setView([-34.9011, -56.1645], 12);
    L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
        attribution: "&copy; OpenStreetMap",
        maxZoom: 19
    }).addTo(mapaGeneralContenedores);
}

function actualizarMarcadoresGenerales() {
    if (!mapaGeneralContenedores || typeof L === "undefined") return;

    marcadoresContenedores.forEach(m => mapaGeneralContenedores.removeLayer(m));
    marcadoresContenedores = [];

    estado.contenedores.forEach(con => {
        const lat = parseFloat(con.latitud);
        const lng = parseFloat(con.longitud);
        if (!lat || !lng) return;

        const marcador = L.marker([lat, lng]).addTo(mapaGeneralContenedores);
        marcador.bindPopup(
            "<b>#" + con.idCon + "</b><br>" + escapar(con.calle) + " y " + escapar(con.esquina) + "<br>" +
            "Zona: " + escapar(con.zona) + "<br>Tipo: " + escapar(con.tipoCon) + "<br>" +
            "Capacidad: " + Number(con.capacidad) + " L<br>" +
            "Estado: " + escapar(con.estCon)
        );
        marcadoresContenedores.push(marcador);
    });

    if (marcadoresContenedores.length > 0) {
        const grupo = L.featureGroup(marcadoresContenedores);
        mapaGeneralContenedores.fitBounds(grupo.getBounds());
    }
}

function inicializarMapaFormContenedor() {
    const div = document.getElementById("mapa-form-contenedor");
    if (!div || mapaFormContenedor || typeof L === "undefined") return;

    mapaFormContenedor = L.map(div).setView([-34.9011, -56.1645], 13);
    L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
        attribution: "&copy; OpenStreetMap",
        maxZoom: 19
    }).addTo(mapaFormContenedor);

    mapaFormContenedor.on("click", async function (e) {
        const lat = e.latlng.lat;
        const lng = e.latlng.lng;

        if (marcadorFormContenedor) {
            marcadorFormContenedor.setLatLng([lat, lng]);
        } else {
            marcadorFormContenedor = L.marker([lat, lng]).addTo(mapaFormContenedor);
        }

        const inputLat = document.getElementById("input-con-lat");
        const inputLng = document.getElementById("input-con-lng");
        if (inputLat) inputLat.value = lat.toFixed(7);
        if (inputLng) inputLng.value = lng.toFixed(7);

        await autocompletarUbicacion(lat, lng);
    });
}

async function autocompletarUbicacion(lat, lng) {
    const form = document.getElementById("form-contenedor");
    if (!form) return;

    try {
        const url = "https://nominatim.openstreetmap.org/reverse?format=json&lat=" + lat + "&lon=" + lng + "&zoom=18&addressdetails=1";
        const res = await fetch(url);
        const data = await res.json();
        const addr = data.address || {};

        const calle = addr.road || addr.pedestrian || "";
        if (calle && form.elements.calle) {
            form.elements.calle.value = calle;
        }

        const urlEsq = "https://nominatim.openstreetmap.org/reverse?format=json&lat=" + (lat + 0.0005) + "&lon=" + lng + "&zoom=18&addressdetails=1";
        const resEsq = await fetch(urlEsq);
        const dataEsq = await resEsq.json();
        const esquina = dataEsq.address?.road || "";
        if (esquina && esquina.toLowerCase() !== calle.toLowerCase() && form.elements.esquina) {
            form.elements.esquina.value = esquina;
        }

        const barrio = (addr.suburb || addr.neighbourhood || "").toLowerCase();
        let zona = "Municipio B";

        if (barrio.includes("pocitos") || barrio.includes("buceo") || barrio.includes("punta carretas") || barrio.includes("batlle")) {
            zona = "Municipio CH";
        } else if (barrio.includes("cerro") || barrio.includes("teja") || barrio.includes("arena") || barrio.includes("casabo")) {
            zona = "Municipio A";
        } else if (barrio.includes("prado") || barrio.includes("reducto") || barrio.includes("capurro") || barrio.includes("figurita")) {
            zona = "Municipio C";
        } else if (barrio.includes("carrasco") || barrio.includes("malvin") || barrio.includes("union") || barrio.includes("canteras")) {
            zona = "Municipio E";
        } else if (barrio.includes("colon") || barrio.includes("sayago") || barrio.includes("peñarol") || barrio.includes("lezica")) {
            zona = "Municipio G";
        } else if (barrio.includes("cerrito") || barrio.includes("casavalle") || barrio.includes("marconi") || barrio.includes("acacias")) {
            zona = "Municipio D";
        } else if (barrio.includes("maroñas") || barrio.includes("manga") || barrio.includes("garcia") || barrio.includes("blancas")) {
            zona = "Municipio F";
        } else if (lng < -56.22) {
            zona = "Municipio A";
        } else if (lat < -34.89 && lng > -56.16) {
            zona = "Municipio CH";
        } else if (lng > -56.12) {
            zona = "Municipio E";
        } else if (lat > -34.85) {
            zona = "Municipio G";
        }

        if (form.elements.zona) {
            form.elements.zona.value = zona;
        }

        if (marcadorFormContenedor && calle) {
            marcadorFormContenedor.bindPopup("<b>" + escapar(calle) + "</b><br>" + (esquina ? escapar(esquina) + "<br>" : "") + escapar(zona)).openPopup();
        }
    } catch (e) {
        console.log(e);
    }
}

iniciarPanel();