/**
 * mi_dia.js
 * Panel personal del Recolector/Chofer (RN de dashboards por rol):
 *  - Recolector ve las incidencias del día de su cuadrilla, su
 *    asignación diaria y quién integra su cuadrilla.
 *  - Chofer ve lo mismo, más el camión que le asignaron, y puede
 *    marcar incidencias como resueltas (además de registrar descargas,
 *    que hace en panel_recoleccion.html).
 */

const BASE_GES = '../api-gestion';
const BASE_REC = '../api-recoleccion';
const BASE_INC = '../api-incidencias';
const BASE_RET = '../api-retiros';
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

function fechaDeHoy() {
  const hoy = new Date();
  return hoy.toISOString().slice(0, 10);
}

async function cargarMiCuadrilla() {
  const div = document.getElementById('miCuadrilla');
  const datos = await llamar(`${BASE_GES}/cuadrillas.php?propia=1`);
  const cuadrilla = datos.exito ? datos.cuadrilla : null;

  if (!cuadrilla) {
    div.innerHTML = '<p style="color:#a0aec0;">Todavía no te asignaron a ninguna cuadrilla.</p>';
    return null;
  }

  const recolectores = cuadrilla.recolectores.map(r => r.nombre_completo).join(', ') || '—';
  div.innerHTML = `
    <p><strong>${cuadrilla.nombre_cuadrilla}</strong></p>
    <p>Camión: ${cuadrilla.camion_matricula || 'sin asignar'}</p>
    <p>Chofer: ${cuadrilla.chofer_nombre || 'sin asignar'}</p>
    <p>Recolectores: ${recolectores}</p>
  `;
  return cuadrilla;
}

async function cargarMiAsignacion() {
  const div = document.getElementById('miAsignacion');
  const datos = await llamar(`${BASE_REC}/asignaciones.php?mias=1&fecha=${fechaDeHoy()}`);
  const asignaciones = datos.exito ? datos.asignaciones : [];

  if (!asignaciones.length) {
    div.innerHTML = '<p style="color:#a0aec0;">No tenés una asignación diaria cargada para hoy.</p>';
    return [];
  }

  div.innerHTML = asignaciones.map(a => `
    <p><strong>${a.turno}</strong> — Ruta: ${a.nombre_zona} — Camión: ${a.matricula}</p>
  `).join('');
  return asignaciones;
}

// ---------------------------------------------------------------
// MAPA DE LA JORNADA: contenedores de la ruta asignada, incidencias y
// retiros especiales de la cuadrilla.
//  - Ruta = rutas habituales de la cuadrilla + la ruta de la asignación
//    diaria de hoy (si hay).
//  - Las incidencias se ubican por su contenedor (que ya tiene coordenadas).
//  - Los retiros solo guardan la dirección: se ubican con
//    api-retiros/ubicacion.php (Nominatim), de a uno y con caché local.
// ---------------------------------------------------------------
const MONTEVIDEO_CENTRO = [-34.9011, -56.1645];
const COLOR_ESTADO_CONTENEDOR = { OPERATIVO: '#2E7D32', DESBORDADO: '#C0392B', FUERA_DE_SERVICIO: '#888888' };

let mapaJornada = null;
const capasMapa = {};
const datosMapa = { idsRuta: [], nombresRuta: [], incidencias: [], retiros: [] };
let tokenMapa = 0;
let tokenRetirosMapa = 0;

function escaparHtml(texto) {
  return String(texto ?? '').replace(/[&<>"']/g, c => (
    { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
  ));
}

function parsearCoordenadas(texto) {
  if (!texto) return null;
  const [lat, lon] = String(texto).split(',').map(Number);
  return Number.isFinite(lat) && Number.isFinite(lon) ? [lat, lon] : null;
}

function iconoMapa(emoji, color) {
  return L.divIcon({
    className: '',
    html: `<div style="background:${color};color:#fff;width:28px;height:28px;border-radius:50%;border:2px solid #fff;display:flex;align-items:center;justify-content:center;font-size:14px;box-shadow:0 1px 4px rgba(0,0,0,.5);">${emoji}</div>`,
    iconSize: [28, 28], iconAnchor: [14, 14], popupAnchor: [0, -14],
  });
}

function iniciarMapaJornada() {
  if (mapaJornada || typeof L === 'undefined') return;
  // preferCanvas: una cuadrilla puede tener más de mil contenedores en su ruta.
  mapaJornada = L.map('mapaMiDia', { preferCanvas: true }).setView(MONTEVIDEO_CENTRO, 12);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    maxZoom: 19,
  }).addTo(mapaJornada);
  capasMapa.ruta = L.layerGroup().addTo(mapaJornada);
  capasMapa.incidencias = L.layerGroup().addTo(mapaJornada);
  capasMapa.retiros = L.layerGroup().addTo(mapaJornada);
  L.control.layers(null, {
    'Contenedores de mi ruta': capasMapa.ruta,
    'Incidencias': capasMapa.incidencias,
    'Retiros especiales': capasMapa.retiros,
  }, { collapsed: false }).addTo(mapaJornada);
}

