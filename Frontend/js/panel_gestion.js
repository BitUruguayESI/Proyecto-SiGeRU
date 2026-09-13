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
// USUARIOS (CRUD completo: listar todos, editar, dar de baja/reactivar)
// ---------------------------------------------------------------
const BASE_USUARIOS = '../api-usuarios';
const formUsuario = document.getElementById('formUsuario');

async function cargarUsuarios() {
  const tipo = document.getElementById('usu_filtro_tipo').value;
  const url = tipo ? `${BASE_USUARIOS}/usuarios.php?tipo_usuario=${tipo}` : `${BASE_USUARIOS}/usuarios.php`;
  const datos = await llamar(url);
  const cuerpo = document.getElementById('tablaUsuarios');
  cuerpo.innerHTML = '';
  if (!datos.exito) return;

  datos.usuarios.forEach(u => {
    const fila = document.createElement('tr');
    fila.innerHTML = `
      <td>${u.ci}</td><td>${u.nombre_completo}</td><td>${u.email}</td>
      <td>${u.tipo_usuario}</td><td>${u.cargo || '—'}</td>
      <td>${u.estado === 'ACTIVO' ? 'Activo' : 'Inactivo'}</td>
      <td>
        <button class="chico btn-editar-usuario" data-ci="${u.ci}">Editar</button>
        ${u.estado === 'ACTIVO'
          ? `<button class="peligro btn-baja-usuario" data-ci="${u.ci}">Dar de baja</button>`
          : `<button class="chico btn-reactivar-usuario" data-ci="${u.ci}">Reactivar</button>`}
      </td>
    `;
    cuerpo.appendChild(fila);
  });

  cuerpo.querySelectorAll('.btn-editar-usuario').forEach(boton => {
    boton.addEventListener('click', () => abrirEdicionUsuario(boton.dataset.ci));
  });
  cuerpo.querySelectorAll('.btn-baja-usuario').forEach(boton => {
    boton.addEventListener('click', async () => {
      if (!confirm(`¿Dar de baja al usuario ${boton.dataset.ci}?`)) return;
      const resultado = await llamar(`${BASE_USUARIOS}/usuarios.php`, {
        method: 'DELETE',
        body: JSON.stringify({ ci: boton.dataset.ci }),
      });
      mostrarMensajeGlobal(resultado.mensaje, resultado.exito ? 'exito' : 'error');
      cargarUsuarios();
    });
  });
  cuerpo.querySelectorAll('.btn-reactivar-usuario').forEach(boton => {
    boton.addEventListener('click', async () => {
      const resultado = await llamar(`${BASE_USUARIOS}/usuarios.php`, {
        method: 'PUT',
        body: JSON.stringify({ ci: boton.dataset.ci, estado: 'ACTIVO' }),
      });
      mostrarMensajeGlobal(resultado.mensaje, resultado.exito ? 'exito' : 'error');
      cargarUsuarios();
    });
  });
}

