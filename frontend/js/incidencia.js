"use strict";

const formularioIncidencia = document.getElementById("form-incidencia");
const listaContenedores = document.getElementById("id-contenedor");
const mensajeIncidencia = document.getElementById("mensaje-incidencia");

async function cargarContenedores() {
    try {
        const resultado = await pedir(API_GESTION, "/contenedores-publicos");
        listaContenedores.innerHTML = '<option value="">Seleccionar contenedor</option>';
        resultado.data
            .forEach(contenedor => {
                const opcion = document.createElement("option");
                opcion.value = contenedor.idCon;
                opcion.textContent =
                    `#${contenedor.idCon} - ${contenedor.calle} y ${contenedor.esquina} ` +
                    `(${contenedor.zona}, ${contenedor.estCon})`;
                listaContenedores.appendChild(opcion);
            });
    } catch (error) {
        listaContenedores.innerHTML = '<option value="">No se pudieron cargar los contenedores</option>';
        mostrarMensaje(mensajeIncidencia, error.message, true);
    }
}

formularioIncidencia.addEventListener("submit", async evento => {
    evento.preventDefault();
    mostrarMensaje(mensajeIncidencia, "");

    const datos = Object.fromEntries(new FormData(formularioIncidencia).entries());
    datos.idCon = Number(datos.idCon);

    try {
        const resultado = await pedir(API_GESTION, "/incidencias", "POST", datos);
        mostrarMensaje(
            mensajeIncidencia,
            `${resultado.message} Número de reporte: #${resultado.data.idIncidencia}.`
        );
        formularioIncidencia.reset();
    } catch (error) {
        mostrarMensaje(mensajeIncidencia, error.message, true);
    }
});

cargarContenedores();