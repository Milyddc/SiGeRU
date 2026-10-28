const formulario = document.getElementById("registroForm");

formulario.addEventListener("submit", async (e) => {

    e.preventDefault();

    const datos = {
        nombre: document.getElementById("nombre").value,
        apellido: document.getElementById("apellido").value,
        correo: document.getElementById("correo").value,
        password: document.getElementById("password").value
    };

    try {

        const respuesta = await fetch(
            "/api/usuarios/registro.php",
            {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(datos)
            }
        );

        const resultado = await respuesta.json();

        alert(resultado.mensaje);

        if (resultado.ok) {
            window.location.href = "/app/login.html";
        }

    } catch (error) {

        alert("No se pudo conectar con el servidor.");

    }

});
