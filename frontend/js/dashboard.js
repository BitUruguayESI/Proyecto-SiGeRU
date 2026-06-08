// ===== DASHBOARD.JS - Lógica del Dashboard con API MySQL =====

const API_BASE = '../../backend/api';
let usuarioActual = null;

document.addEventListener('DOMContentLoaded', function() {
    const usuarioSession = sessionStorage.getItem('usuario');

    if (!usuarioSession) {
        alert('Debes iniciar sesión primero.');
        window.location.href = 'login.html';
        return;
    }

    usuarioActual = JSON.parse(usuarioSession);
    document.getElementById('userName').textContent = usuarioActual.nombre;

    configurarPermisos();

    if (puedeVerUsuarios()) {
        cargarUsuarios();
    }
    cargarContenedores();
    cargarCamiones();
    cargarPerfil();

    const formAgregarUsuario = document.getElementById('formAgregarUsuario');
    if (formAgregarUsuario) {
        formAgregarUsuario.addEventListener('submit', agregarUsuario);
    }

    const formAgregarContenedor = document.getElementById('formAgregarContenedor');
    if (formAgregarContenedor) {
        formAgregarContenedor.addEventListener('submit', agregarContenedor);
    }

    const formAgregarCamion = document.getElementById('formAgregarCamion');
    if (formAgregarCamion) {
        formAgregarCamion.addEventListener('submit', agregarCamion);
    }

    const formCambiarPassword = document.getElementById('formCambiarPassword');
    if (formCambiarPassword) {
        formCambiarPassword.addEventListener('submit', cambiarPassword);
    }

    const logoutLink = document.getElementById('logoutLink');
    if (logoutLink) {
        logoutLink.addEventListener('click', cerrarSesion);
    }
});

async function llamarApi(url, opciones = {}) {
    const response = await fetch(url, opciones);
    const resultado = await response.json();

    if (!resultado.exito) {
        throw new Error(resultado.mensaje || 'Error en la API');
    }

    return resultado.datos;
}

