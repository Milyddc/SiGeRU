const API = "../BACKEND/API%20Gestion/";

async function cargarCentros() {

    const tabla = document.getElementById("tablaCentros");

    if (!tabla) {
        return;
    }

    try {

        const respuesta = await fetch(
            API + "listarCentros.php"
        );

        if (!respuesta.ok) {
            throw new Error(
                "Error HTTP " + respuesta.status
            );
        }

        const centros = await respuesta.json();

        tabla.innerHTML = "";

        if (centros.length === 0) {

            tabla.innerHTML = `
                <tr>
                    <td colspan="5">
                        No hay centros de acopio registrados.
                    </td>
                </tr>
            `;

            return;
        }

        centros.forEach(centro => {

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

            fila.children[0].textContent = centro.id_centro_acopio;
            fila.children[1].textContent = centro.nombre;
            fila.children[2].textContent = centro.direccion;
            fila.children[3].textContent = centro.capacidad;

            fila.querySelector(".btn-editar").addEventListener("click", () => {
                editarCentro(centro.id_centro_acopio, centro.nombre, centro.direccion, centro.capacidad);
            });

            fila.querySelector(".btn-eliminar").addEventListener("click", () => {
                eliminarCentro(centro.id_centro_acopio);
            });

            tabla.appendChild(fila);
        });

    } catch (error) {

        console.error("ERROR AL CARGAR CENTROS:", error);

        tabla.innerHTML = `
            <tr>
                <td colspan="5">
                    No se pudieron cargar los centros de acopio.
                </td>
            </tr>
        `;
    }
}


function editarCentro(id, nombre, direccion, capacidad) {

    const parametros = new URLSearchParams({
        id: id,
        nombre: nombre,
        direccion: direccion,
        capacidad: capacidad
    });

    window.location.href =
        "centroform.html?" + parametros.toString();
}


async function eliminarCentro(id) {

    const confirmar = confirm(
        "¿Seguro que querés eliminar este centro de acopio?"
    );

    if (!confirmar) {
        return;
    }

    try {

        console.log("========== ELIMINAR CENTRO ==========");
        console.log("ID que se intenta eliminar:", id);

        const respuesta = await fetch(
            API + "eliminarCentro.php",
            {
                method: "POST",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify({
                    id_centro_acopio: id
                })
            }
        );

        console.log("Código HTTP:", respuesta.status);
        console.log("Respuesta correcta:", respuesta.ok);

        const texto = await respuesta.text();

        console.log("Respuesta completa del servidor:");
        console.log(texto);

        let resultado;

        try {
            resultado = JSON.parse(texto);
        } catch (error) {

            console.error(
                "La respuesta NO es JSON válido."
            );

            console.error(
                "Texto recibido:",
                texto
            );

            alert(
                "El servidor devolvió un error. Revisá la consola."
            );

            return;
        }

        console.log("Resultado JSON:", resultado);

        alert(
            resultado.mensaje +
            (resultado.error ? "\n\nDetalle: " + resultado.error : "")
        );

        if (resultado.ok) {
            cargarCentros();
        }

    } catch (error) {

        console.error(
            "ERROR AL ELIMINAR CENTRO:",
            error
        );

        alert(
            "No se pudo conectar con el servidor."
        );
    }
}


const formulario = document.getElementById("centroForm");

if (formulario) {

    const parametros = new URLSearchParams(
        window.location.search
    );

    const id = parametros.get("id");

    if (id) {

        document.getElementById("tituloFormulario").textContent =
            "Modificar Centro de Acopio";

        document.getElementById("nombre").value =
            parametros.get("nombre") || "";

        document.getElementById("direccion").value =
            parametros.get("direccion") || "";

        document.getElementById("capacidad").value =
            parametros.get("capacidad") || "";
    }


    formulario.addEventListener("submit", async (e) => {

        e.preventDefault();

        const datos = {

            nombre: document.getElementById("nombre").value,

            direccion: document.getElementById("direccion").value,

            capacidad: parseInt(
                document.getElementById("capacidad").value
            )
        };

        try {

            let url;
            let datosEnviar;

            if (id) {

                url = API + "actualizarCentro.php";

                datosEnviar = {
                    id_centro_acopio: parseInt(id),
                    ...datos
                };

            } else {

                url = API + "crearCentro.php";

                datosEnviar = datos;
            }

            const respuesta = await fetch(
                url,
                {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json"
                    },
                    body: JSON.stringify(datosEnviar)
                }
            );

            const resultado = await respuesta.json();

            alert(resultado.mensaje);

            if (resultado.ok) {

                window.location.href =
                    "centros.html";
            }

        } catch (error) {

            console.error(error);

            alert(
                "No se pudo conectar con el servidor."
            );
        }
    });
}


cargarCentros();