function ajustarVistaMapa() {
  if (!mapaJornada) return;
  const limites = L.featureGroup([capasMapa.ruta, capasMapa.incidencias, capasMapa.retiros]).getBounds();
  if (limites.isValid()) mapaJornada.fitBounds(limites, { padding: [30, 30], maxZoom: 16 });
}

async function contenedoresDelMapa(parametros) {
  const datos = await llamar(`${BASE_GES}/contenedores.php?${parametros}`);
  return datos.exito ? datos.contenedores : [];
}

async function actualizarMapaJornada() {
  if (!mapaJornada) return;
  const token = ++tokenMapa;
  const activas = datosMapa.incidencias.filter(i => i.estado !== 'RESUELTA' && i.id_contenedor);
  const idsContenedoresInc = [...new Set(activas.map(i => i.id_contenedor))];

  const [deRuta, deIncidencias] = await Promise.all([
    datosMapa.idsRuta.length ? contenedoresDelMapa(`id_ruta=${datosMapa.idsRuta.join(',')}`) : [],
    idsContenedoresInc.length ? contenedoresDelMapa(`ids=${idsContenedoresInc.join(',')}`) : [],
  ]);
  if (token !== tokenMapa) return; // llegó una actualización más nueva

  capasMapa.ruta.clearLayers();
  capasMapa.incidencias.clearLayers();

  let contenedoresSinUbicar = 0;
  deRuta.forEach(c => {
    const pos = parsearCoordenadas(c.coordenadas);
    if (!pos) { contenedoresSinUbicar++; return; }
    const color = COLOR_ESTADO_CONTENEDOR[c.estado] || '#888';
    L.circleMarker(pos, { radius: 6, color: '#fff', weight: 1, fillColor: color, fillOpacity: 0.85 })
      .bindPopup(`<strong>${escaparHtml(c.direccion || 'Contenedor #' + c.id_contenedor)}</strong><br>Ruta: ${escaparHtml(c.nombre_zona || '—')}<br>Estado: ${escaparHtml(c.estado)}<br>Nivel de llenado: ${Number(c.nivel_llenado)}%`)
      .addTo(capasMapa.ruta);
  });

  const porId = {};
  deIncidencias.forEach(c => { porId[c.id_contenedor] = c; });
  let incidenciasSinUbicar = 0;
  activas.forEach(inc => {
    const contenedor = porId[inc.id_contenedor];
    const pos = contenedor ? parsearCoordenadas(contenedor.coordenadas) : null;
    if (!pos) { incidenciasSinUbicar++; return; }
    L.marker(pos, { icon: iconoMapa('⚠', '#E67E22') })
      .bindPopup(`<strong>Incidencia #${inc.id_incidencia}</strong> — ${escaparHtml(inc.tipo_incidencia)}<br>${escaparHtml(inc.contenedor_direccion || 'Contenedor #' + inc.id_contenedor)}<br>${escaparHtml(inc.descripcion)}<br>Estado: ${escaparHtml(ETIQUETAS_ESTADO[inc.estado] || inc.estado)}`)
      .addTo(capasMapa.incidencias);
  });

  const resumen = [];
  resumen.push(datosMapa.nombresRuta.length
    ? `Mi ruta: ${datosMapa.nombresRuta.join(', ')} (${deRuta.length} contenedores)`
    : 'No tenés una ruta asignada todavía.');
  resumen.push(`${activas.length - incidenciasSinUbicar} de ${activas.length} incidencias activas en el mapa`);
  document.getElementById('mapaResumen').textContent = resumen.join(' · ');

  const avisos = [];
  if (contenedoresSinUbicar) avisos.push(`${contenedoresSinUbicar} contenedor(es) de la ruta no tienen coordenadas.`);
  if (incidenciasSinUbicar) avisos.push(`${incidenciasSinUbicar} incidencia(s) no se pueden ubicar (contenedor sin coordenadas).`);
  document.getElementById('mapaAviso').textContent = avisos.join(' ');

  ajustarVistaMapa();
  actualizarRetirosMapa();
}

