const BASE_REC = '../api-recoleccion';
const BASE_GES = '../api-gestion';
const BASE_USU = '../api-usuarios';
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
// Datos de referencia (para llenar los <select>)
// ---------------------------------------------------------------
async function cargarReferencias() {
  const [cuadrillas, rutas, camiones, instalaciones, operarios] = await Promise.all([
    llamar(`${BASE_GES}/cuadrillas.php`),
    llamar(`${BASE_GES}/rutas.php`),
    llamar(`${BASE_GES}/camiones.php`),
    llamar(`${BASE_GES}/instalaciones.php`),
    llamar(`${BASE_USU}/listar_operarios.php`).catch(() => ({ exito: false })), // 403 si es Operario
  ]);

  const llenar = (idSelect, lista, campoValor, campoTexto) => {
    const select = document.getElementById(idSelect);
    lista.forEach(item => select.appendChild(new Option(item[campoTexto], item[campoValor])));
  };

  if (cuadrillas.exito) llenar('asig_cuadrilla', cuadrillas.cuadrillas, 'id_cuadrilla', 'nombre_cuadrilla');
  if (rutas.exito) llenar('asig_ruta', rutas.rutas, 'id_ruta', 'nombre_zona');
  if (camiones.exito) {
    ['asig_camion', 'desc_camion', 'mant_camion'].forEach(id => llenar(id, camiones.camiones, 'id_camion', 'matricula'));
    const filtro = document.getElementById('filtroMantCamion');
    camiones.camiones.forEach(c => filtro.appendChild(new Option(c.matricula, c.id_camion)));
  }
  if (instalaciones.exito) llenar('desc_instalacion', instalaciones.instalaciones, 'id_centro', 'nombre');
  if (operarios.exito) {
    operarios.operarios.forEach(o => {
      document.getElementById('asig_chofer').appendChild(new Option(`${o.nombre_completo} (${o.cargo})`, o.id_operario));
      document.getElementById('asig_recolectores').appendChild(new Option(`${o.nombre_completo} (${o.cargo})`, o.id_operario));
    });
  }
}

// ---------------------------------------------------------------
// ASIGNACIÓN DIARIA
// ---------------------------------------------------------------
async function cargarAsignaciones() {
  const mias = document.getElementById('filtroMias').checked ? '?mias=1' : '';
  const datos = await llamar(`${BASE_REC}/asignaciones.php${mias}`);
  const cuerpo = document.getElementById('tablaAsignaciones');
  cuerpo.innerHTML = '';
  if (!datos.exito) return;

  if (!datos.asignaciones.length) {
    cuerpo.innerHTML = '<tr><td colspan="8" style="text-align:center; color:#777;">No hay asignaciones para este filtro.</td></tr>';
    return;
  }

  datos.asignaciones.forEach(a => {
    const operarios = a.operarios.map(o => `${o.nombre_completo} (${o.rol_en_asignacion})`).join(', ') || '—';
    const fila = document.createElement('tr');
    fila.innerHTML = `
      <td>${a.id_asignacion}</td><td>${a.fecha}</td><td>${a.turno}</td>
      <td>${a.nombre_cuadrilla}</td><td>${a.nombre_zona}</td><td>${a.matricula}</td>
      <td>${operarios}</td>
      <td><button class="peligro btn-eliminar-asignacion" data-id="${a.id_asignacion}">Eliminar</button></td>
    `;
    cuerpo.appendChild(fila);
  });

  cuerpo.querySelectorAll('.btn-eliminar-asignacion').forEach(boton => {
    boton.addEventListener('click', async () => {
      const resultado = await llamar(`${BASE_REC}/asignaciones.php`, {
        method: 'DELETE',
        body: JSON.stringify({ id_asignacion: Number(boton.dataset.id) }),
      });
      mostrarMensajeGlobal(resultado.mensaje, resultado.exito ? 'exito' : 'error');
      cargarAsignaciones();
    });
  });
}

document.getElementById('filtroMias').addEventListener('change', cargarAsignaciones);

document.getElementById('formAsignacion').addEventListener('submit', async (evento) => {
  evento.preventDefault();

  const operarios = [];
  const chofer = document.getElementById('asig_chofer').value;
  if (chofer) operarios.push({ id_operario: Number(chofer), rol_en_asignacion: 'Chofer' });
  Array.from(document.getElementById('asig_recolectores').selectedOptions).forEach(o => {
    operarios.push({ id_operario: Number(o.value), rol_en_asignacion: 'Recolector' });
  });

  const resultado = await llamar(`${BASE_REC}/asignaciones.php`, {
    method: 'POST',
    body: JSON.stringify({
      fecha: document.getElementById('asig_fecha').value,
      turno: document.getElementById('asig_turno').value,
      id_cuadrilla: Number(document.getElementById('asig_cuadrilla').value),
      id_ruta: Number(document.getElementById('asig_ruta').value),
      id_camion: Number(document.getElementById('asig_camion').value),
      operarios,
    }),
  });
  mostrarMensajeGlobal(resultado.mensaje, resultado.exito ? 'exito' : 'error');
  if (resultado.exito) { document.getElementById('formAsignacion').reset(); cargarAsignaciones(); }
});

