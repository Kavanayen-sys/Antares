"use strict";

const estado = {
    usuarios: [],
    contenedores: [],
    camiones: [],
    incidencias: []
};
const mensajePanel = document.getElementById("mensaje-panel");

document.querySelectorAll("[data-seccion]").forEach(boton => {
    boton.addEventListener("click", () => {
        document.querySelectorAll("[data-seccion]").forEach(item => item.classList.remove("activo"));
        document.querySelectorAll(".seccion-panel").forEach(item => item.classList.remove("activa"));
        boton.classList.add("activo");
        document.getElementById(`seccion-${boton.dataset.seccion}`).classList.add("activa");
    });
});

document.querySelectorAll("[data-nuevo]").forEach(boton => {
    boton.addEventListener("click", () => abrirEditor(boton.dataset.nuevo));
});

document.querySelectorAll("[data-cancelar]").forEach(boton => {
    boton.addEventListener("click", () => {
        boton.closest("form").reset();
        boton.closest("form").hidden = true;
    });
});

document.getElementById("cerrar-sesion").addEventListener("click", async () => {
    try {
        await pedir(API_USUARIOS, "/logout", "POST");
    } finally {
        window.location.href = "login.html";
    }
});

document.getElementById("form-usuario").addEventListener("submit", guardarUsuario);
document.getElementById("form-contenedor").addEventListener("submit", guardarContenedor);
document.getElementById("form-camion").addEventListener("submit", guardarCamion);

document.getElementById("tabla-usuarios").addEventListener("click", manejarAccion);
document.getElementById("tabla-contenedores").addEventListener("click", manejarAccion);
document.getElementById("tabla-camiones").addEventListener("click", manejarAccion);
document.getElementById("tabla-incidencias").addEventListener("click", manejarAccion);

async function iniciarPanel() {
    try {
        const sesion = await pedir(API_USUARIOS, "/sesion");
        if (sesion.data.rol !== "administrador") {
            window.location.href = "index.html";
            return;
        }
        document.querySelector(".panel-cabecera span").textContent =
            `${sesion.data.priNom} (${sesion.data.rol})`;
        await cargarTodo();
    } catch (error) {
        if (error.estado === 401 || error.estado === 403) {
            window.location.href = "login.html";
            return;
        }
        mostrarMensaje(mensajePanel, error.message, true);
    }
}

async function cargarTodo() {
    const [usuarios, contenedores, camiones, incidencias] = await Promise.all([
        pedir(API_USUARIOS, "/usuarios"),
        pedir(API_GESTION, "/contenedores"),
        pedir(API_GESTION, "/camiones"),
        pedir(API_GESTION, "/incidencias")
    ]);

    estado.usuarios = usuarios.data;
    estado.contenedores = contenedores.data;
    estado.camiones = camiones.data;
    estado.incidencias = incidencias.data;

    dibujarUsuarios();
    dibujarContenedores();
    dibujarCamiones();
    dibujarIncidencias();
    actualizarIndicadores();
}

function dibujarUsuarios() {
    document.getElementById("tabla-usuarios").innerHTML = estado.usuarios.map(usuario => `
        <tr>
            <td>${usuario.idUsu}</td>
            <td>${escapar(usuario.priNom)}</td>
            <td>${escapar(usuario.email)}</td>
            <td>${escapar(usuario.rol)}</td>
            <td>${escapar(usuario.estUsu)}</td>
            <td class="acciones-tabla">
                <button class="accion-tabla" data-accion="editar" data-entidad="usuario"
                        data-id="${usuario.idUsu}">Editar</button>
                <button class="accion-tabla eliminar" data-accion="eliminar" data-entidad="usuario"
                        data-id="${usuario.idUsu}">Eliminar</button>
            </td>
        </tr>`).join("");
}

function dibujarContenedores() {
    document.getElementById("tabla-contenedores").innerHTML = estado.contenedores.map(contenedor => `
        <tr>
            <td>${contenedor.idCon}</td>
            <td>${escapar(contenedor.calle)} y ${escapar(contenedor.esquina)}</td>
            <td>${escapar(contenedor.zona)}</td>
            <td>${escapar(contenedor.tipoCon)}</td>
            <td>${Number(contenedor.capacidad).toLocaleString("es-UY")} l</td>
            <td>${escapar(contenedor.estCon)}</td>
            <td class="acciones-tabla">
                <button class="accion-tabla" data-accion="editar" data-entidad="contenedor"
                        data-id="${contenedor.idCon}">Editar</button>
                <button class="accion-tabla eliminar" data-accion="eliminar" data-entidad="contenedor"
                        data-id="${contenedor.idCon}">Eliminar</button>
            </td>
        </tr>`).join("");
}

