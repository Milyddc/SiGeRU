(function () {

    const nav = document.querySelector("header nav");

    if (!nav || typeof obtenerToken !== "function" || !obtenerToken()) {
        return;
    }

    const usuario = obtenerUsuario() || {};
    const rol = usuario.rol || "";

    const TODOS = ["Administrador", "Operario", "Cuadrilla", "Vecino"];
    const PERSONAL = ["Administrador", "Operario", "Cuadrilla"];
    const GESTION = ["Administrador", "Operario"];

    const ESTRUCTURA = [
        { texto: "Panel", enlace: "dashboard.html", roles: PERSONAL },
        {
            texto: "Operación",
            roles: TODOS,
            items: [
                { texto: "Incidencias", enlace: "incidencias.html", roles: TODOS },
                { texto: "Mapa", enlace: "mapa.html", roles: TODOS },
                { texto: "Recorridos", enlace: "recorridos.html", roles: PERSONAL },
                { texto: "Informes", enlace: "informes.html", roles: GESTION }
            ]
        },
        {
            texto: "Gestión",
            roles: GESTION,
            items: [
                { texto: "Contenedores", enlace: "contenedores.html", roles: GESTION },
                { texto: "Rutas", enlace: "rutas.html", roles: GESTION },
                { texto: "Camiones", enlace: "camiones.html", roles: GESTION },
                { texto: "Mantenimiento", enlace: "mantenimiento.html", roles: GESTION },
                { texto: "Centros de acopio", enlace: "centros.html", roles: GESTION }
            ]
        },
        { texto: "Usuarios", enlace: "usuarios.html", roles: ["Administrador"] }
    ];

    const PAGINA_PADRE = {
        "contenedorform.html": "contenedores.html",
        "camionform.html": "camiones.html",
        "centroform.html": "centros.html",
        "usuarioform.html": "usuarios.html"
    };

    const ETIQUETA_VOLVER = {
        "contenedores.html": "Contenedores",
        "camiones.html": "Camiones",
        "centros.html": "Centros de acopio",
        "usuarios.html": "Usuarios"
    };

    const paginaActual = window.location.pathname.split("/").pop();
    const paginaResaltada = PAGINA_PADRE[paginaActual] || paginaActual;

    const estilo = document.createElement("style");
    estilo.textContent = `
        header nav.menu-principal { display: flex; align-items: center; gap: 6px; }
        header nav.menu-principal a,
        header nav.menu-principal button.menu-boton {
            background: none; border: none; cursor: pointer; font: inherit; font-weight: bold;
            color: #e5e5e5; text-decoration: none; padding: 8px 12px; border-radius: 8px;
            transition: background 0.2s, color 0.2s; transform: none;
        }
        header nav.menu-principal a:hover,
        header nav.menu-principal button.menu-boton:hover {
            background: rgba(154, 203, 108, 0.15); color: #9acb6c; transform: none;
        }
        header nav.menu-principal .activo { color: #9acb6c; background: rgba(154, 203, 108, 0.12); }
        .menu-grupo, .menu-acciones { position: relative; display: flex; align-items: center; }
        .menu-acciones { margin-left: 10px; padding-left: 10px; border-left: 1px solid rgba(255,255,255,0.15); gap: 4px; }
        .menu-panel {
            position: absolute; top: calc(100% + 6px); left: 0; min-width: 200px; display: none;
            flex-direction: column; background: #151a13; border: 1px solid #3a4a30; border-radius: 12px;
            padding: 6px; box-shadow: 0 10px 28px rgba(0,0,0,0.6); z-index: 4000;
        }
        .menu-acciones .menu-panel { left: auto; right: 0; }
        .menu-grupo.abierto > .menu-panel, .menu-acciones .abierto > .menu-panel { display: flex; }
        .menu-panel a, .menu-panel button { text-align: left; white-space: nowrap; }
        .menu-panel .menu-info { color: #9a9a9a; font-size: 0.8rem; padding: 6px 12px; }
        .menu-flecha { font-size: 0.7em; margin-left: 4px; opacity: 0.8; }
        .menu-volver { color: #9acb6c !important; }
        .menu-hamburguesa { display: none; font-size: 1.4rem !important; }
        @media (max-width: 960px) {
            .menu-hamburguesa { display: inline-block; margin-left: auto; }
            header nav.menu-principal {
                display: none; position: fixed; top: 58px; left: 0; right: 0; flex-direction: column;
                align-items: stretch; gap: 2px; flex-wrap: nowrap; background: #0f140d; padding: 10px 4%;
                max-height: calc(100vh - 60px); overflow-y: auto; border-bottom: 2px solid rgba(111,163,66,0.6);
            }
            header nav.menu-principal.menu-abierto { display: flex; }
            .menu-grupo, .menu-acciones { flex-direction: column; align-items: stretch; }
            .menu-acciones { margin: 8px 0 0; padding: 8px 0 0; border-left: none; border-top: 1px solid rgba(255,255,255,0.15); }
            .menu-panel { position: static; box-shadow: none; border: none; background: transparent; padding-left: 14px; }
        }
    `;
    document.head.appendChild(estilo);

    nav.innerHTML = "";
    nav.classList.add("menu-principal");

    const volver = PAGINA_PADRE[paginaActual];

    if (volver) {
        const enlaceVolver = document.createElement("a");
        enlaceVolver.className = "menu-volver";
        enlaceVolver.href = volver;
        enlaceVolver.textContent = "← " + ETIQUETA_VOLVER[volver];
        nav.appendChild(enlaceVolver);
    }

    function enlace(item) {
        const a = document.createElement("a");
        a.href = item.enlace;
        a.textContent = item.texto;
        if (item.enlace === paginaResaltada) {
            a.classList.add("activo");
        }
        return a;
    }

    function cerrarTodos(excepto) {
        nav.querySelectorAll(".abierto").forEach(g => {
            if (g !== excepto) {
                g.classList.remove("abierto");
                const b = g.querySelector("button.menu-boton");
                if (b) b.setAttribute("aria-expanded", "false");
            }
        });
    }

    function grupo(texto, contenidoPanel, resaltado, contenedor) {
        const caja = contenedor || document.createElement("div");
        caja.classList.add("menu-grupo");

        const boton = document.createElement("button");
        boton.type = "button";
        boton.className = "menu-boton" + (resaltado ? " activo" : "");
        boton.setAttribute("aria-haspopup", "true");
        boton.setAttribute("aria-expanded", "false");
        boton.textContent = texto;

        const flecha = document.createElement("span");
        flecha.className = "menu-flecha";
        flecha.textContent = "▾";
        boton.appendChild(flecha);

        const panel = document.createElement("div");
        panel.className = "menu-panel";
        contenidoPanel.forEach(elemento => panel.appendChild(elemento));

        boton.addEventListener("click", (e) => {
            e.stopPropagation();
            const abrir = !caja.classList.contains("abierto");
            cerrarTodos(null);
            caja.classList.toggle("abierto", abrir);
            boton.setAttribute("aria-expanded", String(abrir));
        });

        caja.appendChild(boton);
        caja.appendChild(panel);

        return caja;
    }

    ESTRUCTURA.forEach(entrada => {

        if (!entrada.roles.includes(rol)) return;

        if (!entrada.items) {
            nav.appendChild(enlace(entrada));
            return;
        }

        const visibles = entrada.items.filter(i => i.roles.includes(rol));

        if (visibles.length === 0) return;

        const resaltado = visibles.some(i => i.enlace === paginaResaltada);

        nav.appendChild(grupo(entrada.texto, visibles.map(enlace), resaltado));
    });

    const acciones = document.createElement("div");
    acciones.id = "menuAcciones";
    acciones.className = "menu-acciones";

    const nombre = (usuario.nombre_completo || "Usuario").split(" ")[0];

    const info = document.createElement("div");
    info.className = "menu-info";
    info.textContent = (usuario.nombre_completo || "") + " · " + rol;

    const salir = document.createElement("button");
    salir.type = "button";
    salir.className = "menu-boton";
    salir.textContent = "Cerrar sesión";
    salir.addEventListener("click", cerrarSesion);

    const cajaUsuario = grupo("👤 " + nombre, [info, salir], false);
    cajaUsuario.classList.remove("menu-grupo");
    cajaUsuario.style.position = "relative";
    acciones.appendChild(cajaUsuario);

    nav.appendChild(acciones);

    const hamburguesa = document.createElement("button");
    hamburguesa.type = "button";
    hamburguesa.className = "menu-boton menu-hamburguesa";
    hamburguesa.setAttribute("aria-label", "Abrir menú");
    hamburguesa.textContent = "☰";
    hamburguesa.addEventListener("click", (e) => {
        e.stopPropagation();
        nav.classList.toggle("menu-abierto");
    });

    nav.parentNode.insertBefore(hamburguesa, nav);

    document.addEventListener("click", (e) => {
        if (!nav.contains(e.target)) {
            cerrarTodos(null);
            nav.classList.remove("menu-abierto");
        }
    });

})();
