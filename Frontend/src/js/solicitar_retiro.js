document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('formRetiro');
  const divMensaje = document.getElementById('mensaje');

  function mostrarMensaje(texto, tipo) {
    mostrarToast(texto, tipo);
  }

  form.addEventListener('submit', async (evento) => {
    evento.preventDefault();
    divMensaje.className = 'mensaje';

    const datos = {
      tipo_residuo: document.getElementById('tipoResiduo').value,
      direccion: document.getElementById('direccion').value.trim(),
      descripcion: document.getElementById('descripcion').value.trim(),
      email_vecino: document.getElementById('email').value.trim(),
    };

    try {
      const respuesta = await fetch('../api-retiros/registrar.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(datos),
      });
      const resultado = await respuesta.json();

      if (resultado.exito) {
        mostrarMensaje(resultado.mensaje, 'exito');
        form.reset();
      } else {
        mostrarMensaje(resultado.mensaje || 'No se pudo enviar la solicitud.', 'error');
      }
    } catch (error) {
      mostrarMensaje('Error de conexión con el servidor.', 'error');
    }
  });
});
