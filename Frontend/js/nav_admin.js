/**
 * nav_admin.js
 * Inyecta el menú lateral (sidebar) de las pantallas de personal dentro de
 * un <div id="navAdmin"></div> presente en la página, y completa el badge
 * de rol (#rolUsuario) que cada página trae en su propia topbar. Marca
 * como activo el link que corresponde a la página actual vía data-pagina
 * en <body>, y arma un menú distinto según el rol de la sesión activa (RN
 * de dashboards por rol):
 *   - Administrador: el menú completo, como siempre.
 *   - Recolector: solo "Mi día" (incidencias del día, asignación diaria
 *     y cuadrilla propia).
 *   - Chofer: "Mi día" + "Recolección" (para registrar descargas).
 *
 * Depende de sesion.js (obtenerSesion()) — tiene que cargarse antes.
 *
 * Uso: <body data-pagina="dashboard">
 *        <div class="dashboard">
 *          <div id="navAdmin"></div>
 *          <div class="contenido">
 *            <div class="topbar"><h1>...</h1><div id="rolUsuario"></div></div>
 *            ...
 *      <script src="js/sesion.js"></script>
 *      <script src="js/nav_admin.js"></script>
 */

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

  const aside = document.createElement('aside');
  aside.className = 'sidebar';

  const titulo = document.createElement('h2');
  titulo.textContent = 'SiGeRU';
  aside.appendChild(titulo);

  const lista = document.createElement('ul');

  enlaces.forEach(enlace => {
    const li = document.createElement('li');
    const a = document.createElement('a');
    a.href = enlace.href;
    a.textContent = enlace.texto;
    if (enlace.pagina === paginaActual) a.classList.add('active');
    if (enlace.proximamente) {
      a.classList.add('deshabilitada');
      a.setAttribute('aria-disabled', 'true');
      const etiqueta = document.createElement('span');
      etiqueta.className = 'etiqueta-proximamente';
      etiqueta.textContent = 'próximamente';
      a.appendChild(etiqueta);
    }
    li.appendChild(a);
    lista.appendChild(li);
  });

  aside.appendChild(lista);

  const separador = document.createElement('div');
  separador.className = 'separador';
  aside.appendChild(separador);

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
  aside.appendChild(botonSalir);

  contenedor.replaceWith(aside);

  const ETIQUETAS_ROL = { ADMINISTRADOR: 'Administrador', CHOFER: 'Chofer', RECOLECTOR: 'Recolector' };
  const elRol = document.getElementById('rolUsuario');
  if (elRol) {
    elRol.textContent = `${sesion.nombre_completo} (${ETIQUETAS_ROL[rol] || sesion.tipo_usuario})`;
  }
})();
