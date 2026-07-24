const API_INCIDENCIAS = "http://localhost/Proyecto-SiGeRu/backend/api-incidencias/index.php";

document.addEventListener("DOMContentLoaded", function () {
    
    // 1. VALIDACIÓN DE SEGURIDAD (Obtenemos los datos del vecino logueado)
    const usuarioLogueado = JSON.parse(sessionStorage.getItem("usuarioActivo"));
    if (!usuarioLogueado) {
        window.location.href = "login.html";
        return;
    }

    // Textos dinámicos de bienvenida en el panel
    const nombreVecino = document.getElementById("nombreVecino");
    const saludo = document.getElementById("saludo");
    if (nombreVecino) nombreVecino.textContent = usuarioLogueado.nombre;
    if (saludo) saludo.textContent = `¡Hola, ${usuarioLogueado.nombre.split(' ')[0]}!`;

    // Selectores para alternar las vistas (Formulario vs Historial)
    const vistaFormulario = document.getElementById("vistaFormulario");
    const vistaReportes = document.getElementById("vistaReportes");
    const tituloSeccion = document.getElementById("tituloSeccion");
    const btnMisReportes = document.getElementById("btnMisReportes");
    const btnNuevaIncidencia = document.querySelector(".sidebar ul li a");

    // 2. ACCIÓN: CLIC EN "MIS REPORTES" (Carga el historial desde el Backend)
    if (btnMisReportes) {
        btnMisReportes.addEventListener("click", async function (event) {
            event.preventDefault();

            // Ajuste visual del menú lateral
            if(btnNuevaIncidencia) btnNuevaIncidencia.classList.remove("active");
            btnMisReportes.classList.add("active");

            // Alternamos contenedores
            vistaFormulario.style.display = "none";
            vistaReportes.style.display = "block";
            if(tituloSeccion) tituloSeccion.textContent = "Historial de mis Reportes Enviados";

            try {
                // Consultamos a la API PHP pasando el ID del ciudadano activo
                const respuesta = await fetch(`${API_INCIDENCIAS}?accion=listarPorUsuario&usuario_id=${usuarioLogueado.id}`);
                const data = await respuesta.json();

                const tablaCuerpo = document.getElementById("tablaReportesCuerpo");
                tablaCuerpo.innerHTML = ""; // Limpiamos registros previos

                if (data.exito && data.incidencias.length > 0) {
                    data.incidencias.forEach(incidencia => {
                        // Definición de colores según el estado municipal
                        let colorEstado = "#eab308"; // Amarillo para Abierta
                        if (incidencia.estado === "En Proceso") colorEstado = "#3b82f6"; // Azul
                        if (incidencia.estado === "Solucionada") colorEstado = "#22c55e"; // Verde

                        // Validación del comentario/detalles adicionales
                        const comentarioTexto = incidencia.descripcion ? incidencia.descripcion : `<span style="color:var(--text-muted);">-</span>`;

                        // FILA CORREGIDA: Se respeta estrictamente el orden de las cabeceras HTML
                        const fila = `
                            <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                                <td style="padding: 12px; white-space: nowrap;">${incidencia.fecha}</td>
                                <td style="padding: 12px;">${incidencia.tipo_incidencia}</td>
                                <td style="padding: 12px;">${incidencia.direccion}</td>
                                <td style="padding: 12px; font-size: 14px; max-width: 250px; word-wrap: break-word;">${comentarioTexto}</td>
                                <td style="padding: 12px;">
                                    <span style="background: ${colorEstado}22; color: ${colorEstado}; padding: 4px 8px; border-radius: 6px; font-size: 12px; font-weight: bold;">
                                        ${incidencia.estado}
                                    </span>
                                </td>
                            </tr>
                        `;
                        tablaCuerpo.innerHTML += fila;
                    });
                } else {
                    tablaCuerpo.innerHTML = `<tr><td colspan="5" style="text-align: center; padding: 20px; color: var(--text-muted);">Aún no has registrado ninguna incidencia municipal.</td></tr>`;
                }

            } catch (error) {
                console.error("Error al cargar reportes:", error);
            }
        });
    }

    // 3. ACCIÓN: CLIC EN "NUEVA INCIDENCIA" (Regresa al Formulario)
    if (btnNuevaIncidencia) {
        btnNuevaIncidencia.addEventListener("click", function (event) {
            event.preventDefault();
            if(btnMisReportes) btnMisReportes.classList.remove("active");
            btnNuevaIncidencia.classList.add("active");

            vistaReportes.style.display = "none";
            vistaFormulario.style.display = "block";
            if(tituloSeccion) tituloSeccion.textContent = "Reportar Contenedor en Mal Estado o Desbordado";
        });
    }

    // 4. CONTROL DEL ENVÍO DEL FORMULARIO DE INCIDENCIAS
    const formIncidencia = document.getElementById("formIncidencia");
    if (formIncidencia) {
        formIncidencia.addEventListener("submit", async function (event) {
            event.preventDefault();

            const datosReporte = {
                usuario_id: usuarioLogueado.id,
                tipo_incidencia: document.getElementById("tipo_incidencia").value,
                direccion: document.getElementById("direccion").value,
                descripcion: document.getElementById("descripcion").value
            };

            try {
                const respuesta = await fetch(`${API_INCIDENCIAS}?accion=crear`, {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify(datosReporte)
                });

                const data = await respuesta.json();
                alert(data.mensaje);

                if (data.exito) {
                    formIncidencia.reset(); // Resetea los inputs tras el guardado exitoso
                }

            } catch (error) {
                alert("No se pudo conectar con el servidor.");
                console.error(error);
            }
        });
    }

    // 5. PROGRAMACIÓN DEL BOTÓN DE CERRAR SESIÓN
    const btnCerrarSesion = document.getElementById("btnCerrarSesion");
    if (btnCerrarSesion) {
        btnCerrarSesion.addEventListener("click", function () {
            sessionStorage.removeItem("usuarioActivo");
            window.location.href = "index.html";
        });
    }
});