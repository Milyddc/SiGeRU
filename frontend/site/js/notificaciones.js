(function () {

    const nav = document.querySelector("header nav");

    if (!nav || typeof obtenerToken !== "function" || !obtenerToken()) {
        return;
    }

    const BASE = "/api/gestion/";

    const estilo = document.createElement("style");
    estilo.textContent = `
        .campana-wrap { position: relative; display: inline-block; margin-left: 14px; }
        .campana-btn { background: none; border: none; font-size: 1.3rem; cursor: pointer; position: relative; }
        .campana-badge { position: absolute; top: -6px; right: -8px; background: #e53935; color: #fff;
            border-radius: 999px; font-size: 0.7rem; padding: 1px 6px; font-weight: bold; }
        .campana-panel { position: absolute; right: 0; top: 130%; width: 330px; max-height: 420px; overflow-y: auto;
            background: #151a13; border: 1px solid #3a4a30; border-radius: 12px; padding: 10px; z-index: 5000;
            box-shadow: 0 8px 24px rgba(0,0,0,0.6); text-align: left; }
        .campana-panel[hidden] { display: none; }
        .campana-item { padding: 8px 10px; border-radius: 8px; font-size: 0.85rem; color: #ccc; cursor: pointer; }
        .campana-item.no-leida { background: #1f2a1a; color: #fff; font-weight: bold; }
        .campana-item.alerta { border-left: 4px solid #e53935; }
        .campana-item small { display: block; color: #888; font-weight: normal; margin-top: 2px; }
        .campana-todas { width: 100%; margin-bottom: 8px; padding: 6px; background: #2d3a24; color: #fff;
            border: none; border-radius: 8px; cursor: pointer; }
        .toast-alerta { position: fixed; bottom: 24px; right: 24px; background: #b71c1c; color: #fff;
            padding: 14px 18px; border-radius: 12px; z-index: 6000; max-width: 340px;
            box-shadow: 0 6px 18px rgba(0,0,0,0.5); font-size: 0.9rem; }
    `;
    document.head.appendChild(estilo);

    const envoltorio = document.createElement("span");
    envoltorio.className = "campana-wrap";

    const boton = document.createElement("button");
    boton.type = "button";
    boton.className = "campana-btn";
    boton.title = "Notificaciones";
    boton.textContent = "🔔";

    const insignia = document.createElement("span");
    insignia.className = "campana-badge";
    insignia.hidden = true;
    boton.appendChild(insignia);

    const panel = document.createElement("div");
    panel.className = "campana-panel";
    panel.hidden = true;

    envoltorio.appendChild(boton);
    envoltorio.appendChild(panel);
    const destino = document.getElementById("menuAcciones");

    if (destino) {
        destino.prepend(envoltorio);
    } else {
        nav.appendChild(envoltorio);
    }

    let ultimoIdVisto = Number(sessionStorage.getItem("notif_ultimo_id") || 0);
    let primeraVez = sessionStorage.getItem("notif_ultimo_id") === null;

    async function marcar(id) {

        await apiFetch(BASE + "marcarNotificacion.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(id ? { id_notificacion: id } : {})
        });

        cargar();
    }

    function mostrarToast(texto) {

        const toast = document.createElement("div");
        toast.className = "toast-alerta";
        toast.textContent = "⚠️ " + texto;

        document.body.appendChild(toast);

        setTimeout(() => toast.remove(), 6000);
    }

    function dibujar(datos) {

        insignia.textContent = datos.no_leidas;
        insignia.hidden = datos.no_leidas === 0;

        panel.innerHTML = "";

        if (datos.no_leidas > 0) {
            const todas = document.createElement("button");
            todas.type = "button";
            todas.className = "campana-todas";
            todas.textContent = "Marcar todas como leídas";
            todas.addEventListener("click", () => marcar(null));
            panel.appendChild(todas);
        }

        if (datos.notificaciones.length === 0) {
            const vacio = document.createElement("div");
            vacio.className = "campana-item";
            vacio.textContent = "No tenés notificaciones.";
            panel.appendChild(vacio);
        }

        datos.notificaciones.forEach(n => {

            const item = document.createElement("div");
            item.className = "campana-item" + (n.leida ? "" : " no-leida") + (n.tipo === "alerta" ? " alerta" : "");

            item.textContent = n.mensaje;

            const fecha = document.createElement("small");
            fecha.textContent = n.fecha;
            item.appendChild(fecha);

            item.addEventListener("click", () => {
                if (!n.leida) marcar(n.id_notificacion);
            });

            panel.appendChild(item);
        });

        const maxId = datos.notificaciones.reduce((m, n) => Math.max(m, n.id_notificacion), 0);

        if (!primeraVez) {
            datos.notificaciones
                .filter(n => n.id_notificacion > ultimoIdVisto && n.tipo === "alerta" && !n.leida)
                .slice(0, 2)
                .forEach(n => mostrarToast(n.mensaje));
        }

        primeraVez = false;
        ultimoIdVisto = Math.max(ultimoIdVisto, maxId);
        sessionStorage.setItem("notif_ultimo_id", String(ultimoIdVisto));
    }

    async function cargar() {

        try {

            const respuesta = await apiFetch(BASE + "listarNotificaciones.php");

            if (!respuesta.ok) return;

            dibujar(await respuesta.json());

        } catch (error) {
            console.error("Notificaciones:", error);
        }
    }

    boton.addEventListener("click", (e) => {
        e.stopPropagation();
        panel.hidden = !panel.hidden;
    });

    document.addEventListener("click", (e) => {
        if (!envoltorio.contains(e.target)) panel.hidden = true;
    });

    cargar();
    setInterval(cargar, 20000);

})();
