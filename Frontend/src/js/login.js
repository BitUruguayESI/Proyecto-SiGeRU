document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('formLogin');
  const divMensaje = document.getElementById('mensaje');

  function mostrarMensaje(texto, tipo) {
    mostrarToast(texto, tipo);
  }

  form.addEventListener('submit', async (evento) => {
    evento.preventDefault();
    divMensaje.className = 'mensaje';

    const datos = {
      ci: document.getElementById('ci').value.trim(),
      contrasena: document.getElementById('contrasena').value,
    };

    try {
      const respuesta = await fetch('../api-usuarios/login.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(datos),
      });
      const resultado = await respuesta.json();

      if (resultado.exito) {
        mostrarMensaje(`Bienvenido/a, ${resultado.nombre_completo}.`, 'exito');
        const destino = resultado.tipo_usuario === 'ADMINISTRADOR' ? 'dashboard.html' : 'mi_dia.html';
        setTimeout(() => { window.location.href = destino; }, 700);
      } else {
        mostrarMensaje(resultado.mensaje || 'No se pudo iniciar sesión.', 'error');
      }
    } catch (error) {
      mostrarMensaje('Error de conexión con el servidor.', 'error');
    }
  });
});
