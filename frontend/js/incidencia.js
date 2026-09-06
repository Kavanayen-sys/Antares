"use strict";

const formularioIncidencia = document.getElementById("form-incidencia");
const listaContenedores = document.getElementById("id-contenedor");
const mensajeIncidencia = document.getElementById("mensaje-incidencia");

let mapaIncidencia = null;
const marcadores = {};

function inicializarMapa() {
    if (mapaIncidencia || !document.getElementById("mapa-incidencia") || typeof L === "undefined") return;
    mapaIncidencia = L.map("mapa-incidencia").setView([-34.9011, -56.1645], 13);
    L.tileLayer("https://tile.openstreetmap.org/{z}/{x}/{y}.png", {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
    }).addTo(mapaIncidencia);
}

async function cargarContenedores() {
    inicializarMapa();
    try {
        const resultado = await pedir(API_GESTION, "/contenedores-publicos");
        listaContenedores.innerHTML = '<option value="">Seleccionar contenedor</option>';

        resultado.data.forEach(contenedor => {
            const opcion = document.createElement("option");
            opcion.value = contenedor.idCon;
            opcion.textContent =
                `#${contenedor.idCon} - ${contenedor.calle} y ${contenedor.esquina} ` +
                `(${contenedor.zona}, ${contenedor.estCon})`;
            listaContenedores.appendChild(opcion);

            if (mapaIncidencia && contenedor.latitud && contenedor.longitud) {
                const lat = Number(contenedor.latitud);
                const lng = Number(contenedor.longitud);
                const marcador = L.marker([lat, lng]).addTo(mapaIncidencia);
                marcador.bindPopup(`
                    <b>Contenedor #${contenedor.idCon}</b><br>
                    ${escapar(contenedor.calle)} y ${escapar(contenedor.esquina)}<br>
                    <small>Tipo: ${escapar(contenedor.tipoCon)} | Zona: ${escapar(contenedor.zona)}</small><br>
                    <button type="button" style="margin-top:6px; padding:4px 8px; font-size:0.85rem; cursor:pointer;" onclick="seleccionarContenedorDesdeMapa(${contenedor.idCon})">Seleccionar este</button>
                `);
                marcador.on("click", () => {
                    listaContenedores.value = String(contenedor.idCon);
                });
                marcadores[contenedor.idCon] = marcador;
            }
        });

        if (mapaIncidencia) {
            setTimeout(() => mapaIncidencia.invalidateSize(), 200);
        }
    } catch (error) {
        listaContenedores.innerHTML = '<option value="">No se pudieron cargar los contenedores</option>';
        mostrarMensaje(mensajeIncidencia, error.message, true);
    }
}

window.seleccionarContenedorDesdeMapa = function(idCon) {
    listaContenedores.value = String(idCon);
    if (marcadores[idCon]) {
        marcadores[idCon].closePopup();
    }
};

listaContenedores.addEventListener("change", () => {
    const id = listaContenedores.value;
    if (marcadores[id] && mapaIncidencia) {
        mapaIncidencia.setView(marcadores[id].getLatLng(), 16);
        marcadores[id].openPopup();
    }
});

formularioIncidencia.addEventListener("submit", async evento => {
    evento.preventDefault();
    mostrarMensaje(mensajeIncidencia, "");

    const datos = Object.fromEntries(new FormData(formularioIncidencia).entries());
    datos.idCon = Number(datos.idCon);

    try {
        const resultado = await pedir(API_GESTION, "/incidencias", "POST", datos);
        mostrarMensaje(
            mensajeIncidencia,
            `${resultado.message} Número de reporte: #${resultado.data.idInci}.`
        );
        formularioIncidencia.reset();
    } catch (error) {
        mostrarMensaje(mensajeIncidencia, error.message, true);
    }
});

cargarContenedores();