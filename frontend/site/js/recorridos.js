const BASE = "/api/recoleccion/";

const usuarioSesion = obtenerUsuario();
const rolSesion = usuarioSesion ? usuarioSesion.rol : "";
const puedeOperar = ["Administrador", "Operario", "Cuadrilla"].includes(rolSesion);

let recorridos = [];
let seleccionado = null;

const tabla = document.getElementById("tablaRecorridos");
const panel = document.getElementById("panelRecorrido");

async function postJson(archivo, datos) {

    const respuesta = await apiFetch(BASE + archivo, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(datos)
    });

    return respuesta.json();
}

function opcion(select, valor, texto) {
    const op = document.createElement("option");
    op.value = valor;
    op.textContent = texto;
    select.appendChild(op);
}

async function cargarSelects() {

    const [rRutas, rCamiones] = await Promise.all([
        apiFetch(BASE + "listarRutas.php"),
        apiFetch(BASE + "listarCamiones.php")
    ]);

    const rutas = await rRutas.json();
    const camiones = await rCamiones.json();

    const selRuta = document.getElementById("id_ruta");
    const selCamion = document.getElementById("id_camion");

    selRuta.length = 1;
    selCamion.length = 1;

    rutas.forEach(r => opcion(selRuta, r.id_ruta, r.nombre + (r.cuadrilla ? " (" + r.cuadrilla + ")" : "")));

    camiones
        .filter(c => c.estado === "Disponible")
        .forEach(c => opcion(selCamion, c.id_camion, c.matricula + " - " + (c.modelo || "")));
}

function celdaTexto(fila, texto) {
    fila.insertCell().textContent = texto;
}

async function cargarRecorridos() {

    try {

        const respuesta = await apiFetch(BASE + "listarRecorridos.php");

        if (!respuesta.ok) throw new Error("HTTP " + respuesta.status);

        recorridos = await respuesta.json();

        tabla.innerHTML = "";

        if (recorridos.length === 0) {
            const fila = tabla.insertRow();
            const celda = fila.insertCell();
            celda.colSpan = 9;
            celda.textContent = "No hay recorridos registrados.";
        }

        recorridos.forEach(r => {

            const fila = tabla.insertRow();

            celdaTexto(fila, r.id_recorrido);
            celdaTexto(fila, r.ruta);
            celdaTexto(fila, r.camion);
            celdaTexto(fila, r.fecha_inicio);
            celdaTexto(fila, r.fecha_fin || "-");

            const estado = fila.insertCell();
            const badge = document.createElement("span");
            badge.className = "badge " + (r.estado === "En curso" ? "estado-en-curso" : "estado-resuelta");
            badge.textContent = r.estado;
            estado.appendChild(badge);

            celdaTexto(fila, r.volumen_kg !== null ? r.volumen_kg : "-");
            celdaTexto(fila, r.recursos.length);

            const boton = document.createElement("button");
            boton.type = "button";
            boton.className = "btn-editar";
            boton.textContent = r.estado === "En curso" && puedeOperar ? "Gestionar" : "Ver detalle";
            boton.addEventListener("click", () => { seleccionado = r.id_recorrido; dibujarPanel(); });
            fila.insertCell().appendChild(boton);
        });

        if (seleccionado) dibujarPanel();

    } catch (error) {

        console.error(error);
        tabla.innerHTML = "";
        const fila = tabla.insertRow();
        const celda = fila.insertCell();
        celda.colSpan = 9;
        celda.textContent = "No se pudieron cargar los recorridos.";
    }
}

function campo(etiqueta, tipo, id, extra) {

    const grupo = document.createElement("div");
    grupo.className = "input-group";

    const l = document.createElement("label");
    l.textContent = etiqueta;
    l.htmlFor = id;

    const i = document.createElement("input");
    i.type = tipo;
    i.id = id;
    Object.assign(i, extra || {});

    grupo.appendChild(l);
    grupo.appendChild(i);

    return grupo;
}

