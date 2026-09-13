async function cargarCamiones() {

    const tabla = document.getElementById("tablaCamiones");

    if (!tabla) {
        return;
    }

    try {

        const respuesta = await fetch(
            "../BACKEND/API%20Gestion/listarCamiones.php"
        );

        const texto = await respuesta.text();

        console.log("Respuesta camiones:", texto);

        if (!respuesta.ok) {
            throw new Error(
                "Error HTTP " + respuesta.status
            );
        }

        const camiones = JSON.parse(texto);

        tabla.innerHTML = "";

        if (camiones.length === 0) {

            tabla.innerHTML = `
                <tr>
                    <td colspan="5">
                        No hay camiones registrados.
                    </td>
                </tr>
            `;

            return;
        }

        camiones.forEach(camion => {

            const fila = document.createElement("tr");

            fila.innerHTML = `
                <td>${camion.id_camion}</td>
                <td></td>
                <td></td>
                <td></td>
                <td>
                    <button type="button" class="btn-editar">Editar</button>
                    <button type="button" class="btn-eliminar">Eliminar</button>
                </td>
            `;

            fila.children[1].textContent = camion.matricula;
            fila.children[2].textContent = camion.modelo || "";
            fila.children[3].textContent = camion.estado || "";

            fila.querySelector(".btn-editar").addEventListener("click", () => {
                editarCamion(camion);
            });

            fila.querySelector(".btn-eliminar").addEventListener("click", () => {
                eliminarCamion(camion.id_camion);
            });

            tabla.appendChild(fila);

        });

    } catch (error) {

        console.error(error);

        tabla.innerHTML = `

            <tr>

                <td colspan="5">
                    No se pudieron cargar los camiones.
                </td>

            </tr>

        `;

    }

}


function editarCamion(camion) {

    const parametros = new URLSearchParams({
        id_camion: camion.id_camion,
        matricula: camion.matricula || "",
        modelo: camion.modelo || "",
        capacidad: camion.capacidad || "",
        estado: camion.estado || "",
        id_cuadrilla: camion.id_cuadrilla || ""
    });

    window.location.href = "camionform.html?" + parametros.toString();
}


async function eliminarCamion(id) {

    const confirmar = confirm(
        "¿Seguro que querés eliminar este camión?"
    );

    if (!confirmar) {
        return;
    }

    try {

        const respuesta = await fetch(
            "../BACKEND/API%20Gestion/eliminarCamion.php",
            {
                method: "POST",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify({
                    id_camion: id
                })
            }
        );

        const resultado = await respuesta.json();

        alert(resultado.mensaje);

        if (resultado.ok) {
            cargarCamiones();
        }

    } catch (error) {

        console.error(error);

        alert(
            "No se pudo conectar con el servidor."
        );
    }

}

cargarCamiones();
