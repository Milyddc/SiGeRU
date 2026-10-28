const PAGINAS_PUBLICAS = ["", "login.html"];

function guardarSesion(token, usuario) {
    sessionStorage.setItem("token", token);
    sessionStorage.setItem("usuario", JSON.stringify(usuario));
}

function obtenerToken() {
    return sessionStorage.getItem("token");
}

function obtenerUsuario() {
    try {
        return JSON.parse(sessionStorage.getItem("usuario"));
    } catch (e) {
        return null;
    }
}

function cerrarSesion() {
    sessionStorage.clear();
    window.location.href = "login.html";
}

async function apiFetch(url, opciones = {}) {

    const headers = Object.assign({}, opciones.headers || {});
    const token = obtenerToken();

    if (token) {
        headers["Authorization"] = "Bearer " + token;
    }

    const respuesta = await fetch(url, Object.assign({}, opciones, { headers }));

    if (respuesta.status === 401) {
        sessionStorage.clear();
        sessionStorage.setItem("aviso", "Tu sesión venció o no es válida. Volvé a iniciar sesión.");
        window.location.href = "login.html";
    }

    return respuesta;
}

(function () {
    const pagina = window.location.pathname.split("/").pop();

    if (!PAGINAS_PUBLICAS.includes(pagina) && !obtenerToken()) {
        window.location.href = "login.html";
    }
})();