function escapeHtml(valor) {
    return String(valor ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function mostrarErrorTabla(tablaId, columnas, mensaje) {
    document.getElementById(tablaId).innerHTML = `<tr><td colspan="${columnas}" style="text-align: center;">${escapeHtml(mensaje)}</td></tr>`;
}

function esAdmin() {
    return usuarioActual && usuarioActual.rol === 'admin';
}

function puedeVerUsuarios() {
    return usuarioActual && ['admin', 'gerente'].includes(usuarioActual.rol);
}

function puedeGestionarOperacion() {
    return usuarioActual && ['admin', 'gerente'].includes(usuarioActual.rol);
}

function configurarPermisos() {
    const tabUsuarios = document.querySelector('.nav-tab[href="#usuarios"]');
    const seccionUsuarios = document.getElementById('usuarios');

    if (!puedeVerUsuarios()) {
        if (tabUsuarios) {
            tabUsuarios.style.display = 'none';
        }
        if (seccionUsuarios) {
            seccionUsuarios.style.display = 'none';
        }
        mostrarSeccion('contenedores');
    }

    const botonAgregarUsuario = document.querySelector('button[onclick="mostrarFormularioUsuario()"]');
    if (botonAgregarUsuario && !esAdmin()) {
        botonAgregarUsuario.style.display = 'none';
    }

    const botonAgregarContenedor = document.querySelector('button[onclick="mostrarFormularioContenedor()"]');
    if (botonAgregarContenedor && !puedeGestionarOperacion()) {
        botonAgregarContenedor.style.display = 'none';
    }

    const botonAgregarCamion = document.querySelector('button[onclick="mostrarFormularioCamion()"]');
    if (botonAgregarCamion && !puedeGestionarOperacion()) {
        botonAgregarCamion.style.display = 'none';
    }
}

async function cerrarSesion(e) {
    e.preventDefault();
    sessionStorage.removeItem('usuario');

    try {
        await llamarApi(`${API_BASE}/usuarios.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ accion: 'logout' })
        });
    } catch (error) {
        console.error(error);
    }

    window.location.href = 'login.html';
}

// ===== FUNCIONES DE NAVEGACIÓN =====

function mostrarSeccion(seccion) {
    document.querySelectorAll('.seccion').forEach(s => {
        s.style.display = 'none';
    });

    const seccionActual = document.getElementById(seccion);
    if (seccionActual) {
        seccionActual.style.display = 'block';
    }

    document.querySelectorAll('.nav-tab').forEach(tab => {
        tab.classList.toggle('active', tab.getAttribute('href') === `#${seccion}`);
    });
}

// ===== FUNCIONES DE USUARIOS =====

async function cargarUsuarios() {
    const tablaUsuarios = document.getElementById('tabla-usuarios');
    tablaUsuarios.innerHTML = '<tr><td colspan="5" style="text-align: center;">Cargando usuarios...</td></tr>';

    try {
        const usuarios = await llamarApi(`${API_BASE}/usuarios.php?accion=listar`);

        if (!usuarios || usuarios.length === 0) {
            mostrarErrorTabla('tabla-usuarios', 5, 'No hay usuarios registrados');
            return;
        }

        tablaUsuarios.innerHTML = usuarios.map(usuario => `
            <tr>
                <td>${escapeHtml(usuario.id)}</td>
                <td>${escapeHtml(usuario.nombre)}</td>
                <td>${escapeHtml(usuario.email)}</td>
                <td>${escapeHtml(usuario.rol)}</td>
                <td>${accionesUsuario(usuario)}</td>
            </tr>
        `).join('');
    } catch (error) {
        console.error(error);
        mostrarErrorTabla('tabla-usuarios', 5, 'No se pudieron cargar usuarios desde MySQL');
    }
}

function accionesUsuario(usuario) {
    if (!esAdmin()) {
        return '<span class="text-muted">Sin acciones</span>';
    }

    return `
        <div class="table-actions">
            <button class="btn btn-primary" onclick="editarUsuario(${Number(usuario.id)})">Editar</button>
            <button class="btn btn-danger" onclick="eliminarUsuario(${Number(usuario.id)})">Eliminar</button>
        </div>
    `;
}

function mostrarFormularioUsuario() {
    document.getElementById('formulario-usuario').style.display = 'block';
}

function ocultarFormularioUsuario() {
    document.getElementById('formulario-usuario').style.display = 'none';
    document.getElementById('formAgregarUsuario').reset();
}

async function agregarUsuario(e) {
    e.preventDefault();

    const nombre = document.getElementById('usuario-nombre').value;
    const email = document.getElementById('usuario-email').value;
    const rol = document.getElementById('usuario-rol').value;

    if (!nombre || !email || !rol) {
        alert('Por favor completa todos los campos.');
        return;
    }

    try {
        await llamarApi(`${API_BASE}/usuarios.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                accion: 'agregar',
                datos: { nombre, email, rol, password: 'default123' }
            })
        });

        alert('Usuario agregado exitosamente.');
        ocultarFormularioUsuario();
        cargarUsuarios();
    } catch (error) {
        alert('Error al agregar usuario: ' + error.message);
    }
}

function editarUsuario(id) {
    alert('Función de edición próximamente.');
}

async function eliminarUsuario(id) {
    if (!confirm('¿Estás seguro de que deseas eliminar este usuario?')) {
        return;
    }

    try {
        await llamarApi(`${API_BASE}/usuarios.php?id=${id}`, { method: 'DELETE' });
        alert('Usuario eliminado exitosamente.');
        cargarUsuarios();
    } catch (error) {
        alert('Error al eliminar usuario: ' + error.message);
    }
}

// ===== FUNCIONES DE CONTENEDORES =====

async function cargarContenedores() {
    const tablaContenedores = document.getElementById('tabla-contenedores');
    tablaContenedores.innerHTML = '<tr><td colspan="5" style="text-align: center;">Cargando contenedores...</td></tr>';

    try {
        const contenedores = await llamarApi(`${API_BASE}/contenedores.php?accion=listar`);

        if (!contenedores || contenedores.length === 0) {
            mostrarErrorTabla('tabla-contenedores', 5, 'No hay contenedores registrados');
            return;
        }

        tablaContenedores.innerHTML = contenedores.map(contenedor => `
            <tr>
                <td>${escapeHtml(contenedor.id)}</td>
                <td>${escapeHtml(contenedor.codigo)}</td>
                <td>${escapeHtml(contenedor.tipo)}</td>
                <td>${escapeHtml(contenedor.capacidad)} kg</td>
                <td>${accionesContenedor(contenedor)}</td>
            </tr>
        `).join('');
    } catch (error) {
        console.error(error);
        mostrarErrorTabla('tabla-contenedores', 5, 'No se pudieron cargar contenedores desde MySQL');
    }
}

function accionesContenedor(contenedor) {
    if (!puedeGestionarOperacion()) {
        return '<span class="text-muted">Sin acciones</span>';
    }

    return `
        <div class="table-actions">
            <button class="btn btn-primary" onclick="editarContenedor(${Number(contenedor.id)})">Editar</button>
            <button class="btn btn-danger" onclick="eliminarContenedor(${Number(contenedor.id)})">Eliminar</button>
        </div>
    `;
}

function mostrarFormularioContenedor() {
    document.getElementById('formulario-contenedor').style.display = 'block';
}

function ocultarFormularioContenedor() {
    document.getElementById('formulario-contenedor').style.display = 'none';
    document.getElementById('formAgregarContenedor').reset();
}

async function agregarContenedor(e) {
    e.preventDefault();

    const codigo = document.getElementById('contenedor-codigo').value;
    const tipo = document.getElementById('contenedor-tipo').value;
    const capacidad = document.getElementById('contenedor-capacidad').value;

    if (!codigo || !tipo || !capacidad) {
        alert('Por favor completa todos los campos.');
        return;
    }

    try {
        await llamarApi(`${API_BASE}/contenedores.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                accion: 'agregar',
                datos: { codigo, tipo, capacidad: Number(capacidad) }
            })
        });

        alert('Contenedor agregado exitosamente.');
        ocultarFormularioContenedor();
        cargarContenedores();
    } catch (error) {
        alert('Error al agregar contenedor: ' + error.message);
    }
}