// Los retiros se ubican de a uno (Nominatim: ~1 consulta por segundo) y el
// resultado se guarda en localStorage por solicitud+dirección, así la
// próxima vez que se abre la página aparecen al instante.
function leerCacheRetiro(clave) {
  try { return JSON.parse(localStorage.getItem(clave)); } catch (e) { return null; }
}
function guardarCacheRetiro(clave, valor) {
  try { localStorage.setItem(clave, JSON.stringify(valor)); } catch (e) { /* sin caché, no pasa nada */ }
}

async function actualizarRetirosMapa() {
  if (!mapaJornada) return;
  const token = ++tokenRetirosMapa;
  capasMapa.retiros.clearLayers();
  const pendientes = datosMapa.retiros.filter(s => s.estado === 'PENDIENTE');
  let sinUbicar = 0;
  let consultoRed = false;

  for (const s of pendientes) {
    if (token !== tokenRetirosMapa) return;
    const clave = `sigeru_geo_retiro_${s.id_solicitud}_${s.direccion}`;
    let pos = leerCacheRetiro(clave);
    if (!pos) {
      if (consultoRed) await new Promise(r => setTimeout(r, 1200));
      consultoRed = true;
      try {
        const datos = await llamar(`${BASE_RET}/ubicacion.php?id_solicitud=${s.id_solicitud}`);
        if (datos.exito) { pos = [datos.lat, datos.lon]; guardarCacheRetiro(clave, pos); }
      } catch (e) { /* se cuenta como sin ubicar */ }
      if (token !== tokenRetirosMapa) return;
    }
    if (!pos) { sinUbicar++; continue; }
    L.marker(pos, { icon: iconoMapa('📦', '#8E44AD') })
      .bindPopup(`<strong>Retiro #${s.id_solicitud}</strong> — ${escaparHtml(s.tipo_residuo)}<br>${escaparHtml(s.direccion)}<br>${escaparHtml(s.descripcion || '')}`)
      .addTo(capasMapa.retiros);
    ajustarVistaMapa();
  }

  const aviso = document.getElementById('mapaAviso');
  const base = aviso.textContent.replace(/\s*\d+ retiro\(s\) no se pudieron? ubicar[^.]*\./g, '').trim();
  aviso.textContent = [base, sinUbicar ? `${sinUbicar} retiro(s) no se pudo ubicar por su dirección.` : ''].filter(Boolean).join(' ');
}

const ETIQUETAS_ESTADO = { PENDIENTE: 'Pendiente', EN_PROCESO: 'En proceso', RESUELTA: 'Resuelta' };

async function cargarMisIncidencias(idCuadrilla, puedeResolver) {
  const cuerpo = document.getElementById('tablaMisIncidencias');
  cuerpo.innerHTML = '';

  if (!idCuadrilla) {
    cuerpo.innerHTML = '<tr><td colspan="7" style="text-align:center; color:#a0aec0;">Sin cuadrilla asignada todavía.</td></tr>';
    return;
  }

  const datos = await llamar(`${BASE_INC}/listar.php?id_cuadrilla=${idCuadrilla}`);
  const incidencias = datos.exito ? datos.incidencias : [];
  datosMapa.incidencias = incidencias;
  actualizarMapaJornada();

  if (!incidencias.length) {
    cuerpo.innerHTML = '<tr><td colspan="7" style="text-align:center; color:#a0aec0;">Tu cuadrilla no tiene incidencias asignadas.</td></tr>';
    return;
  }

  incidencias.forEach(inc => {
    const fila = document.createElement('tr');
    const acciones = inc.estado === 'EN_PROCESO' && puedeResolver
      ? `<button class="chico btn-resolver" data-id="${inc.id_incidencia}">Marcar resuelta</button>`
      : (inc.estado === 'RESUELTA' ? `Resuelta el ${inc.fecha_resolucion || '—'}` : '—');
    fila.innerHTML = `
      <td>${inc.id_incidencia}</td>
      <td>#${inc.id_contenedor}</td>
      <td>${inc.tipo_incidencia}</td>
      <td>${inc.descripcion}</td>
      <td>${ETIQUETAS_ESTADO[inc.estado] || inc.estado}</td>
      <td>${inc.fecha_registro}</td>
      <td>${acciones}</td>
    `;
    cuerpo.appendChild(fila);
  });

  cuerpo.querySelectorAll('.btn-resolver').forEach(boton => {
    boton.addEventListener('click', async () => {
      const resultado = await llamar(`${BASE_INC}/actualizar_estado.php`, {
        method: 'PUT',
        body: JSON.stringify({ id_incidencia: Number(boton.dataset.id), estado: 'RESUELTA' }),
      });
      mostrarMensajeGlobal(resultado.mensaje, resultado.exito ? 'exito' : 'error');
      if (resultado.exito) cargarMisIncidencias(idCuadrilla, puedeResolver);
    });
  });
}

