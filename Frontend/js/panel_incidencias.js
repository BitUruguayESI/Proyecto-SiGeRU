const BASE_INC = '../api-incidencias';
const BASE_GES = '../api-gestion';
const divMensajeGlobal = document.getElementById('mensajeGlobal');
let cuadrillasDisponibles = [];
let puedeResolverIncidencias = false; // Administrador o Chofer (ver Comun/funciones.php::requerirPuedeResolverIncidencia)

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

const ETIQUETAS_ESTADO = {
  PENDIENTE: 'Pendiente', EN_PROCESO: 'En proceso', RESUELTA: 'Resuelta',
};

function selectCuadrillas(idAtributo) {
  const opciones = cuadrillasDisponibles
    .map(c => `<option value="${c.id_cuadrilla}">${c.nombre_cuadrilla}</option>`)
    .join('');
  return `<select data-id-incidencia="${idAtributo}" class="select-cuadrilla"><option value="">Elegir cuadrilla...</option>${opciones}</select>`;
}

function accionesPorEstado(inc) {
  switch (inc.estado) {
    case 'PENDIENTE':
      return `${selectCuadrillas(inc.id_incidencia)}
              <button class="chico btn-asignar" data-id="${inc.id_incidencia}">Asignar (pasa a En proceso)</button>`;
    case 'EN_PROCESO':
      return puedeResolverIncidencias
        ? `<button class="chico btn-resolver" data-id="${inc.id_incidencia}">Marcar resuelta</button>`
        : 'En proceso (la resuelve el Administrador o el Chofer)';
    case 'RESUELTA':
      return `Resuelta el ${inc.fecha_resolucion || '—'}`;
    default:
      return '—';
  }
}

async function cargarIncidencias() {
  const estado = document.getElementById('filtroEstado').value;
  const url = estado ? `${BASE_INC}/listar.php?estado=${encodeURIComponent(estado)}` : `${BASE_INC}/listar.php`;
  const datos = await llamar(url);
  const cuerpo = document.getElementById('tablaIncidencias');
  cuerpo.innerHTML = '';
  if (!datos.exito) return;

  if (!datos.incidencias.length) {
    cuerpo.innerHTML = '<tr><td colspan="8" style="text-align:center; color:var(--texto-tenue);">No hay incidencias para este filtro.</td></tr>';
    return;
  }

  datos.incidencias.forEach(inc => {
    const fila = document.createElement('tr');
    fila.innerHTML = `
      <td>${inc.id_incidencia}</td>
      <td>${inc.contenedor_direccion || `Contenedor #${inc.id_contenedor} (sin dirección cargada)`}</td>
      <td>${inc.tipo_incidencia}</td>
      <td>${inc.descripcion}</td>
      <td>${ETIQUETAS_ESTADO[inc.estado] || inc.estado}</td>
      <td>${inc.nombre_cuadrilla || '—'}</td>
      <td>${inc.fecha_registro}</td>
      <td>${accionesPorEstado(inc)}</td>
    `;
    cuerpo.appendChild(fila);
  });

  cuerpo.querySelectorAll('.btn-asignar').forEach(boton => {
    boton.addEventListener('click', async () => {
      const fila = boton.closest('tr');
      const select = fila.querySelector('.select-cuadrilla');
      if (!select.value) {
        mostrarMensajeGlobal('Elegí una cuadrilla primero.', 'error');
        return;
      }
      const resultado = await llamar(`${BASE_INC}/asignar_cuadrilla.php`, {
        method: 'PUT',
        body: JSON.stringify({ id_incidencia: Number(boton.dataset.id), id_cuadrilla: Number(select.value) }),
      });
      mostrarMensajeGlobal(resultado.mensaje, resultado.exito ? 'exito' : 'error');
      cargarIncidencias();
    });
  });

  const conectarCambioEstado = (selector, estadoDestino) => {
    cuerpo.querySelectorAll(selector).forEach(boton => {
      boton.addEventListener('click', async () => {
        const resultado = await llamar(`${BASE_INC}/actualizar_estado.php`, {
          method: 'PUT',
          body: JSON.stringify({ id_incidencia: Number(boton.dataset.id), estado: estadoDestino }),
        });
        mostrarMensajeGlobal(resultado.mensaje, resultado.exito ? 'exito' : 'error');
        cargarIncidencias();
      });
    });
  };
  conectarCambioEstado('.btn-resolver', 'RESUELTA');
}

document.getElementById('filtroEstado').addEventListener('change', cargarIncidencias);

(async function iniciar() {
  // Este panel (gestión de TODAS las incidencias) es solo para
  // Administrador; el Recolector/Chofer ven las suyas en "Mi día".
  const sesion = await obtenerSesion();
  if (!exigirAdministrador(sesion)) return;
  puedeResolverIncidencias = true; // acá siempre es Administrador

  // Permite entrar directo con un estado filtrado (ej. desde el Dashboard:
  // panel_incidencias.html?estado=PENDIENTE).
  const estadoDesdeUrl = new URLSearchParams(location.search).get('estado');
  if (estadoDesdeUrl && ETIQUETAS_ESTADO[estadoDesdeUrl]) {
    document.getElementById('filtroEstado').value = estadoDesdeUrl;
  }

  const datosCuadrillas = await llamar(`${BASE_GES}/cuadrillas.php`);
  cuadrillasDisponibles = datosCuadrillas.exito ? datosCuadrillas.cuadrillas : [];
  await cargarIncidencias();
})();
