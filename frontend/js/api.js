"use strict";

const servidor = window.location.hostname || "localhost";
const API_USUARIOS = `http://${servidor}:8091`;
const API_GESTION = `http://${servidor}:8092`;
let tokenCsrf = "";

async function pedir(base, ruta, metodo = "GET", datos = null) {
    if (!tokenCsrf) {
        const respuestaToken = await fetch(`${base}/csrf`, { credentials: "include" });
        const resultadoToken = await respuestaToken.json();
        if (!respuestaToken.ok) {
            throw new Error(resultadoToken.error || "No se pudo iniciar la comunicación con la API.");
        }
        tokenCsrf = resultadoToken.data.csrf;
    }

    const opciones = {
        method: metodo,
        credentials: "include",
        headers: { "Content-Type": "application/json" }
    };

    if (["POST", "PUT", "DELETE"].includes(metodo)) {
        opciones.headers["X-CSRF-Token"] = tokenCsrf;
    }
    if (datos !== null) {
        opciones.body = JSON.stringify(datos);
    }

    const respuesta = await fetch(base + ruta, opciones);
    const resultado = await respuesta.json();
    if (!respuesta.ok) {
        const error = new Error(resultado.error || "No se pudo completar la operación.");
        error.estado = respuesta.status;
        throw error;
    }
    return resultado;
}

function escapar(valor) {
    return String(valor ?? "")
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#039;");
}

function mostrarMensaje(elemento, texto, error = false) {
    elemento.textContent = texto;
    elemento.classList.toggle("error", error);
}