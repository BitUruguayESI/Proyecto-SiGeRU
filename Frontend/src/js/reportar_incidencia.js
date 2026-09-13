document.addEventListener('DOMContentLoaded', async () => {
  const form = document.getElementById('formIncidencia');
  const inputDireccion = document.getElementById('direccionContenedor');
  const listaDirecciones = document.getElementById('listaDirecciones');
  const inputContenedor = document.getElementById('contenedor');
  const avisoContenedor = document.getElementById('avisoContenedor');
  const divMapa = document.getElementById('mapaReporte');
  const divMensaje = document.getElementById('mensaje');

  // El vecino identifica el contenedor por su dirección (no por id ni
  // coordenadas): esta tabla mapea el texto exacto que ve en la lista de
  // sugerencias con el contenedor real. Si dos contenedores comparten
  // dirección, se desambiguan agregando "(2)", "(3)", etc.
  let contenedoresPorEtiqueta = {};
  let mapaReporte = null;
  let marcadorReporte = null;
  const MONTEVIDEO_CENTRO = [-34.9011, -56.1645];

  function etiquetaEstado(estado) {
    return estado === 'DESBORDADO' ? ' (ya reportado como desbordado)' : '';
  }

  function mostrarMensaje(texto, tipo) {
    divMensaje.textContent = texto;
    divMensaje.className = 'mensaje ' + tipo;
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

  try {
    const respuesta = await fetch('../api-incidencias/contenedores_disponibles.php');
    const datos = await respuesta.json();

    if (datos.exito && datos.contenedores.length) {
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
      email_vecino: document.getElementById('email').value.trim(),
    };

    if (!datos.id_contenedor) {
      mostrarMensaje('Tenés que elegir la dirección del contenedor de la lista de sugerencias.', 'error');
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
      } else {
        mostrarMensaje(resultado.mensaje || 'No se pudo registrar la incidencia.', 'error');
      }
    } catch (error) {
      mostrarMensaje('Error de conexión con el servidor.', 'error');
    }
  });
});
