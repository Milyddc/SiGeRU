const formulario = document.getElementById("usuarioForm");

const parametros = new URLSearchParams(window.location.search);
const idUsuario = parametros.get("id_usuario");

const inputPassword = document.getElementById("password");

if (idUsuario) {

    document.getElementById("tituloFormulario").textContent = "Modificar Usuario";
    document.getElementById("labelPassword").textContent = "Contraseña (dejar vacío para no cambiarla)";

    document.getElementById("nombre").value = parametros.get("nombre") || "";
    document.getElementById("apellido").value = parametros.get("apellido") || "";
    document.getElementById("correo").value = parametros.get("correo") || "";

    const rol = parametros.get("rol");
    if (rol) {
        document.getElementById("rol").value = rol;
    }

} else {

    inputPassword.required = true;
}


formulario.addEventListener("submit", async (e) => {

    e.preventDefault();

    try {

        const datos = {
            nombre: document.getElementById("nombre").value,
            apellido: document.getElementById("apellido").value,
            correo: document.getElementById("correo").value,
            rol: parseInt(document.getElementById("rol").value)
        };

        let url = "../BACKEND/API%20USUARIOS/registro.php";

        if (idUsuario) {

            datos.id_usuario = parseInt(idUsuario);
            datos.password = inputPassword.value;

            url = "../BACKEND/API%20USUARIOS/actualizarusuario.php";

        } else {

            datos.password = inputPassword.value;
        }

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

        console.log("ESTADO HTTP:", respuesta.status);
        console.log("RESPUESTA:", texto);

        const resultado = JSON.parse(texto);

        if (resultado.ok) {

            alert(
                idUsuario
                    ? "Usuario actualizado correctamente"
                    : "Usuario registrado correctamente"
            );

            window.location.href = "usuarios.html";

        } else {

            alert(
                resultado.mensaje ||
                "No se pudo guardar el usuario"
            );

        }

    } catch (error) {

        console.error(error);

        alert("No se pudo conectar con el servidor.");

    }

});
