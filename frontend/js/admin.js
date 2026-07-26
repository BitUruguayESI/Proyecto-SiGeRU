const API_INCIDENCIAS = "../backend/api-incidencias/index.php";
const API_RESUMEN_USUARIOS = "../backend/api-usuarios/index.php";
const API_RESUMEN_CONTENEDORES = "../backend/api-gestion/index.php";
const API_RESUMEN_CAMIONES = "../backend/api-recoleccion/index.php";

document.addEventListener("DOMContentLoaded", function () {
    
    // 1. VALIDACIÓN DE SEGURIDAD
    const usuarioLogueado = JSON.parse(sessionStorage.getItem("usuarioActivo"));
    if (!usuarioLogueado || (usuarioLogueado.rol !== 'Administrador' && usuarioLogueado.rol !== 'Cuadrilla')) {
        window.location.href = "login.html";
        return;
    }

    // El dashboard siempre usa los datos actuales de las mismas APIs de gestión.
    cargarResumenDashboard();
    window.addEventListener("focus", cargarResumenDashboard);
    document.addEventListener("visibilitychange", function () {
        if (!document.hidden) cargarResumenDashboard();
    });

    // Captura de contenedores de las distintas páginas (pueden ser null si no estamos en esa hoja)
    const vistaCards = document.getElementById("vistaCards");
    const vistaUsuariosGral = document.getElementById("vistaUsuariosGral");
    const vistaCamionesGral = document.getElementById("vistaCamionesGral");
    const vistaContenedoresGral = document.getElementById("vistaContenedoresGral"); // Soporte para contenedores.html
    const vistaIncidenciasAdmin = document.getElementById("vistaIncidenciasAdmin");
    const vistaReportesEstadisticas = document.getElementById("vistaReportesEstadisticas");
    const tituloPanel = document.getElementById("tituloPanel");
    
    // Captura de botones del menú lateral
    const btnDashboard = document.getElementById("btnDashboard");
    const btnUsuariosAdmin = document.getElementById("btnUsuariosAdmin");
    const btnCamionesAdmin = document.getElementById("btnCamionesAdmin");
    const btnContenedoresAdmin = document.getElementById("btnContenedoresAdmin");
    const btnIncidenciasAdmin = document.getElementById("btnIncidenciasAdmin");
    const btnReportes = document.getElementById("btnReportes");
    const btnVerTodasIncidencias = document.getElementById("btnVerTodasIncidencias");

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
            if (vistaReportesEstadisticas) vistaReportesEstadisticas.style.display = "none";
            
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
            // Si estamos en otra página física, el menú conserva su redirección normal.
            if (!vistaCards) return;
            e.preventDefault();
            mostrarVistaPrincipal();
        });
    }

    if (btnReportes) {
        btnReportes.addEventListener("click", function (e) {
            e.preventDefault();
            mostrarVistaReportes();
        });
    }

    if (btnVerTodasIncidencias) {
        btnVerTodasIncidencias.addEventListener("click", function () {
            if (btnIncidenciasAdmin) btnIncidenciasAdmin.click();
        });
    }

    if (new URLSearchParams(window.location.search).get("vista") === "reportes") {
        mostrarVistaReportes();
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
                            <td style="padding: 12px;">
                                <button type="button" class="btn-eliminar-incidencia" data-id="${incidencia.id}" style="background: #dc2626; color: white; border: 0; border-radius: 6px; padding: 7px 10px; cursor: pointer;">🗑 Eliminar</button>
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

                document.querySelectorAll(".btn-eliminar-incidencia").forEach(boton => {
                    boton.addEventListener("click", function () {
                        eliminarIncidencia(this.dataset.id);
                    });
                });

            } else {
                tablaCuerpo.innerHTML = `<tr><td colspan="6" style="text-align: center; padding: 35px; color: var(--text-muted);">No hay reportes ciudadanos registrados actualmente.</td></tr>`;
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
            if (data.exito) await cargarResumenDashboard();
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

    async function cargarResumenDashboard() {
        const destinos = [
            { url: `${API_RESUMEN_USUARIOS}?accion=listar`, propiedad: "usuarios", elemento: "totalUsuarios" },
            { url: `${API_RESUMEN_CONTENEDORES}?accion=listar`, propiedad: "contenedores", elemento: "totalContenedores" },
            { url: `${API_RESUMEN_CAMIONES}?accion=listar`, propiedad: "camiones", elemento: "totalCamiones" },
            { url: `${API_INCIDENCIAS}?accion=listar`, propiedad: "incidencias", elemento: "totalIncidencias" }
        ];

        // En páginas distintas de dashboard no existen estas tarjetas.
        if (!document.getElementById("totalUsuarios")) return;

        const estado = document.getElementById("estadoResumen");
        try {
            const resultados = await Promise.all(destinos.map(async ({ url, propiedad, elemento }) => {
                const respuesta = await fetch(url, { cache: "no-store" });
                if (!respuesta.ok) throw new Error(`HTTP ${respuesta.status}`);
                const data = await respuesta.json();
                if (!data.exito || !Array.isArray(data[propiedad])) throw new Error("Respuesta inválida de la API");
                return { elemento, total: data[propiedad].length, datos: data[propiedad] };
            }));

            resultados.forEach(({ elemento, total }) => {
                document.getElementById(elemento).textContent = total;
            });
            const resumenIncidencias = resultados.find(({ elemento }) => elemento === "totalIncidencias");
            if (resumenIncidencias) actualizarPanelIncidenciasDashboard(resumenIncidencias.datos);
            if (estado) estado.textContent = `Información actualizada: ${new Date().toLocaleTimeString()}`;
        } catch (error) {
            console.error("Error al actualizar el resumen del dashboard:", error);
            if (estado) estado.textContent = "No se pudo actualizar el resumen. Verificá la conexión con XAMPP.";
        }
    }

    async function eliminarIncidencia(id) {
        if (!confirm("¿Seguro que querés eliminar esta incidencia? Esta acción no se puede deshacer.")) return;

        try {
            const respuesta = await fetch(`${API_INCIDENCIAS}?accion=eliminar&id=${encodeURIComponent(id)}`, {
                method: "DELETE"
            });
            const data = await respuesta.json();
            alert(data.mensaje);

            if (data.exito) {
                await cargarIncidenciasGlobales();
                await cargarResumenDashboard();
            }
        } catch (error) {
            console.error("Error al eliminar incidencia:", error);
            alert("No se pudo eliminar la incidencia.");
        }
    }

    function mostrarVistaPrincipal() {
        if (vistaCards) vistaCards.style.display = "block";
        if (vistaIncidenciasAdmin) vistaIncidenciasAdmin.style.display = "none";
        if (vistaReportesEstadisticas) vistaReportesEstadisticas.style.display = "none";
        if (btnDashboard) btnDashboard.classList.add("active");
        if (btnIncidenciasAdmin) btnIncidenciasAdmin.classList.remove("active");
        if (btnReportes) btnReportes.classList.remove("active");
        if (tituloPanel) tituloPanel.textContent = "Panel de Control";
        window.history.replaceState({}, "", "dashboard.html");
    }

    function mostrarVistaReportes() {
        if (!vistaReportesEstadisticas) return;
        if (vistaCards) vistaCards.style.display = "none";
        if (vistaIncidenciasAdmin) vistaIncidenciasAdmin.style.display = "none";
        vistaReportesEstadisticas.style.display = "block";
        if (btnDashboard) btnDashboard.classList.remove("active");
        if (btnIncidenciasAdmin) btnIncidenciasAdmin.classList.remove("active");
        if (btnReportes) btnReportes.classList.add("active");
        if (tituloPanel) tituloPanel.textContent = "Reportes y estadísticas";
        cargarEstadisticasReportes();
    }

    async function cargarEstadisticasReportes() {
        try {
            const respuesta = await fetch(`${API_INCIDENCIAS}?accion=listar`, { cache: "no-store" });
            const data = await respuesta.json();
            if (!data.exito) throw new Error("No se pudieron cargar las incidencias");

            const incidencias = data.incidencias;
            const porEstado = {
                "Abierta": incidencias.filter(i => i.estado === "Abierta").length,
                "En Proceso": incidencias.filter(i => i.estado === "En Proceso").length,
                "Solucionada": incidencias.filter(i => i.estado === "Solucionada").length
            };

            document.getElementById("metricaTotalReportes").textContent = incidencias.length;
            document.getElementById("metricaAbiertas").textContent = porEstado.Abierta;
            document.getElementById("metricaEnProceso").textContent = porEstado["En Proceso"];
            document.getElementById("metricaSolucionadas").textContent = porEstado.Solucionada;

            const tiemposProceso = incidencias.map(i => diferenciaHoras(i.fecha_creacion, i.fecha_en_proceso)).filter(Number.isFinite);
            const tiemposSolucion = incidencias.map(i => diferenciaHoras(i.fecha_creacion, i.fecha_solucionada)).filter(Number.isFinite);
            mostrarTiempoPromedio("metricaTiempoProceso", "detalleTiempoProceso", tiemposProceso, "reportes que pasaron a En Proceso");
            mostrarTiempoPromedio("metricaTiempoSolucion", "detalleTiempoSolucion", tiemposSolucion, "reportes solucionados");
            dibujarGraficoEstados(porEstado, incidencias.length);
        } catch (error) {
            console.error("Error al cargar las estadísticas:", error);
            document.getElementById("graficoEstados").innerHTML = '<p style="color: var(--text-muted);">No se pudieron cargar las estadísticas.</p>';
        }
    }

    function diferenciaHoras(inicio, fin) {
        if (!inicio || !fin) return NaN;
        const diferencia = new Date(fin) - new Date(inicio);
        return diferencia >= 0 ? diferencia / 3600000 : NaN;
    }

    function mostrarTiempoPromedio(idMetrica, idDetalle, tiempos, descripcion) {
        const metrica = document.getElementById(idMetrica);
        const detalle = document.getElementById(idDetalle);
        if (!tiempos.length) {
            metrica.textContent = "Sin datos";
            detalle.textContent = `Aún no hay ${descripcion}.`;
            return;
        }
        const promedio = tiempos.reduce((total, horas) => total + horas, 0) / tiempos.length;
        metrica.textContent = promedio >= 24 ? `${(promedio / 24).toFixed(1)} días` : `${Math.round(promedio)} h`;
        detalle.textContent = `Basado en ${tiempos.length} ${descripcion}.`;
    }

    function dibujarGraficoEstados(porEstado, total) {
        const colores = { "Abierta": "#eab308", "En Proceso": "#3b82f6", "Solucionada": "#22c55e" };
        const grafico = document.getElementById("graficoEstados");
        grafico.innerHTML = Object.entries(porEstado).map(([estado, cantidad]) => {
            const porcentaje = total ? Math.round((cantidad / total) * 100) : 0;
            return `<div style="margin: 14px 0;">
                <div style="display:flex; justify-content:space-between; margin-bottom:6px;"><span>${estado}</span><strong>${cantidad} (${porcentaje}%)</strong></div>
                <div style="height: 12px; background: rgba(255,255,255,0.12); border-radius: 999px; overflow:hidden;"><div style="width:${porcentaje}%; height:100%; background:${colores[estado]}; border-radius:999px;"></div></div>
            </div>`;
        }).join("");
    }

    function actualizarPanelIncidenciasDashboard(incidencias) {
        const estados = {
            "Abierta": incidencias.filter(i => i.estado === "Abierta").length,
            "En Proceso": incidencias.filter(i => i.estado === "En Proceso").length,
            "Solucionada": incidencias.filter(i => i.estado === "Solucionada").length
        };
        dibujarBarras("graficoEstadosDashboard", estados, { "Abierta": "#eab308", "En Proceso": "#3b82f6", "Solucionada": "#22c55e" });

        const lista = document.getElementById("listaIncidenciasRecientes");
        if (!lista) return;
        if (!incidencias.length) {
            lista.innerHTML = '<p style="padding: 18px; text-align:center; color:var(--text-muted);">No hay incidencias registradas.</p>';
            return;
        }
        const estilosEstado = {
            "Abierta": { color: "#eab308", fondo: "rgba(234,179,8,0.16)", icono: "⚠" },
            "En Proceso": { color: "#3b82f6", fondo: "rgba(59,130,246,0.16)", icono: "🔧" },
            "Solucionada": { color: "#22c55e", fondo: "rgba(34,197,94,0.16)", icono: "✓" }
        };
        const recientes = [...incidencias].sort((a, b) => new Date(b.fecha_creacion || b.fecha) - new Date(a.fecha_creacion || a.fecha)).slice(0, 5);
        lista.innerHTML = recientes.map(incidencia => {
            const estilo = estilosEstado[incidencia.estado] || estilosEstado.Abierta;
            return `<div style="display:grid; grid-template-columns:auto 1fr auto auto; align-items:center; gap:12px; padding:13px 4px; border-bottom:1px solid rgba(255,255,255,0.1);">
                <span style="width:10px; height:10px; border-radius:50%; background:${estilo.color};"></span>
                <div style="min-width:0;"><strong style="display:block; color:white;">${escaparHtml(incidencia.tipo_incidencia || "Sin especificar")}</strong><span style="display:block; margin-top:3px; color:var(--text-muted); font-size:13px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${escaparHtml(incidencia.direccion || "Sin dirección")}</span></div>
                <span style="background:${estilo.fondo}; color:${estilo.color}; border-radius:999px; padding:5px 9px; font-size:12px; font-weight:700; white-space:nowrap;">${estilo.icono} ${escaparHtml(incidencia.estado || "Abierta")}</span>
                <span style="color:var(--text-muted); font-size:12px; white-space:nowrap;">${escaparHtml(formatearFechaIncidencia(incidencia.fecha_creacion || incidencia.fecha))}</span>
            </div>`;
        }).join("");
    }

    function formatearFechaIncidencia(fecha) {
        if (!fecha) return "-";
        const fechaObjeto = new Date(fecha);
        return Number.isNaN(fechaObjeto.getTime()) ? fecha : fechaObjeto.toLocaleDateString("es-UY");
    }

    function dibujarBarras(idContenedor, datos, colores = {}, colorPredeterminado = "#8b5cf6") {
        const contenedor = document.getElementById(idContenedor);
        if (!contenedor) return;
        const total = Object.values(datos).reduce((suma, cantidad) => suma + cantidad, 0);
        if (!total) {
            contenedor.innerHTML = '<p style="color: var(--text-muted);">Aún no hay datos para mostrar.</p>';
            return;
        }
        contenedor.innerHTML = Object.entries(datos).map(([etiqueta, cantidad]) => {
            const porcentaje = Math.round((cantidad / total) * 100);
            return `<div style="margin: 14px 0;">
                <div style="display:flex; justify-content:space-between; gap:12px; margin-bottom:6px;"><span>${escaparHtml(etiqueta)}</span><strong>${cantidad} (${porcentaje}%)</strong></div>
                <div style="height: 12px; background: rgba(255,255,255,0.12); border-radius: 999px; overflow:hidden;"><div style="width:${porcentaje}%; height:100%; background:${colores[etiqueta] || colorPredeterminado}; border-radius:999px;"></div></div>
            </div>`;
        }).join("");
    }

    function escaparHtml(valor) {
        return String(valor).replace(/[&<>'"]/g, caracter => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", "'": "&#039;", '"': "&quot;" })[caracter]);
    }
});
