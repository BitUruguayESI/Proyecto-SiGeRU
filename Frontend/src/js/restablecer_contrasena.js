document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('formRestablecer');
  const divMensaje = document.getElementById('mensaje');

  function mostrarMensaje(texto, tipo) {
    mostrarToast(texto, tipo);
  }

  // Si vino desde el link del email (restablecer_contrasena.html?email=...),
  // precarga el email para que solo haga falta tipear el código.
  const parametros = new URLSearchParams(window.location.search);
  const emailDesdeUrl = parametros.get('email');
  if (emailDesdeUrl) {
    document.getElementById('email').value = emailDesdeUrl;
  }

  form.addEventListener('submit', async (evento) => {
    evento.preventDefault();
    divMensaje.className = 'mensaje';

    const email = document.getElementById('email').value.trim();
    const codigo = document.getElementById('codigo').value.trim();
    const contrasena = document.getElementById('contrasena').value;
    const confirmar = document.getElementById('confirmar_contrasena').value;

    if (!/^[0-9]{6}$/.test(codigo)) {
      mostrarMensaje('El código tiene que ser de 6 dígitos.', 'error');
      return;
    }
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
        body: JSON.stringify({ email, codigo, contrasena }),
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
