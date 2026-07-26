const API_INCIDENCIAS = "../backend/api-incidencias/index.php";

document.addEventListener("DOMContentLoaded", function () {
    
    // 1. VALIDACIÓN DE SEGURIDAD
    const usuarioLogueado = JSON.parse(sessionStorage.getItem("usuarioActivo"));
    if (!usuarioLogueado || (usuarioLogueado.rol !== 'Administrador' && usuarioLogueado.rol !== 'Cuadrilla')) {
        window.location.href = "login.html";
        return;
    }

    // Captura de contenedores de las distintas páginas (pueden ser null si no estamos en esa hoja)
    const vistaCards = document.getElementById("vistaCards");
    const vistaUsuariosGral = document.getElementById("vistaUsuariosGral");
    const vistaCamionesGral = document.getElementById("vistaCamionesGral");
    const vistaContenedoresGral = document.getElementById("vistaContenedoresGral"); // Soporte para contenedores.html
    const vistaIncidenciasAdmin = document.getElementById("vistaIncidenciasAdmin");
    const tituloPanel = document.getElementById("tituloPanel");
    
    // Captura de botones del menú lateral
    const btnDashboard = document.getElementById("btnDashboard");
    const btnUsuariosAdmin = document.getElementById("btnUsuariosAdmin");
    const btnCamionesAdmin = document.getElementById("btnCamionesAdmin");
    const btnContenedoresAdmin = document.getElementById("btnContenedoresAdmin");
    const btnIncidenciasAdmin = document.getElementById("btnIncidenciasAdmin");

    // 2. INTERRUPTOR: CLIC EN "INCIDENCIAS"
    if (btnIncidenciasAdmin) {
        btnIncidenciasAdmin.addEventListener("click", function (e) {
            e.preventDefault();
            
            // Gestionar clases activas visuales en el menú de navegación lateral
            if (btnDashboard) btnDashboard.classList.remove("active");
            if (btnUsuariosAdmin) btnUsuariosAdmin.classList.remove("active");
            if (btnCamionesAdmin) btnCamionesAdmin.classList.remove("active");
            if (btnContenedoresAdmin) btnContenedoresAdmin.classList.remove("active");
            btnIncidenciasAdmin.classList.add("active");
            
            // Ocultar de forma segura la vista activa de la página en la que estemos parados
            if (vistaCards) vistaCards.style.display = "none";
            if (vistaUsuariosGral) vistaUsuariosGral.style.display = "none";
            if (vistaCamionesGral) vistaCamionesGral.style.display = "none";
            if (vistaContenedoresGral) vistaContenedoresGral.style.display = "none";
            
            // Mostrar el contenedor Bento de incidencias municipales
            if (vistaIncidenciasAdmin) vistaIncidenciasAdmin.style.display = "block";
            if (tituloPanel) tituloPanel.textContent = "Gestión de Incidencias Municipales";
            
            // Invocar la carga asíncrona de datos desde el backend
            cargarIncidenciasGlobales();
        });
    }

    // 3. INTERRUPTOR: CLIC EN "DASHBOARD"
    if (btnDashboard) {
        btnDashboard.addEventListener("click", function (e) {
            if (vistaIncidenciasAdmin && vistaIncidenciasAdmin.style.display === "block") {
                // Si estamos en otra página física, el menú debe permitir la redirección normal
                if (!vistaCards) {
                    return; 
                }

                // Si estamos en el dashboard original, alternamos las vistas de forma dinámica en la misma hoja
                e.preventDefault();
                btnIncidenciasAdmin.classList.remove("active");
                btnDashboard.classList.add("active");
                vistaIncidenciasAdmin.style.display = "none";
                vistaCards.style.display = "block";
                if (tituloPanel) tituloPanel.textContent = "Panel de Control";
            }
        });
    }

    // 4. CARGAR REPORTES CON SELECTORES DE ESTADO INTERACTIVOS
    async function cargarIncidenciasGlobales() {
        try {
            const respuesta = await fetch(`${API_INCIDENCIAS}?accion=listar`);
            const data = await respuesta.json();

            const tablaCuerpo = document.getElementById("tablaAdminIncidencias");
            if (!tablaCuerpo) return;

            tablaCuerpo.innerHTML = "";

            if (data.exito && data.incidencias.length > 0) {
                data.incidencias.forEach(incidencia => {
                    const comentarioTexto = incidencia.descripcion ? incidencia.descripcion : `<span style="color:var(--text-muted);">-</span>`;

                    const fila = `
                        <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                            <td style="padding: 12px; white-space: nowrap; color: white;">${incidencia.fecha}</td>
                            <td style="padding: 12px; font-weight: 600; color: white;">${incidencia.tipo_incidencia}</td>
                            <td style="padding: 12px; color: white;">${incidencia.direccion}</td>
                            <td style="padding: 12px; font-size: 14px; max-width: 250px; word-wrap: break-word; color: #cbd5e1;">"${comentarioTexto}"</td>
                            <td style="padding: 12px;">
                                <select class="select-estado" data-id="${incidencia.id}" style="background: rgba(255,255,255,0.1); color: white; border: 1px solid rgba(255,255,255,0.2); padding: 6px; border-radius: 6px; cursor: pointer;">
                                    <option value="Abierta" ${incidencia.estado === 'Abierta' ? 'selected' : ''} style="color:#000; background:#fff;">⚠️ Abierta</option>
                                    <option value="En Proceso" ${incidencia.estado === 'En Proceso' ? 'selected' : ''} style="color:#000; background:#fff;">🔧 En Proceso</option>
                                    <option value="Solucionada" ${incidencia.estado === 'Solucionada' ? 'selected' : ''} style="color:#000; background:#fff;">✅ Solucionada</option>
                                </select>
                            </td>
                        </tr>
                    `;
                    tablaCuerpo.innerHTML += fila;
                });

                // Escuchar los selectores dinámicos generados para mutar estados en caliente
                document.querySelectorAll(".select-estado").forEach(select => {
                    select.addEventListener("change", async function () {
                        const idIncidencia = this.getAttribute("data-id");
                        const nuevoEstado = this.value;
                        await actualizarEstadoIncidencia(idIncidencia, nuevoEstado);
                    });
                });

            } else {
                tablaCuerpo.innerHTML = `<tr><td colspan="5" style="text-align: center; padding: 35px; color: var(--text-muted);">No hay reportes ciudadanos registrados actualmente.</td></tr>`;
            }
        } catch (error) {
            console.error("Error al cargar incidencias:", error);
        }
    }

    // 5. ENVIAR CAMBIO DE ESTADO EN TIEMPO REAL AL BACKEND
    async function actualizarEstadoIncidencia(id, estado) {
        try {
            const respuesta = await fetch(`${API_INCIDENCIAS}?accion=actualizarEstado`, {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ id: id, estado: estado })
            });
            const data = await respuesta.json();
            alert(data.mensaje);
        } catch (error) {
            console.error("Error al cambiar estado:", error);
        }
    }

    // 6. CONTROL DEL BOTÓN CERRAR SESIÓN MUNICIPAL
    const btnCerrarSesion = document.getElementById("btnCerrarSesion");
    if (btnCerrarSesion) {
        btnCerrarSesion.addEventListener("click", function () {
            sessionStorage.removeItem("usuarioActivo");
            window.location.href = "index.html";
        });
    }
});
