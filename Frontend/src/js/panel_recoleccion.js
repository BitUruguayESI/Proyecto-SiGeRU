const BASE_REC = '../api-recoleccion';
const BASE_GES = '../api-gestion';
const divMensajeGlobal = document.getElementById('mensajeGlobal');

function mostrarMensajeGlobal(texto, tipo) {
  mostrarToast(texto, tipo);
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
function activarPestania(nombre) {
  const boton = document.querySelector(`.pestanias button[data-seccion="${nombre}"]`);
  const seccion = document.getElementById('seccion-' + nombre);
  if (!boton || !seccion) return;
  document.querySelectorAll('.pestanias button').forEach(b => b.classList.remove('activa'));
  document.querySelectorAll('.seccion-panel').forEach(s => s.classList.remove('activa'));
  boton.classList.add('activa');
  seccion.classList.add('activa');
}

document.querySelectorAll('.pestanias button').forEach(boton => {
  boton.addEventListener('click', () => activarPestania(boton.dataset.seccion));
});

// Permite entrar directo a una pestaña por link (ej. desde el Dashboard:
// panel_recoleccion.html#asignaciones).
if (location.hash) {
  activarPestania(location.hash.slice(1));
}

// ---------------------------------------------------------------
// Datos de referencia (para llenar los <select>)
// ---------------------------------------------------------------
// Cuadrillas cargadas, indexadas por id_cuadrilla — se usan para completar
// solas el camión y el chofer de "Crear asignación diaria" a partir de la
// cuadrilla estable (ya no se eligen a mano ahí, ver actualizarInfoCuadrilla).
let cuadrillasPorId = {};

async function cargarReferencias() {
  const [cuadrillas, rutas, camiones, instalaciones, tiposResiduo] = await Promise.all([
    llamar(`${BASE_GES}/cuadrillas.php`),
    llamar(`${BASE_GES}/rutas.php`),
    llamar(`${BASE_GES}/camiones.php`),
    llamar(`${BASE_GES}/instalaciones.php`),
    llamar(`${BASE_GES}/tipos_residuo.php`),
  ]);

  const llenar = (idSelect, lista, campoValor, campoTexto) => {
    const select = document.getElementById(idSelect);
    lista.forEach(item => select.appendChild(new Option(item[campoTexto], item[campoValor])));
  };

  if (cuadrillas.exito) {
    llenar('asig_cuadrilla', cuadrillas.cuadrillas, 'id_cuadrilla', 'nombre_cuadrilla');
    cuadrillasPorId = Object.fromEntries(cuadrillas.cuadrillas.map(c => [String(c.id_cuadrilla), c]));
  }
  if (rutas.exito) llenar('asig_ruta', rutas.rutas, 'id_ruta', 'nombre_zona');
  if (camiones.exito) {
    ['desc_camion', 'mant_camion'].forEach(id => llenar(id, camiones.camiones, 'id_camion', 'matricula'));
    const filtro = document.getElementById('filtroMantCamion');
    camiones.camiones.forEach(c => filtro.appendChild(new Option(c.matricula, c.id_camion)));
  }
  if (instalaciones.exito) llenar('desc_instalacion', instalaciones.instalaciones, 'id_centro', 'nombre');
  if (tiposResiduo.exito) {
    llenar('desc_tipo_residuo', tiposResiduo.tipos_residuo, 'id_tipo_residuo', 'nombre');
  }

  actualizarInfoCuadrilla();
}

// Al elegir una cuadrilla en "Crear asignación diaria", camión y chofer ya
// no se piden de nuevo: son los de su cuadrilla estable (Gestión →
// Cuadrillas). Acá solo se muestran, y el select de recolectores se llena
// con los de esa cuadrilla, todos preseleccionados por si alguno falta hoy.
function actualizarInfoCuadrilla() {
  const idCuadrilla = document.getElementById('asig_cuadrilla').value;
  const info = document.getElementById('asig_infoCuadrilla');
  const selectRecolectores = document.getElementById('asig_recolectores');
  selectRecolectores.innerHTML = '';

  const cuadrilla = cuadrillasPorId[idCuadrilla];
  if (!cuadrilla) {
    info.className = 'ficha-cuadrilla vacia';
    info.textContent = 'Elegí una cuadrilla para ver su camión, chofer y recolectores asignados.';
    return;
  }

  const nombresRecolectores = (cuadrilla.recolectores || []).map(r => r.nombre_completo);

  info.className = 'ficha-cuadrilla';
  info.innerHTML = `
    <p><strong>Camión:</strong> ${cuadrilla.camion_matricula || 'sin asignar'}</p>
    <p><strong>Chofer:</strong> ${cuadrilla.chofer_nombre || 'sin asignar'}</p>
    <p><strong>Recolectores:</strong> ${nombresRecolectores.join(', ') || 'sin recolectores asignados'}</p>
    ${(!cuadrilla.id_camion || !cuadrilla.id_chofer)
      ? '<p style="color:#fc8181;">Esta cuadrilla todavía no tiene camión y/o chofer asignado. Asignalo primero en Gestión → Cuadrillas.</p>'
      : ''}
  `;

  (cuadrilla.recolectores || []).forEach(r => {
    const opcion = new Option(r.nombre_completo, r.id_operario);
    opcion.selected = true;
    selectRecolectores.appendChild(opcion);
  });
}

document.getElementById('asig_cuadrilla').addEventListener('change', actualizarInfoCuadrilla);

// ---------------------------------------------------------------
// ASIGNACIÓN DIARIA
// ---------------------------------------------------------------
// Asignaciones cargadas, indexadas por id_asignacion — para completar el
// formulario al editar (ver btn-editar-asignacion más abajo).
let asignacionesPorId = {};

async function cargarAsignaciones() {
  const mias = document.getElementById('filtroMias').checked ? '?mias=1' : '';
  const datos = await llamar(`${BASE_REC}/asignaciones.php${mias}`);
  const cuerpo = document.getElementById('tablaAsignaciones');
  cuerpo.innerHTML = '';
  if (!datos.exito) return;

  asignacionesPorId = {};

  if (!datos.asignaciones.length) {
    cuerpo.innerHTML = '<tr><td colspan="8" style="text-align:center; color:#a0aec0;">No hay asignaciones para este filtro.</td></tr>';
    return;
  }

  datos.asignaciones.forEach(a => {
    asignacionesPorId[a.id_asignacion] = a;
    const operarios = a.operarios.map(o => `${o.nombre_completo} (${o.rol_en_asignacion})`).join(', ') || '—';
    const fila = document.createElement('tr');
    fila.innerHTML = `
      <td>${a.id_asignacion}</td><td>${a.fecha}</td><td>${a.turno}</td>
      <td>${a.nombre_cuadrilla}</td><td>${a.nombre_zona}</td><td>${a.matricula}</td>
      <td>${operarios}</td>
      <td>
        <button class="chico btn-editar-asignacion" data-id="${a.id_asignacion}">Editar</button>
        <button class="peligro btn-eliminar-asignacion" data-id="${a.id_asignacion}">Eliminar</button>
      </td>
    `;
    cuerpo.appendChild(fila);
  });

  cuerpo.querySelectorAll('.btn-editar-asignacion').forEach(boton => {
    boton.addEventListener('click', () => {
      const a = asignacionesPorId[boton.dataset.id];
      document.getElementById('asig_id').value = a.id_asignacion;
      document.getElementById('asig_fecha').value = a.fecha;
      document.getElementById('asig_turno').value = a.turno;
      document.getElementById('asig_cuadrilla').value = String(a.id_cuadrilla);
      actualizarInfoCuadrilla();
      document.getElementById('asig_ruta').value = String(a.id_ruta);
      // Sobreescribe la preselección "todos los recolectores de la
      // cuadrilla" con los que realmente quedaron en esta asignación.
      const idsRecolectores = a.operarios
        .filter(o => o.rol_en_asignacion === 'Recolector')
        .map(o => String(o.id_operario));
      Array.from(document.getElementById('asig_recolectores').options).forEach(opcion => {
        opcion.selected = idsRecolectores.includes(opcion.value);
      });
      document.getElementById('asig_titulo_form').textContent = `Editar asignación #${a.id_asignacion}`;
      document.getElementById('asig_boton_submit').textContent = 'Guardar cambios';
      document.getElementById('asig_cancelar').hidden = false;
      document.getElementById('formAsignacion').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    });
  });
  cuerpo.querySelectorAll('.btn-eliminar-asignacion').forEach(boton => {
    boton.addEventListener('click', async () => {
      const resultado = await llamar(`${BASE_REC}/asignaciones.php`, {
        method: 'DELETE',
        body: JSON.stringify({ id_asignacion: Number(boton.dataset.id) }),
      });
      mostrarMensajeGlobal(resultado.mensaje, resultado.exito ? 'exito' : 'error');
      if (String(document.getElementById('asig_id').value) === String(boton.dataset.id)) {
        salirModoEdicionAsignacion();
      }
      cargarAsignaciones();
    });
  });
}

document.getElementById('filtroMias').addEventListener('change', cargarAsignaciones);

function salirModoEdicionAsignacion() {
  document.getElementById('formAsignacion').reset();
  document.getElementById('asig_id').value = '';
  document.getElementById('asig_titulo_form').textContent = 'Crear asignación diaria (RF23)';
  document.getElementById('asig_boton_submit').textContent = 'Crear asignación';
  document.getElementById('asig_cancelar').hidden = true;
  actualizarInfoCuadrilla();
}
document.getElementById('asig_cancelar').addEventListener('click', salirModoEdicionAsignacion);

document.getElementById('formAsignacion').addEventListener('submit', async (evento) => {
  evento.preventDefault();

  const idAsignacion = document.getElementById('asig_id').value;
  const idCuadrilla = document.getElementById('asig_cuadrilla').value;
  const cuadrilla = cuadrillasPorId[idCuadrilla];
  if (!cuadrilla || !cuadrilla.id_camion || !cuadrilla.id_chofer) {
    mostrarMensajeGlobal('Esa cuadrilla no tiene camión y chofer asignados todavía. Asignalo primero en Gestión → Cuadrillas.', 'error');
    return;
  }

  const operarios = [{ id_operario: Number(cuadrilla.id_chofer), rol_en_asignacion: 'Chofer' }];
  Array.from(document.getElementById('asig_recolectores').selectedOptions).forEach(o => {
    operarios.push({ id_operario: Number(o.value), rol_en_asignacion: 'Recolector' });
  });

  const cuerpo = {
    fecha: document.getElementById('asig_fecha').value,
    turno: document.getElementById('asig_turno').value,
    id_cuadrilla: Number(idCuadrilla),
    id_ruta: Number(document.getElementById('asig_ruta').value),
    id_camion: Number(cuadrilla.id_camion),
    operarios,
  };

  const resultado = idAsignacion
    ? await llamar(`${BASE_REC}/asignaciones.php`, { method: 'PUT', body: JSON.stringify({ id_asignacion: Number(idAsignacion), ...cuerpo }) })
    : await llamar(`${BASE_REC}/asignaciones.php`, { method: 'POST', body: JSON.stringify(cuerpo) });

  mostrarMensajeGlobal(resultado.mensaje, resultado.exito ? 'exito' : 'error');
  if (resultado.exito) { salirModoEdicionAsignacion(); cargarAsignaciones(); }
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
    cuerpo.innerHTML = '<tr><td colspan="7" style="text-align:center; color:#a0aec0;">Todavía no hay descargas registradas.</td></tr>';
    return;
  }

  datos.descargas.forEach(d => {
    const fila = document.createElement('tr');
    fila.innerHTML = `
      <td>${d.id_descarga}</td><td>${d.matricula}</td><td>${d.instalacion}</td>
      <td>${d.fecha_descarga}</td><td>${d.hora_descarga}</td><td>${d.peso}</td>
      <td>${d.nombre_tipo_residuo || '<em style="color:#a0aec0;">sin clasificar</em>'}</td>
    `;
    cuerpo.appendChild(fila);
  });
}

document.getElementById('formDescarga').addEventListener('submit', async (evento) => {
  evento.preventDefault();
  const idTipoResiduo = document.getElementById('desc_tipo_residuo').value;
  const resultado = await llamar(`${BASE_REC}/descargas.php`, {
    method: 'POST',
    body: JSON.stringify({
      id_camion: Number(document.getElementById('desc_camion').value),
      id_centro: Number(document.getElementById('desc_instalacion').value),
      fecha_descarga: document.getElementById('desc_fecha').value,
      hora_descarga: document.getElementById('desc_hora').value,
      peso: Number(document.getElementById('desc_peso').value),
      id_tipo_residuo: idTipoResiduo ? Number(idTipoResiduo) : null,
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
    cuerpo.innerHTML = '<tr><td colspan="6" style="text-align:center; color:#a0aec0;">No hay mantenimientos registrados.</td></tr>';
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
  const sesion = await obtenerSesion();
  const rol = rolDeSesion(sesion);
  // Este panel es de Administrador (todas las pestañas) o Chofer (solo
  // Descargas, para registrar sus descargas — RN de dashboards por rol).
  // El Recolector no tiene link a esta página, pero por las dudas si
  // entra por URL directa, lo mandamos a "Mi día".
  if (rol === 'RECOLECTOR') {
    window.location.href = 'mi_dia.html';
    return;
  }
  const esAdministrador = rol === 'ADMINISTRADOR';

  if (!esAdministrador) {
    // Chofer: solo le sirve la pestaña de Descargas.
    document.querySelector('.pestanias button[data-seccion="asignaciones"]').hidden = true;
    document.querySelector('.pestanias button[data-seccion="mantenimiento"]').hidden = true;
    activarPestania('descargas');
  }

  await cargarReferencias();
  if (esAdministrador) {
    await cargarAsignaciones();
    await cargarMantenimiento();
  } else {
    // Le fijamos de entrada el camión de SU cuadrilla (si ya se lo asignaron),
    // así no tiene que buscarlo en la lista de todos los camiones.
    const propia = await llamar(`${BASE_GES}/cuadrillas.php?propia=1`);
    if (propia.exito && propia.cuadrilla && propia.cuadrilla.id_camion) {
      const selectCamion = document.getElementById('desc_camion');
      selectCamion.value = String(propia.cuadrilla.id_camion);
    }
  }
  await cargarDescargas();
})();
