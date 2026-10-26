// Panel de control: KPIs y gráficos (Chart.js) a partir de estadisticas.php

(async function () {

    const seccion = document.getElementById("seccionEstadisticas");

    if (!seccion || typeof Chart === "undefined") {
        return;
    }

    let datos;

    try {

        const respuesta = await apiFetch("/api/gestion/estadisticas.php");

        // Solo Administrador y Operario ven las estadísticas
        if (respuesta.status === 403) {
            seccion.hidden = true;
            return;
        }

        if (!respuesta.ok) throw new Error("HTTP " + respuesta.status);

        datos = await respuesta.json();

    } catch (error) {

        console.error(error);
        document.getElementById("kpiTiempo").textContent = "-";
        return;
    }

    Chart.defaults.color = "#ddd";
    Chart.defaults.borderColor = "rgba(255,255,255,0.12)";

    const PALETA = ["#9acb6c", "#ffd54f", "#ff8a65", "#4fc3f7", "#ba68c8", "#e57373", "#a1887f"];

    const cantidad = (estado) => {
        const fila = datos.por_estado.find(f => f.estado === estado);
        return fila ? Number(fila.cantidad) : 0;
    };

    document.getElementById("kpiAbiertas").textContent = cantidad("Abierta");
    document.getElementById("kpiEnCurso").textContent = cantidad("En curso");
    document.getElementById("kpiResueltas").textContent = cantidad("Resuelta");
    document.getElementById("kpiTiempo").textContent =
        datos.tiempo_promedio_horas === null ? "-" : datos.tiempo_promedio_horas + " h";

    function grafico(idLienzo, tipo, filas, campoEtiqueta, campoValor, titulo) {

        const lienzo = document.getElementById(idLienzo);

        if (!filas || filas.length === 0) {
            const vacio = document.createElement("div");
            vacio.className = "vacio";
            vacio.textContent = "Sin datos para mostrar";
            lienzo.parentNode.replaceChild(vacio, lienzo);
            return;
        }

        new Chart(lienzo, {
            type: tipo,
            data: {
                labels: filas.map(f => f[campoEtiqueta]),
                datasets: [{
                    label: titulo,
                    data: filas.map(f => Number(f[campoValor])),
                    backgroundColor: tipo === "line" ? "rgba(154,203,108,0.25)" : PALETA,
                    borderColor: "#9acb6c",
                    fill: tipo === "line",
                    tension: 0.3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: tipo === "doughnut" } },
                scales: tipo === "doughnut" ? {} : {
                    y: { beginAtZero: true, ticks: { precision: 0 } }
                }
            }
        });
    }

    grafico("graficoTop", "bar", datos.top_desbordados, "codigo", "cantidad", "Desbordes del mes");
    grafico("graficoZona", "bar", datos.por_zona, "zona", "cantidad", "Incidencias");
    grafico("graficoEstado", "doughnut", datos.por_estado, "estado", "cantidad", "Incidencias");
    grafico("graficoDia", "line", datos.por_dia, "dia", "cantidad", "Incidencias por día");
    grafico("graficoContenedores", "doughnut", datos.contenedores_por_estado, "estado", "cantidad", "Contenedores");
    grafico("graficoVolumen", "bar", datos.volumen_simulado, "zona", "volumen", "Volumen estimado (simulado)");

})();
