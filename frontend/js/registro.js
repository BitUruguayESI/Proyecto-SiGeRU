const PAGINA_XAMPP = "http://localhost/Proyecto-SiGeRU/frontend/registro.html";

// Impide ejecutar la app desde file:// o Live Server, donde PHP no puede atender la API.
if (window.location.origin !== "http://localhost" ||
    !window.location.pathname.toLowerCase().endsWith("/proyecto-sigeru/frontend/registro.html")) {
    window.location.replace(PAGINA_XAMPP);
}

// Ruta relativa: funciona cuando el proyecto se abre desde Apache/XAMPP.
const API_USUARIOS = "../backend/api-usuarios/index.php";

document.getElementById("formRegistro").addEventListener("submit", async function (event) {
    event.preventDefault();

    const datos = {
        nombre: document.getElementById("nombre").value,
        usuario: document.getElementById("CI").value,
        password: document.getElementById("password").value,
        rol: "Vecino" // <-- Añade esta línea fija
        //rol: document.getElementById("rol").value// <--viene directo del formulariio en registro.html
    };

    try {
        const respuesta = await fetch(`${API_USUARIOS}?accion=registrar`, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(datos)
        });

        if (!respuesta.ok) {
            throw new Error(`La API respondió HTTP ${respuesta.status}`);
        }

        const data = await respuesta.json();
        alert(data.mensaje);

        if (data.exito) {
            window.location.href = "login.html";
        }

    } catch (error) {
        alert("No se pudo registrar. Abrí la aplicación desde XAMPP: " + PAGINA_XAMPP);
        console.error(error);
    }
});
