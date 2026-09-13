document.addEventListener("DOMContentLoaded", function () {

    cargarUsuarios();
    cargarContenedores();
    cargarCamiones();
    cargarCentros();

});


async function cargarUsuarios() {

    const elemento = document.getElementById("cantidadUsuarios");

    try {

        const respuesta = await fetch("../BACKEND/API%20USUARIOS/listarusuarios.php");

        const datos = await respuesta.json();

        if (Array.isArray(datos)) {

            elemento.textContent = datos.length;

        } else if (datos.usuarios && Array.isArray(datos.usuarios)) {

            elemento.textContent = datos.usuarios.length;

        } else {

            elemento.textContent = "0";

        }

    } catch (error) {

        console.error("Error al cargar usuarios:", error);

        elemento.textContent = "0";

    }

}


async function cargarContenedores() {

    const elemento = document.getElementById("cantidadContenedores");

    try {

        const respuesta = await fetch("../BACKEND/API%20Gestion/listarContenedores.php");

        const datos = await respuesta.json();

        if (Array.isArray(datos)) {

            elemento.textContent = datos.length;

        } else if (datos.contenedores && Array.isArray(datos.contenedores)) {

            elemento.textContent = datos.contenedores.length;

        } else {

            elemento.textContent = "0";

        }

    } catch (error) {

        console.error("Error al cargar contenedores:", error);

        elemento.textContent = "0";

    }

}


async function cargarCamiones() {

    const elemento = document.getElementById("cantidadCamiones");

    try {

        const respuesta = await fetch("../BACKEND/API%20Gestion/listarCamiones.php");

        const datos = await respuesta.json();

        if (Array.isArray(datos)) {

            elemento.textContent = datos.length;

        } else if (datos.camiones && Array.isArray(datos.camiones)) {

            elemento.textContent = datos.camiones.length;

        } else {

            elemento.textContent = "0";

        }

    } catch (error) {

        console.error("Error al cargar camiones:", error);

        elemento.textContent = "0";

    }

}


async function cargarCentros() {

    const elemento = document.getElementById("cantidadCentros");

    try {

        const respuesta = await fetch("../BACKEND/API%20Gestion/listarCentros.php");

        const datos = await respuesta.json();

        if (Array.isArray(datos)) {

            elemento.textContent = datos.length;

        } else {

            elemento.textContent = "0";

        }

    } catch (error) {

        console.error("Error al cargar centros:", error);

        elemento.textContent = "0";

    }

}