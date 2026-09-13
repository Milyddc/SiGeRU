async function cargarContenedores() {

    const tabla = document.getElementById("tablaContenedores");

    if (!tabla) {
        return;
    }

    try {

        const respuesta = await fetch(
            "../BACKEND/API%20Gestion/listarContenedores.php"
        );

        const texto = await respuesta.text();

        console.log("Respuesta contenedores:", texto);

        if (!respuesta.ok) {
            throw new Error(
                "Error HTTP " + respuesta.status
            );
        }

        const contenedores = JSON.parse(texto);

        tabla.innerHTML = "";

        if (contenedores.length === 0) {

            tabla.innerHTML = `
                <tr>
                    <td colspan="5">
                        No hay contenedores registrados.
                    </td>
                </tr>
            `;

            return;
        }

        contenedores.forEach(contenedor => {

            const fila = document.createElement("tr");

            fila.innerHTML = `
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td>
                    <button type="button" class="btn-editar">Editar</button>
                    <button type="button" class="btn-eliminar">Eliminar</button>
                </td>
            `;

            fila.children[0].textContent = contenedor.codigo;
            fila.children[1].textContent = contenedor.ubicacion;
            fila.children[2].textContent = contenedor.estado;
            fila.children[3].textContent = contenedor.tipo_residuo || "Sin especificar";

            fila.querySelector(".btn-editar").addEventListener("click", () => {
                editarContenedor(contenedor);
            });

            fila.querySelector(".btn-eliminar").addEventListener("click", () => {
                eliminarContenedor(contenedor.id_contenedor);
            });

            tabla.appendChild(fila);

        });

    } catch (error) {

        console.error("ERROR:", error);

        tabla.innerHTML = `
            <tr>
                <td colspan="5">
                    No se pudieron cargar los contenedores.
                </td>
            </tr>
        `;

    }

}


function editarContenedor(contenedor) {

    const parametros = new URLSearchParams({
        id_contenedor: contenedor.id_contenedor,
        codigo: contenedor.codigo || "",
        ubicacion: contenedor.ubicacion || "",
        estado: contenedor.estado || "",
        capacidad: contenedor.capacidad || "",
        id_tipo_residuo: contenedor.id_tipo_residuo || "",
        id_ruta: contenedor.id_ruta || "",
        id_centro_acopio: contenedor.id_centro_acopio || ""
    });

    window.location.href = "contenedorform.html?" + parametros.toString();
}


async function eliminarContenedor(id) {

    const confirmar = confirm(
        "¿Seguro que querés eliminar este contenedor?"
    );

    if (!confirmar) {
        return;
    }

    try {

        const respuesta = await fetch(
            "../BACKEND/API%20Gestion/eliminarContenedor.php",
            {
                method: "POST",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify({
                    id_contenedor: id
                })
            }
        );

        const resultado = await respuesta.json();

        alert(
            resultado.mensaje +
            (resultado.error ? "\n\nDetalle: " + resultado.error : "")
        );

        if (resultado.ok) {
            cargarContenedores();
        }

    } catch (error) {

        console.error(error);

        alert(
            "No se pudo conectar con el servidor."
        );
    }

}


cargarContenedores();