async function abrirEdicionUsuario(ci) {
  const datos = await llamar(`${BASE_USUARIOS}/usuarios.php?ci=${encodeURIComponent(ci)}`);
  if (!datos.exito) { mostrarMensajeGlobal(datos.mensaje, 'error'); return; }

  const u = datos.usuario;
  document.getElementById('usu_sin_seleccion').hidden = true;
  formUsuario.hidden = false;
  document.getElementById('usu_ci').value = u.ci;
  document.getElementById('usu_ci_visible').textContent = `${u.ci} (${u.tipo_usuario})`;
  document.getElementById('usu_nombre').value = u.nombre_completo;
  document.getElementById('usu_email').value = u.email;
  document.getElementById('usu_telefonos').value = u.telefonos.join(', ');
  document.getElementById('usu_contrasena').value = '';
  document.getElementById('usu_cargo_contenedor').style.display = u.tipo_usuario === 'OPERARIO' ? '' : 'none';
  document.getElementById('usu_cargo').value = u.cargo || '';
  formUsuario.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

document.getElementById('usu_filtro_tipo').addEventListener('change', cargarUsuarios);

document.getElementById('usu_cancelar').addEventListener('click', () => {
  formUsuario.reset();
  formUsuario.hidden = true;
  document.getElementById('usu_sin_seleccion').hidden = false;
});

formUsuario.addEventListener('submit', async (evento) => {
  evento.preventDefault();
  const telefonos = document.getElementById('usu_telefonos').value.split(',').map(t => t.trim()).filter(Boolean);
  const cuerpo = {
    ci: document.getElementById('usu_ci').value,
    nombre_completo: document.getElementById('usu_nombre').value.trim(),
    email: document.getElementById('usu_email').value.trim(),
    telefonos,
  };
  const cargo = document.getElementById('usu_cargo').value.trim();
  if (document.getElementById('usu_cargo_contenedor').style.display !== 'none') {
    cuerpo.cargo = cargo;
  }
  const contrasena = document.getElementById('usu_contrasena').value;
  if (contrasena) cuerpo.contrasena = contrasena;

  const resultado = await llamar(`${BASE_USUARIOS}/usuarios.php`, {
    method: 'PUT',
    body: JSON.stringify(cuerpo),
  });
  mostrarMensajeGlobal(resultado.mensaje, resultado.exito ? 'exito' : 'error');
  if (resultado.exito) {
    formUsuario.reset();
    formUsuario.hidden = true;
    document.getElementById('usu_sin_seleccion').hidden = false;
    cargarUsuarios();
  }
});

// ---------------------------------------------------------------
// CONTENEDORES + mapa interactivo (RF13, Leaflet + OpenStreetMap)
// ---------------------------------------------------------------
const COLOR_ESTADO_CONTENEDOR = {
  OPERATIVO: '#2E7D32', DESBORDADO: '#C0392B', FUERA_DE_SERVICIO: '#888888',
};

let mapaContenedores = null;
let capaMarcadoresContenedores = null;

function iniciarMapaContenedores() {
  if (mapaContenedores || typeof L === 'undefined') return;
  mapaContenedores = L.map('mapaContenedores').setView([-34.9011, -56.1645], 12); // Montevideo
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    maxZoom: 19,
  }).addTo(mapaContenedores);
  capaMarcadoresContenedores = L.layerGroup().addTo(mapaContenedores);
}

function actualizarMapaContenedores(contenedores) {
  const aviso = document.getElementById('mapaContenedoresAviso');
  if (!mapaContenedores) {
    aviso.hidden = false;
    aviso.textContent = 'No se pudo cargar el mapa (¿sin conexión a internet para Leaflet/OpenStreetMap?).';
    return;
  }

  capaMarcadoresContenedores.clearLayers();
  const puntos = [];

  contenedores.forEach(c => {
    if (!c.coordenadas) return;
    const [lat, lon] = c.coordenadas.split(',').map(Number);
    if (Number.isNaN(lat) || Number.isNaN(lon)) return;

    L.circleMarker([lat, lon], {
      radius: 8,
      color: '#fff',
      weight: 2,
      fillColor: COLOR_ESTADO_CONTENEDOR[c.estado] || '#888',
      fillOpacity: 0.9,
    })
      .bindPopup(`<strong>${c.direccion || 'Contenedor #' + c.id_contenedor}</strong><br>Estado: ${c.estado}<br>Nivel de llenado: ${c.nivel_llenado}%`)
      .addTo(capaMarcadoresContenedores);
    puntos.push([lat, lon]);
  });

  const sinUbicar = contenedores.length - puntos.length;
  if (puntos.length) {
    mapaContenedores.fitBounds(puntos, { padding: [24, 24], maxZoom: 15 });
  }
  if (sinUbicar > 0) {
    aviso.hidden = false;
    aviso.textContent = puntos.length
      ? `${sinUbicar} de ${contenedores.length} contenedores no se pudieron ubicar en el mapa todavía (dirección no encontrada o sin conexión al geocodificar).`
      : 'Ningún contenedor tiene ubicación en el mapa todavía (revisá la conexión a internet, o editá la dirección para reintentar).';
  } else {
    aviso.hidden = true;
  }
}

