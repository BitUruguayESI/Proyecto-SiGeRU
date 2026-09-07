const BASE_GESTION = '../api-gestion';

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

(async function cargarDashboard() {
  const contenedorTarjetas = document.getElementById('tarjetasEstadisticas');

  const [contenedores, camiones, cuadrillas, rutas, instalaciones] = await Promise.all([
    pedir(`${BASE_GESTION}/contenedores.php`),
    pedir(`${BASE_GESTION}/camiones.php`),
    pedir(`${BASE_GESTION}/cuadrillas.php`),
    pedir(`${BASE_GESTION}/rutas.php`),
    pedir(`${BASE_GESTION}/instalaciones.php`),
  ]);

  const listaContenedores = contenedores.exito ? contenedores.contenedores : [];
  const listaCamiones = camiones.exito ? camiones.camiones : [];
  const listaCuadrillas = cuadrillas.exito ? cuadrillas.cuadrillas : [];
  const listaRutas = rutas.exito ? rutas.rutas : [];
  const listaInstalaciones = instalaciones.exito ? instalaciones.instalaciones : [];

  const desbordados = listaContenedores.filter(c => c.estado === 'DESBORDADO').length;
  const camionesDisponibles = listaCamiones.filter(c => c.estado === 'DISPONIBLE').length;

  contenedorTarjetas.appendChild(tarjeta(listaContenedores.length, 'Contenedores totales'));
  contenedorTarjetas.appendChild(tarjeta(desbordados, 'Contenedores desbordados', desbordados > 0));
  contenedorTarjetas.appendChild(tarjeta(`${camionesDisponibles} / ${listaCamiones.length}`, 'Camiones disponibles'));
  contenedorTarjetas.appendChild(tarjeta(listaCuadrillas.length, 'Cuadrillas'));
  contenedorTarjetas.appendChild(tarjeta(listaRutas.length, 'Rutas definidas'));
  contenedorTarjetas.appendChild(tarjeta(listaInstalaciones.length, 'Instalaciones'));
})();
