const BASE_GESTION = "/api/gestion/";

let datosActuales = [];

const tabla = document.getElementById("tablaInforme");


async function cargarZonas() {

    const respuesta = await apiFetch(BASE_GESTION + "listarContenedores.php");
    const contenedores = await respuesta.json();

    const zonas = [...new Set(contenedores.map(c => c.zona).filter(Boolean))].sort();
    const select = document.getElementById("fZona");

    zonas.forEach(z => {
        const op = document.createElement("option");
        op.value = z;
        op.textContent = z;
        select.appendChild(op);
    });
}


async function generar() {

    const params = new URLSearchParams();

    ["fEstado:estado", "fZona:zona", "fDesde:desde", "fHasta:hasta"].forEach(par => {
        const [idCampo, nombre] = par.split(":");
        const valor = document.getElementById(idCampo).value;
        if (valor) params.set(nombre, valor);
    });

    try {

        const respuesta = await apiFetch(BASE_GESTION + "listarIncidencias.php?" + params.toString());

        if (!respuesta.ok) throw new Error("HTTP " + respuesta.status);

        datosActuales = await respuesta.json();

        tabla.innerHTML = "";

        datosActuales.forEach(i => {

            const fila = tabla.insertRow();

            [
                i.id_incidencia, i.fecha, i.contenedor, i.zona || "-", i.tipo,
                i.prioridad, i.estado, i.cuadrilla || "Sin asignar", i.fecha_resolucion || "-"
            ].forEach(valor => {
                fila.insertCell().textContent = valor;
            });
        });

        if (datosActuales.length === 0) {
            const fila = tabla.insertRow();
            const celda = fila.insertCell();
            celda.colSpan = 9;
            celda.textContent = "No hay incidencias con esos filtros.";
        }

        const cerradas = datosActuales.filter(i => i.estado === "Resuelta").length;

        document.getElementById("resumen").textContent =
            datosActuales.length + " incidencias · " +
            (datosActuales.length - cerradas) + " abiertas · " +
            cerradas + " cerradas";

    } catch (error) {

        console.error(error);
        document.getElementById("resumen").textContent = "No se pudo generar el informe.";
    }
}


// Evita "inyección de fórmulas" al abrir el CSV en Excel
function celdaCsv(valor) {

    let texto = String(valor ?? "");

    if (/^[=+\-@\t\r]/.test(texto)) {
        texto = "'" + texto;
    }

    return '"' + texto.replace(/"/g, '""') + '"';
}


function exportarCsv() {

    if (datosActuales.length === 0) {
        alert("Primero generá el informe.");
        return;
    }

    const columnas = [
        ["id_incidencia", "ID"], ["fecha", "Fecha"], ["contenedor", "Contenedor"],
        ["zona", "Zona"], ["tipo", "Tipo"], ["prioridad", "Prioridad"],
        ["estado", "Estado"], ["cuadrilla", "Cuadrilla"], ["reportante", "Reportó"],
        ["fecha_resolucion", "Fecha resolución"], ["descripcion", "Descripción"]
    ];

    const lineas = [columnas.map(c => celdaCsv(c[1])).join(";")];

    datosActuales.forEach(i => {
        lineas.push(columnas.map(c => celdaCsv(i[c[0]])).join(";"));
    });

    // \uFEFF = BOM para que Excel respete los acentos
    const blob = new Blob(["\uFEFF" + lineas.join("\r\n")], { type: "text/csv;charset=utf-8" });
    const url = URL.createObjectURL(blob);

    const enlace = document.createElement("a");
    enlace.href = url;
    enlace.download = "informe_incidencias_" + new Date().toISOString().slice(0, 10) + ".csv";
    document.body.appendChild(enlace);
    enlace.click();
    enlace.remove();

    URL.revokeObjectURL(url);
}


document.getElementById("btnGenerar").addEventListener("click", generar);
document.getElementById("btnCsv").addEventListener("click", exportarCsv);
document.getElementById("btnImprimir").addEventListener("click", () => window.print());

cargarZonas().then(generar);
