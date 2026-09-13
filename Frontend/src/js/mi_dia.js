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
    return;
  }

  div.innerHTML = asignaciones.map(a => `
    <p><strong>${a.turno}</strong> — Ruta: ${a.nombre_zona} — Camión: ${a.matricula}</p>
  `).join('');
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

(async function iniciar() {
  const sesion = await obtenerSesion();
  if (!sesion) return;
  const rol = rolDeSesion(sesion);
  const esChofer = rol === 'CHOFER';

  document.getElementById('avisoChofer').hidden = !esChofer;

  const cuadrilla = await cargarMiCuadrilla();
  await cargarMiAsignacion();
  await cargarMisIncidencias(cuadrilla ? cuadrilla.id_cuadrilla : null, esChofer);
})();
