// Cambiá esta URL si tu servidor corre en otra ruta
const API_USUARIOS = "http://localhost/Proyecto-SiGeRu/backend/api-usuarios/index.php";

document.getElementById("formRegistro").addEventListener("submit", async function (event) {
    event.preventDefault();

    const datos = {
        nombre: document.getElementById("nombre").value,
        usuario: document.getElementById("usuario").value,
        password: document.getElementById("password").value,
        rol: document.getElementById("rol").value
    };

    try {
        const respuesta = await fetch(`${API_USUARIOS}?accion=registrar`, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(datos)
        });

        const data = await respuesta.json();
        alert(data.mensaje);

        if (data.exito) {
            window.location.href = "login.html";
        }

    } catch (error) {
        alert("No se pudo conectar con el servidor. Verifica que la API este corriendo.");
        console.error(error);
    }
});