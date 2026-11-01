document.addEventListener('DOMContentLoaded', async () => {
  const form = document.getElementById('formIncidencia');
  const inputDireccion = document.getElementById('direccionContenedor');
  const listaDirecciones = document.getElementById('listaDirecciones');
  const inputContenedor = document.getElementById('contenedor');
  const avisoContenedor = document.getElementById('avisoContenedor');
  const divMapa = document.getElementById('mapaReporte');
  const divMensaje = document.getElementById('mensaje');

  const botonesModo = document.querySelectorAll('.modo-btn');
  const bloqueDireccion = document.getElementById('bloqueDireccion');
  const bloqueMapa = document.getElementById('bloqueMapa');
  const seleccionMapaInfo = document.getElementById('seleccionMapaInfo');

  // El vecino identifica el contenedor por su dirección (no por id ni
  // coordenadas): esta tabla mapea el texto exacto que ve en la lista de
  // sugerencias con el contenedor real. Si dos contenedores comparten
  // dirección, se desambiguan agregando "(2)", "(3)", etc.
  let contenedoresPorEtiqueta = {};
  let todosLosContenedores = [];
  let mapaReporte = null;
  let marcadorReporte = null;
  let modoActual = 'direccion';

  // Mapa grande de selección por clic (RF18 — el vecino no siempre
  // recuerda la dirección exacta, así que puede elegir el contenedor
  // haciendo clic directamente sobre su marcador).
  let mapaSeleccion = null;
  let capaMarcadoresSeleccion = null;
  let marcadorSeleccionado = null;
  const MONTEVIDEO_CENTRO = [-34.9011, -56.1645];
  const MONTEVIDEO_ZOOM = 12;
  const COLOR_ESTADO_CONTENEDOR = { OPERATIVO: '#2E7D32', DESBORDADO: '#C0392B', FUERA_DE_SERVICIO: '#888888' };
  const COLOR_SELECCIONADO = '#5C61F2';

  function etiquetaEstado(estado) {
    return estado === 'DESBORDADO' ? ' (ya reportado como desbordado)' : '';
  }

  function mostrarMensaje(texto, tipo) {
    mostrarToast(texto, tipo);
  }

  function mostrarAviso(texto) {
    avisoContenedor.textContent = texto;
    avisoContenedor.hidden = false;
  }

  function ocultarAviso() {
    avisoContenedor.hidden = true;
  }

  function ocultarMapa() {
    divMapa.hidden = true;
  }

  function mostrarMapa(contenedor) {
    if (!contenedor.coordenadas) {
      ocultarMapa();
      mostrarAviso(`Dirección encontrada${etiquetaEstado(contenedor.estado)}, pero todavía no tiene ubicación cargada en el mapa.`);
      return;
    }
    const [lat, lon] = contenedor.coordenadas.split(',').map(Number);
    if (Number.isNaN(lat) || Number.isNaN(lon)) {
      ocultarMapa();
      mostrarAviso(`Dirección encontrada${etiquetaEstado(contenedor.estado)}, pero todavía no tiene ubicación cargada en el mapa.`);
      return;
    }

    if (contenedor.estado === 'DESBORDADO') {
      mostrarAviso('Este contenedor ya está reportado como desbordado.');
    } else {
      ocultarAviso();
    }

    divMapa.hidden = false;
    if (!mapaReporte && typeof L !== 'undefined') {
      mapaReporte = L.map('mapaReporte').setView([lat, lon], 16);
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        maxZoom: 19,
      }).addTo(mapaReporte);
    }
    if (!mapaReporte) {
      ocultarMapa();
      mostrarAviso('No se pudo cargar el mapa (¿sin conexión a internet para Leaflet/OpenStreetMap?).');
      return;
    }

    mapaReporte.setView([lat, lon], 16);
    if (marcadorReporte) marcadorReporte.remove();
    marcadorReporte = L.marker([lat, lon]).addTo(mapaReporte)
      .bindPopup(contenedor.direccion || `Contenedor #${contenedor.id_contenedor}`)
      .openPopup();
    // Si el mapa se creó mientras el div estaba oculto, Leaflet necesita
    // que se lo avise una vez que ya tiene tamaño real.
    setTimeout(() => mapaReporte.invalidateSize(), 100);
  }

  function actualizarSeleccion() {
    const texto = inputDireccion.value.trim();
    const contenedor = contenedoresPorEtiqueta[texto];

    if (!contenedor) {
      inputContenedor.value = '';
      ocultarMapa();
      if (texto) {
        mostrarAviso('No encontramos un contenedor con esa dirección exacta. Elegí una de las sugerencias de la lista.');
      } else {
        ocultarAviso();
      }
      return;
    }

    inputContenedor.value = contenedor.id_contenedor;
    mostrarMapa(contenedor);
  }

  inputDireccion.addEventListener('input', actualizarSeleccion);

  // ---------------------------------------------------------------
  // Selección por clic en el mapa
  // ---------------------------------------------------------------
  function iniciarMapaSeleccion() {
    if (mapaSeleccion || typeof L === 'undefined') return;
    mapaSeleccion = L.map('mapaSeleccion').setView(MONTEVIDEO_CENTRO, MONTEVIDEO_ZOOM);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
      maxZoom: 19,
    }).addTo(mapaSeleccion);
    // Son miles de contenedores reales, así que se agrupan en clusters
    // en vez de pintar un circleMarker suelto por cada uno (si no, el
    // mapa queda inusable con tantos puntos).
    capaMarcadoresSeleccion = (typeof L.markerClusterGroup === 'function')
      ? L.markerClusterGroup({ spiderfyOnMaxZoom: true, showCoverageOnHover: false, maxClusterRadius: 50 })
      : L.layerGroup();
    capaMarcadoresSeleccion.addTo(mapaSeleccion);
  }

  function pintarMarcadoresSeleccion() {
    if (!mapaSeleccion || !capaMarcadoresSeleccion) return;
    capaMarcadoresSeleccion.clearLayers();

    const marcadores = [];
    todosLosContenedores.forEach((c) => {
      if (!c.coordenadas) return;
      const [lat, lon] = c.coordenadas.split(',').map(Number);
      if (Number.isNaN(lat) || Number.isNaN(lon)) return;

      const colorEstado = COLOR_ESTADO_CONTENEDOR[c.estado] || '#888';
      const etiqueta = c.direccion || `Contenedor #${c.id_contenedor} (sin dirección cargada)`;
      const marcador = L.circleMarker([lat, lon], {
        radius: 8,
        color: '#1a1a2e',
        weight: 1.5,
        fillColor: colorEstado,
        fillOpacity: 0.9,
      }).bindPopup(`${etiqueta}${etiquetaEstado(c.estado)}`);

      marcador.on('click', () => seleccionarContenedorDesdeMapa(c, marcador));
      marcadores.push(marcador);
    });

    if (typeof capaMarcadoresSeleccion.addLayers === 'function') {
      capaMarcadoresSeleccion.addLayers(marcadores);
    } else {
      marcadores.forEach((m) => m.addTo(capaMarcadoresSeleccion));
    }
  }

  function seleccionarContenedorDesdeMapa(contenedor, marcador) {
    if (marcadorSeleccionado) {
      marcadorSeleccionado.setStyle({ color: '#1a1a2e', weight: 1.5 });
    }
    marcador.setStyle({ color: COLOR_SELECCIONADO, weight: 3 });
    marcadorSeleccionado = marcador;

    inputContenedor.value = contenedor.id_contenedor;
    const etiqueta = contenedor.direccion || `Contenedor #${contenedor.id_contenedor} (sin dirección cargada)`;
    seleccionMapaInfo.textContent = `Seleccionaste: ${etiqueta}${etiquetaEstado(contenedor.estado)}`;
    seleccionMapaInfo.hidden = false;
  }

  function cambiarModo(modo) {
    if (modo === modoActual) return;
    modoActual = modo;

    botonesModo.forEach((btn) => btn.classList.toggle('activo', btn.dataset.modo === modo));

    if (modo === 'direccion') {
      bloqueMapa.hidden = true;
      bloqueDireccion.hidden = false;
    } else {
      bloqueDireccion.hidden = true;
      bloqueMapa.hidden = false;
      iniciarMapaSeleccion();
      pintarMarcadoresSeleccion();
      setTimeout(() => { if (mapaSeleccion) mapaSeleccion.invalidateSize(); }, 100);
    }

    // Al cambiar de modo se descarta cualquier selección previa: evita
    // que quede elegido un contenedor del otro modo sin que se vea.
    inputContenedor.value = '';
    inputDireccion.value = '';
    ocultarAviso();
    ocultarMapa();
    seleccionMapaInfo.hidden = true;
    if (marcadorSeleccionado) {
      marcadorSeleccionado.setStyle({ color: '#1a1a2e', weight: 1.5 });
      marcadorSeleccionado = null;
    }
  }

  botonesModo.forEach((btn) => {
    btn.addEventListener('click', () => cambiarModo(btn.dataset.modo));
  });

  try {
    const respuesta = await fetch('../api-incidencias/contenedores_disponibles.php');
    const datos = await respuesta.json();

    if (datos.exito && datos.contenedores.length) {
      todosLosContenedores = datos.contenedores;
      datos.contenedores.forEach(c => {
        const base = c.direccion ? c.direccion.trim() : `Contenedor #${c.id_contenedor} (sin dirección cargada)`;
        let etiqueta = base;
        let sufijo = 2;
        while (contenedoresPorEtiqueta[etiqueta] && contenedoresPorEtiqueta[etiqueta].id_contenedor !== c.id_contenedor) {
          etiqueta = `${base} (${sufijo})`;
          sufijo++;
        }
        contenedoresPorEtiqueta[etiqueta] = c;
        listaDirecciones.appendChild(new Option(etiqueta));
      });
      inputDireccion.placeholder = 'Empezá a escribir la dirección...';
    } else {
      inputDireccion.placeholder = 'No hay contenedores cargados todavía';
      inputDireccion.disabled = true;
    }
  } catch (error) {
    inputDireccion.placeholder = 'Error al cargar contenedores';
    inputDireccion.disabled = true;
  }

  form.addEventListener('submit', async (evento) => {
    evento.preventDefault();
    divMensaje.className = 'mensaje';

    const datos = {
      id_contenedor: Number(inputContenedor.value),
      tipo_incidencia: document.getElementById('tipo').value,
      descripcion: document.getElementById('descripcion').value.trim(),
    };

    if (!datos.id_contenedor) {
      const mensajeError = modoActual === 'mapa'
        ? 'Tenés que hacer clic en el contenedor correspondiente en el mapa.'
        : 'Tenés que elegir la dirección del contenedor de la lista de sugerencias.';
      mostrarMensaje(mensajeError, 'error');
      return;
    }

    try {
      const respuesta = await fetch('../api-incidencias/registrar.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(datos),
      });
      const resultado = await respuesta.json();

      if (resultado.exito) {
        mostrarMensaje(resultado.mensaje, 'exito');
        form.reset();
        inputContenedor.value = '';
        ocultarAviso();
        ocultarMapa();
        seleccionMapaInfo.hidden = true;
        if (marcadorSeleccionado) {
          marcadorSeleccionado.setStyle({ color: '#1a1a2e', weight: 1.5 });
          marcadorSeleccionado = null;
        }
        // Una vez registrada la incidencia no queda nada más para
        // hacer en este formulario: se lo lleva de vuelta al inicio,
        // dándole tiempo a leer el aviso de confirmación primero.
        setTimeout(() => { window.location.href = 'landing.html'; }, 2000);
      } else {
        mostrarMensaje(resultado.mensaje || 'No se pudo registrar la incidencia.', 'error');
      }
    } catch (error) {
      mostrarMensaje('Error de conexión con el servidor.', 'error');
    }
  });
});
