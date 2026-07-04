// Cambiá esta URL si tu servidor corre en otra ruta
const API_RECOLECCION = "http://localhost/Proyecto-SiGeRu/backend/api-recoleccion/index.php";

document.addEventListener("DOMContentLoaded", cargarCamiones);

// ------- LISTAR -------
async function cargarCamiones() {
    try {
        const respuesta = await fetch(`${API_RECOLECCION}?accion=listar`);
        const data = await respuesta.json();

        const tbody = document.getElementById("tablaCamiones");
        tbody.innerHTML = "";

        if (data.exito) {
            data.camiones.forEach(c => {
                tbody.innerHTML += `
                    <tr>
                        <td>${c.id}</td>
                        <td>${c.patente}</td>
                        <td>${c.capacidad_kg} kg</td>
                        <td>${c.estado}</td>
                        <td>
                            <button onclick="editarCamion(${c.id})">✏ Editar</button>
                            <button onclick="eliminarCamion(${c.id})">🗑 Eliminar</button>
                        </td>
                    </tr>
                `;
            });
        }
    } catch (error) {
        console.error("Error al cargar camiones:", error);
    }
}

// ------- CREAR / EDITAR (mismo formulario) -------
document.getElementById("formCamion").addEventListener("submit", async function (event) {
    event.preventDefault();

    const id = document.getElementById("camionId").value;

    const datos = {
        patente: document.getElementById("patente").value,
        capacidad_kg: Number(document.getElementById("capacidad").value),
        estado: document.getElementById("estadoCamion").value
    };

    try {
        let respuesta;

        if (id) {
            respuesta = await fetch(`${API_RECOLECCION}?accion=editar&id=${id}`, {
                method: "PUT",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(datos)
            });
        } else {
            respuesta = await fetch(`${API_RECOLECCION}?accion=crear`, {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(datos)
            });
        }

        const data = await respuesta.json();
        alert(data.mensaje);

        if (data.exito) {
            cancelarEdicionCamion();
            cargarCamiones();
        }

    } catch (error) {
        alert("No se pudo conectar con el servidor.");
        console.error(error);
    }
});

// ------- Cargar datos en el formulario para editar -------
async function editarCamion(id) {
    try {
        const respuesta = await fetch(`${API_RECOLECCION}?accion=obtener&id=${id}`);
        const data = await respuesta.json();

        if (data.exito) {
            document.getElementById("camionId").value = data.camion.id;
            document.getElementById("patente").value = data.camion.patente;
            document.getElementById("capacidad").value = data.camion.capacidad_kg;
            document.getElementById("estadoCamion").value = data.camion.estado;
            document.getElementById("tituloFormularioCamion").innerText = "Editar Camión";
        }
    } catch (error) {
        console.error("Error al obtener camion:", error);
    }
}

// ------- Eliminar -------
async function eliminarCamion(id) {
    if (!confirm("¿Seguro que queres eliminar este camión?")) return;

    try {
        const respuesta = await fetch(`${API_RECOLECCION}?accion=eliminar&id=${id}`, {
            method: "DELETE"
        });
        const data = await respuesta.json();
        alert(data.mensaje);
        cargarCamiones();
    } catch (error) {
        console.error("Error al eliminar camion:", error);
    }
}

// ------- Volver el formulario al modo "crear" -------
function cancelarEdicionCamion() {
    document.getElementById("formCamion").reset();
    document.getElementById("camionId").value = "";
    document.getElementById("tituloFormularioCamion").innerText = "Nuevo Camión";
}