const formulario = document.getElementById("loginForm");

formulario.addEventListener("submit", async (e) => {

    e.preventDefault();

    const datos = {
        correo: document.getElementById("correo").value,
        password: document.getElementById("password").value
    };

    try {

        const respuesta = await fetch(
            "/api/usuarios/login.php",
            {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(datos)
            }
        );

        const resultado = await respuesta.json();

        if (resultado.ok) {

            guardarSesion(resultado.token, resultado.usuario);

            window.location.href = "dashboard.html";

        } else {

            alert(resultado.mensaje || "Correo o contraseña incorrectos");

        }

    } catch (error) {

        alert("No se pudo conectar con el servidor.");

    }

});
