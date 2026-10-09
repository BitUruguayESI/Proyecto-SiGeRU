document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('formRecuperar');
  const divMensaje = document.getElementById('mensaje');

  function mostrarMensaje(texto, tipo) {
    divMensaje.textContent = texto;
    divMensaje.className = 'mensaje ' + tipo;
  }

  form.addEventListener('submit', async (evento) => {
    evento.preventDefault();
    divMensaje.className = 'mensaje';

    const datos = {
      email: document.getElementById('email').value.trim(),
    };

    try {
      const respuesta = await fetch('../api-usuarios/solicitar_recuperacion.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(datos),
      });
      const resultado = await respuesta.json();

      mostrarMensaje(resultado.mensaje || 'Ocurrió un error.', resultado.exito ? 'exito' : 'error');
      if (resultado.exito) {
        form.reset();
      }
    } catch (error) {
      mostrarMensaje('Error de conexión con el servidor.', 'error');
    }
  });
});