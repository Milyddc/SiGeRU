const formulario = document.getElementById("contenedorForm");

const parametros = new URLSearchParams(window.location.search);
const idContenedor = parametros.get("id_contenedor");


async function cargarCentrosEnSelect() {

    const select = document.getElementById("id_centro_acopio");

    try {

        const respuesta = await fetch(
            "../BACKEND/API%20Gestion/listarCentros.php"
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

    const idRuta = parametros.get("id_ruta");
    if (idRuta) {
        document.getElementById("id_ruta").value = idRuta;
    }
}

cargarCentrosEnSelect();


formulario.addEventListener("submit", async (e) => {

    e.preventDefault();

    const idCentroValor = document.getElementById("id_centro_acopio").value;

    const datos = {
        codigo: document.getElementById("codigo").value,
        ubicacion: document.getElementById("ubicacion").value,
        estado: document.getElementById("estado").value,
        capacidad: parseInt(document.getElementById("capacidad").value),
        id_tipo_residuo: parseInt(document.getElementById("id_tipo_residuo").value),
        id_ruta: parseInt(document.getElementById("id_ruta").value),
        id_centro_acopio: idCentroValor ? parseInt(idCentroValor) : null
    };

    let url = "../BACKEND/API%20Gestion/crearContenedor.php";

    if (idContenedor) {
        datos.id_contenedor = parseInt(idContenedor);
        url = "../BACKEND/API%20Gestion/actualizarContenedor.php";
    }

    try {

        const respuesta = await fetch(
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
