/**
 * nav_admin.js
 * Inyecta la barra de navegación de las pantallas de personal dentro de
 * un <div id="navAdmin"></div> presente en la página. Marca como activo
 * el link que corresponde a la página actual vía data-pagina en <body>,
 * y arma un menú distinto según el rol de la sesión activa (RN de
 * dashboards por rol):
 *   - Administrador: el menú completo, como siempre, más la campana de
 *     notificaciones (nivel avanzado 3.1) junto al botón de cerrar
 *     sesión, para que se vea desde cualquier pantalla del staff, no
 *     solo desde "Gestión de recursos".
 *   - Recolector: solo "Mi día" (incidencias del día, asignación diaria
 *     y cuadrilla propia).
 *   - Chofer: "Mi día" + "Recolección" (para registrar descargas).
 *
 * Depende de sesion.js (obtenerSesion()) — tiene que cargarse antes.
 *
 * Uso: <body data-pagina="dashboard"> ... <div id="navAdmin"></div>
 *      <script src="js/sesion.js"></script>
 *      <script src="js/nav_admin.js"></script>
 */

// ---------------------------------------------------------------
// Campana de notificaciones (nivel avanzado 3.1) — solo Administrador,
// que es el único rol autorizado por api-gestion/notificaciones.php.
// ---------------------------------------------------------------
const BASE_NOTIFICACIONES = '../api-gestion';

const ETIQUETAS_TIPO_NOTIFICACION = {
  CONTENEDOR_DESBORDADO: 'Contenedor desbordado',
  INCIDENCIA_PENDIENTE: 'Incidencia pendiente',
  RETIRO_SIN_RESPONDER: 'Retiro sin responder',
  CONSULTA_SIN_RESPONDER: 'Consulta sin responder',
  CARNET_POR_VENCER: 'Carnet de salud por vencer',
  LICENCIA_POR_VENCER: 'Licencia de conducir por vencer',
};
const TIPOS_URGENTES_NOTIFICACION = ['CONTENEDOR_DESBORDADO', 'CARNET_POR_VENCER', 'LICENCIA_POR_VENCER'];

function crearCampanaNotificaciones() {
  const contenedor = document.createElement('div');
  contenedor.className = 'campana-notificaciones';
  contenedor.innerHTML = `
    <button id="btnNotificaciones" class="btn-campana" type="button" title="Notificaciones">
      <svg viewBox="0 0 448 512" width="24" height="24" fill="currentColor"><path d="M224 0c-17.7 0-32 14.3-32 32V51.2C119.5 66 64 130.6 64 208v18.8c0 47-17.3 92.4-48.5 127.6l-7.4 8.3c-8.4 9.4-10.4 22.9-5.3 34.4S19.4 416 32 416H416c12.6 0 24-7.4 29.2-18.9s3.1-25-5.3-34.4l-7.4-8.3C401.3 319.2 384 273.9 384 226.8V208c0-77.4-55.5-142-128-156.8V32c0-17.7-14.3-32-32-32zm45.3 493.3c12-12 18.7-28.3 18.7-45.3H160c0 17 6.7 33.3 18.7 45.3s28.3 18.7 45.3 18.7s33.3-6.7 45.3-18.7z"/></svg>
      <span id="contadorNoLeidas" class="badge-notificacion" hidden>0</span>
    </button>
    <div id="panelNotificaciones" class="panel-notificaciones" hidden>
      <div class="panel-notificaciones-header">
        <strong>Notificaciones</strong>
        <button id="btnMarcarTodasLeidas" class="chico secundario" type="button">Marcar todas como leídas</button>
      </div>
      <ul id="listaNotificaciones"><li class="item-notificacion-vacio">Sin notificaciones.</li></ul>
    </div>
  `;
  return contenedor;
}

// Recibe el propio contenedor de la campana (en vez de buscar por ID en
// document) para que funcione sin importar si ya está insertado en la
// página o todavía está siendo armado (evita una carrera con el resto
// de iniciarNavAdmin, que recién inserta el <nav> al final).
async function cargarNotificacionesNav(campana) {
  try {
    const respuesta = await fetch(`${BASE_NOTIFICACIONES}/notificaciones.php`);
    if (!respuesta.ok) return;
    const datos = await respuesta.json();
    if (!datos.exito) return;

    const contador = campana.querySelector('#contadorNoLeidas');
    contador.textContent = String(datos.no_leidas);
    contador.hidden = datos.no_leidas === 0;

    const lista = campana.querySelector('#listaNotificaciones');
    lista.innerHTML = '';
    if (datos.notificaciones.length === 0) {
      lista.innerHTML = '<li class="item-notificacion-vacio">Sin notificaciones.</li>';
      return;
    }

    datos.notificaciones.forEach(n => {
      const item = document.createElement('li');
      item.className = 'item-notificacion'
        + (!n.leida ? ' no-leida' : '')
        + (TIPOS_URGENTES_NOTIFICACION.includes(n.tipo) ? ' urgente' : '');
      item.innerHTML = `
        <div class="item-notificacion-tipo">${ETIQUETAS_TIPO_NOTIFICACION[n.tipo] || n.tipo}</div>
        <div class="item-notificacion-mensaje">${n.mensaje}</div>
        <div class="item-notificacion-fecha">${n.fecha_hora}</div>
        ${!n.leida ? `<button class="chico secundario btn-marcar-leida-notificacion" data-id="${n.id_notificacion}">Marcar como leída</button>` : ''}
      `;
      lista.appendChild(item);
    });

    lista.querySelectorAll('.btn-marcar-leida-notificacion').forEach(boton => {
      boton.addEventListener('click', async () => {
        await fetch(`${BASE_NOTIFICACIONES}/notificaciones.php`, {
          method: 'PUT',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ id_notificacion: Number(boton.dataset.id) }),
        });
        cargarNotificacionesNav(campana);
      });
    });
  } catch (error) {
    // Silencioso: si falla (sin conexión, etc.) la campana simplemente
    // no actualiza el contador, no rompe el resto de la página.
  }
}

