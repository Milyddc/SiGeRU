const formulario = document.getElementById("camionForm");

const parametros = new URLSearchParams(window.location.search);
const idCamion = parametros.get("id_camion");
let cuadrillaInicial = null;

if (idCamion) {

    document.getElementById("tituloFormulario").textContent = "Modificar Camión";

    document.getElementById("matricula").value = parametros.get("matricula") || "";
    document.getElementById("modelo").value = parametros.get("modelo") || "";
    document.getElementById("capacidad").value = parametros.get("capacidad") || "";

    cuadrillaInicial = parametros.get("id_cuadrilla");

    const estado = parametros.get("estado");
    if (estado) {
        document.getElementById("estado").value = estado;
    }
}

formulario.addEventListener("submit", async (e) => {

    e.preventDefault();

    const datos = {

        matricula: document.getElementById("matricula").value,

        modelo: document.getElementById("modelo").value,

        capacidad: parseFloat(
            document.getElementById("capacidad").value
        ),

        id_cuadrilla: parseInt(
            document.getElementById("id_cuadrilla").value
        ),

        estado: document.getElementById("estado").value
    };

    let url = "/api/recoleccion/crearCamion.php";

    if (idCamion) {
        datos.id_camion = parseInt(idCamion);
        url = "/api/recoleccion/actualizarCamion.php";
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
                idCamion
                    ? "Camión actualizado correctamente"
                    : "Camión registrado correctamente"
            );

            window.location.href = "camiones.html";

        } else {

            alert(
                (idCamion ? "No se pudo actualizar el camión: " : "No se pudo registrar el camión: ") +
                resultado.mensaje
            );

            console.error(resultado.error);
        }

    } catch (error) {

        console.error(error);

        alert("Error al guardar el camión. Revisá la consola.");

    }

});

async function cargarCuadrillas() {

    const select = document.getElementById("id_cuadrilla");

    try {

        const respuesta = await apiFetch("/api/recoleccion/listarCuadrillas.php");
        const cuadrillas = await respuesta.json();

        cuadrillas.forEach(c => {
            const op = document.createElement("option");
            op.value = c.id_cuadrilla;
            op.textContent = c.nombre + " (" + c.turno + ")";
            select.appendChild(op);
        });

    } catch (error) {
        console.error("No se pudieron cargar las cuadrillas:", error);
    }

    if (cuadrillaInicial) {
        select.value = cuadrillaInicial;
    }
}

cargarCuadrillas();
