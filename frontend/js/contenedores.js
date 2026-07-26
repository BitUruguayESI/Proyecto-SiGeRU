// Cambiá esta URL si tu servidor corre en otra ruta
const API_GESTION = "../backend/api-gestion/index.php";

document.addEventListener("DOMContentLoaded", cargarContenedores);

// ------- LISTAR -------
async function cargarContenedores() {
    try {
        const respuesta = await fetch(`${API_GESTION}?accion=listar`);
        const data = await respuesta.json();

        const tbody = document.getElementById("tablaContenedores");
        tbody.innerHTML = "";

        if (data.exito) {
            data.contenedores.forEach(c => {
                tbody.innerHTML += `
                    <tr>
                        <td>${c.id}</td>
                        <td>${c.ubicacion}</td>
                        <td>${c.tipo_residuo}</td>
                        <td>${c.estado}</td>
                        <td>
                            <button onclick="editarContenedor(${c.id})">✏ Editar</button>
                            <button onclick="eliminarContenedor(${c.id})">🗑 Eliminar</button>
                        </td>
                    </tr>
                `;
            });
        }
    } catch (error) {
        console.error("Error al cargar contenedores:", error);
    }
}

// ------- CREAR / EDITAR (mismo formulario) -------
document.getElementById("formContenedor").addEventListener("submit", async function (event) {
    event.preventDefault();

    const id = document.getElementById("contenedorId").value;

    const datos = {
        ubicacion: document.getElementById("ubicacion").value,
        tipo_residuo: document.getElementById("tipoResiduo").value,
        estado: document.getElementById("estado").value
    };

    try {
        let respuesta;

        if (id) {
            respuesta = await fetch(`${API_GESTION}?accion=editar&id=${id}`, {
                method: "PUT",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(datos)
            });
        } else {
            respuesta = await fetch(`${API_GESTION}?accion=crear`, {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(datos)
            });
        }

        const data = await respuesta.json();
        alert(data.mensaje);

        if (data.exito) {
            cancelarEdicionContenedor();
            cargarContenedores();
        }

    } catch (error) {
        alert("No se pudo conectar con el servidor.");
        console.error(error);
    }
});

// ------- Cargar datos en el formulario para editar -------
async function editarContenedor(id) {
    try {
        const respuesta = await fetch(`${API_GESTION}?accion=obtener&id=${id}`);
        const data = await respuesta.json();

        if (data.exito) {
            document.getElementById("contenedorId").value = data.contenedor.id;
            document.getElementById("ubicacion").value = data.contenedor.ubicacion;
            document.getElementById("tipoResiduo").value = data.contenedor.tipo_residuo;
            document.getElementById("estado").value = data.contenedor.estado;
            document.getElementById("tituloFormularioContenedor").innerText = "Editar Contenedor";
        }
    } catch (error) {
        console.error("Error al obtener contenedor:", error);
    }
}

// ------- Eliminar -------
async function eliminarContenedor(id) {
    if (!confirm("¿Seguro que queres eliminar este contenedor?")) return;

    try {
        const respuesta = await fetch(`${API_GESTION}?accion=eliminar&id=${id}`, {
            method: "DELETE"
        });
        const data = await respuesta.json();
        alert(data.mensaje);
        cargarContenedores();
    } catch (error) {
        console.error("Error al eliminar contenedor:", error);
    }
}

// ------- Volver el formulario al modo "crear" -------
function cancelarEdicionContenedor() {
    document.getElementById("formContenedor").reset();
    document.getElementById("contenedorId").value = "";
    document.getElementById("tituloFormularioContenedor").innerText = "Nuevo Contenedor";
}
