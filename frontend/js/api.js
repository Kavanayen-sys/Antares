"use strict";

const servidor = window.location.hostname || "localhost";
const API_USUARIOS = `http://${servidor}:8091`;
const API_LOGISTICA = `http://${servidor}:8092`;
const API_CONTENEDORES = `http://${servidor}:8093`;
const API_CENTROS = `http://${servidor}:8094`;
const API_GESTION = `http://${servidor}:8092`;

const tokensCsrf = {};

function resolverBasePorRuta(base, ruta) {
    if (base && base !== API_GESTION) {
        return base;
    }
    if (ruta.startsWith("/usuarios") || ruta.startsWith("/login") || ruta.startsWith("/logout") || ruta.startsWith("/sesion") || ruta.startsWith("/registro") || ruta.startsWith("/mi-perfil") || ruta.startsWith("/solicitar-recuperacion") || ruta.startsWith("/confirmar-recuperacion") || ruta.startsWith("/logs-login")) {
        return API_USUARIOS;
    }
    if (ruta.startsWith("/contenedores") || ruta.startsWith("/incidencias") || ruta.startsWith("/cedulas-bloqueadas")) {
        return API_CONTENEDORES;
    }
    if (ruta.startsWith("/centros") || ruta.startsWith("/vertederos") || ruta.startsWith("/maquinaria") || ruta.startsWith("/descargas") || ruta.startsWith("/llegada-camion") || ruta.startsWith("/envios-vertedero")) {
        return API_CENTROS;
    }
    return API_LOGISTICA;
}

async function pedir(base, ruta, metodo = "GET", datos = null) {
    const urlBase = resolverBasePorRuta(base, ruta);

    if (!tokensCsrf[urlBase]) {
        try {
            const respuestaToken = await fetch(`${urlBase}/csrf`, { credentials: "include" });
            const resultadoToken = await respuestaToken.json();
            if (respuestaToken.ok && resultadoToken?.data?.csrf) {
                tokensCsrf[urlBase] = resultadoToken.data.csrf;
            }
        } catch (e) {
            // Silencioso
        }
    }

    const opciones = {
        method: metodo,
        credentials: "include",
        headers: { "Content-Type": "application/json" }
    };

    if (["POST", "PUT", "DELETE"].includes(metodo) && tokensCsrf[urlBase]) {
        opciones.headers["X-CSRF-Token"] = tokensCsrf[urlBase];
    }
    if (datos !== null) {
        opciones.body = JSON.stringify(datos);
    }

    const respuesta = await fetch(urlBase + ruta, opciones);
    const texto = await respuesta.text();
    let resultado = {};
    try {
        resultado = texto ? JSON.parse(texto) : {};
    } catch (e) {
        console.error("Respuesta no es JSON:", texto);
        throw new Error("El servidor devolvió una respuesta inesperada (" + respuesta.status + ").");
    }
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