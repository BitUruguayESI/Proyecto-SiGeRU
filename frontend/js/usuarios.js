// Cambiá esta URL si tu servidor PHP corre en otro puerto o ruta
const API_USUARIOS = "http://localhost/Proyecto-SiGeRu/backend/api-usuarios/index.php";

document.addEventListener("DOMContentLoaded", cargarUsuarios);

// ------- LISTAR -------
async function cargarUsuarios() {
    try {
        const respuesta = await fetch(`${API_USUARIOS}?accion=listar`);
        const data = await respuesta.json();

        const tbody = document.getElementById("tablaUsuarios");
        tbody.innerHTML = "";

        if (data.exito) {
            data.usuarios.forEach(u => {
                tbody.innerHTML += `
                    <tr>
                        <td>${u.id}</td>
                        <td>${u.nombre}</td>
                        <td>${u.usuario}</td>
                        <td>${u.rol}</td>
                        <td>
                            <button onclick="editarUsuario(${u.id})">✏ Editar</button>
                            <button onclick="eliminarUsuario(${u.id})">🗑 Eliminar</button>
                        </td>
                    </tr>
                `;
            });
        }
    } catch (error) {
        console.error("Error al cargar usuarios:", error);
    }
}

// ------- CREAR / EDITAR (mismo formulario) -------
document.getElementById("formUsuario").addEventListener("submit", async function (event) {
    event.preventDefault();

    const id = document.getElementById("usuarioId").value;

    const datos = {
        nombre: document.getElementById("nombre").value,
        usuario: document.getElementById("usuarioLogin").value,
        password: document.getElementById("passwordUsuario").value,
        rol: document.getElementById("rol").value
    };

    try {
        let respuesta;

        if (id) {
            // Editar usuario existente
            respuesta = await fetch(`${API_USUARIOS}?accion=editar&id=${id}`, {
                method: "PUT",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(datos)
            });
        } else {
            // Crear usuario nuevo
            respuesta = await fetch(`${API_USUARIOS}?accion=registrar`, {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(datos)
            });
        }

        const data = await respuesta.json();
        alert(data.mensaje);

        if (data.exito) {
            cancelarEdicion();
            cargarUsuarios();
        }

    } catch (error) {
        alert("No se pudo conectar con el servidor.");
        console.error(error);
    }
});

// ------- Cargar datos en el formulario para editar -------
async function editarUsuario(id) {
    try {
        const respuesta = await fetch(`${API_USUARIOS}?accion=obtener&id=${id}`);
        const data = await respuesta.json();

        if (data.exito) {
            document.getElementById("usuarioId").value = data.usuario.id;
            document.getElementById("nombre").value = data.usuario.nombre;
            document.getElementById("usuarioLogin").value = data.usuario.usuario;
            document.getElementById("rol").value = data.usuario.rol;
            document.getElementById("passwordUsuario").value = "";
            document.getElementById("tituloFormulario").innerText = "Editar Usuario";
        }
    } catch (error) {
        console.error("Error al obtener usuario:", error);
    }
}

// ------- Eliminar -------
async function eliminarUsuario(id) {
    if (!confirm("¿Seguro que queres eliminar este usuario?")) return;

    try {
        const respuesta = await fetch(`${API_USUARIOS}?accion=eliminar&id=${id}`, {
            method: "DELETE"
        });
        const data = await respuesta.json();
        alert(data.mensaje);
        cargarUsuarios();
    } catch (error) {
        console.error("Error al eliminar usuario:", error);
    }
}

// ------- Volver el formulario al modo "crear" -------
function cancelarEdicion() {
    document.getElementById("formUsuario").reset();
    document.getElementById("usuarioId").value = "";
    document.getElementById("tituloFormulario").innerText = "Nuevo Usuario";
}