function dibujarPanel() {

    const r = recorridos.find(x => x.id_recorrido === seleccionado || Number(x.id_recorrido) === Number(seleccionado));

    if (!r) {
        panel.hidden = true;
        return;
    }

    panel.hidden = false;
    panel.innerHTML = "";

    const titulo = document.createElement("h3");
    titulo.textContent = "Recorrido #" + r.id_recorrido + " · " + r.ruta + " · " + r.camion;
    panel.appendChild(titulo);

    const info = document.createElement("p");
    info.textContent = "Iniciado por " + r.usuario + " el " + r.fecha_inicio
        + (r.observaciones ? " · Observaciones: " + r.observaciones : "");
    panel.appendChild(info);

    const sub = document.createElement("strong");
    sub.textContent = "Recursos utilizados";
    panel.appendChild(sub);

    const lista = document.createElement("ul");

    if (r.recursos.length === 0) {
        const li = document.createElement("li");
        li.textContent = "Todavía no se registraron recursos.";
        lista.appendChild(li);
    }

    r.recursos.forEach(rec => {
        const li = document.createElement("li");
        li.textContent = rec.recurso + ": " + rec.cantidad + (rec.unidad ? " " + rec.unidad : "");
        lista.appendChild(li);
    });

    panel.appendChild(lista);

    if (r.estado !== "En curso" || !puedeOperar) {
        return;
    }

    const formRecurso = document.createElement("form");
    formRecurso.className = "form-ruta";
    formRecurso.appendChild(campo("Recurso", "text", "nuevoRecurso", { required: true, maxLength: 100, placeholder: "Combustible" }));
    formRecurso.appendChild(campo("Cantidad", "number", "nuevaCantidad", { required: true, step: "any", min: "0.01" }));
    formRecurso.appendChild(campo("Unidad", "text", "nuevaUnidad", { maxLength: 20, placeholder: "L, u, kg" }));

    const botonRecurso = document.createElement("button");
    botonRecurso.type = "submit";
    botonRecurso.className = "btn-login";
    botonRecurso.textContent = "Agregar recurso";
    formRecurso.appendChild(botonRecurso);

    formRecurso.addEventListener("submit", async (e) => {
        e.preventDefault();

        const res = await postJson("registrarRecurso.php", {
            id_recorrido: r.id_recorrido,
            recurso: document.getElementById("nuevoRecurso").value,
            cantidad: document.getElementById("nuevaCantidad").value,
            unidad: document.getElementById("nuevaUnidad").value
        });

        if (!res.ok) alert(res.mensaje);

        cargarRecorridos();
    });

    panel.appendChild(formRecurso);

    const formFin = document.createElement("form");
    formFin.className = "form-ruta";
    formFin.appendChild(campo("Volumen recolectado (kg)", "number", "volumenFinal", { step: "any", min: "0" }));
    formFin.appendChild(campo("Observaciones", "text", "obsFinal", { maxLength: 250 }));

    const botonFin = document.createElement("button");
    botonFin.type = "submit";
    botonFin.className = "btn-eliminar";
    botonFin.textContent = "Finalizar recorrido";
    formFin.appendChild(botonFin);

    formFin.addEventListener("submit", async (e) => {
        e.preventDefault();

        if (!confirm("¿Finalizar el recorrido? El camión quedará disponible.")) return;

        const res = await postJson("finalizarRecorrido.php", {
            id_recorrido: r.id_recorrido,
            volumen_kg: document.getElementById("volumenFinal").value,
            observaciones: document.getElementById("obsFinal").value
        });

        alert(res.mensaje);

        if (res.ok) {
            seleccionado = null;
            await cargarSelects();
        }

        cargarRecorridos();
    });

    panel.appendChild(formFin);
}

document.getElementById("recorridoForm").addEventListener("submit", async (e) => {

    e.preventDefault();

    const res = await postJson("iniciarRecorrido.php", {
        id_ruta: parseInt(document.getElementById("id_ruta").value),
        id_camion: parseInt(document.getElementById("id_camion").value)
    });

    alert(res.mensaje);

    if (res.ok) {
        e.target.reset();
        seleccionado = res.id_recorrido;
        await cargarSelects();
        cargarRecorridos();
    }
});

if (!puedeOperar) {
    document.getElementById("recorridoForm").hidden = true;
}

cargarSelects().then(cargarRecorridos);
