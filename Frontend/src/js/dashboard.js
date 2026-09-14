const BASE_GESTION = '../api-gestion';
const BASE_INCIDENCIAS = '../api-incidencias';
const BASE_USUARIOS = '../api-usuarios';

const ETIQUETAS_ESTADO_INC = {
  PENDIENTE: 'Abierta', EN_PROCESO: 'En Proceso', RESUELTA: 'Solucionada',
};
const ICONOS_ESTADO_INC = {
  PENDIENTE: '⚠', EN_PROCESO: '↘', RESUELTA: '✓',
};
const COLOR_ESTADO_INC = {
  PENDIENTE: 'ambar', EN_PROCESO: 'azul', RESUELTA: 'verde',
};

const ICONOS_TARJETA = {
  usuarios: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
  contenedores: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>',
  camiones: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="6" width="14" height="11"/><path d="M15 9h4l3 3v5h-7z"/><circle cx="6" cy="18.5" r="1.8"/><circle cx="17.5" cy="18.5" r="1.8"/></svg>',
  incidencias: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
  cuadrillas: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
  rutas: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="6" cy="19" r="2"/><circle cx="18" cy="5" r="2"/><path d="M6 17V9a4 4 0 0 1 4-4h8"/></svg>',
  instalaciones: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M5 21V7l7-4 7 4v14"/><path d="M9 21v-6h6v6"/></svg>',
};

async function pedir(url) {
  const respuesta = await fetch(url);
  if (respuesta.status === 401) {
    window.location.href = 'login.html';
    throw new Error('Sin sesión');
  }
  return respuesta.json();
}

function tarjeta(icono, etiqueta, numero, alerta = false) {
  const div = document.createElement('div');
  div.className = 'tarjeta-estadistica' + (alerta ? ' alerta' : '');
  div.innerHTML = `
    <div class="icono-tarjeta">${ICONOS_TARJETA[icono] || ''}<span>${etiqueta}</span></div>
    <div class="numero">${numero}</div>
    <div class="etiqueta">${etiqueta}</div>
  `;
  return div;
}

// Los 3 estados de INCIDENCIA se muestran directo, uno a uno.
const GRUPOS_ESTADO = [
  { clave: 'pendientes', etiqueta: ETIQUETAS_ESTADO_INC.PENDIENTE, estados: ['PENDIENTE'], color: COLOR_ESTADO_INC.PENDIENTE },
  { clave: 'en_proceso', etiqueta: ETIQUETAS_ESTADO_INC.EN_PROCESO, estados: ['EN_PROCESO'], color: COLOR_ESTADO_INC.EN_PROCESO },
  { clave: 'resueltas', etiqueta: ETIQUETAS_ESTADO_INC.RESUELTA, estados: ['RESUELTA'], color: COLOR_ESTADO_INC.RESUELTA },
];

function renderGraficoEstados(incidencias) {
  const contenedor = document.getElementById('graficoEstadosIncidencias');
  contenedor.innerHTML = '';

  if (!incidencias.length) {
    contenedor.innerHTML = '<p class="nota-pie-oscura">Todavía no hay incidencias registradas.</p>';
    return;
  }

  const conteos = GRUPOS_ESTADO.map(grupo => ({
    ...grupo,
    total: incidencias.filter(inc => grupo.estados.includes(inc.estado)).length,
  }));

  conteos.forEach(grupo => {
    const porcentaje = Math.round((grupo.total / incidencias.length) * 100);
    const fila = document.createElement('div');
    fila.className = 'barra-estado-oscura';
    fila.title = `${grupo.etiqueta}: ${grupo.total} de ${incidencias.length} incidencias`;
    fila.innerHTML = `
      <div class="barra-etiqueta-oscura">
        <span>${grupo.etiqueta}</span>
        <span class="valor ${grupo.color}">${grupo.total} (${porcentaje}%)</span>
      </div>
      <div class="barra-pista-oscura">
        <div class="barra-relleno-oscura ${grupo.color}" style="width:${porcentaje}%"></div>
      </div>
    `;
    contenedor.appendChild(fila);
  });
}

function formatearFecha(fechaTexto) {
  const fecha = new Date(fechaTexto.replace(' ', 'T'));
  if (Number.isNaN(fecha.getTime())) return fechaTexto;
  return `${fecha.getDate()}/${fecha.getMonth() + 1}/${fecha.getFullYear()}`;
}

