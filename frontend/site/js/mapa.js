if (typeof L === "undefined") {
    document.getElementById("resumen").textContent =
        "No se pudo cargar la librería del mapa (Leaflet). Revisá tu conexión a internet o si un bloqueador la está bloqueando.";
    throw new Error("Leaflet no está disponible");
}

const LIMITES_MONTEVIDEO = L.latLngBounds([-34.99, -56.46], [-34.70, -55.99]);

const mapa = L.map("mapa", {
    maxBounds: LIMITES_MONTEVIDEO,
    maxBoundsViscosity: 1.0,
    minZoom: 11,
    maxZoom: 18
}).setView([-34.88, -56.18], 12);

const fondo = L.tileLayer("https://tile.openstreetmap.org/{z}/{x}/{y}.png", {
    maxZoom: 18,
    bounds: LIMITES_MONTEVIDEO,
    attribution: "&copy; OpenStreetMap",
    referrerPolicy: "strict-origin-when-cross-origin"
}).addTo(mapa);

fondo.once("tileerror", () => {
    const aviso = document.getElementById("avisoMapa");
    if (aviso) aviso.hidden = false;
    console.warn("No se pudieron cargar los mapas de fondo de OpenStreetMap");
});

const capas = {
    contenedores: L.layerGroup().addTo(mapa),
    incidencias: L.layerGroup().addTo(mapa),
    centros: L.layerGroup().addTo(mapa),
    rutas: L.layerGroup().addTo(mapa)
};

const COLORES_RUTA = ["#2196f3", "#ff9800", "#9c27b0", "#00bcd4", "#e91e63", "#8bc34a"];

let primeraCarga = true;

function colorContenedor(estado) {

    const e = (estado || "").toLowerCase();

    if (e === "disponible") return "#4caf50";
    if (e === "lleno" || e === "desbordado") return "#f44336";

    return "#9e9e9e";
}

function crearPopup(titulo, lineas) {

    const caja = document.createElement("div");
    caja.className = "popup-mapa";

    const t = document.createElement("strong");
    t.textContent = titulo;
    caja.appendChild(t);

    lineas.forEach(texto => {
        const l = document.createElement("div");
        l.textContent = texto;
        caja.appendChild(l);
    });

    return caja;
}

function iconoEmoji(emoji) {
    return L.divIcon({
        className: "icono-mapa",
        html: emoji,
        iconSize: [24, 24],
        iconAnchor: [12, 12]
    });
}

function dibujar(datos) {

    Object.values(capas).forEach(capa => capa.clearLayers());

    const puntos = [];

    datos.contenedores.forEach(c => {

        const marcador = L.circleMarker([c.latitud, c.longitud], {
            radius: 9,
            color: "#111",
            weight: 2,
            fillColor: colorContenedor(c.estado),
            fillOpacity: 0.95
        });

        marcador.bindPopup(crearPopup(c.codigo, [
            "Ubicación: " + c.ubicacion,
            "Estado: " + c.estado,
            "Tipo: " + (c.tipo_residuo || "Sin especificar"),
            "Zona: " + (c.zona || "-"),
            "Ruta: " + (c.ruta || "Sin ruta"),
            "Incidencias abiertas: " + Number(c.incidencias_abiertas)
        ]));

        marcador.addTo(capas.contenedores);
        puntos.push([c.latitud, c.longitud]);
    });

    datos.incidencias.forEach(i => {

        const marcador = L.marker([i.latitud, i.longitud], {
            icon: iconoEmoji("⚠️"),
            zIndexOffset: 1000
        });

        marcador.bindPopup(crearPopup("Incidencia #" + i.id_incidencia + " - " + i.tipo, [
            "Contenedor: " + i.contenedor,
            "Estado: " + i.estado,
            "Prioridad: " + i.prioridad,
            "Cuadrilla: " + (i.cuadrilla || "Sin asignar"),
            "Fecha: " + i.fecha,
            i.descripcion
        ]));

        marcador.addTo(capas.incidencias);
    });

    datos.centros.forEach(c => {

        const marcador = L.marker([c.latitud, c.longitud], { icon: iconoEmoji("🏭") });

        marcador.bindPopup(crearPopup(c.nombre, [
            "Dirección: " + c.direccion,
            "Capacidad: " + c.capacidad
        ]));

        marcador.addTo(capas.centros);
        puntos.push([c.latitud, c.longitud]);
    });

    datos.rutas.forEach((r, indice) => {

        if (r.puntos.length < 2) {
            return;
        }

        const linea = L.polyline(r.puntos, {
            color: COLORES_RUTA[indice % COLORES_RUTA.length],
            weight: 4,
            opacity: 0.8,
            dashArray: "8 8"
        });

        linea.bindPopup(crearPopup(r.nombre, [
            "Zona: " + r.zona,
            "Cuadrilla: " + (r.cuadrilla || "Sin asignar"),
            "Contenedores: " + r.puntos.length
        ]));

        linea.addTo(capas.rutas);
    });

    document.getElementById("resumen").textContent =
        datos.contenedores.length + " contenedores · " +
        datos.incidencias.length + " incidencias abiertas · " +
        datos.centros.length + " centros";

    if (primeraCarga && puntos.length > 0) {
        mapa.fitBounds(puntos, { padding: [40, 40], maxZoom: 16 });
        primeraCarga = false;
    }
}

async function cargarMapa() {

    try {

        const respuesta = await apiFetch("/api/gestion/listarMapa.php");

        if (!respuesta.ok) {
            throw new Error("Error HTTP " + respuesta.status);
        }

        dibujar(await respuesta.json());

    } catch (error) {

        console.error(error);
        document.getElementById("resumen").textContent = "No se pudieron cargar los datos del mapa";

    }
}

[
    ["chkContenedores", "contenedores"],
    ["chkIncidencias", "incidencias"],
    ["chkCentros", "centros"],
    ["chkRutas", "rutas"]
].forEach(([idCheck, nombreCapa]) => {

    document.getElementById(idCheck).addEventListener("change", (e) => {

        if (e.target.checked) {
            capas[nombreCapa].addTo(mapa);
        } else {
            mapa.removeLayer(capas[nombreCapa]);
        }

    });

});

cargarMapa();

setInterval(cargarMapa, 30000);
