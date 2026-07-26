"use strict";

const formularioRegistro = document.getElementById("form-registro");
const mensajeRegistro = document.getElementById("mensaje-registro");

formularioRegistro.addEventListener("submit", async evento => {
    evento.preventDefault();
    mostrarMensaje(mensajeRegistro, "");

    if (formularioRegistro.password.value !== document.getElementById("repetir-password").value) {
        mostrarMensaje(mensajeRegistro, "Las contraseñas no coinciden.", true);
        return;
    }

    try {
        const datos = Object.fromEntries(new FormData(formularioRegistro).entries());
        const resultado = await pedir(API_USUARIOS, "/registro", "POST", datos);
        mostrarMensaje(mensajeRegistro, resultado.message + " Ya podés iniciar sesión.");
        formularioRegistro.reset();
    } catch (error) {
        mostrarMensaje(mensajeRegistro, error.message, true);
    }
});