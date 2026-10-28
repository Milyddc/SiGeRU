const formulario = document.getElementById("loginForm");

function mostrarMensaje(texto) {

    let caja = document.getElementById("mensajeLogin");

    if (!caja) {
        caja = document.createElement("p");
        caja.id = "mensajeLogin";
        caja.setAttribute("role", "alert");
        caja.style.cssText = "color:#ff8a80;font-weight:bold;margin:10px 0;";
        formulario.prepend(caja);
    }

    caja.textContent = texto;
}

async function pedirLogin(datos) {

    let respuesta;

    try {
        respuesta = await fetch("/api/usuarios/login.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(datos)
        });
    } catch (error) {
        return { ok: false, mensaje: "No se pudo conectar con el servidor. Revisá que Docker esté en marcha." };
    }

    if (respuesta.status === 429) {
        return { ok: false, mensaje: "Demasiados intentos seguidos. Esperá un minuto e intentá de nuevo." };
    }

    if (respuesta.status === 502 || respuesta.status === 503 || respuesta.status === 504) {
        return { ok: false, mensaje: "El servicio de usuarios no está disponible (HTTP " + respuesta.status + "). Esperá unos segundos o revisá los contenedores." };
    }

    let cuerpo = null;

    try {
        cuerpo = await respuesta.json();
    } catch (error) {
        cuerpo = null;
    }

    if (!cuerpo) {
        return { ok: false, mensaje: "Respuesta inesperada del servidor (HTTP " + respuesta.status + ")." };
    }

    if (respuesta.status >= 500) {
        return { ok: false, mensaje: "Error interno del servidor (HTTP " + respuesta.status + "). Revisá los registros de api-usuarios." };
    }

    return cuerpo;
}

const avisoPrevio = sessionStorage.getItem("aviso");

if (avisoPrevio) {
    sessionStorage.removeItem("aviso");
    mostrarMensaje(avisoPrevio);
}

formulario.addEventListener("submit", async (e) => {

    e.preventDefault();

    const datos = {
        correo: document.getElementById("correo").value,
        password: document.getElementById("password").value
    };

    const resultado = await pedirLogin(datos);

    if (resultado.ok) {
        guardarSesion(resultado.token, resultado.usuario);
        window.location.href = "dashboard.html";
        return;
    }

    mostrarMensaje(resultado.mensaje || "Correo o contraseña incorrectos");
});
