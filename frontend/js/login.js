"use strict";

const formularioLogin = document.getElementById("form-login");
const mensajeLogin = document.getElementById("mensaje-login");
const formulario2FA = document.getElementById("form-2fa");
const mensaje2FA = document.getElementById("mensaje-2fa");
const infoSecret2FA = document.getElementById("info-secret-2fa");

const linkAbrirRecuperacion = document.getElementById("link-abrir-recuperacion");
const seccionRecuperar = document.getElementById("seccion-recuperar");
const linkVolverLogin = document.getElementById("link-volver-login");
const formSolicitarCodigo = document.getElementById("form-solicitar-codigo");
const formVerificarCodigo = document.getElementById("form-verificar-codigo");
const mensajeRecuperar = document.getElementById("mensaje-recuperar");
let emailEnRecuperacion = "";

document.getElementById("completar-prueba")?.addEventListener("click", () => {
    formularioLogin.email.value = "admin@sigeru.local";
    formularioLogin.password.value = "admin123";
});

// 1. Envío inicial de Login (contraseña)
formularioLogin.addEventListener("submit", async evento => {
    evento.preventDefault();
    mostrarMensaje(mensajeLogin, "");

    try {
        const resultado = await pedir(API_USUARIOS, "/login", "POST", {
            email: formularioLogin.email.value.trim(),
            password: formularioLogin.password.value
        });

        if (resultado.requiere2fa) {
            formularioLogin.style.display = "none";
            formulario2FA.style.display = "block";

            // Cargar imagen del código QR para escanear con Google Authenticator / Authy
            const imgQr = document.getElementById("img-qr-2fa");
            if (imgQr && resultado.urlOtp) {
                imgQr.src = `https://api.qrserver.com/v1/create-qr-code/?size=170x170&data=${encodeURIComponent(resultado.urlOtp)}`;
            }

            if (infoSecret2FA) {
                infoSecret2FA.textContent = resultado.secret;
            }

            mostrarMensaje(mensaje2FA, resultado.message);
            document.getElementById("codigo-2fa")?.focus();
            return;
        }

        const rolesConPanel = ["administrador", "municipal", "cuadrilla", "operario"];
        window.location.href = rolesConPanel.includes(resultado.data?.rol) ? "panel.html" : "index.html";
    } catch (error) {
        mostrarMensaje(mensajeLogin, error.message, true);
    }
});

// 2. Verificación de 2FA
formulario2FA?.addEventListener("submit", async evento => {
    evento.preventDefault();
    mostrarMensaje(mensaje2FA, "");

    const codigo = document.getElementById("codigo-2fa").value.trim();
    try {
        const resultado = await pedir(API_USUARIOS, "/verificar-2fa", "POST", { codigo });
        mostrarMensaje(mensaje2FA, resultado.message);
        const rolesConPanel = ["administrador", "municipal", "cuadrilla", "operario"];
        window.location.href = rolesConPanel.includes(resultado.data?.rol) ? "panel.html" : "index.html";
    } catch (error) {
        mostrarMensaje(mensaje2FA, error.message, true);
    }
});

// 3. CU-34: Recuperación de contraseña
if (linkAbrirRecuperacion && seccionRecuperar) {
    linkAbrirRecuperacion.addEventListener("click", e => {
        e.preventDefault();
        formularioLogin.style.display = "none";
        if (formulario2FA) formulario2FA.style.display = "none";
        seccionRecuperar.style.display = "block";
        formSolicitarCodigo.style.display = "block";
        formVerificarCodigo.style.display = "none";
        mostrarMensaje(mensajeRecuperar, "");
    });
}

if (linkVolverLogin && seccionRecuperar) {
    linkVolverLogin.addEventListener("click", e => {
        e.preventDefault();
        seccionRecuperar.style.display = "none";
        formularioLogin.style.display = "block";
        mostrarMensaje(mensajeLogin, "");
    });
}

// Paso A: Solicitar código a correo
formSolicitarCodigo?.addEventListener("submit", async e => {
    e.preventDefault();
    mostrarMensaje(mensajeRecuperar, "");
    emailEnRecuperacion = document.getElementById("recup-email").value.trim();

    try {
        const res = await pedir(API_USUARIOS, "/solicitar-recuperacion", "POST", { email: emailEnRecuperacion });
        let msg = res.message;
        if (res.codigoSimulado) {
            msg += ` (Código de prueba generado: ${res.codigoSimulado})`;
            document.getElementById("recup-codigo").value = res.codigoSimulado;
        }
        mostrarMensaje(mensajeRecuperar, msg);
        formSolicitarCodigo.style.display = "none";
        formVerificarCodigo.style.display = "block";
        document.getElementById("recup-codigo").focus();
    } catch (err) {
        mostrarMensaje(mensajeRecuperar, err.message, true);
    }
});

// Paso B: Verificar código y nueva contraseña
formVerificarCodigo?.addEventListener("submit", async e => {
    e.preventDefault();
    mostrarMensaje(mensajeRecuperar, "");

    const codigo = document.getElementById("recup-codigo").value.trim();
    const password = document.getElementById("recup-nueva-pass").value;
    const confirmPassword = document.getElementById("recup-confirm-pass").value;

    try {
        const res = await pedir(API_USUARIOS, "/confirmar-recuperacion", "POST", {
            email: emailEnRecuperacion,
            codigo,
            password,
            confirmPassword
        });
        mostrarMensaje(mensajeRecuperar, res.message);
        setTimeout(() => {
            seccionRecuperar.style.display = "none";
            formularioLogin.style.display = "block";
            formularioLogin.email.value = emailEnRecuperacion;
            formularioLogin.password.value = "";
            mostrarMensaje(mensajeLogin, "¡Contraseña actualizada! Ya podés ingresar con tu nueva clave.");
        }, 1500);
    } catch (err) {
        mostrarMensaje(mensajeRecuperar, err.message, true);
    }
});
