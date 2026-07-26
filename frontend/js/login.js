const PAGINA_XAMPP = "http://localhost/Proyecto-SiGeRU/frontend/login.html";

// Impide ejecutar la app desde file:// o Live Server, donde PHP no puede atender la API.
if (window.location.origin !== "http://localhost" ||
    !window.location.pathname.toLowerCase().endsWith("/proyecto-sigeru/frontend/login.html")) {
    window.location.replace(PAGINA_XAMPP);
}

// Ruta relativa: funciona cuando el proyecto se abre desde Apache/XAMPP.
const API_USUARIOS = "../backend/api-usuarios/index.php";

document.getElementById("formLogin").addEventListener("submit", async function (event) {
    event.preventDefault();

    // Capturamos la C.I. y la contraseña del formulario HTML
    const usuario = document.getElementById("CI").value;
    const password = document.getElementById("password").value;

    try {
        const respuesta = await fetch(`${API_USUARIOS}?accion=login`, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            credentials: "include",
            body: JSON.stringify({ usuario, password }) // Enviamos la C.I. mapeada como 'usuario'
        });

        if (!respuesta.ok) {
            throw new Error(`La API respondió HTTP ${respuesta.status}`);
        }

        const data = await respuesta.json();

        if (data.exito) {
            // Guardamos los datos del usuario activo en el navegador
            sessionStorage.setItem("usuarioActivo", JSON.stringify(data.usuario));
            
            // Redirección inteligente según el rol del usuario
            if (data.usuario.rol === "Administrador") {
                window.location.href = "dashboard.html"; // Panel global de control
            } else if (data.usuario.rol === "Vecino") {
                window.location.href = "panel-vecino.html"; // Panel de reportes para el ciudadano
            } else {
                // Por si en el futuro agregas los roles 'Cuadrilla' o 'Operario'
                window.location.href = "dashboard.html";
            }
        } else {
            // Si las credenciales son incorrectas, muestra el mensaje del backend
            alert(data.mensaje);
        }

    } catch (error) {
        alert("No se pudo iniciar sesión. Abrí la aplicación desde XAMPP: " + PAGINA_XAMPP);
        console.error(error);
    }
});