async function cargarContenedores() {
  const datos = await llamar(`${BASE}/contenedores.php`);
  const cuerpo = document.getElementById('tablaContenedores');
  cuerpo.innerHTML = '';
  if (!datos.exito) return;

  const contenedoresPorId = {};
  datos.contenedores.forEach(c => {
    contenedoresPorId[c.id_contenedor] = c;
    const fila = document.createElement('tr');
    fila.innerHTML = `
      <td>${c.id_contenedor}</td>
      <td>${c.direccion || '<em style="color:#999;">sin dirección cargada</em>'}</td>
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
      <td>
        <button class="chico btn-editar-contenedor" data-id="${c.id_contenedor}">Editar</button>
        <button class="peligro btn-baja-contenedor" data-id="${c.id_contenedor}">Dar de baja</button>
      </td>
    `;
    cuerpo.appendChild(fila);
  });

  actualizarMapaContenedores(datos.contenedores);

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
  cuerpo.querySelectorAll('.btn-editar-contenedor').forEach(boton => {
    boton.addEventListener('click', () => {
      const c = contenedoresPorId[boton.dataset.id];
      document.getElementById('cont_id').value = c.id_contenedor;
      document.getElementById('cont_direccion').value = c.direccion || '';
      document.getElementById('cont_id_ruta').value = c.id_ruta || '';
      document.getElementById('cont_nivel').value = c.nivel_llenado;
      document.getElementById('cont_tipos').value = c.tipos_residuo.join(', ');
      document.getElementById('cont_titulo_form').textContent = `Editar contenedor #${c.id_contenedor}`;
      document.getElementById('cont_boton_submit').textContent = 'Guardar cambios';
      document.getElementById('cont_cancelar').hidden = false;
      document.getElementById('formContenedor').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
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

function salirModoEdicionContenedor() {
  document.getElementById('formContenedor').reset();
  document.getElementById('cont_id').value = '';
  document.getElementById('cont_titulo_form').textContent = 'Registrar contenedor (RF07)';
  document.getElementById('cont_boton_submit').textContent = 'Registrar contenedor';
  document.getElementById('cont_cancelar').hidden = true;
}
document.getElementById('cont_cancelar').addEventListener('click', salirModoEdicionContenedor);

document.getElementById('formContenedor').addEventListener('submit', async (evento) => {
  evento.preventDefault();
  const idContenedor = document.getElementById('cont_id').value;
  const tipos = document.getElementById('cont_tipos').value.split(',').map(t => t.trim()).filter(Boolean);
  const idRuta = document.getElementById('cont_id_ruta').value;
  const cuerpo = {
    direccion: document.getElementById('cont_direccion').value.trim(),
    nivel_llenado: Number(document.getElementById('cont_nivel').value || 0),
    id_ruta: idRuta ? Number(idRuta) : null,
    tipos_residuo: tipos,
  };
  const resultado = idContenedor
    ? await llamar(`${BASE}/contenedores.php`, { method: 'PUT', body: JSON.stringify({ id_contenedor: Number(idContenedor), ...cuerpo }) })
    : await llamar(`${BASE}/contenedores.php`, { method: 'POST', body: JSON.stringify(cuerpo) });
  mostrarMensajeGlobal(resultado.mensaje, resultado.exito ? 'exito' : 'error');
  if (resultado.exito) { salirModoEdicionContenedor(); cargarContenedores(); }
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
      <td>
        <button class="chico btn-editar-ruta" data-id="${r.id_ruta}" data-zona="${r.nombre_zona}" data-frecuencia="${r.frecuencia}">Editar</button>
        <button class="peligro btn-eliminar-ruta" data-id="${r.id_ruta}">Eliminar</button>
      </td>
    `;
    cuerpo.appendChild(fila);

    const opcion1 = new Option(r.nombre_zona, r.id_ruta);
    selectContenedor.appendChild(opcion1);
    const opcion2 = new Option(r.nombre_zona, r.id_ruta);
    selectCuadrilla.appendChild(opcion2);
  });

  cuerpo.querySelectorAll('.btn-editar-ruta').forEach(boton => {
    boton.addEventListener('click', () => {
      document.getElementById('ruta_id').value = boton.dataset.id;
      document.getElementById('ruta_zona').value = boton.dataset.zona;
      document.getElementById('ruta_frecuencia').value = boton.dataset.frecuencia;
      document.getElementById('ruta_titulo_form').textContent = `Editar ruta #${boton.dataset.id}`;
      document.getElementById('ruta_boton_submit').textContent = 'Guardar cambios';
      document.getElementById('ruta_cancelar').hidden = false;
      document.getElementById('formRuta').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    });
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

function salirModoEdicionRuta() {
  document.getElementById('formRuta').reset();
  document.getElementById('ruta_id').value = '';
  document.getElementById('ruta_titulo_form').textContent = 'Registrar ruta (RF17 / RF22)';
  document.getElementById('ruta_boton_submit').textContent = 'Registrar ruta';
  document.getElementById('ruta_cancelar').hidden = true;
}
document.getElementById('ruta_cancelar').addEventListener('click', salirModoEdicionRuta);

document.getElementById('formRuta').addEventListener('submit', async (evento) => {
  evento.preventDefault();
  const idRuta = document.getElementById('ruta_id').value;
  const cuerpo = {
    nombre_zona: document.getElementById('ruta_zona').value.trim(),
    frecuencia: document.getElementById('ruta_frecuencia').value.trim(),
  };
  const resultado = idRuta
    ? await llamar(`${BASE}/rutas.php`, { method: 'PUT', body: JSON.stringify({ id_ruta: Number(idRuta), ...cuerpo }) })
    : await llamar(`${BASE}/rutas.php`, { method: 'POST', body: JSON.stringify(cuerpo) });
  mostrarMensajeGlobal(resultado.mensaje, resultado.exito ? 'exito' : 'error');
  if (resultado.exito) { salirModoEdicionRuta(); cargarRutas(); }
});

// ---------------------------------------------------------------
// CUADRILLAS
// ---------------------------------------------------------------
async function cargarCuadrillas() {
  const datos = await llamar(`${BASE}/cuadrillas.php`);
  const cuerpo = document.getElementById('tablaCuadrillas');
  cuerpo.innerHTML = '';
  if (!datos.exito) return;

  const cuadrillasPorId = {};
  datos.cuadrillas.forEach(c => {
    cuadrillasPorId[c.id_cuadrilla] = c;
    const rutas = c.rutas_habituales.map(r => r.nombre_zona).join(', ') || '—';
    const fila = document.createElement('tr');
    fila.innerHTML = `
      <td>${c.id_cuadrilla}</td><td>${c.nombre_cuadrilla}</td><td>${c.cantidad_integrantes}</td><td>${rutas}</td>
      <td>
        <button class="chico btn-editar-cuadrilla" data-id="${c.id_cuadrilla}">Editar</button>
        <button class="peligro btn-eliminar-cuadrilla" data-id="${c.id_cuadrilla}">Eliminar</button>
      </td>
    `;
    cuerpo.appendChild(fila);
  });

  cuerpo.querySelectorAll('.btn-editar-cuadrilla').forEach(boton => {
    boton.addEventListener('click', () => {
      const c = cuadrillasPorId[boton.dataset.id];
      document.getElementById('cuad_id').value = c.id_cuadrilla;
      document.getElementById('cuad_nombre').value = c.nombre_cuadrilla;
      document.getElementById('cuad_cantidad').value = c.cantidad_integrantes;
      const idsHabituales = c.rutas_habituales.map(r => String(r.id_ruta));
      Array.from(document.getElementById('cuad_rutas').options).forEach(opcion => {
        opcion.selected = idsHabituales.includes(opcion.value);
      });
      document.getElementById('cuad_titulo_form').textContent = `Editar cuadrilla #${c.id_cuadrilla}`;
      document.getElementById('cuad_boton_submit').textContent = 'Guardar cambios';
      document.getElementById('cuad_cancelar').hidden = false;
      document.getElementById('formCuadrilla').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    });
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

function salirModoEdicionCuadrilla() {
  document.getElementById('formCuadrilla').reset();
  document.getElementById('cuad_id').value = '';
  document.getElementById('cuad_titulo_form').textContent = 'Registrar cuadrilla';
  document.getElementById('cuad_boton_submit').textContent = 'Registrar cuadrilla';
  document.getElementById('cuad_cancelar').hidden = true;
}
document.getElementById('cuad_cancelar').addEventListener('click', salirModoEdicionCuadrilla);

document.getElementById('formCuadrilla').addEventListener('submit', async (evento) => {
  evento.preventDefault();
  const idCuadrilla = document.getElementById('cuad_id').value;
  const rutasSeleccionadas = Array.from(document.getElementById('cuad_rutas').selectedOptions).map(o => Number(o.value));
  const cuerpo = {
    nombre_cuadrilla: document.getElementById('cuad_nombre').value.trim(),
    cantidad_integrantes: Number(document.getElementById('cuad_cantidad').value || 0),
    rutas_habituales: rutasSeleccionadas,
  };
  const resultado = idCuadrilla
    ? await llamar(`${BASE}/cuadrillas.php`, { method: 'PUT', body: JSON.stringify({ id_cuadrilla: Number(idCuadrilla), ...cuerpo }) })
    : await llamar(`${BASE}/cuadrillas.php`, { method: 'POST', body: JSON.stringify(cuerpo) });
  mostrarMensajeGlobal(resultado.mensaje, resultado.exito ? 'exito' : 'error');
  if (resultado.exito) { salirModoEdicionCuadrilla(); cargarCuadrillas(); }
});

// ---------------------------------------------------------------
// CAMIONES
// ---------------------------------------------------------------
async function cargarCamiones() {
  const datos = await llamar(`${BASE}/camiones.php`);
  const cuerpo = document.getElementById('tablaCamiones');
  cuerpo.innerHTML = '';
  if (!datos.exito) return;

  const camionesPorId = {};
  datos.camiones.forEach(c => {
    camionesPorId[c.id_camion] = c;
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
      <td>
        <button class="chico btn-editar-camion" data-id="${c.id_camion}">Editar</button>
        <button class="peligro btn-baja-camion" data-id="${c.id_camion}">Dar de baja</button>
      </td>
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
  cuerpo.querySelectorAll('.btn-editar-camion').forEach(boton => {
    boton.addEventListener('click', () => {
      const c = camionesPorId[boton.dataset.id];
      document.getElementById('cam_id').value = c.id_camion;
      document.getElementById('cam_matricula').value = c.matricula;
      document.getElementById('cam_marca').value = c.marca;
      document.getElementById('cam_modelo').value = c.modelo;
      document.getElementById('cam_capacidad').value = c.capacidad_carga;
      document.getElementById('cam_kilometraje').value = c.kilometraje;
      document.getElementById('cam_titulo_form').textContent = `Editar camión #${c.id_camion}`;
      document.getElementById('cam_boton_submit').textContent = 'Guardar cambios';
      document.getElementById('cam_cancelar').hidden = false;
      document.getElementById('formCamion').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
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

function salirModoEdicionCamion() {
  document.getElementById('formCamion').reset();
  document.getElementById('cam_id').value = '';
  document.getElementById('cam_titulo_form').textContent = 'Registrar camión (RF26)';
  document.getElementById('cam_boton_submit').textContent = 'Registrar camión';
  document.getElementById('cam_cancelar').hidden = true;
}
document.getElementById('cam_cancelar').addEventListener('click', salirModoEdicionCamion);

document.getElementById('formCamion').addEventListener('submit', async (evento) => {
  evento.preventDefault();
  const idCamion = document.getElementById('cam_id').value;
  const cuerpo = {
    matricula: document.getElementById('cam_matricula').value.trim(),
    marca: document.getElementById('cam_marca').value.trim(),
    modelo: document.getElementById('cam_modelo').value.trim(),
    capacidad_carga: Number(document.getElementById('cam_capacidad').value),
    kilometraje: Number(document.getElementById('cam_kilometraje').value || 0),
  };
  const resultado = idCamion
    ? await llamar(`${BASE}/camiones.php`, { method: 'PUT', body: JSON.stringify({ id_camion: Number(idCamion), ...cuerpo }) })
    : await llamar(`${BASE}/camiones.php`, { method: 'POST', body: JSON.stringify(cuerpo) });
  mostrarMensajeGlobal(resultado.mensaje, resultado.exito ? 'exito' : 'error');
  if (resultado.exito) { salirModoEdicionCamion(); cargarCamiones(); }
});

// ---------------------------------------------------------------
// INSTALACIONES
// ---------------------------------------------------------------
async function cargarInstalaciones() {
  const datos = await llamar(`${BASE}/instalaciones.php`);
  const cuerpo = document.getElementById('tablaInstalaciones');
  const selectMaquinaria = document.getElementById('maq_id_centro');
  cuerpo.innerHTML = '';
  selectMaquinaria.innerHTML = '';
  if (!datos.exito) return;

  const instalacionesPorId = {};
  datos.instalaciones.forEach(i => {
    instalacionesPorId[i.id_centro] = i;
    const fila = document.createElement('tr');
    fila.innerHTML = `
      <td>${i.id_centro}</td><td>${i.nombre}</td>
      <td>${i.tipo_instalacion === 'CENTRO_ACOPIO' ? 'Centro de acopio' : 'Vertedero'}</td>
      <td>${i.coordenadas}</td><td>${i.capacidad_maxima}</td><td>${i.residuos_aceptados}</td>
      <td>
        <button class="chico btn-editar-instalacion" data-id="${i.id_centro}">Editar</button>
        <button class="peligro btn-eliminar-instalacion" data-id="${i.id_centro}">Eliminar</button>
      </td>
    `;
    cuerpo.appendChild(fila);
    selectMaquinaria.appendChild(new Option(i.nombre, i.id_centro));
  });

  cuerpo.querySelectorAll('.btn-editar-instalacion').forEach(boton => {
    boton.addEventListener('click', () => {
      const i = instalacionesPorId[boton.dataset.id];
      document.getElementById('inst_id').value = i.id_centro;
      document.getElementById('inst_nombre').value = i.nombre;
      document.getElementById('inst_tipo').value = i.tipo_instalacion;
      document.getElementById('inst_coordenadas').value = i.coordenadas;
      document.getElementById('inst_capacidad').value = i.capacidad_maxima;
      document.getElementById('inst_residuos').value = i.residuos_aceptados;
      document.getElementById('inst_titulo_form').textContent = `Editar instalación #${i.id_centro}`;
      document.getElementById('inst_boton_submit').textContent = 'Guardar cambios';
      document.getElementById('inst_cancelar').hidden = false;
      document.getElementById('formInstalacion').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    });
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

function salirModoEdicionInstalacion() {
  document.getElementById('formInstalacion').reset();
  document.getElementById('inst_id').value = '';
  document.getElementById('inst_titulo_form').textContent = 'Registrar centro de acopio / vertedero (RF10 / RF11)';
  document.getElementById('inst_boton_submit').textContent = 'Registrar instalación';
  document.getElementById('inst_cancelar').hidden = true;
}
document.getElementById('inst_cancelar').addEventListener('click', salirModoEdicionInstalacion);

document.getElementById('formInstalacion').addEventListener('submit', async (evento) => {
  evento.preventDefault();
  const idCentro = document.getElementById('inst_id').value;
  const cuerpo = {
    nombre: document.getElementById('inst_nombre').value.trim(),
    tipo_instalacion: document.getElementById('inst_tipo').value,
    coordenadas: document.getElementById('inst_coordenadas').value.trim(),
    capacidad_maxima: Number(document.getElementById('inst_capacidad').value),
    residuos_aceptados: document.getElementById('inst_residuos').value.trim(),
  };
  const resultado = idCentro
    ? await llamar(`${BASE}/instalaciones.php`, { method: 'PUT', body: JSON.stringify({ id_centro: Number(idCentro), ...cuerpo }) })
    : await llamar(`${BASE}/instalaciones.php`, { method: 'POST', body: JSON.stringify(cuerpo) });
  mostrarMensajeGlobal(resultado.mensaje, resultado.exito ? 'exito' : 'error');
  if (resultado.exito) { salirModoEdicionInstalacion(); cargarInstalaciones(); }
});

// ---------------------------------------------------------------
// MAQUINARIA + REPUESTOS
// ---------------------------------------------------------------
async function cargarRepuestos() {
  const datos = await llamar(`${BASE}/repuestos.php`);
  const cuerpo = document.getElementById('tablaRepuestos');
  const selectMaquinaria = document.getElementById('maq_repuestos');
  cuerpo.innerHTML = '';
  selectMaquinaria.innerHTML = '';
  if (!datos.exito) return;

  datos.repuestos.forEach(r => {
    const fila = document.createElement('tr');
    fila.innerHTML = `
      <td>${r.id_repuesto}</td><td>${r.nombre}</td><td>${r.tipo}</td><td>${r.stock}</td><td>${r.estado}</td>
      <td><button class="peligro btn-eliminar-repuesto" data-id="${r.id_repuesto}">Eliminar</button></td>
    `;
    cuerpo.appendChild(fila);
    selectMaquinaria.appendChild(new Option(`${r.nombre} (${r.tipo})`, r.id_repuesto));
  });

  cuerpo.querySelectorAll('.btn-eliminar-repuesto').forEach(boton => {
    boton.addEventListener('click', async () => {
      const resultado = await llamar(`${BASE}/repuestos.php`, {
        method: 'DELETE',
        body: JSON.stringify({ id_repuesto: Number(boton.dataset.id) }),
      });
      mostrarMensajeGlobal(resultado.mensaje, resultado.exito ? 'exito' : 'error');
      cargarRepuestos();
      cargarMaquinaria();
    });
  });
}

document.getElementById('formRepuesto').addEventListener('submit', async (evento) => {
  evento.preventDefault();
  const resultado = await llamar(`${BASE}/repuestos.php`, {
    method: 'POST',
    body: JSON.stringify({
      nombre: document.getElementById('rep_nombre').value.trim(),
      tipo: document.getElementById('rep_tipo').value.trim(),
      stock: Number(document.getElementById('rep_stock').value || 0),
    }),
  });
  mostrarMensajeGlobal(resultado.mensaje, resultado.exito ? 'exito' : 'error');
  if (resultado.exito) { document.getElementById('formRepuesto').reset(); cargarRepuestos(); }
});

async function cargarMaquinaria() {
  const datos = await llamar(`${BASE}/maquinaria.php`);
  const cuerpo = document.getElementById('tablaMaquinaria');
  cuerpo.innerHTML = '';
  if (!datos.exito) return;

  datos.maquinas.forEach(m => {
    const repuestos = m.repuestos.map(r => r.nombre).join(', ') || '—';
    const fila = document.createElement('tr');
    fila.innerHTML = `
      <td>${m.id_maquinaria}</td><td>${m.nombre}</td><td>${m.tipo}</td>
      <td>
        <select data-id="${m.id_maquinaria}" class="select-estado-maquinaria">
          <option value="OPERATIVA" ${m.estado === 'OPERATIVA' ? 'selected' : ''}>Operativa</option>
          <option value="EN_MANTENIMIENTO" ${m.estado === 'EN_MANTENIMIENTO' ? 'selected' : ''}>En mantenimiento</option>
          <option value="FUERA_DE_SERVICIO" ${m.estado === 'FUERA_DE_SERVICIO' ? 'selected' : ''}>Fuera de servicio</option>
        </select>
      </td>
      <td>${m.nombre_instalacion}</td><td>${repuestos}</td>
      <td><button class="peligro btn-eliminar-maquinaria" data-id="${m.id_maquinaria}">Eliminar</button></td>
    `;
    cuerpo.appendChild(fila);
  });

  cuerpo.querySelectorAll('.select-estado-maquinaria').forEach(select => {
    select.addEventListener('change', async () => {
      const resultado = await llamar(`${BASE}/maquinaria.php`, {
        method: 'PUT',
        body: JSON.stringify({ id_maquinaria: Number(select.dataset.id), estado: select.value }),
      });
      mostrarMensajeGlobal(resultado.mensaje, resultado.exito ? 'exito' : 'error');
      if (resultado.exito) cargarMaquinaria();
    });
  });
  cuerpo.querySelectorAll('.btn-eliminar-maquinaria').forEach(boton => {
    boton.addEventListener('click', async () => {
      const resultado = await llamar(`${BASE}/maquinaria.php`, {
        method: 'DELETE',
        body: JSON.stringify({ id_maquinaria: Number(boton.dataset.id) }),
      });
      mostrarMensajeGlobal(resultado.mensaje, resultado.exito ? 'exito' : 'error');
      cargarMaquinaria();
    });
  });
}

document.getElementById('formMaquinaria').addEventListener('submit', async (evento) => {
  evento.preventDefault();
  const repuestos = Array.from(document.getElementById('maq_repuestos').selectedOptions).map(o => Number(o.value));
  const resultado = await llamar(`${BASE}/maquinaria.php`, {
    method: 'POST',
    body: JSON.stringify({
      nombre: document.getElementById('maq_nombre').value.trim(),
      tipo: document.getElementById('maq_tipo').value.trim(),
      id_centro: Number(document.getElementById('maq_id_centro').value),
      repuestos,
    }),
  });
  mostrarMensajeGlobal(resultado.mensaje, resultado.exito ? 'exito' : 'error');
  if (resultado.exito) { document.getElementById('formMaquinaria').reset(); cargarMaquinaria(); }
});

// ---------------------------------------------------------------
// Carga inicial
// ---------------------------------------------------------------
(async function iniciar() {
  iniciarMapaContenedores();
  await cargarUsuarios();
  await cargarRutas();       // primero, porque llena los <select> que usan otras secciones
  await cargarContenedores();
  await cargarCuadrillas();
  await cargarCamiones();
  await cargarInstalaciones();
  await cargarRepuestos();   // antes de maquinaria, porque llena el <select> de repuestos
  await cargarMaquinaria();
})();
