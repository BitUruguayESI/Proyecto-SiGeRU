const BASE_GESTION = '../api-gestion';
const BASE_INCIDENCIAS = '../api-incidencias';

const ETIQUETAS_ESTADO_INC = {
  PENDIENTE: 'Pendiente', EN_PROCESO: 'En proceso', RESUELTA: 'Resuelta',
};

async function pedir(url) {
  const respuesta = await fetch(url);
  if (respuesta.status === 401) {
    window.location.href = 'login.html';
    throw new Error('Sin sesión');
  }
  return respuesta.json();
}

function tarjeta(numero, etiqueta, alerta = false) {
  const div = document.createElement('div');
  div.className = 'tarjeta-estadistica' + (alerta ? ' alerta' : '');
  div.innerHTML = `<div class="numero">${numero}</div><div class="etiqueta">${etiqueta}</div>`;
  return div;
}

// Los 3 estados de INCIDENCIA se muestran directo, uno a uno.
const GRUPOS_ESTADO = [
  { clave: 'pendientes', etiqueta: 'Pendientes', estados: ['PENDIENTE'], color: 'rojo' },
  { clave: 'en_proceso', etiqueta: 'En proceso', estados: ['EN_PROCESO'], color: 'azul' },
  { clave: 'resueltas', etiqueta: 'Resueltas', estados: ['RESUELTA'], color: 'verde' },
];

function renderGraficoEstados(incidencias) {
  const contenedor = document.getElementById('graficoEstadosIncidencias');
  contenedor.innerHTML = '';

  if (!incidencias.length) {
    contenedor.innerHTML = '<p style="color:#777; font-size:13px;">Todavía no hay incidencias registradas.</p>';
    return;
  }

  const conteos = GRUPOS_ESTADO.map(grupo => ({
    ...grupo,
    total: incidencias.filter(inc => grupo.estados.includes(inc.estado)).length,
  }));
  const maximo = Math.max(...conteos.map(g => g.total), 1);

  conteos.forEach(grupo => {
    const fila = document.createElement('div');
    fila.className = 'barra-estado';
    fila.title = `${grupo.etiqueta}: ${grupo.total} de ${incidencias.length} incidencias`;
    fila.innerHTML = `
      <div class="barra-etiqueta">${grupo.etiqueta} <span class="barra-valor">${grupo.total}</span></div>
      <div class="barra-pista">
        <div class="barra-relleno ${grupo.color}" style="width:${(grupo.total / maximo) * 100}%"></div>
      </div>
    `;
    contenedor.appendChild(fila);
  });
}

function renderIncidenciasRecientes(incidencias) {
  const cuerpo = document.getElementById('cuerpoIncidenciasRecientes');
  cuerpo.innerHTML = '';

  if (!incidencias.length) {
    cuerpo.innerHTML = '<tr><td colspan="4" style="text-align:center; color:#777;">Sin incidencias todavía.</td></tr>';
    return;
  }

  // La API ya devuelve las incidencias ordenadas por fecha_registro DESC.
  incidencias.slice(0, 5).forEach(inc => {
    const fila = document.createElement('tr');
    fila.innerHTML = `
      <td>${inc.tipo_incidencia}</td>
      <td>${ETIQUETAS_ESTADO_INC[inc.estado] || inc.estado}</td>
      <td>#${inc.id_contenedor}</td>
      <td>${inc.fecha_registro}</td>
    `;
    cuerpo.appendChild(fila);
  });
}

(async function cargarDashboard() {
  const sesion = await obtenerSesion();
  if (!exigirAdministrador(sesion)) return; // el Dashboard completo es solo para Administrador

  const contenedorTarjetas = document.getElementById('tarjetasEstadisticas');

  const [contenedores, camiones, cuadrillas, rutas, instalaciones, incidencias] = await Promise.all([
    pedir(`${BASE_GESTION}/contenedores.php`),
    pedir(`${BASE_GESTION}/camiones.php`),
    pedir(`${BASE_GESTION}/cuadrillas.php`),
    pedir(`${BASE_GESTION}/rutas.php`),
    pedir(`${BASE_GESTION}/instalaciones.php`),
    pedir(`${BASE_INCIDENCIAS}/listar.php`),
  ]);

  const listaContenedores = contenedores.exito ? contenedores.contenedores : [];
  const listaCamiones = camiones.exito ? camiones.camiones : [];
  const listaCuadrillas = cuadrillas.exito ? cuadrillas.cuadrillas : [];
  const listaRutas = rutas.exito ? rutas.rutas : [];
  const listaInstalaciones = instalaciones.exito ? instalaciones.instalaciones : [];
  const listaIncidencias = incidencias.exito ? incidencias.incidencias : [];

  const desbordados = listaContenedores.filter(c => c.estado === 'DESBORDADO').length;
  const camionesDisponibles = listaCamiones.filter(c => c.estado === 'DISPONIBLE').length;

  contenedorTarjetas.appendChild(tarjeta(listaContenedores.length, 'Contenedores totales'));
  contenedorTarjetas.appendChild(tarjeta(desbordados, 'Contenedores desbordados', desbordados > 0));
  contenedorTarjetas.appendChild(tarjeta(`${camionesDisponibles} / ${listaCamiones.length}`, 'Camiones disponibles'));
  contenedorTarjetas.appendChild(tarjeta(listaCuadrillas.length, 'Cuadrillas'));
  contenedorTarjetas.appendChild(tarjeta(listaRutas.length, 'Rutas definidas'));
  contenedorTarjetas.appendChild(tarjeta(listaInstalaciones.length, 'Instalaciones'));

  renderGraficoEstados(listaIncidencias);
  renderIncidenciasRecientes(listaIncidencias);
})();