function iniciarCampanaNotificaciones(nav) {
  const campana = crearCampanaNotificaciones();
  nav.appendChild(campana);

  const btnNotificaciones = campana.querySelector('#btnNotificaciones');
  const panel = campana.querySelector('#panelNotificaciones');
  const btnMarcarTodas = campana.querySelector('#btnMarcarTodasLeidas');

  btnNotificaciones.addEventListener('click', () => {
    panel.hidden = !panel.hidden;
    if (!panel.hidden) cargarNotificacionesNav(campana);
  });

  document.addEventListener('click', (evento) => {
    if (!panel.hidden && !campana.contains(evento.target)) {
      panel.hidden = true;
    }
  });

  btnMarcarTodas.addEventListener('click', async () => {
    await fetch(`${BASE_NOTIFICACIONES}/notificaciones.php`, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ marcar_todas: true }),
    });
    cargarNotificacionesNav(campana);
  });

  cargarNotificacionesNav(campana);
  setInterval(() => cargarNotificacionesNav(campana), 60000);
}

(async function iniciarNavAdmin() {
  const contenedor = document.getElementById('navAdmin');
  if (!contenedor) return;

  const paginaActual = document.body.dataset.pagina || '';

  const sesion = await obtenerSesion();
  if (!sesion) return; // obtenerSesion ya redirige a login.html si no hay sesión

  const enlacesAdministrador = [
    { pagina: 'dashboard', href: 'dashboard.html', texto: 'Dashboard' },
    { pagina: 'gestion', href: 'panel_gestion.html', texto: 'Gestión de recursos' },
    { pagina: 'incidencias', href: 'panel_incidencias.html', texto: 'Incidencias' },
    { pagina: 'recoleccion', href: 'panel_recoleccion.html', texto: 'Recolección' },
    { pagina: 'asignacion-diaria', href: 'panel_recoleccion.html#asignaciones', texto: 'Asignación diaria' },
  ];
  const enlacesRecolector = [
    { pagina: 'mi-dia', href: 'mi_dia.html', texto: 'Mi día' },
  ];
  const enlacesChofer = [
    { pagina: 'mi-dia', href: 'mi_dia.html', texto: 'Mi día' },
    { pagina: 'recoleccion', href: 'panel_recoleccion.html', texto: 'Recolección' },
  ];

  const rol = rolDeSesion(sesion);
  const enlaces = rol === 'ADMINISTRADOR' ? enlacesAdministrador
    : rol === 'CHOFER' ? enlacesChofer
    : enlacesRecolector;

  const nav = document.createElement('nav');
  nav.className = 'nav-admin';

  enlaces.forEach(enlace => {
    const a = document.createElement('a');
    a.href = enlace.href;
    a.textContent = enlace.texto;
    if (enlace.pagina === paginaActual) a.classList.add('activa');
    if (enlace.proximamente) {
      a.classList.add('deshabilitada');
      a.setAttribute('aria-disabled', 'true');
      const etiqueta = document.createElement('span');
      etiqueta.className = 'etiqueta-proximamente';
      etiqueta.textContent = 'próximamente';
      a.appendChild(etiqueta);
    }
    nav.appendChild(a);
  });

  const separador = document.createElement('div');
  separador.className = 'separador';
  nav.appendChild(separador);

  // La campana va antes del nombre de usuario y "Cerrar sesión", en la
  // misma barra, para que se vea desde cualquier pantalla del staff.
  if (rol === 'ADMINISTRADOR') {
    iniciarCampanaNotificaciones(nav);
  }

  const spanUsuario = document.createElement('span');
  spanUsuario.className = 'usuario-actual';
  spanUsuario.textContent = '';
  nav.appendChild(spanUsuario);

  const botonSalir = document.createElement('button');
  botonSalir.className = 'cerrar-sesion';
  botonSalir.textContent = 'Cerrar sesión';
  botonSalir.addEventListener('click', async () => {
    try {
      await fetch('../api-usuarios/logout.php', { method: 'POST' });
    } finally {
      window.location.href = 'landing.html';
    }
  });
  nav.appendChild(botonSalir);

  contenedor.replaceWith(nav);

  const ETIQUETAS_ROL = { ADMINISTRADOR: 'Administrador', CHOFER: 'Chofer', RECOLECTOR: 'Recolector' };
  spanUsuario.textContent = `${sesion.nombre_completo} (${ETIQUETAS_ROL[rol] || sesion.tipo_usuario})`;
})();