function editarContenedor(id) {
    alert('Función de edición próximamente.');
}

async function eliminarContenedor(id) {
    if (!confirm('¿Estás seguro de que deseas eliminar este contenedor?')) {
        return;
    }

    try {
        await llamarApi(`${API_BASE}/contenedores.php?id=${id}`, { method: 'DELETE' });
        alert('Contenedor eliminado exitosamente.');
        cargarContenedores();
    } catch (error) {
        alert('Error al eliminar contenedor: ' + error.message);
    }
}

// ===== FUNCIONES DE CAMIONES =====

async function cargarCamiones() {
    const tablaCamiones = document.getElementById('tabla-camiones');
    tablaCamiones.innerHTML = '<tr><td colspan="5" style="text-align: center;">Cargando camiones...</td></tr>';

    try {
        const camiones = await llamarApi(`${API_BASE}/camiones.php?accion=listar`);

        if (!camiones || camiones.length === 0) {
            mostrarErrorTabla('tabla-camiones', 5, 'No hay camiones registrados');
            return;
        }

        tablaCamiones.innerHTML = camiones.map(camion => `
            <tr>
                <td>${escapeHtml(camion.id)}</td>
                <td>${escapeHtml(camion.patente)}</td>
                <td>${escapeHtml(camion.modelo)}</td>
                <td>${escapeHtml(camion.capacidad)} kg</td>
                <td>${accionesCamion(camion)}</td>
            </tr>
        `).join('');
    } catch (error) {
        console.error(error);
        mostrarErrorTabla('tabla-camiones', 5, 'No se pudieron cargar camiones desde MySQL');
    }
}

function accionesCamion(camion) {
    if (!puedeGestionarOperacion()) {
        return '<span class="text-muted">Sin acciones</span>';
    }

    return `
        <div class="table-actions">
            <button class="btn btn-primary" onclick="editarCamion(${Number(camion.id)})">Editar</button>
            <button class="btn btn-danger" onclick="eliminarCamion(${Number(camion.id)})">Eliminar</button>
        </div>
    `;
}

function mostrarFormularioCamion() {
    document.getElementById('formulario-camion').style.display = 'block';
}

function ocultarFormularioCamion() {
    document.getElementById('formulario-camion').style.display = 'none';
    document.getElementById('formAgregarCamion').reset();
}

async function agregarCamion(e) {
    e.preventDefault();

    const patente = document.getElementById('camion-patente').value;
    const modelo = document.getElementById('camion-modelo').value;
    const capacidad = document.getElementById('camion-capacidad').value;

    if (!patente || !modelo || !capacidad) {
        alert('Por favor completa todos los campos.');
        return;
    }

    try {
        await llamarApi(`${API_BASE}/camiones.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                accion: 'agregar',
                datos: { patente, modelo, capacidad: Number(capacidad), tipo: 'plataforma' }
            })
        });

        alert('Camión agregado exitosamente.');
        ocultarFormularioCamion();
        cargarCamiones();
    } catch (error) {
        alert('Error al agregar camión: ' + error.message);
    }
}

function editarCamion(id) {
    alert('Función de edición próximamente.');
}

async function eliminarCamion(id) {
    if (!confirm('¿Estás seguro de que deseas eliminar este camión?')) {
        return;
    }

    try {
        await llamarApi(`${API_BASE}/camiones.php?id=${id}`, { method: 'DELETE' });
        alert('Camión eliminado exitosamente.');
        cargarCamiones();
    } catch (error) {
        alert('Error al eliminar camión: ' + error.message);
    }
}

// ===== FUNCIONES DE PERFIL =====

function cargarPerfil() {
    document.getElementById('perfil-nombre').value = usuarioActual.nombre;
    document.getElementById('perfil-email').value = usuarioActual.email;
    document.getElementById('perfil-rol').value = usuarioActual.rol;
}

async function cambiarPassword(e) {
    e.preventDefault();

    const passwordActual = document.getElementById('password-actual').value;
    const passwordNueva = document.getElementById('password-nueva').value;
    const passwordConfirmacion = document.getElementById('password-confirmacion').value;

    if (!passwordActual || !passwordNueva || !passwordConfirmacion) {
        alert('Por favor completa todos los campos de contraseña.');
        return;
    }

    if (passwordNueva !== passwordConfirmacion) {
        alert('La nueva contraseña y su confirmación no coinciden.');
        return;
    }

    if (passwordNueva.length < 6) {
        alert('La nueva contraseña debe tener al menos 6 caracteres.');
        return;
    }

    try {
        await llamarApi(`${API_BASE}/usuarios.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                accion: 'cambiar_password',
                password_actual: passwordActual,
                password_nueva: passwordNueva,
                password_confirmacion: passwordConfirmacion
            })
        });

        document.getElementById('formCambiarPassword').reset();
        alert('Contraseña actualizada exitosamente.');
    } catch (error) {
        alert('Error al cambiar contraseña: ' + error.message);
    }
}
