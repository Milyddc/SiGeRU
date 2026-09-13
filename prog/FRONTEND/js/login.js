const formulario = document.getElementById("loginForm");

formulario.addEventListener("submit", async (e) => {

    e.preventDefault();

    const datos = {
        correo: document.getElementById("correo").value,
        password: document.getElementById("password").value
    };

    try {

        const respuesta = await fetch(
            "../BACKEND/API%20USUARIOS/login.php",
            {
                method: "POST",

                headers: {
                    "Content-Type": "application/json"
                },

                body: JSON.stringify(datos)
            }
        );

        const texto = await respuesta.text();

        console.log("Estado HTTP:", respuesta.status);
        console.log("Respuesta del servidor:", texto);

        if (!respuesta.ok) {

            alert(
                "El servidor devolvió un error.\n\n" +
                "Código: " + respuesta.status +
                "\n\n" +
                texto
            );

            return;
        }

        const resultado = JSON.parse(texto);

        if (resultado.ok) {

            alert("Bienvenido");

            window.location.href = "dashboard.html";

        } else {

            alert(
                resultado.mensaje ||
                "Correo o contraseña incorrectos"
            );

        }

    } catch (error) {

        console.error("Error:", error);

        alert(
            "Error al comunicarse con el servidor.\n\n" +
            error.message
        );

    }

});
