async function cargarCamiones(){


const respuesta = await fetch(
    "../backend/api/listarCamiones.php"
);


const camiones = await respuesta.json();



const tabla = document.getElementById("tablaCamiones");



camiones.forEach(camion => {


tabla.innerHTML += `

<tr>

<td>${camion.id}</td>

<td>${camion.matricula}</td>

<td>${camion.modelo}</td>

<td>${camion.estado}</td>


</tr>


`;



});


}



cargarCamiones();