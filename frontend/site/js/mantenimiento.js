const BASE = "/api/recoleccion/";

const tabla = document.getElementById("tablaMantenimientos");

async function postJson(archivo, datos) {

    const respuesta = await apiFetch(BASE + archivo, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(datos)
    });

    return respuesta.json();
}

async function cargarCamiones() {

    const respuesta = await apiFetch(BASE + "listarCamiones.php");
    const camiones = await respuesta.json();

    const select = document.getElementById("id_camion");

    camiones.forEach(c => {
        const op = document.createElement("option");
        op.value = c.id_camion;
        op.textContent = c.matricula + " (" + c.estado + ")";
        select.appendChild(op);
    });
}

async function cargarMantenimientos() {

    try {

        const respuesta = await apiFetch(BASE + "listarMantenimientos.php");

        if (!respuesta.ok) throw new Error("HTTP " + respuesta.status);

        const lista = await respuesta.json();

        tabla.innerHTML = "";

        if (lista.length === 0) {
            const fila = tabla.insertRow();
            const celda = fila.insertCell();
            celda.colSpan = 7;
            celda.textContent = "No hay mantenimientos registrados.";
            return;
        }

        lista.forEach(m => {

            const fila = tabla.insertRow();

            fila.insertCell().textContent = m.matricula;
            fila.insertCell().textContent = m.tipo;
            fila.insertCell().textContent = m.descripcion;
            fila.insertCell().textContent = m.fecha_programada;
            fila.insertCell().textContent = m.fecha_realizada || "-";

            const estado = fila.insertCell();
            const badge = document.createElement("span");
            badge.className = "badge " + (m.estado === "Programado" ? "estado-en-curso" : "estado-resuelta");
            badge.textContent = m.estado;
            estado.appendChild(badge);

            const acciones = fila.insertCell();

            if (m.estado === "Programado") {
                const boton = document.createElement("button");
                boton.type = "button";
                boton.className = "btn-editar";
                boton.textContent = "Marcar realizado";
                boton.addEventListener("click", async () => {
                    const r = await postJson("completarMantenimiento.php", { id_mantenimiento: m.id_mantenimiento });
                    if (!r.ok) alert(r.mensaje);
                    cargarMantenimientos();
                });
                acciones.appendChild(boton);
            }
        });

    } catch (error) {

        console.error(error);
        tabla.innerHTML = "";
        const fila = tabla.insertRow();
        const celda = fila.insertCell();
        celda.colSpan = 7;
        celda.textContent = "No se pudieron cargar los mantenimientos.";
    }
}

document.getElementById("mantenimientoForm").addEventListener("submit", async (e) => {

    e.preventDefault();

    const r = await postJson("programarMantenimiento.php", {
        id_camion: parseInt(document.getElementById("id_camion").value),
        tipo: document.getElementById("tipo").value,
        fecha_programada: document.getElementById("fecha_programada").value,
        descripcion: document.getElementById("descripcion").value
    });

    alert(r.mensaje);

    if (r.ok) {
        e.target.reset();
        cargarMantenimientos();
    }
});

document.getElementById("fecha_programada").value = new Date().toISOString().slice(0, 10);

cargarCamiones().then(cargarMantenimientos);
