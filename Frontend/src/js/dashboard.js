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
  usuarios: '<svg viewBox="0 0 100 100" width="36" height="36"><defs><linearGradient id="gradUsuarios" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" stop-color="#4facfe"/><stop offset="100%" stop-color="#1565c0"/></linearGradient></defs><circle cx="50" cy="50" r="48" fill="url(#gradUsuarios)"/><circle cx="50" cy="38" r="16" fill="#ffffff"/><path d="M20 84c0-17 13.5-29 30-29s30 12 30 29" fill="#ffffff"/></svg>',
  contenedores: '<img src="img/icono_contenedores.png" alt="Contenedores" class="icono-img icono-contenedores">',
  camiones: '<img src="img/icono_camiones.png" alt="Camiones" class="icono-img icono-camiones">',
  incidencias: '<span class="icono-emoji">⚠️</span>',
  cuadrillas: '<img src="img/imagen.cuadrilla.png" alt="Cuadrillas" class="icono-img icono-cuadrillas">',
  rutas: '<img src="img/imagen.rutas.png" alt="Rutas" class="icono-img icono-rutas">',
  instalaciones: '<img src="img/imagen.instalaciones.png" alt="Instalaciones" class="icono-img icono-instalaciones">',
  contenedores_desbordados: '<img src="img/imagen.contenedores_desbordados.png" alt="Contenedores desbordados" class="icono-img icono-desbordados">',
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

const ICONO_BOTE_BASURA = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>';

// Severidad visual derivada del tipo de incidencia (mismo criterio que el
// auto-marcado de contenedor DESBORDADO en api-incidencias/registrar.php).
function severidadIncidencia(tipoIncidencia) {
  const texto = (tipoIncidencia || '').toLowerCase();
  if (texto.includes('desbord')) return { etiqueta: 'Alta', color: 'rojo' };
  if (texto.includes('dañ') || texto.includes('dan')) return { etiqueta: 'Media', color: 'ambar' };
  return { etiqueta: 'Baja', color: 'verde' };
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
    const severidad = severidadIncidencia(inc.tipo_incidencia);
    const fila = document.createElement('li');
    fila.className = 'fila-incidencia-reciente';
    fila.innerHTML = `
      <span class="icono-incidencia-reciente ${severidad.color}">${ICONO_BOTE_BASURA}</span>
      <div class="info-incidencia-reciente">
        <div class="titulo-incidencia-reciente">${inc.tipo_incidencia}</div>
        <div class="direccion-incidencia-reciente">${inc.contenedor_direccion || `Contenedor #${inc.id_contenedor}`}</div>
      </div>
      <div class="columna-estado-fecha">
        <span class="chip-severidad-incidencia ${severidad.color}">${severidad.etiqueta}</span>
        <span class="fecha-incidencia-reciente">${formatearFecha(inc.fecha_registro)}</span>
      </div>
    `;
    lista.appendChild(fila);
  });
}

// Top de contenedores desbordados del mes (panel de control con
// estadísticas — 3ra entrega). No hay endpoint de stats dedicado: se
// calcula acá mismo, en el cliente, a partir de las incidencias ya
// cargadas — mismo criterio que el resto del Dashboard.
function calcularTopDesbordados(incidencias, dias = 30) {
  const limite = new Date();
  limite.setDate(limite.getDate() - dias);

  const conteos = new Map(); // id_contenedor -> { direccion, total }

  incidencias
    .filter(inc => (inc.tipo_incidencia || '').toLowerCase().includes('desborde'))
    .filter(inc => {
      const fecha = new Date((inc.fecha_registro || '').replace(' ', 'T'));
      return !Number.isNaN(fecha.getTime()) && fecha >= limite;
    })
    .forEach(inc => {
      const clave = inc.id_contenedor;
      const actual = conteos.get(clave) || {
        direccion: inc.contenedor_direccion || `Contenedor #${inc.id_contenedor}`,
        total: 0,
      };
      actual.total += 1;
      conteos.set(clave, actual);
    });

  return [...conteos.values()].sort((a, b) => b.total - a.total).slice(0, 5);
}

function renderTopDesbordados(incidencias) {
  const lista = document.getElementById('listaTopDesbordados');
  if (!lista) return;
  lista.innerHTML = '';

  const top = calcularTopDesbordados(incidencias);

  if (!top.length) {
    lista.innerHTML = '<li class="nota-pie-oscura">Sin desbordes reportados en los últimos 30 días.</li>';
    return;
  }

  top.forEach((item, indice) => {
    const fila = document.createElement('li');
    fila.className = 'fila-top-desbordado';
    fila.innerHTML = `
      <span class="puesto-top-desbordado">${indice + 1}</span>
      <div class="info-top-desbordado">
        <div class="direccion-top-desbordado">${item.direccion}</div>
        <div class="conteo-top-desbordado">${item.total === 1 ? '1 desborde reportado' : `${item.total} desbordes reportados`}</div>
      </div>
      <span class="chip-conteo-desbordados">${item.total}×</span>
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
  contenedorTarjetas.appendChild(tarjeta('contenedores_desbordados', 'Contenedores desbordados', desbordados, desbordados > 0));
  contenedorTarjetas.appendChild(tarjeta('camiones', 'Camiones', `${camionesDisponibles} / ${listaCamiones.length}`));
  contenedorTarjetas.appendChild(tarjeta('incidencias', 'Incidencias', listaIncidencias.length));
  contenedorTarjetas.appendChild(tarjeta('cuadrillas', 'Cuadrillas', listaCuadrillas.length));
  contenedorTarjetas.appendChild(tarjeta('rutas', 'Rutas definidas', listaRutas.length));
  contenedorTarjetas.appendChild(tarjeta('instalaciones', 'Instalaciones', listaInstalaciones.length));
  

  actualizarHoraInfo();
  renderGraficoEstados(listaIncidencias);
  renderIncidenciasRecientes(listaIncidencias);
  renderTopDesbordados(listaIncidencias);
})();