function renderIncidenciasRecientes(incidencias) {
  const lista = document.getElementById('listaIncidenciasRecientes');
  lista.innerHTML = '';

  if (!incidencias.length) {
    lista.innerHTML = '<li class="nota-pie-oscura">Sin incidencias todavía.</li>';
    return;
  }

  // La API ya devuelve las incidencias ordenadas por fecha_registro DESC.
  incidencias.slice(0, 5).forEach(inc => {
    const color = COLOR_ESTADO_INC[inc.estado] || 'azul';
    const etiqueta = ETIQUETAS_ESTADO_INC[inc.estado] || inc.estado;
    const icono = ICONOS_ESTADO_INC[inc.estado] || '';
    const fila = document.createElement('li');
    fila.className = 'fila-incidencia-reciente';
    fila.innerHTML = `
      <span class="punto-estado ${color}"></span>
      <div class="info-incidencia-reciente">
        <div class="titulo-incidencia-reciente">${inc.tipo_incidencia}</div>
        <div class="direccion-incidencia-reciente">${inc.contenedor_direccion || `Contenedor #${inc.id_contenedor}`}</div>
      </div>
      <div class="columna-estado-fecha">
        <span class="chip-estado-incidencia ${color}">${icono} ${etiqueta}</span>
        <span class="fecha-incidencia-reciente">${formatearFecha(inc.fecha_registro)}</span>
      </div>
    `;
    lista.appendChild(fila);
  });
}

function actualizarHoraInfo() {
  const elemento = document.getElementById('infoActualizada');
  if (elemento) {
    elemento.textContent = `Información actualizada: ${new Date().toLocaleTimeString('es-UY')}`;
  }
}

(async function cargarDashboard() {
  const sesion = await obtenerSesion();
  if (!exigirAdministrador(sesion)) return; // el Dashboard completo es solo para Administrador

  const pildora = document.getElementById('pildoraRol');
  if (pildora && sesion?.nombre_completo) {
    pildora.textContent = `👤 ${sesion.nombre_completo}`;
  }

  const contenedorTarjetas = document.getElementById('tarjetasEstadisticas');

  const [usuarios, contenedores, camiones, cuadrillas, rutas, instalaciones, incidencias] = await Promise.all([
    pedir(`${BASE_USUARIOS}/usuarios.php`),
    pedir(`${BASE_GESTION}/contenedores.php`),
    pedir(`${BASE_GESTION}/camiones.php`),
    pedir(`${BASE_GESTION}/cuadrillas.php`),
    pedir(`${BASE_GESTION}/rutas.php`),
    pedir(`${BASE_GESTION}/instalaciones.php`),
    pedir(`${BASE_INCIDENCIAS}/listar.php`),
  ]);

  const listaUsuarios = usuarios.exito ? usuarios.usuarios : [];
  const listaContenedores = contenedores.exito ? contenedores.contenedores : [];
  const listaCamiones = camiones.exito ? camiones.camiones : [];
  const listaCuadrillas = cuadrillas.exito ? cuadrillas.cuadrillas : [];
  const listaRutas = rutas.exito ? rutas.rutas : [];
  const listaInstalaciones = instalaciones.exito ? instalaciones.instalaciones : [];
  const listaIncidencias = incidencias.exito ? incidencias.incidencias : [];

  const desbordados = listaContenedores.filter(c => c.estado === 'DESBORDADO').length;
  const camionesDisponibles = listaCamiones.filter(c => c.estado === 'DISPONIBLE').length;

  contenedorTarjetas.appendChild(tarjeta('usuarios', 'Usuarios', listaUsuarios.length));
  contenedorTarjetas.appendChild(tarjeta('contenedores', 'Contenedores', listaContenedores.length));
  contenedorTarjetas.appendChild(tarjeta('camiones', 'Camiones', `${camionesDisponibles} / ${listaCamiones.length}`));
  contenedorTarjetas.appendChild(tarjeta('incidencias', 'Incidencias', listaIncidencias.length));
  contenedorTarjetas.appendChild(tarjeta('contenedores', 'Contenedores desbordados', desbordados, desbordados > 0));
  contenedorTarjetas.appendChild(tarjeta('cuadrillas', 'Cuadrillas', listaCuadrillas.length));
  contenedorTarjetas.appendChild(tarjeta('rutas', 'Rutas definidas', listaRutas.length));
  contenedorTarjetas.appendChild(tarjeta('instalaciones', 'Instalaciones', listaInstalaciones.length));

  actualizarHoraInfo();
  renderGraficoEstados(listaIncidencias);
  renderIncidenciasRecientes(listaIncidencias);
})();
