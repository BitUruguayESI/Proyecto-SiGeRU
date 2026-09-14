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

// Permite entrar directo a una pestaña por link (ej. desde "Registrar
// nuevo usuario": panel_gestion.html#usuarios).
if (location.hash) {
  const boton = document.querySelector(`.pestanias button[data-seccion="${location.hash.slice(1)}"]`);
  if (boton) boton.click();
}

// ---------------------------------------------------------------
// USUARIOS (CRUD completo: listar todos, editar, dar de baja/reactivar)
// ---------------------------------------------------------------
const BASE_USUARIOS = '../api-usuarios';
const formUsuario = document.getElementById('formUsuario');
const ETIQUETAS_ROL = { ADMINISTRADOR: 'Administrador', RECOLECTOR: 'Recolector', CHOFER: 'Chofer' };
let sesionActual = null; // se completa en iniciar(), la usa abrirEdicionUsuario para bloquear el auto-cambio de rol

function filtroUsuariosATipoYCargo(filtro) {
  if (filtro === 'ADMINISTRADOR') return { tipo_usuario: 'ADMINISTRADOR' };
  if (filtro === 'RECOLECTOR' || filtro === 'CHOFER') return { tipo_usuario: 'OPERARIO', cargo: filtro };
  return {};
}

async function cargarUsuarios() {
  const filtro = document.getElementById('usu_filtro_tipo').value;
  const params = new URLSearchParams(filtroUsuariosATipoYCargo(filtro));
  const query = params.toString();
  const url = query ? `${BASE_USUARIOS}/usuarios.php?${query}` : `${BASE_USUARIOS}/usuarios.php`;
  const datos = await llamar(url);
  const cuerpo = document.getElementById('tablaUsuarios');
  cuerpo.innerHTML = '';
  if (!datos.exito) return;

  datos.usuarios.forEach(u => {
    const fila = document.createElement('tr');
    fila.innerHTML = `
      <td>${u.ci}</td><td>${u.nombre_completo}</td><td>${u.email}</td>
      <td>${ETIQUETAS_ROL[u.rol] || u.rol}</td>
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
  const esUnoMismo = sesionActual && sesionActual.ci === u.ci;

  document.getElementById('usu_sin_seleccion').hidden = true;
  formUsuario.hidden = false;
  document.getElementById('usu_ci').value = u.ci;
  document.getElementById('usu_ci_visible').textContent = `${u.ci} (${ETIQUETAS_ROL[u.rol] || u.rol})`;
  document.getElementById('usu_nombre').value = u.nombre_completo;
  document.getElementById('usu_email').value = u.email;
  document.getElementById('usu_telefonos').value = u.telefonos.join(', ');
  document.getElementById('usu_contrasena').value = '';
  document.getElementById('usu_rol').value = u.rol;
  document.getElementById('usu_rol').disabled = esUnoMismo;
  document.getElementById('usu_rol_aviso').hidden = !esUnoMismo;
  document.getElementById('usu_carnet_salud').value = u.carnet_salud_vencimiento || '';
  document.getElementById('usu_licencia_categoria').value = u.licencia_categoria || '';
  document.getElementById('usu_licencia_vencimiento').value = u.licencia_vencimiento || '';
  actualizarBloqueLicenciaUsuario();
  formUsuario.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function actualizarBloqueLicenciaUsuario() {
  const esChofer = document.getElementById('usu_rol').value === 'CHOFER';
  document.getElementById('usu_bloque_licencia').classList.toggle('oculto', !esChofer);
}
document.getElementById('usu_rol').addEventListener('change', actualizarBloqueLicenciaUsuario);

document.getElementById('usu_filtro_tipo').addEventListener('change', cargarUsuarios);

document.getElementById('usu_cancelar').addEventListener('click', () => {
  formUsuario.reset();
  formUsuario.hidden = true;
  document.getElementById('usu_rol').disabled = false;
  document.getElementById('usu_rol_aviso').hidden = true;
  document.getElementById('usu_bloque_licencia').classList.add('oculto');
  document.getElementById('usu_sin_seleccion').hidden = false;
});

formUsuario.addEventListener('submit', async (evento) => {
  evento.preventDefault();
  const telefonos = document.getElementById('usu_telefonos').value.split(',').map(t => t.trim()).filter(Boolean);
  const selectRol = document.getElementById('usu_rol');
  const cuerpo = {
    ci: document.getElementById('usu_ci').value,
    nombre_completo: document.getElementById('usu_nombre').value.trim(),
    email: document.getElementById('usu_email').value.trim(),
    telefonos,
    carnet_salud_vencimiento: document.getElementById('usu_carnet_salud').value,
  };
  if (!selectRol.disabled) {
    cuerpo.rol = selectRol.value;
  }
  if (!document.getElementById('usu_bloque_licencia').classList.contains('oculto')) {
    cuerpo.licencia_categoria = document.getElementById('usu_licencia_categoria').value.trim();
    cuerpo.licencia_vencimiento = document.getElementById('usu_licencia_vencimiento').value;
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
    document.getElementById('usu_rol').disabled = false;
    document.getElementById('usu_rol_aviso').hidden = true;
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
const MONTEVIDEO_CENTRO = [-34.9011, -56.1645];
const MONTEVIDEO_ZOOM = 12;

let mapaContenedores = null;
let capaMarcadoresContenedores = null;

function iniciarMapaContenedores() {
  if (mapaContenedores || typeof L === 'undefined') return;
  mapaContenedores = L.map('mapaContenedores').setView(MONTEVIDEO_CENTRO, MONTEVIDEO_ZOOM);
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
    // Siempre incluye a Montevideo en el encuadre (además de los
    // contenedores), para que el mapa quede centrado ahí en vez de
    // desplazarse a otro lado si algún contenedor quedó mal geocodificado.
    const limites = L.latLngBounds(puntos).extend(MONTEVIDEO_CENTRO);
    mapaContenedores.fitBounds(limites, { padding: [24, 24], maxZoom: 15 });
  } else {
    mapaContenedores.setView(MONTEVIDEO_CENTRO, MONTEVIDEO_ZOOM);
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
      <td>${c.direccion || '<em style="color:#a0aec0;">sin dirección cargada</em>'}</td>
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
// CUADRILLAS (con su "cuadrilla estable": camión + chofer + recolectores)
// ---------------------------------------------------------------
async function cargarReferenciasCuadrilla() {
  const [camiones, choferes, recolectores] = await Promise.all([
    llamar(`${BASE}/camiones.php`),
    llamar(`${BASE_USUARIOS}/listar_operarios.php?cargo=CHOFER`),
    llamar(`${BASE_USUARIOS}/listar_operarios.php?cargo=RECOLECTOR`),
  ]);
  const selectCamion = document.getElementById('cuad_camion');
  const selectChofer = document.getElementById('cuad_chofer');
  const selectRecolectores = document.getElementById('cuad_recolectores');
  selectCamion.innerHTML = '<option value="">Sin asignar</option>';
  selectChofer.innerHTML = '<option value="">Sin asignar</option>';
  selectRecolectores.innerHTML = '';

  if (camiones.exito) {
    camiones.camiones.forEach(c => selectCamion.appendChild(new Option(c.matricula, c.id_camion)));
  }
  if (choferes.exito) {
    choferes.operarios.forEach(o => selectChofer.appendChild(new Option(o.nombre_completo, o.id_operario)));
  }
  if (recolectores.exito) {
    recolectores.operarios.forEach(o => selectRecolectores.appendChild(new Option(o.nombre_completo, o.id_operario)));
  }
}

async function cargarCuadrillas() {
  const datos = await llamar(`${BASE}/cuadrillas.php`);
  cuadrillasDisponiblesRetiro = datos.exito ? datos.cuadrillas : []; // la usa la sección de Retiros especiales
  const cuerpo = document.getElementById('tablaCuadrillas');
  cuerpo.innerHTML = '';
  if (!datos.exito) return;

  const cuadrillasPorId = {};
  datos.cuadrillas.forEach(c => {
    cuadrillasPorId[c.id_cuadrilla] = c;
    const rutas = c.rutas_habituales.map(r => r.nombre_zona).join(', ') || '—';
    const recolectores = c.recolectores.map(r => r.nombre_completo).join(', ') || '—';
    const fila = document.createElement('tr');
    fila.innerHTML = `
      <td>${c.id_cuadrilla}</td><td>${c.nombre_cuadrilla}</td><td>${c.cantidad_integrantes}</td>
      <td>${c.camion_matricula || '—'}</td><td>${c.chofer_nombre || '—'}</td><td>${recolectores}</td><td>${rutas}</td>
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
      document.getElementById('cuad_camion').value = c.id_camion || '';
      document.getElementById('cuad_chofer').value = c.id_chofer || '';
      const idsRecolectores = c.recolectores.map(r => String(r.id_operario));
      Array.from(document.getElementById('cuad_recolectores').options).forEach(opcion => {
        opcion.selected = idsRecolectores.includes(opcion.value);
      });
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
  const recolectoresSeleccionados = Array.from(document.getElementById('cuad_recolectores').selectedOptions).map(o => Number(o.value));
  const camion = document.getElementById('cuad_camion').value;
  const chofer = document.getElementById('cuad_chofer').value;
  const cuerpo = {
    nombre_cuadrilla: document.getElementById('cuad_nombre').value.trim(),
    cantidad_integrantes: Number(document.getElementById('cuad_cantidad').value || 0),
    id_camion: camion ? Number(camion) : null,
    id_chofer: chofer ? Number(chofer) : null,
    recolectores: recolectoresSeleccionados,
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
let repuestosPorId = {};

async function cargarRepuestos() {
  const datos = await llamar(`${BASE}/repuestos.php`);
  const cuerpo = document.getElementById('tablaRepuestos');
  const selectMaquinaria = document.getElementById('maq_repuestos');
  cuerpo.innerHTML = '';
  selectMaquinaria.innerHTML = '';
  if (!datos.exito) return;

  repuestosPorId = {};
  datos.repuestos.forEach(r => {
    repuestosPorId[r.id_repuesto] = r;
    const fila = document.createElement('tr');
    fila.innerHTML = `
      <td>${r.id_repuesto}</td><td>${r.nombre}</td><td>${r.tipo}</td><td>${r.stock}</td><td>${r.estado}</td>
      <td>
        <button class="chico btn-editar-repuesto" data-id="${r.id_repuesto}">Editar</button>
        <button class="peligro btn-eliminar-repuesto" data-id="${r.id_repuesto}">Eliminar</button>
      </td>
    `;
    cuerpo.appendChild(fila);
    selectMaquinaria.appendChild(new Option(`${r.nombre} (${r.tipo})`, r.id_repuesto));
  });

  cuerpo.querySelectorAll('.btn-editar-repuesto').forEach(boton => {
    boton.addEventListener('click', () => {
      const r = repuestosPorId[boton.dataset.id];
      document.getElementById('rep_id').value = r.id_repuesto;
      document.getElementById('rep_nombre').value = r.nombre;
      document.getElementById('rep_tipo').value = r.tipo;
      document.getElementById('rep_stock').value = r.stock;
      document.getElementById('rep_estado').value = r.estado;
      document.getElementById('rep_titulo_form').textContent = `Editar repuesto #${r.id_repuesto}`;
      document.getElementById('rep_boton_submit').textContent = 'Guardar cambios';
      document.getElementById('rep_cancelar').hidden = false;
      document.getElementById('formRepuesto').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    });
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

function salirModoEdicionRepuesto() {
  document.getElementById('formRepuesto').reset();
  document.getElementById('rep_id').value = '';
  document.getElementById('rep_titulo_form').textContent = 'Catálogo de repuestos';
  document.getElementById('rep_boton_submit').textContent = 'Registrar repuesto';
  document.getElementById('rep_cancelar').hidden = true;
}
document.getElementById('rep_cancelar').addEventListener('click', salirModoEdicionRepuesto);

document.getElementById('formRepuesto').addEventListener('submit', async (evento) => {
  evento.preventDefault();
  const idRepuesto = document.getElementById('rep_id').value;
  const cuerpo = {
    nombre: document.getElementById('rep_nombre').value.trim(),
    tipo: document.getElementById('rep_tipo').value.trim(),
    stock: Number(document.getElementById('rep_stock').value || 0),
    estado: document.getElementById('rep_estado').value,
  };
  const resultado = idRepuesto
    ? await llamar(`${BASE}/repuestos.php`, { method: 'PUT', body: JSON.stringify({ id_repuesto: Number(idRepuesto), ...cuerpo }) })
    : await llamar(`${BASE}/repuestos.php`, { method: 'POST', body: JSON.stringify(cuerpo) });
  mostrarMensajeGlobal(resultado.mensaje, resultado.exito ? 'exito' : 'error');
  if (resultado.exito) { salirModoEdicionRepuesto(); cargarRepuestos(); }
});

let maquinariasPorId = {};

async function cargarMaquinaria() {
  const datos = await llamar(`${BASE}/maquinaria.php`);
  const cuerpo = document.getElementById('tablaMaquinaria');
  cuerpo.innerHTML = '';
  if (!datos.exito) return;

  maquinariasPorId = {};
  datos.maquinas.forEach(m => {
    maquinariasPorId[m.id_maquinaria] = m;
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
      <td>
        <button class="chico btn-editar-maquinaria" data-id="${m.id_maquinaria}">Editar</button>
        <button class="peligro btn-eliminar-maquinaria" data-id="${m.id_maquinaria}">Eliminar</button>
      </td>
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
  cuerpo.querySelectorAll('.btn-editar-maquinaria').forEach(boton => {
    boton.addEventListener('click', () => {
      const m = maquinariasPorId[boton.dataset.id];
      document.getElementById('maq_id').value = m.id_maquinaria;
      document.getElementById('maq_nombre').value = m.nombre;
      document.getElementById('maq_tipo').value = m.tipo;
      document.getElementById('maq_id_centro').value = m.id_centro;
      document.getElementById('maq_estado').value = m.estado;
      const idsRepuesto = m.repuestos.map(r => String(r.id_repuesto));
      Array.from(document.getElementById('maq_repuestos').options).forEach(opcion => {
        opcion.selected = idsRepuesto.includes(opcion.value);
      });
      document.getElementById('maq_titulo_form').textContent = `Editar maquinaria #${m.id_maquinaria}`;
      document.getElementById('maq_boton_submit').textContent = 'Guardar cambios';
      document.getElementById('maq_cancelar').hidden = false;
      document.getElementById('formMaquinaria').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
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

function salirModoEdicionMaquinaria() {
  document.getElementById('formMaquinaria').reset();
  document.getElementById('maq_id').value = '';
  document.getElementById('maq_titulo_form').textContent = 'Registrar maquinaria';
  document.getElementById('maq_boton_submit').textContent = 'Registrar maquinaria';
  document.getElementById('maq_cancelar').hidden = true;
}
document.getElementById('maq_cancelar').addEventListener('click', salirModoEdicionMaquinaria);

document.getElementById('formMaquinaria').addEventListener('submit', async (evento) => {
  evento.preventDefault();
  const idMaquinaria = document.getElementById('maq_id').value;
  const repuestos = Array.from(document.getElementById('maq_repuestos').selectedOptions).map(o => Number(o.value));
  const cuerpo = {
    nombre: document.getElementById('maq_nombre').value.trim(),
    tipo: document.getElementById('maq_tipo').value.trim(),
    id_centro: Number(document.getElementById('maq_id_centro').value),
    estado: document.getElementById('maq_estado').value,
    repuestos,
  };
  const resultado = idMaquinaria
    ? await llamar(`${BASE}/maquinaria.php`, { method: 'PUT', body: JSON.stringify({ id_maquinaria: Number(idMaquinaria), ...cuerpo }) })
    : await llamar(`${BASE}/maquinaria.php`, { method: 'POST', body: JSON.stringify(cuerpo) });
  mostrarMensajeGlobal(resultado.mensaje, resultado.exito ? 'exito' : 'error');
  if (resultado.exito) { salirModoEdicionMaquinaria(); cargarMaquinaria(); }
});

// ---------------------------------------------------------------
// RETIROS ESPECIALES (solicitudes públicas: poda, residuos electrónicos,
// escombros, muebles, etc. — no tienen día fijo, se coordinan por
// solicitud; acá solo se listan y se marcan como retiradas)
// ---------------------------------------------------------------
const BASE_RET = '../api-retiros';
const ETIQUETAS_ESTADO_RETIRO = { PENDIENTE: 'Pendiente', RETIRADO: 'Retirado' };
let cuadrillasDisponiblesRetiro = []; // se llena en cargarCuadrillas(), la reusa esta sección

function selectCuadrillasRetiro(idSolicitud) {
  const opciones = cuadrillasDisponiblesRetiro
    .map(c => `<option value="${c.id_cuadrilla}">${c.nombre_cuadrilla}</option>`)
    .join('');
  return `<select data-id-solicitud="${idSolicitud}" class="select-cuadrilla-retiro"><option value="">Elegir cuadrilla...</option>${opciones}</select>`;
}

function accionesRetiro(s) {
  if (s.estado === 'RETIRADO') {
    return `Retirado el ${s.fecha_retiro || '—'}`;
  }
  // PENDIENTE: si todavía no tiene cuadrilla asignada, primero hay que
  // asignarle una (la ve el Chofer en "Mi día"); si ya la tiene, el
  // Administrador también puede marcarla retirada directamente.
  if (!s.id_cuadrilla) {
    return `${selectCuadrillasRetiro(s.id_solicitud)}
            <button class="chico btn-asignar-retiro" data-id="${s.id_solicitud}">Asignar</button>`;
  }
  return `<button class="chico btn-marcar-retirado" data-id="${s.id_solicitud}">Marcar retirado</button>`;
}

async function cargarRetiros() {
  const estado = document.getElementById('ret_filtro_estado').value;
  const url = estado ? `${BASE_RET}/listar.php?estado=${encodeURIComponent(estado)}` : `${BASE_RET}/listar.php`;
  const datos = await llamar(url);
  const cuerpo = document.getElementById('tablaRetiros');
  cuerpo.innerHTML = '';
  if (!datos.exito) return;

  if (!datos.solicitudes.length) {
    cuerpo.innerHTML = '<tr><td colspan="9" style="text-align:center; color:#a0aec0;">No hay solicitudes para este filtro.</td></tr>';
    return;
  }

  datos.solicitudes.forEach(s => {
    const fila = document.createElement('tr');
    fila.innerHTML = `
      <td>${s.id_solicitud}</td>
      <td>${s.tipo_residuo}</td>
      <td>${s.direccion}</td>
      <td>${s.descripcion || '—'}</td>
      <td>${s.vecino_email || '—'}</td>
      <td>${ETIQUETAS_ESTADO_RETIRO[s.estado] || s.estado}</td>
      <td>${s.nombre_cuadrilla || '—'}</td>
      <td>${s.fecha_registro}</td>
      <td>${accionesRetiro(s)}</td>
    `;
    cuerpo.appendChild(fila);
  });

  cuerpo.querySelectorAll('.btn-asignar-retiro').forEach(boton => {
    boton.addEventListener('click', async () => {
      const fila = boton.closest('tr');
      const select = fila.querySelector('.select-cuadrilla-retiro');
      if (!select.value) {
        mostrarMensajeGlobal('Elegí una cuadrilla primero.', 'error');
        return;
      }
      const resultado = await llamar(`${BASE_RET}/asignar_cuadrilla.php`, {
        method: 'PUT',
        body: JSON.stringify({ id_solicitud: Number(boton.dataset.id), id_cuadrilla: Number(select.value) }),
      });
      mostrarMensajeGlobal(resultado.mensaje, resultado.exito ? 'exito' : 'error');
      cargarRetiros();
    });
  });

  cuerpo.querySelectorAll('.btn-marcar-retirado').forEach(boton => {
    boton.addEventListener('click', async () => {
      const resultado = await llamar(`${BASE_RET}/actualizar_estado.php`, {
        method: 'PUT',
        body: JSON.stringify({ id_solicitud: Number(boton.dataset.id) }),
      });
      mostrarMensajeGlobal(resultado.mensaje, resultado.exito ? 'exito' : 'error');
      cargarRetiros();
    });
  });
}

document.getElementById('ret_filtro_estado').addEventListener('change', cargarRetiros);

// ---------------------------------------------------------------
// Carga inicial
// ---------------------------------------------------------------
(async function iniciar() {
  const sesion = await obtenerSesion();
  if (!exigirAdministrador(sesion)) return; // Gestión de recursos es solo para Administrador
  sesionActual = sesion;

  iniciarMapaContenedores();
  await cargarUsuarios();
  await cargarRutas();       // primero, porque llena los <select> que usan otras secciones
  await cargarContenedores();
  await cargarReferenciasCuadrilla(); // camión/chofer/recolectores para el form de cuadrillas
  await cargarCuadrillas();
  await cargarCamiones();
  await cargarInstalaciones();
  await cargarRepuestos();   // antes de maquinaria, porque llena el <select> de repuestos
  await cargarMaquinaria();
  await cargarRetiros();
})();
