// Cambiá esta URL si tu servidor PHP corre en otro puerto o ruta
const API_USUARIOS = "http://localhost/Proyecto-SiGeRu/backend/api-usuarios/index.php";

document.getElementById("formLogin").addEventListener("submit", async function (event) {
    event.preventDefault();

    const usuario = document.getElementById("usuario").value;
    const password = document.getElementById("password").value;

    try {
        const respuesta = await fetch(`${API_USUARIOS}?accion=login`, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            credentials: "include",
            body: JSON.stringify({ usuario, password })
        });

        const data = await respuesta.json();

        if (data.exito) {
            // Guardamos los datos del usuario activo para usarlos en el dashboard
            sessionStorage.setItem("usuarioActivo", JSON.stringify(data.usuario));
            window.location.href = "dashboard.html";
        } else {
            alert(data.mensaje);
        }

    } catch (error) {
        alert("No se pudo conectar con el servidor. Verifica que la API este corriendo.");
        console.error(error);
    }
});