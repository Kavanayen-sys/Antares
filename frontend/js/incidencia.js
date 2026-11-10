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

// CU-12: Búsqueda por calle y esquina priorizando el primer resultado
const btnBuscar = document.getElementById("btn-buscar-contenedor");
const inputCalle = document.getElementById("input-buscar-calle");
const inputEsquina = document.getElementById("input-buscar-esquina");
const divResBusqueda = document.getElementById("resultado-busqueda-contenedor");

if (btnBuscar) {
    btnBuscar.addEventListener("click", async () => {
        const calle = inputCalle?.value.trim() || "";
        const esquina = inputEsquina?.value.trim() || "";
        if (!calle && !esquina) {
            divResBusqueda.textContent = "Ingresá al menos una calle o esquina para buscar.";
            divResBusqueda.style.color = "#c0392b";
            return;
        }

        divResBusqueda.textContent = "Buscando contenedores...";
        divResBusqueda.style.color = "var(--gris)";

        try {
            const resp = await pedir(API_GESTION, `/contenedores-buscar?calle=${encodeURIComponent(calle)}&esquina=${encodeURIComponent(esquina)}`);
            const resultados = resp.data || [];
            if (resultados.length === 0) {
                divResBusqueda.textContent = "No se encontraron contenedores para esa ubicación.";
                divResBusqueda.style.color = "#e67e22";
                return;
            }

            // Priorizar primer resultado (CU-12)
            const primero = resultados[0];
            listaContenedores.value = String(primero.idCon);
            if (marcadores[primero.idCon] && mapaIncidencia) {
                mapaIncidencia.setView(marcadores[primero.idCon].getLatLng(), 17);
                marcadores[primero.idCon].openPopup();
            }

            divResBusqueda.innerHTML = `✅ <b>Seleccionado primer resultado más coincidente:</b> Contenedor #${primero.idCon} (${escapar(primero.calle)} y ${escapar(primero.esquina)}). Encontrados ${resultados.length} en total.`;
            divResBusqueda.style.color = "#27ae60";
        } catch (e) {
            divResBusqueda.textContent = "Error al buscar: " + e.message;
            divResBusqueda.style.color = "#c0392b";
        }
    });
}

formularioIncidencia.addEventListener("submit", async evento => {
    evento.preventDefault();
    mostrarMensaje(mensajeIncidencia, "");

    const datos = Object.fromEntries(new FormData(formularioIncidencia).entries());
    datos.idCon = Number(datos.idCon);

    try {
        const resultado = await pedir(API_GESTION, "/incidencias", "POST", datos);
        const numeroReporte = resultado.data?.idInci > 0 ? `#${resultado.data.idInci}` : `CONF-${Math.floor(Math.random()*90000+10000)}`;
        mostrarMensaje(
            mensajeIncidencia,
            `${resultado.message} Número de reporte: ${numeroReporte}.`
        );
        formularioIncidencia.reset();
    } catch (error) {
        mostrarMensaje(mensajeIncidencia, error.message, true);
    }
});

cargarContenedores();