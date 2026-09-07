const BASE = '../api-gestion';
const divMensajeGlobal = document.getElementById('mensajeGlobal');

function mostrarMensajeGlobal(texto, tipo) {
  divMensajeGlobal.textContent = texto;
  divMensajeGlobal.className = 'mensaje ' + tipo;
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

async function llamar(url, opciones = {}) {
  const respuesta = await fetch(url, {
    headers: { 'Content-Type': 'application/json' },
    ...opciones,
  });
  if (respuesta.status === 401) {
    window.location.href = 'login.html';
    throw new Error('Sin sesión');
  }
  return respuesta.json();
}

// ---------------------------------------------------------------
// Pestañas
// ---------------------------------------------------------------
document.querySelectorAll('.pestanias button').forEach(boton => {
  boton.addEventListener('click', () => {
    document.querySelectorAll('.pestanias button').forEach(b => b.classList.remove('activa'));
    document.querySelectorAll('.seccion-panel').forEach(s => s.classList.remove('activa'));
    boton.classList.add('activa');
    document.getElementById('seccion-' + boton.dataset.seccion).classList.add('activa');
  });
});

// ---------------------------------------------------------------
// CONTENEDORES
// ---------------------------------------------------------------
async function cargarContenedores() {
  const datos = await llamar(`${BASE}/contenedores.php`);
  const cuerpo = document.getElementById('tablaContenedores');
  cuerpo.innerHTML = '';
  if (!datos.exito) return;

  datos.contenedores.forEach(c => {
    const fila = document.createElement('tr');
    fila.innerHTML = `
      <td>${c.id_contenedor}</td>
      <td>${c.coordenadas}</td>
      <td>
        <select data-id="${c.id_contenedor}" class="select-estado-contenedor">
          <option value="OPERATIVO" ${c.estado === 'OPERATIVO' ? 'selected' : ''}>Operativo</option>
          <option value="DESBORDADO" ${c.estado === 'DESBORDADO' ? 'selected' : ''}>Desbordado</option>
          <option value="FUERA_DE_SERVICIO" ${c.estado === 'FUERA_DE_SERVICIO' ? 'selected' : ''}>Fuera de servicio</option>
        </select>
      </td>
      <td>${c.nivel_llenado}</td>
      <td>${c.nombre_zona || '—'}</td>
      <td>${c.tipos_residuo.join(', ') || '—'}</td>
      <td><button class="peligro btn-baja-contenedor" data-id="${c.id_contenedor}">Dar de baja</button></td>
    `;
    cuerpo.appendChild(fila);
  });

  cuerpo.querySelectorAll('.select-estado-contenedor').forEach(select => {
    select.addEventListener('change', async () => {
      const resultado = await llamar(`${BASE}/contenedores.php`, {
        method: 'PUT',
        body: JSON.stringify({ id_contenedor: Number(select.dataset.id), estado: select.value }),
      });
      mostrarMensajeGlobal(resultado.mensaje, resultado.exito ? 'exito' : 'error');
      if (resultado.exito) cargarContenedores();
    });
  });
  cuerpo.querySelectorAll('.btn-baja-contenedor').forEach(boton => {
    boton.addEventListener('click', async () => {
      const resultado = await llamar(`${BASE}/contenedores.php`, {
        method: 'DELETE',
        body: JSON.stringify({ id_contenedor: Number(boton.dataset.id) }),
      });
      mostrarMensajeGlobal(resultado.mensaje, resultado.exito ? 'exito' : 'error');
      cargarContenedores();
    });
  });
}

document.getElementById('formContenedor').addEventListener('submit', async (evento) => {
  evento.preventDefault();
  const tipos = document.getElementById('cont_tipos').value.split(',').map(t => t.trim()).filter(Boolean);
  const idRuta = document.getElementById('cont_id_ruta').value;
  const resultado = await llamar(`${BASE}/contenedores.php`, {
    method: 'POST',
    body: JSON.stringify({
      coordenadas: document.getElementById('cont_coordenadas').value.trim(),
      nivel_llenado: Number(document.getElementById('cont_nivel').value || 0),
      id_ruta: idRuta ? Number(idRuta) : null,
      tipos_residuo: tipos,
    }),
  });
  mostrarMensajeGlobal(resultado.mensaje, resultado.exito ? 'exito' : 'error');
  if (resultado.exito) { document.getElementById('formContenedor').reset(); cargarContenedores(); }
});

// ---------------------------------------------------------------
// RUTAS
// ---------------------------------------------------------------
async function cargarRutas() {
  const datos = await llamar(`${BASE}/rutas.php`);
  const cuerpo = document.getElementById('tablaRutas');
  cuerpo.innerHTML = '';
  const selectContenedor = document.getElementById('cont_id_ruta');
  const selectCuadrilla = document.getElementById('cuad_rutas');
  selectContenedor.innerHTML = '<option value="">Sin ruta</option>';
  selectCuadrilla.innerHTML = '';
  if (!datos.exito) return;

  datos.rutas.forEach(r => {
    const fila = document.createElement('tr');
    fila.innerHTML = `
      <td>${r.id_ruta}</td><td>${r.nombre_zona}</td><td>${r.frecuencia}</td>
      <td><button class="peligro btn-eliminar-ruta" data-id="${r.id_ruta}">Eliminar</button></td>
    `;
    cuerpo.appendChild(fila);

    const opcion1 = new Option(r.nombre_zona, r.id_ruta);
    selectContenedor.appendChild(opcion1);
    const opcion2 = new Option(r.nombre_zona, r.id_ruta);
    selectCuadrilla.appendChild(opcion2);
  });

  cuerpo.querySelectorAll('.btn-eliminar-ruta').forEach(boton => {
    boton.addEventListener('click', async () => {
      const resultado = await llamar(`${BASE}/rutas.php`, {
        method: 'DELETE',
        body: JSON.stringify({ id_ruta: Number(boton.dataset.id) }),
      });
      mostrarMensajeGlobal(resultado.mensaje, resultado.exito ? 'exito' : 'error');
      cargarRutas();
    });
  });
}

document.getElementById('formRuta').addEventListener('submit', async (evento) => {
  evento.preventDefault();
  const resultado = await llamar(`${BASE}/rutas.php`, {
    method: 'POST',
    body: JSON.stringify({
      nombre_zona: document.getElementById('ruta_zona').value.trim(),
      frecuencia: document.getElementById('ruta_frecuencia').value.trim(),
    }),
  });
  mostrarMensajeGlobal(resultado.mensaje, resultado.exito ? 'exito' : 'error');
  if (resultado.exito) { document.getElementById('formRuta').reset(); cargarRutas(); }
});

// ---------------------------------------------------------------
// CUADRILLAS
// ---------------------------------------------------------------
async function cargarCuadrillas() {
  const datos = await llamar(`${BASE}/cuadrillas.php`);
  const cuerpo = document.getElementById('tablaCuadrillas');
  cuerpo.innerHTML = '';
  if (!datos.exito) return;

  datos.cuadrillas.forEach(c => {
    const rutas = c.rutas_habituales.map(r => r.nombre_zona).join(', ') || '—';
    const fila = document.createElement('tr');
    fila.innerHTML = `
      <td>${c.id_cuadrilla}</td><td>${c.nombre_cuadrilla}</td><td>${c.cantidad_integrantes}</td><td>${rutas}</td>
      <td><button class="peligro btn-eliminar-cuadrilla" data-id="${c.id_cuadrilla}">Eliminar</button></td>
    `;
    cuerpo.appendChild(fila);
  });

  cuerpo.querySelectorAll('.btn-eliminar-cuadrilla').forEach(boton => {
    boton.addEventListener('click', async () => {
      const resultado = await llamar(`${BASE}/cuadrillas.php`, {
        method: 'DELETE',
        body: JSON.stringify({ id_cuadrilla: Number(boton.dataset.id) }),
      });
      mostrarMensajeGlobal(resultado.mensaje, resultado.exito ? 'exito' : 'error');
      cargarCuadrillas();
    });
  });
}

document.getElementById('formCuadrilla').addEventListener('submit', async (evento) => {
  evento.preventDefault();
  const rutasSeleccionadas = Array.from(document.getElementById('cuad_rutas').selectedOptions).map(o => Number(o.value));
  const resultado = await llamar(`${BASE}/cuadrillas.php`, {
    method: 'POST',
    body: JSON.stringify({
      nombre_cuadrilla: document.getElementById('cuad_nombre').value.trim(),
      cantidad_integrantes: Number(document.getElementById('cuad_cantidad').value || 0),
      rutas_habituales: rutasSeleccionadas,
    }),
  });
  mostrarMensajeGlobal(resultado.mensaje, resultado.exito ? 'exito' : 'error');
  if (resultado.exito) { document.getElementById('formCuadrilla').reset(); cargarCuadrillas(); }
});

// ---------------------------------------------------------------
// CAMIONES
// ---------------------------------------------------------------
async function cargarCamiones() {
  const datos = await llamar(`${BASE}/camiones.php`);
  const cuerpo = document.getElementById('tablaCamiones');
  cuerpo.innerHTML = '';
  if (!datos.exito) return;

  datos.camiones.forEach(c => {
    const fila = document.createElement('tr');
    fila.innerHTML = `
      <td>${c.id_camion}</td><td>${c.matricula}</td><td>${c.marca} ${c.modelo}</td><td>${c.capacidad_carga}</td>
      <td>
        <select data-id="${c.id_camion}" class="select-estado-camion">
          <option value="DISPONIBLE" ${c.estado === 'DISPONIBLE' ? 'selected' : ''}>Disponible</option>
          <option value="EN_RUTA" ${c.estado === 'EN_RUTA' ? 'selected' : ''}>En ruta</option>
          <option value="EN_MANTENIMIENTO" ${c.estado === 'EN_MANTENIMIENTO' ? 'selected' : ''}>En mantenimiento</option>
          <option value="FUERA_DE_SERVICIO" ${c.estado === 'FUERA_DE_SERVICIO' ? 'selected' : ''}>Fuera de servicio</option>
        </select>
      </td>
      <td>${c.kilometraje}</td>
      <td><button class="peligro btn-baja-camion" data-id="${c.id_camion}">Dar de baja</button></td>
    `;
    cuerpo.appendChild(fila);
  });

  cuerpo.querySelectorAll('.select-estado-camion').forEach(select => {
    select.addEventListener('change', async () => {
      const resultado = await llamar(`${BASE}/camiones.php`, {
        method: 'PUT',
        body: JSON.stringify({ id_camion: Number(select.dataset.id), estado: select.value }),
      });
      mostrarMensajeGlobal(resultado.mensaje, resultado.exito ? 'exito' : 'error');
      if (resultado.exito) cargarCamiones();
    });
  });
  cuerpo.querySelectorAll('.btn-baja-camion').forEach(boton => {
    boton.addEventListener('click', async () => {
      const resultado = await llamar(`${BASE}/camiones.php`, {
        method: 'DELETE',
        body: JSON.stringify({ id_camion: Number(boton.dataset.id) }),
      });
      mostrarMensajeGlobal(resultado.mensaje, resultado.exito ? 'exito' : 'error');
      cargarCamiones();
    });
  });
}

document.getElementById('formCamion').addEventListener('submit', async (evento) => {
  evento.preventDefault();
  const resultado = await llamar(`${BASE}/camiones.php`, {
    method: 'POST',
    body: JSON.stringify({
      matricula: document.getElementById('cam_matricula').value.trim(),
      marca: document.getElementById('cam_marca').value.trim(),
      modelo: document.getElementById('cam_modelo').value.trim(),
      capacidad_carga: Number(document.getElementById('cam_capacidad').value),
    }),
  });
  mostrarMensajeGlobal(resultado.mensaje, resultado.exito ? 'exito' : 'error');
  if (resultado.exito) { document.getElementById('formCamion').reset(); cargarCamiones(); }
});

// ---------------------------------------------------------------
// INSTALACIONES
// ---------------------------------------------------------------
async function cargarInstalaciones() {
  const datos = await llamar(`${BASE}/instalaciones.php`);
  const cuerpo = document.getElementById('tablaInstalaciones');
  cuerpo.innerHTML = '';
  if (!datos.exito) return;

  datos.instalaciones.forEach(i => {
    const fila = document.createElement('tr');
    fila.innerHTML = `
      <td>${i.id_centro}</td><td>${i.nombre}</td>
      <td>${i.tipo_instalacion === 'CENTRO_ACOPIO' ? 'Centro de acopio' : 'Vertedero'}</td>
      <td>${i.coordenadas}</td><td>${i.capacidad_maxima}</td><td>${i.residuos_aceptados}</td>
      <td><button class="peligro btn-eliminar-instalacion" data-id="${i.id_centro}">Eliminar</button></td>
    `;
    cuerpo.appendChild(fila);
  });

  cuerpo.querySelectorAll('.btn-eliminar-instalacion').forEach(boton => {
    boton.addEventListener('click', async () => {
      const resultado = await llamar(`${BASE}/instalaciones.php`, {
        method: 'DELETE',
        body: JSON.stringify({ id_centro: Number(boton.dataset.id) }),
      });
      mostrarMensajeGlobal(resultado.mensaje, resultado.exito ? 'exito' : 'error');
      cargarInstalaciones();
    });
  });
}

document.getElementById('formInstalacion').addEventListener('submit', async (evento) => {
  evento.preventDefault();
  const resultado = await llamar(`${BASE}/instalaciones.php`, {
    method: 'POST',
    body: JSON.stringify({
      nombre: document.getElementById('inst_nombre').value.trim(),
      tipo_instalacion: document.getElementById('inst_tipo').value,
      coordenadas: document.getElementById('inst_coordenadas').value.trim(),
      capacidad_maxima: Number(document.getElementById('inst_capacidad').value),
      residuos_aceptados: document.getElementById('inst_residuos').value.trim(),
    }),
  });
  mostrarMensajeGlobal(resultado.mensaje, resultado.exito ? 'exito' : 'error');
  if (resultado.exito) { document.getElementById('formInstalacion').reset(); cargarInstalaciones(); }
});

// ---------------------------------------------------------------
// Carga inicial
// ---------------------------------------------------------------
(async function iniciar() {
  await cargarRutas();       // primero, porque llena los <select> que usan otras secciones
  await cargarContenedores();
  await cargarCuadrillas();
  await cargarCamiones();
  await cargarInstalaciones();
})();
