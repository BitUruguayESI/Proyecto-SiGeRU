// Cambiá esta URL si tu servidor PHP corre en otro puerto o ruta
const API_USUARIOS = "http://localhost/Proyecto-SiGeRu/backend/api-usuarios/index.php";

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
        alert("No se pudo conectar con el servidor. Verifica que la API este corriendo.");
        console.error(error);
    }
});