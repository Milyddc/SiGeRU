const BASE_REC = "/api/recoleccion/";

let cuadrillas = [];
let rutaEditando = null;

const formulario = document.getElementById("rutaForm");
const tabla = document.getElementById("tablaRutas");
const selectCuadrilla = document.getElementById("id_cuadrilla");


async function llamar(archivo, datos) {

    const respuesta = await apiFetch(BASE_REC + archivo, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(datos)
    });

    return respuesta.json();
}


function opcionesCuadrilla(select, seleccionada) {

    select.innerHTML = "";

    const vacia = document.createElement("option");
    vacia.value = "";
    vacia.textContent = "Sin asignar";
    select.appendChild(vacia);

    cuadrillas.forEach(c => {
        const op = document.createElement("option");
        op.value = c.id_cuadrilla;
        op.textContent = c.nombre + " (" + c.turno + ")";
        select.appendChild(op);
    });

    select.value = seleccionada ? String(seleccionada) : "";
}


async function cargarCuadrillas() {

    const respuesta = await apiFetch(BASE_REC + "listarCuadrillas.php");

    cuadrillas = await respuesta.json();

    opcionesCuadrilla(selectCuadrilla, null);
}


async function cargarRutas() {

    try {

        const respuesta = await apiFetch(BASE_REC + "listarRutas.php");

        if (!respuesta.ok) {
            throw new Error("Error HTTP " + respuesta.status);
        }

        const rutas = await respuesta.json();

        tabla.innerHTML = "";

        if (rutas.length === 0) {
            const fila = tabla.insertRow();
            const celda = fila.insertCell();
            celda.colSpan = 6;
            celda.textContent = "No hay rutas registradas.";
            return;
        }

        rutas.forEach(ruta => {

            const fila = document.createElement("tr");

            for (let i = 0; i < 6; i++) {
                fila.appendChild(document.createElement("td"));
            }

            fila.children[0].textContent = ruta.nombre;
            fila.children[1].textContent = ruta.zona;
            fila.children[2].textContent = ruta.frecuencia || "-";

            // Cambio rápido de cuadrilla desde la tabla
            const select = document.createElement("select");
            opcionesCuadrilla(select, ruta.id_cuadrilla);
            select.addEventListener("change", () => asignarCuadrilla(ruta.id_ruta, select.value));
            fila.children[3].appendChild(select);

            fila.children[4].textContent =
                ruta.cantidad_contenedores + (ruta.contenedores ? " (" + ruta.contenedores + ")" : "");

            const editar = document.createElement("button");
            editar.type = "button";
            editar.className = "btn-editar";
            editar.textContent = "Editar";
            editar.addEventListener("click", () => editarRuta(ruta));

            const borrar = document.createElement("button");
            borrar.type = "button";
            borrar.className = "btn-eliminar";
            borrar.textContent = "Eliminar";
            borrar.addEventListener("click", () => eliminarRuta(ruta.id_ruta));

            fila.children[5].appendChild(editar);
            fila.children[5].appendChild(borrar);

            tabla.appendChild(fila);
        });

    } catch (error) {

        console.error(error);

        tabla.innerHTML = "";
        const fila = tabla.insertRow();
        const celda = fila.insertCell();
        celda.colSpan = 6;
        celda.textContent = "No se pudieron cargar las rutas.";
    }
}


async function asignarCuadrilla(idRuta, idCuadrilla) {

    const resultado = await llamar("asignarCuadrilla.php", {
        id_ruta: idRuta,
        id_cuadrilla: idCuadrilla === "" ? null : parseInt(idCuadrilla)
    });

    if (!resultado.ok) {
        alert(resultado.mensaje);
    }

    cargarRutas();
}


function editarRuta(ruta) {

    rutaEditando = ruta.id_ruta;

    document.getElementById("nombre").value = ruta.nombre;
    document.getElementById("zona").value = ruta.zona;
    document.getElementById("frecuencia").value = ruta.frecuencia || "";
    selectCuadrilla.value = ruta.id_cuadrilla ? String(ruta.id_cuadrilla) : "";

    document.getElementById("btnGuardar").textContent = "Actualizar ruta";
    document.getElementById("btnCancelar").hidden = false;

    window.scrollTo({ top: 0, behavior: "smooth" });
}


function limpiarFormulario() {

    rutaEditando = null;
    formulario.reset();

    document.getElementById("btnGuardar").textContent = "Guardar ruta";
    document.getElementById("btnCancelar").hidden = true;
}


async function eliminarRuta(id) {

    if (!confirm("¿Seguro que querés eliminar esta ruta? Sus contenedores quedarán sin ruta.")) {
        return;
    }

    const resultado = await llamar("eliminarRuta.php", { id_ruta: id });

    alert(resultado.mensaje);

    if (resultado.ok) {
        cargarRutas();
    }
}


formulario.addEventListener("submit", async (e) => {

    e.preventDefault();

    const datos = {
        nombre: document.getElementById("nombre").value,
        zona: document.getElementById("zona").value,
        frecuencia: document.getElementById("frecuencia").value,
        id_cuadrilla: selectCuadrilla.value === "" ? null : parseInt(selectCuadrilla.value)
    };

    let archivo = "crearRuta.php";

    if (rutaEditando) {
        datos.id_ruta = rutaEditando;
        archivo = "actualizarRuta.php";
    }

    try {

        const resultado = await llamar(archivo, datos);

        alert(resultado.mensaje);

        if (resultado.ok) {
            limpiarFormulario();
            cargarRutas();
        }

    } catch (error) {

        console.error(error);
        alert("No se pudo conectar con el servidor.");

    }
});

document.getElementById("btnCancelar").addEventListener("click", limpiarFormulario);

cargarCuadrillas().then(cargarRutas);
