async function cargarUsuarios(){


    const respuesta = await fetch(
        "../backend/api/listarUsuarios.php"
    );


    const usuarios = await respuesta.json();


    const tabla = document.getElementById("tablaUsuarios");


    usuarios.forEach(usuario => {


        tabla.innerHTML += `

        <tr>

            <td>${usuario.id}</td>

            <td>
            ${usuario.nombre} ${usuario.apellido}
            </td>

            <td>
            ${usuario.correo}
            </td>

            <td>
            ${usuario.rol}
            </td>


        </tr>

        `;


    });


}



cargarUsuarios();
