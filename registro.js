const formulario = document.querySelector("form");

formulario.addEventListener("submit", async (e)=>{

    e.preventDefault();

    const datos={

        nombre:document.getElementById("nombre").value,

        apellido:document.getElementById("apellido").value,

        correo:document.getElementById("email").value,

        password:document.getElementById("password").value,

        rol:document.getElementById("rol").value

    };

    const respuesta=await fetch(

        "../backend/api/registro.php",

        {

            method:"POST",

            headers:{

                "Content-Type":"application/json"

            },

            body:JSON.stringify(datos)

        }

    );

    const resultado=await respuesta.json();

    alert(resultado.mensaje);

    window.location="login.html";

});
