const formulario = document.getElementById("camionForm");

const parametros = new URLSearchParams(window.location.search);
const idCamion = parametros.get("id_camion");

if (idCamion) {

    document.getElementById("tituloFormulario").textContent = "Modificar Camión";

    document.getElementById("matricula").value = parametros.get("matricula") || "";
    document.getElementById("modelo").value = parametros.get("modelo") || "";
    document.getElementById("capacidad").value = parametros.get("capacidad") || "";

    const idCuadrilla = parametros.get("id_cuadrilla");
    if (idCuadrilla) {
        document.getElementById("id_cuadrilla").value = idCuadrilla;
    }

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

    let url = "../BACKEND/API%20Gestion/crearCamion.php";

    if (idCamion) {
        datos.id_camion = parseInt(idCamion);
        url = "../BACKEND/API%20Gestion/actualizarCamion.php";
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