const ETIQUETAS_ESTADO_RETIRO = { PENDIENTE: 'Pendiente', RETIRADO: 'Retirado' };

async function cargarMisRetiros(idCuadrilla, puedeConfirmar) {
  const cuerpo = document.getElementById('tablaMisRetiros');
  cuerpo.innerHTML = '';

  if (!idCuadrilla) {
    cuerpo.innerHTML = '<tr><td colspan="7" style="text-align:center; color:#a0aec0;">Sin cuadrilla asignada todavía.</td></tr>';
    return;
  }

  const datos = await llamar(`${BASE_RET}/listar.php?id_cuadrilla=${idCuadrilla}`);
  const solicitudes = datos.exito ? datos.solicitudes : [];
  datosMapa.retiros = solicitudes;
  actualizarRetirosMapa();

  if (!solicitudes.length) {
    cuerpo.innerHTML = '<tr><td colspan="7" style="text-align:center; color:#a0aec0;">Tu cuadrilla no tiene retiros especiales asignados.</td></tr>';
    return;
  }

  solicitudes.forEach(sol => {
    const fila = document.createElement('tr');
    const acciones = sol.estado === 'PENDIENTE' && puedeConfirmar
      ? `<button class="chico btn-confirmar-retiro" data-id="${sol.id_solicitud}">Marcar retirado</button>`
      : (sol.estado === 'RETIRADO' ? `Retirado el ${sol.fecha_retiro || '—'}` : '—');
    fila.innerHTML = `
      <td>${sol.id_solicitud}</td>
      <td>${sol.tipo_residuo}</td>
      <td>${sol.direccion}</td>
      <td>${sol.descripcion || '—'}</td>
      <td>${ETIQUETAS_ESTADO_RETIRO[sol.estado] || sol.estado}</td>
      <td>${sol.fecha_registro}</td>
      <td>${acciones}</td>
    `;
    cuerpo.appendChild(fila);
  });

  cuerpo.querySelectorAll('.btn-confirmar-retiro').forEach(boton => {
    boton.addEventListener('click', async () => {
      const resultado = await llamar(`${BASE_RET}/actualizar_estado.php`, {
        method: 'PUT',
        body: JSON.stringify({ id_solicitud: Number(boton.dataset.id), id_cuadrilla: idCuadrilla }),
      });
      mostrarMensajeGlobal(resultado.mensaje, resultado.exito ? 'exito' : 'error');
      if (resultado.exito) cargarMisRetiros(idCuadrilla, puedeConfirmar);
    });
  });
}

(async function iniciar() {
  const sesion = await obtenerSesion();
  if (!sesion) return;
  const rol = rolDeSesion(sesion);
  const esChofer = rol === 'CHOFER';

  document.getElementById('avisoChofer').hidden = !esChofer;

  const cuadrilla = await cargarMiCuadrilla();
  const asignaciones = await cargarMiAsignacion();

  // Ruta asignada: rutas habituales de la cuadrilla + la de la asignación de hoy.
  const rutas = new Map();
  ((cuadrilla && cuadrilla.rutas_habituales) || []).forEach(r => rutas.set(Number(r.id_ruta), r.nombre_zona));
  asignaciones.forEach(a => rutas.set(Number(a.id_ruta), a.nombre_zona));
  datosMapa.idsRuta = [...rutas.keys()];
  datosMapa.nombresRuta = [...rutas.values()];
  iniciarMapaJornada();
  if (!cuadrilla) actualizarMapaJornada();

  await cargarMisIncidencias(cuadrilla ? cuadrilla.id_cuadrilla : null, esChofer);
  await cargarMisRetiros(cuadrilla ? cuadrilla.id_cuadrilla : null, esChofer);
})();
