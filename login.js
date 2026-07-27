const formulario = document.querySelector("form");

formulario.addEventListener("submit", async (e) => {

    e.preventDefault();

    const datos = {

        correo: document.getElementById("email").value,

        password: document.getElementById("password").value

    };

    const respuesta = await fetch(
        "../backend/api/login.php",
        {
            method: "POST",
            headers: {
                "Content-Type":"application/json"
            },
            body: JSON.stringify(datos)
        }
    );

    const resultado = await respuesta.json();

    if(resultado.ok){

        alert("Bienvenido");

        window.location="dashboard.html";

    }else{

        alert(resultado.mensaje);

    }

});
