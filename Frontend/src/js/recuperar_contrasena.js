document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('formRecuperar');
  const divMensaje = document.getElementById('mensaje');

  function mostrarMensaje(texto, tipo) {
    mostrarToast(texto, tipo);
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
        // Lleva directo a la pantalla donde se ingresa el código de 6
        // dígitos (con el email ya precargado), para no hacer buscar el
        // link del email y volver a tipear el email a mano.
        setTimeout(() => {
          window.location.href = 'restablecer_contrasena.html?email=' + encodeURIComponent(datos.email);
        }, 1500);
      }
    } catch (error) {
      mostrarMensaje('Error de conexión con el servidor.', 'error');
    }
  });
});