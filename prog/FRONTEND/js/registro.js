const formulario = document.getElementById("registroForm");

formulario.addEventListener("submit", async (e) => {

    e.preventDefault();

    try {

        const datos = {
            nombre: document.getElementById("nombre").value,
            apellido: document.getElementById("apellido").value,
            correo: document.getElementById("correo").value,
            password: document.getElementById("password").value,
            rol: parseInt(document.getElementById("rol").value)
        };

        const respuesta = await fetch(
            "../BACKEND/API%20USUARIOS/registro.php",
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

        if (!respuesta.ok) {

            throw new Error(
                "Error HTTP " + respuesta.status
            );

        }

        const resultado = JSON.parse(texto);

        if (resultado.ok) {

            alert("Usuario registrado correctamente");

            window.location.href = "login.html";

        } else {

            alert(
                resultado.mensaje ||
                "No se pudo registrar el usuario"
            );

        }

    } catch (error) {

        console.error("ERROR:", error);

        alert(
            "Error al registrar el usuario:\n\n" +
            error.message
        );

    }

});

