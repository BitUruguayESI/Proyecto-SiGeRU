/**
 * nav_admin.js
 * Inyecta la barra de navegación de las pantallas de personal (Dashboard,
 * Panel de gestión, y a futuro Incidencias/Recolección) dentro de un
 * <div id="navAdmin"></div> presente en la página. Marca como activo el
 * link que corresponde a la página actual vía data-pagina en <body>.
 *
 * Uso: <body data-pagina="dashboard"> ... <div id="navAdmin"></div>
 *      <script src="js/nav_admin.js"></script>
 */

(async function iniciarNavAdmin() {
  const contenedor = document.getElementById('navAdmin');
  if (!contenedor) return;

  const paginaActual = document.body.dataset.pagina || '';

  const enlaces = [
    { pagina: 'dashboard', href: 'dashboard.html', texto: 'Dashboard' },
    { pagina: 'gestion', href: 'panel_gestion.html', texto: 'Gestión de recursos' },
    { pagina: 'incidencias', href: 'panel_incidencias.html', texto: 'Incidencias' },
    { pagina: 'recoleccion', href: 'panel_recoleccion.html', texto: 'Recolección' },
    { pagina: 'asignacion-diaria', href: 'panel_recoleccion.html#asignaciones', texto: 'Asignación diaria' },
  ];

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

  try {
    const respuesta = await fetch('../api-usuarios/quien_soy.php');
    if (respuesta.status === 401) {
      window.location.href = 'login.html';
      return;
    }
    const datos = await respuesta.json();
    if (datos.exito) {
      spanUsuario.textContent = `${datos.nombre_completo} (${datos.tipo_usuario})`;
    }
  } catch (error) {
    // Si falla la consulta de sesión, no rompemos la página; el resto
    // de los endpoints igual van a exigir sesión por su cuenta.
  }
})();
