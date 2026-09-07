document.addEventListener('DOMContentLoaded', async () => {
  const form = document.getElementById('formIncidencia');
  const selectContenedor = document.getElementById('contenedor');
  const divMensaje = document.getElementById('mensaje');

  function mostrarMensaje(texto, tipo) {
    divMensaje.textContent = texto;
    divMensaje.className = 'mensaje ' + tipo;
  }

  function etiquetaEstado(estado) {
    return estado === 'DESBORDADO' ? ' (ya reportado como desbordado)' : '';
  }

  try {
    const respuesta = await fetch('../api-incidencias/contenedores_disponibles.php');
    const datos = await respuesta.json();
    selectContenedor.innerHTML = '<option value="">Seleccionar...</option>';
    if (datos.exito && datos.contenedores.length) {
      datos.contenedores.forEach(c => {
        const opcion = new Option(
          `Contenedor #${c.id_contenedor} — ${c.coordenadas}${etiquetaEstado(c.estado)}`,
          c.id_contenedor
        );
        selectContenedor.appendChild(opcion);
      });
    } else {
      selectContenedor.innerHTML = '<option value="">No hay contenedores cargados todavía</option>';
    }
  } catch (error) {
    selectContenedor.innerHTML = '<option value="">Error al cargar contenedores</option>';
  }

  form.addEventListener('submit', async (evento) => {
    evento.preventDefault();
    divMensaje.className = 'mensaje';

    const datos = {
      id_contenedor: Number(selectContenedor.value),
      tipo_incidencia: document.getElementById('tipo').value,
      descripcion: document.getElementById('descripcion').value.trim(),
      email_vecino: document.getElementById('email').value.trim(),
    };

    if (!datos.id_contenedor) {
      mostrarMensaje('Tenés que seleccionar un contenedor.', 'error');
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
      } else {
        mostrarMensaje(resultado.mensaje || 'No se pudo registrar la incidencia.', 'error');
      }
    } catch (error) {
      mostrarMensaje('Error de conexión con el servidor.', 'error');
    }
  });
});
