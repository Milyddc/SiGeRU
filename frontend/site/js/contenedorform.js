const formulario = document.getElementById("contenedorForm");

const parametros = new URLSearchParams(window.location.search);
const idContenedor = parametros.get("id_contenedor");


async function cargarCentrosEnSelect() {

    const select = document.getElementById("id_centro_acopio");

    try {

        const respuesta = await apiFetch(
            "/api/gestion/listarCentros.php"
        );

        const centros = await respuesta.json();

        if (Array.isArray(centros)) {

            centros.forEach(centro => {

                const opcion = document.createElement("option");
                opcion.value = centro.id_centro_acopio;
                opcion.textContent = centro.nombre;

                select.appendChild(opcion);
            });
        }

    } catch (error) {
        console.error("No se pudieron cargar los centros de acopio:", error);
    }

    const idCentroActual = parametros.get("id_centro_acopio");

    if (idCentroActual) {
        select.value = idCentroActual;
    }
}


async function cargarRutasEnSelect() {

    const select = document.getElementById("id_ruta");

    try {

        const respuesta = await apiFetch(
            "/api/recoleccion/listarRutas.php"
        );

        const rutas = await respuesta.json();

        if (Array.isArray(rutas)) {

            rutas.forEach(ruta => {

                const opcion = document.createElement("option");
                opcion.value = ruta.id_ruta;
                opcion.textContent = ruta.nombre;

                select.appendChild(opcion);
            });
        }

    } catch (error) {
        console.error("No se pudieron cargar las rutas:", error);
    }

    const idRutaActual = parametros.get("id_ruta");

    if (idRutaActual) {
        select.value = idRutaActual;
    }
}


if (idContenedor) {

    document.getElementById("tituloFormulario").textContent = "Modificar Contenedor";

    document.getElementById("codigo").value = parametros.get("codigo") || "";
    document.getElementById("ubicacion").value = parametros.get("ubicacion") || "";
    document.getElementById("capacidad").value = parametros.get("capacidad") || "";

    const estado = parametros.get("estado");
    if (estado) {
        document.getElementById("estado").value = estado;
    }

    const idTipo = parametros.get("id_tipo_residuo");
    if (idTipo) {
        document.getElementById("id_tipo_residuo").value = idTipo;
    }

    document.getElementById("zona").value = parametros.get("zona") || "";
    document.getElementById("latitud").value = parametros.get("latitud") || "";
    document.getElementById("longitud").value = parametros.get("longitud") || "";
}

cargarCentrosEnSelect();
cargarRutasEnSelect();


formulario.addEventListener("submit", async (e) => {

    e.preventDefault();

    const idCentroValor = document.getElementById("id_centro_acopio").value;

    const datos = {
        codigo: document.getElementById("codigo").value,
        ubicacion: document.getElementById("ubicacion").value,
        estado: document.getElementById("estado").value,
        capacidad: parseInt(document.getElementById("capacidad").value),
        id_tipo_residuo: parseInt(document.getElementById("id_tipo_residuo").value),
        id_ruta: document.getElementById("id_ruta").value === ""
            ? null
            : parseInt(document.getElementById("id_ruta").value),
        zona: document.getElementById("zona").value,
        latitud: document.getElementById("latitud").value,
        longitud: document.getElementById("longitud").value,
        id_centro_acopio: idCentroValor ? parseInt(idCentroValor) : null
    };

    let url = "/api/gestion/crearContenedor.php";

    if (idContenedor) {
        datos.id_contenedor = parseInt(idContenedor);
        url = "/api/gestion/actualizarContenedor.php";
    }

    try {

        const respuesta = await apiFetch(
            url,
            {
                method: "POST",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify(datos)
            }
        );

        const texto = await respuesta.text();

        console.log("Respuesta del servidor:", texto);

        const resultado = JSON.parse(texto);

        if (resultado.ok) {

            alert(
                idContenedor
                    ? "Contenedor actualizado correctamente"
                    : "Contenedor registrado correctamente"
            );

            window.location.href = "contenedores.html";

        } else {

            alert(
                (idContenedor ? "No se pudo actualizar el contenedor: " : "No se pudo registrar el contenedor: ") +
                resultado.mensaje
            );

            console.error(resultado.error);
        }

    } catch (error) {

        console.error(error);

        alert("Error al guardar el contenedor. Revisá la consola.");

    }

});
