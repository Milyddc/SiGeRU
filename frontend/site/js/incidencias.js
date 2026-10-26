const BASE_GESTION = "/api/gestion/";
const BASE_RECOLECCION = "/api/recoleccion/";

const usuarioSesion = obtenerUsuario();
const rolSesion = usuarioSesion ? usuarioSesion.rol : "";

const puedeCambiarEstado = ["Administrador", "Operario", "Cuadrilla"].includes(rolSesion);
const puedeAsignar = ["Administrador", "Operario"].includes(rolSesion);

const ESTADOS = ["Abierta", "En curso", "Resuelta"];

let cuadrillas = [];

const tabla = document.getElementById("tablaIncidencias");
const panelHistorial = document.getElementById("panelHistorial");


async function postJson(url, datos) {

    const respuesta = await apiFetch(url, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(datos)
    });

    return respuesta.json();
}


function clase(prefijo, valor) {
    return prefijo + "-" + String(valor).toLowerCase().replace(/\s+/g, "-");
}


function badge(prefijo, valor) {
    const s = document.createElement("span");
    s.className = "badge " + clase(prefijo, valor);
    s.textContent = valor;
    return s;
}


async function cargarContenedores() {

    const respuesta = await apiFetch(BASE_GESTION + "listarContenedores.php");
    const contenedores = await respuesta.json();

    const select = document.getElementById("id_contenedor");
    const zonas = new Set();

    contenedores.forEach(c => {

        const op = document.createElement("option");
        op.value = c.id_contenedor;
        op.textContent = c.codigo + " - " + c.ubicacion;
        select.appendChild(op);

        if (c.zona) zonas.add(c.zona);
    });

    const selectZona = document.getElementById("fZona");

    [...zonas].sort().forEach(z => {
        const op = document.createElement("option");
        op.value = z;
        op.textContent = z;
        selectZona.appendChild(op);
    });
}


async function cargarCuadrillas() {

    if (!puedeAsignar) return;

    const respuesta = await apiFetch(BASE_RECOLECCION + "listarCuadrillas.php");
    cuadrillas = await respuesta.json();
}


function selectEstado(inc) {

    const select = document.createElement("select");

    ESTADOS.forEach(e => {
        const op = document.createElement("option");
        op.value = e;
        op.textContent = e;
        select.appendChild(op);
    });

    select.value = inc.estado;

    select.addEventListener("change", async () => {

        const comentario = prompt("Comentario (opcional):") || "";

        const r = await postJson(BASE_GESTION + "cambiarEstadoIncidencia.php", {
            id_incidencia: inc.id_incidencia,
            estado: select.value,
            comentario: comentario
        });

        if (!r.ok) alert(r.mensaje);

        cargarIncidencias();
    });

    return select;
}


function selectCuadrilla(inc) {

    const select = document.createElement("select");

    const vacia = document.createElement("option");
    vacia.value = "";
    vacia.textContent = "Asignar cuadrilla...";
    select.appendChild(vacia);

    cuadrillas.forEach(c => {
        const op = document.createElement("option");
        op.value = c.id_cuadrilla;
        op.textContent = c.nombre;
        select.appendChild(op);
    });

    select.value = inc.id_cuadrilla ? String(inc.id_cuadrilla) : "";

    select.addEventListener("change", async () => {

        if (select.value === "") return;

        const r = await postJson(BASE_GESTION + "asignarCuadrillaIncidencia.php", {
            id_incidencia: inc.id_incidencia,
            id_cuadrilla: parseInt(select.value)
        });

        if (!r.ok) alert(r.mensaje);

        cargarIncidencias();
    });

    return select;
}


async function verHistorial(id) {

    const respuesta = await apiFetch(BASE_GESTION + "historialIncidencia.php?id_incidencia=" + id);
    const r = await respuesta.json();

    panelHistorial.innerHTML = "";
    panelHistorial.hidden = false;

    const titulo = document.createElement("h3");
    titulo.textContent = "Historial de la incidencia #" + id;
    panelHistorial.appendChild(titulo);

    if (!r.ok) {
        const p = document.createElement("p");
        p.textContent = r.mensaje;
        panelHistorial.appendChild(p);
        return;
    }

    const lista = document.createElement("ul");

    r.historial.forEach(h => {
        const li = document.createElement("li");
        const cambio = h.estado_anterior
            ? h.estado_anterior + " → " + h.estado_nuevo
            : h.estado_nuevo;
        li.textContent = h.fecha + " · " + cambio + " · " + h.usuario
            + (h.comentario ? " · " + h.comentario : "");
        lista.appendChild(li);
    });

    panelHistorial.appendChild(lista);
    panelHistorial.scrollIntoView({ behavior: "smooth" });
}


async function cargarIncidencias() {

    const params = new URLSearchParams();
    const estado = document.getElementById("fEstado").value;
    const zona = document.getElementById("fZona").value;

    if (estado) params.set("estado", estado);
    if (zona) params.set("zona", zona);

    try {

        const respuesta = await apiFetch(BASE_GESTION + "listarIncidencias.php?" + params.toString());

        if (!respuesta.ok) throw new Error("HTTP " + respuesta.status);

        const incidencias = await respuesta.json();

        tabla.innerHTML = "";

        if (incidencias.length === 0) {
            const fila = tabla.insertRow();
            const celda = fila.insertCell();
            celda.colSpan = 9;
            celda.textContent = "No hay incidencias para mostrar.";
            return;
        }

        incidencias.forEach(inc => {

            const fila = tabla.insertRow();

            fila.insertCell().textContent = inc.id_incidencia;
            fila.insertCell().textContent = inc.fecha;
            fila.insertCell().textContent = inc.contenedor;
            fila.insertCell().textContent = inc.tipo;
            fila.insertCell().appendChild(badge("prioridad", inc.prioridad));
            fila.insertCell().appendChild(badge("estado", inc.estado));
            fila.insertCell().textContent = inc.cuadrilla || "Sin asignar";
            fila.insertCell().textContent = inc.reportante;

            const acciones = fila.insertCell();

            const historial = document.createElement("button");
            historial.type = "button";
            historial.className = "btn-editar";
            historial.textContent = "Historial";
            historial.addEventListener("click", () => verHistorial(inc.id_incidencia));
            acciones.appendChild(historial);

            if (puedeCambiarEstado) acciones.appendChild(selectEstado(inc));
            if (puedeAsignar) acciones.appendChild(selectCuadrilla(inc));
        });

    } catch (error) {

        console.error(error);
        tabla.innerHTML = "";
        const fila = tabla.insertRow();
        const celda = fila.insertCell();
        celda.colSpan = 9;
        celda.textContent = "No se pudieron cargar las incidencias.";
    }
}


document.getElementById("incidenciaForm").addEventListener("submit", async (e) => {

    e.preventDefault();

    const r = await postJson(BASE_GESTION + "crearIncidencia.php", {
        id_contenedor: parseInt(document.getElementById("id_contenedor").value),
        tipo: document.getElementById("tipo").value,
        prioridad: document.getElementById("prioridad").value,
        descripcion: document.getElementById("descripcion").value
    });

    alert(r.mensaje);

    if (r.ok) {
        e.target.reset();
        cargarIncidencias();
    }
});

document.getElementById("fEstado").addEventListener("change", cargarIncidencias);
document.getElementById("fZona").addEventListener("change", cargarIncidencias);

// Un vecino no elige la prioridad
if (rolSesion === "Vecino") {
    document.getElementById("grupoPrioridad").hidden = true;
}

Promise.all([cargarContenedores(), cargarCuadrillas()]).then(cargarIncidencias);
