document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('formLogin');
  const divMensaje = document.getElementById('mensaje');

  function mostrarMensaje(texto, tipo) {
    divMensaje.textContent = texto;
    divMensaje.className = 'mensaje ' + tipo;
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
        mostrarMensaje(`Bienvenido/a, ${resultado.nombre_completo} (${resultado.tipo_usuario}).`, 'exito');
        const destino = resultado.tipo_usuario === 'ADMINISTRADOR' ? 'dashboard.html' : 'panel_incidencias.html';
        setTimeout(() => { window.location.href = destino; }, 700);
        // TODO: cuando exista api-recoleccion, el Operario probablemente
        // quiera un panel propio en vez de caer directo en incidencias.
      } else {
        mostrarMensaje(resultado.mensaje || 'No se pudo iniciar sesión.', 'error');
      }
    } catch (error) {
      mostrarMensaje('Error de conexión con el servidor.', 'error');
    }
  });
});
