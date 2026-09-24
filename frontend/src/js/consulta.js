document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('formConsulta');
  const divMensaje = document.getElementById('mensajeEstado');

  function mostrarMensaje(texto, tipo) {
    divMensaje.textContent = texto;
    divMensaje.className = 'mensaje ' + tipo;
  }

  form.addEventListener('submit', async (evento) => {
    evento.preventDefault();
    divMensaje.className = 'mensaje';

    const datos = {
      nombre: document.getElementById('nombre').value.trim(),
      email: document.getElementById('email').value.trim(),
      mensaje: document.getElementById('mensaje').value.trim(),
    };

    try {
      const respuesta = await fetch('../api-gestion/registrar_consulta.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(datos),
      });
      const resultado = await respuesta.json();

      if (resultado.exito) {
        mostrarMensaje(resultado.mensaje, 'exito');
        form.reset();
      } else {
        mostrarMensaje(resultado.mensaje || 'No se pudo enviar la consulta.', 'error');
      }
    } catch (error) {
      mostrarMensaje('Error de conexión con el servidor.', 'error');
    }
  });
});
