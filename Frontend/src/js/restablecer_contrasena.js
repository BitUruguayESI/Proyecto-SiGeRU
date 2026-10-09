document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('formRestablecer');
  const divMensaje = document.getElementById('mensaje');

  function mostrarMensaje(texto, tipo) {
    divMensaje.textContent = texto;
    divMensaje.className = 'mensaje ' + tipo;
  }

  const parametros = new URLSearchParams(window.location.search);
  const token = parametros.get('token');

  if (!token) {
    mostrarMensaje('Este link no es válido. Pedí uno nuevo desde "Olvidé mi contraseña".', 'error');
    form.querySelector('button[type="submit"]').disabled = true;
    return;
  }

  form.addEventListener('submit', async (evento) => {
    evento.preventDefault();
    divMensaje.className = 'mensaje';

    const contrasena = document.getElementById('contrasena').value;
    const confirmar = document.getElementById('confirmar_contrasena').value;

    if (contrasena.length < 8) {
      mostrarMensaje('La contraseña debe tener al menos 8 caracteres.', 'error');
      return;
    }
    if (contrasena !== confirmar) {
      mostrarMensaje('Las contraseñas no coinciden.', 'error');
      return;
    }

    try {
      const respuesta = await fetch('../api-usuarios/restablecer_contrasena.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ token, contrasena }),
      });
      const resultado = await respuesta.json();

      mostrarMensaje(resultado.mensaje || 'Ocurrió un error.', resultado.exito ? 'exito' : 'error');
      if (resultado.exito) {
        form.reset();
        form.querySelector('button[type="submit"]').disabled = true;
        setTimeout(() => { window.location.href = 'login.html'; }, 1500);
      }
    } catch (error) {
      mostrarMensaje('Error de conexión con el servidor.', 'error');
    }
  });
});