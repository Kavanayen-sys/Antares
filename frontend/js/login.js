"use strict";

const formularioLogin = document.getElementById("form-login");
const mensajeLogin = document.getElementById("mensaje-login");

document.getElementById("completar-prueba").addEventListener("click", () => {
    formularioLogin.email.value = "admin@sigeru.local";
    formularioLogin.password.value = "admin123";
});

formularioLogin.addEventListener("submit", async evento => {
    evento.preventDefault();
    mostrarMensaje(mensajeLogin, "");

    try {
        const resultado = await pedir(API_USUARIOS, "/login", "POST", {
            email: formularioLogin.email.value.trim(),
            password: formularioLogin.password.value
        });
        mostrarMensaje(mensajeLogin, resultado.message);
        window.location.href = resultado.data.rol === "administrador" ? "panel.html" : "index.html";
    } catch (error) {
        mostrarMensaje(mensajeLogin, error.message, true);
    }
});