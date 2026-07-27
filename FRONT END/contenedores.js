async function cargarContenedores(){


const respuesta = await fetch(
    "../backend/api/listarContenedores.php"
);



const contenedores = await respuesta.json();



const tabla = document.getElementById("tablaContenedores");



contenedores.forEach(contenedor => {



tabla.innerHTML += `


<tr>


<td>${contenedor.id}</td>

<td>${contenedor.ubicacion}</td>

<td>${contenedor.estado}</td>

<td>${contenedor.tipo}</td>



</tr>


`;



});



}



cargarContenedores();