// ---------------------------------------------------------------
// DESCARGAS
// ---------------------------------------------------------------
async function cargarDescargas() {
  const datos = await llamar(`${BASE_REC}/descargas.php`);
  const cuerpo = document.getElementById('tablaDescargas');
  cuerpo.innerHTML = '';
  if (!datos.exito) return;

  if (!datos.descargas.length) {
    cuerpo.innerHTML = '<tr><td colspan="6" style="text-align:center; color:#777;">Todavía no hay descargas registradas.</td></tr>';
    return;
  }

  datos.descargas.forEach(d => {
    const fila = document.createElement('tr');
    fila.innerHTML = `
      <td>${d.id_descarga}</td><td>${d.matricula}</td><td>${d.instalacion}</td>
      <td>${d.fecha_descarga}</td><td>${d.hora_descarga}</td><td>${d.peso}</td>
    `;
    cuerpo.appendChild(fila);
  });
}

document.getElementById('formDescarga').addEventListener('submit', async (evento) => {
  evento.preventDefault();
  const resultado = await llamar(`${BASE_REC}/descargas.php`, {
    method: 'POST',
    body: JSON.stringify({
      id_camion: Number(document.getElementById('desc_camion').value),
      id_centro: Number(document.getElementById('desc_instalacion').value),
      fecha_descarga: document.getElementById('desc_fecha').value,
      hora_descarga: document.getElementById('desc_hora').value,
      peso: Number(document.getElementById('desc_peso').value),
    }),
  });
  mostrarMensajeGlobal(resultado.mensaje, resultado.exito ? 'exito' : 'error');
  if (resultado.exito) { document.getElementById('formDescarga').reset(); cargarDescargas(); }
});

// ---------------------------------------------------------------
// MANTENIMIENTO
// ---------------------------------------------------------------
async function cargarMantenimiento() {
  const idCamion = document.getElementById('filtroMantCamion').value;
  const url = idCamion ? `${BASE_REC}/mantenimiento.php?id_camion=${idCamion}` : `${BASE_REC}/mantenimiento.php`;
  const datos = await llamar(url);
  const cuerpo = document.getElementById('tablaMantenimiento');
  cuerpo.innerHTML = '';
  if (!datos.exito) return;

  if (!datos.mantenimientos.length) {
    cuerpo.innerHTML = '<tr><td colspan="6" style="text-align:center; color:#777;">No hay mantenimientos registrados.</td></tr>';
    return;
  }

  datos.mantenimientos.forEach(m => {
    const fila = document.createElement('tr');
    fila.innerHTML = `
      <td>${m.id_mantenimiento}</td><td>${m.matricula}</td><td>${m.fecha}</td>
      <td>${m.kilometraje}</td><td>${m.tipo_mantenimiento}</td><td>${m.descripcion || '—'}</td>
    `;
    cuerpo.appendChild(fila);
  });
}

document.getElementById('filtroMantCamion').addEventListener('change', cargarMantenimiento);

document.getElementById('formMantenimiento').addEventListener('submit', async (evento) => {
  evento.preventDefault();
  const resultado = await llamar(`${BASE_REC}/mantenimiento.php`, {
    method: 'POST',
    body: JSON.stringify({
      id_camion: Number(document.getElementById('mant_camion').value),
      fecha: document.getElementById('mant_fecha').value,
      kilometraje: Number(document.getElementById('mant_km').value),
      tipo_mantenimiento: document.getElementById('mant_tipo').value.trim(),
      descripcion: document.getElementById('mant_descripcion').value.trim(),
    }),
  });
  mostrarMensajeGlobal(resultado.mensaje, resultado.exito ? 'exito' : 'error');
  if (resultado.exito) { document.getElementById('formMantenimiento').reset(); cargarMantenimiento(); }
});

// ---------------------------------------------------------------
// Carga inicial
// ---------------------------------------------------------------
(async function iniciar() {
  await cargarReferencias();
  await cargarAsignaciones();
  await cargarDescargas();
  await cargarMantenimiento();
})();
