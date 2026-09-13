async function cargarUsuarios() {

    const tabla = document.getElementById("tablaUsuarios");

    if (!tabla) {
        return;
    }

    try {

        const respuesta = await fetch(
            "../BACKEND/API%20USUARIOS/listarusuarios.php"
        );

        const texto = await respuesta.text();

        console.log("Respuesta listarUsuarios:", texto);

        if (!respuesta.ok) {
            throw new Error(
                "Error al cargar usuarios: " + respuesta.status
            );
        }

        const usuarios = JSON.parse(texto);

        tabla.innerHTML = "";

        usuarios.forEach(usuario => {

            let rol = "Sin rol";

            if (usuario.id_rol == 1) {
                rol = "Administrador";
            } else if (usuario.id_rol == 2) {
                rol = "Recolector";
            } else if (usuario.id_rol == 3) {
                rol = "Operador";
            } else if (usuario.id_rol == 4) {
                rol = "Vecino";
            }

            const fila = document.createElement("tr");

            fila.innerHTML = `
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td>
                    <button type="button" class="btn-editar">Editar</button>
                    <button type="button" class="btn-eliminar">Eliminar</button>
                </td>
            `;

            fila.children[0].textContent = usuario.id_usuario;
            fila.children[1].textContent = usuario.nombre_completo;
            fila.children[2].textContent = usuario.email;
            fila.children[3].textContent = rol;
            fila.children[4].textContent = usuario.estado;

            fila.querySelector(".btn-editar").addEventListener("click", () => {
                editarUsuario(usuario);
            });

            fila.querySelector(".btn-eliminar").addEventListener("click", () => {
                eliminarUsuario(usuario.id_usuario);
            });

            tabla.appendChild(fila);

        });

    } catch (error) {

        console.error(error);

        tabla.innerHTML = `

            <tr>

                <td colspan="6">
                    No se pudieron cargar los usuarios.
                </td>

            </tr>

        `;

    }

}


function editarUsuario(usuario) {

    const partes = (usuario.nombre_completo || "").trim().split(" ");
    const nombre = partes.shift() || "";
    const apellido = partes.join(" ");

    const parametros = new URLSearchParams({
        id_usuario: usuario.id_usuario,
        nombre: nombre,
        apellido: apellido,
        correo: usuario.email || "",
        rol: usuario.id_rol || ""
    });

    window.location.href = "usuarioform.html?" + parametros.toString();
}


async function eliminarUsuario(id) {

    const confirmar = confirm(
        "¿Seguro que querés eliminar este usuario?"
    );

    if (!confirmar) {
        return;
    }

    try {

        const respuesta = await fetch(
            "../BACKEND/API%20USUARIOS/eliminarusuario.php",
            {
                method: "POST",

                headers: {
                    "Content-Type": "application/json"
                },

                body: JSON.stringify({
                    id_usuario: id
                })
            }
        );

        const resultado = await respuesta.json();

        alert(resultado.mensaje);

        if (resultado.ok) {
            cargarUsuarios();
        }

    } catch (error) {

        console.error(error);

        alert(
            "No se pudo conectar con el servidor."
        );

    }

}


cargarUsuarios();