function dibujarCamiones() {
    document.getElementById("tabla-camiones").innerHTML = estado.camiones.map(camion => `
        <tr>
            <td>${camion.idCamion}</td>
            <td>${escapar(camion.matricula)}</td>
            <td>${escapar(camion.marca)} ${escapar(camion.modelo)}</td>
            <td>${escapar(camion.tipoCamion)}</td>
            <td>${Number(camion.capacidad).toLocaleString("es-UY")} kg</td>
            <td>${escapar(camion.estCamion)}</td>
            <td class="acciones-tabla">
                <button class="accion-tabla" data-accion="editar" data-entidad="camion"
                        data-id="${camion.idCamion}">Editar</button>
                <button class="accion-tabla eliminar" data-accion="eliminar" data-entidad="camion"
                        data-id="${camion.idCamion}">Eliminar</button>
            </td>
        </tr>`).join("");
}

function dibujarIncidencias() {
    document.getElementById("tabla-incidencias").innerHTML = estado.incidencias.map(incidencia => `
        <tr>
            <td>${incidencia.idIncidencia}</td>
            <td>#${incidencia.idCon} - ${escapar(incidencia.ubicacion)}</td>
            <td title="${escapar(incidencia.descInci)}">${escapar(incidencia.tipoInci)}</td>
            <td>${escapar(incidencia.nomReporte)}</td>
            <td>${escapar(incidencia.telReporte)}</td>
            <td>${escapar(incidencia.prioridad)}</td>
            <td>
                <button class="accion-tabla eliminar" data-accion="eliminar"
                        data-entidad="incidencia" data-id="${incidencia.idIncidencia}">Eliminar</button>
            </td>
        </tr>`).join("");
}

function actualizarIndicadores() {
    document.getElementById("cantidad-usuarios").textContent = estado.usuarios.length;
    document.getElementById("cantidad-contenedores").textContent = estado.contenedores.length;
    document.getElementById("cantidad-camiones").textContent = estado.camiones.length;
    document.getElementById("cantidad-incidencias").textContent = estado.incidencias.length;
}

function abrirEditor(entidad, registro = null) {
    const formulario = document.getElementById(`form-${entidad}`);
    formulario.reset();
    formulario.elements.id.value = registro ? obtenerId(entidad, registro) : "";
    document.getElementById(`titulo-form-${entidad}`).textContent =
        registro ? `Editar ${entidad}` : `Nuevo ${entidad}`;

    if (registro && entidad === "usuario") {
        formulario.elements.nombre.value = registro.priNom;
        formulario.elements.telefono.value = registro.telUsu;
        formulario.elements.email.value = registro.email;
        formulario.elements.rol.value = registro.rol;
        formulario.elements.estado.value = registro.estUsu;
    }
    if (registro && entidad === "contenedor") {
        formulario.elements.capacidad.value = registro.capacidad;
        formulario.elements.calle.value = registro.calle;
        formulario.elements.esquina.value = registro.esquina;
        formulario.elements.zona.value = registro.zona;
        formulario.elements.tipo.value = registro.tipoCon;
        formulario.elements.estado.value = registro.estCon;
        formulario.elements.repuesto.checked = Number(registro.repuesto) === 1;
    }
    if (registro && entidad === "camion") {
        formulario.elements.matricula.value = registro.matricula;
        formulario.elements.marca.value = registro.marca;
        formulario.elements.modelo.value = registro.modelo;
        formulario.elements.capacidad.value = registro.capacidad;
        formulario.elements.tipo.value = registro.tipoCamion;
        formulario.elements.estado.value = registro.estCamion;
        formulario.elements.repuesto.checked = Number(registro.repuesto) === 1;
    }

    formulario.hidden = false;
    formulario.scrollIntoView({ behavior: "smooth", block: "start" });
}

function obtenerId(entidad, registro) {
    if (entidad === "usuario") return registro.idUsu;
    if (entidad === "contenedor") return registro.idCon;
    return registro.idCamion;
}

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
        const lista = await pedir(API_USUARIOS, "/usuarios");
        estado.usuarios = lista.data;
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
    datos.repuesto = formulario.elements.repuesto.checked;

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
    datos.repuesto = formulario.elements.repuesto.checked;

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

async function manejarAccion(evento) {
    const boton = evento.target.closest("[data-accion]");
    if (!boton) return;

    const entidad = boton.dataset.entidad;
    const id = Number(boton.dataset.id);

    if (boton.dataset.accion === "editar") {
        const plural = {
            usuario: "usuarios",
            contenedor: "contenedores",
            camion: "camiones"
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
            incidencia: "incidencias"
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
    const [contenedores, camiones, incidencias] = await Promise.all([
        pedir(API_GESTION, "/contenedores"),
        pedir(API_GESTION, "/camiones"),
        pedir(API_GESTION, "/incidencias")
    ]);
    estado.contenedores = contenedores.data;
    estado.camiones = camiones.data;
    estado.incidencias = incidencias.data;
    dibujarContenedores();
    dibujarCamiones();
    dibujarIncidencias();
    actualizarIndicadores();
}

async function ejecutar(accion) {
    try {
        await accion();
    } catch (error) {
        mostrarMensaje(mensajePanel, error.message, true);
    }
}

iniciarPanel();