document.addEventListener('DOMContentLoaded', async () => {
  const sesion = await obtenerSesion();
  if (!exigirAdministrador(sesion)) return;

  const form = document.getElementById('formRegistro');
  const selectTipo = document.getElementById('tipo_usuario');
  const bloqueCargo = document.getElementById('bloqueCargo');
  const campoCargo = document.getElementById('cargo');
  const bloqueLicencia = document.getElementById('bloqueLicencia');
  const campoLicenciaCategoria = document.getElementById('licencia_categoria');
  const campoLicenciaVencimiento = document.getElementById('licencia_vencimiento');
  const listaTelefonos = document.getElementById('listaTelefonos');
  const btnAgregarTelefono = document.getElementById('btnAgregarTelefono');
  const divMensaje = document.getElementById('mensaje');

  function actualizarBloqueLicencia() {
    const esChofer = campoCargo.value === 'CHOFER';
    bloqueLicencia.classList.toggle('oculto', !esChofer);
    campoLicenciaCategoria.required = esChofer;
    campoLicenciaVencimiento.required = esChofer;
  }

  // Mostrar/ocultar "cargo" según el tipo de usuario elegido
  selectTipo.addEventListener('change', () => {
    const esOperario = selectTipo.value === 'OPERARIO';
    bloqueCargo.classList.toggle('oculto', !esOperario);
    campoCargo.required = esOperario;
    if (!esOperario) {
      campoCargo.value = '';
      actualizarBloqueLicencia();
    }
  });

  // Mostrar/ocultar los datos de libreta según el cargo elegido (solo Chofer)
  campoCargo.addEventListener('change', actualizarBloqueLicencia);

  function agregarFilaTelefono() {
    const fila = document.createElement('div');
    fila.className = 'fila-telefono';
    fila.innerHTML = `
      <input type="text" class="telefono" placeholder="Ej: 099123456">
      <button type="button" class="secundario btn-quitar">Quitar</button>
    `;
    fila.querySelector('.btn-quitar').addEventListener('click', () => fila.remove());
    listaTelefonos.appendChild(fila);
  }
  btnAgregarTelefono.addEventListener('click', agregarFilaTelefono);
  agregarFilaTelefono(); // arrancar con un campo de teléfono visible

  function mostrarMensaje(texto, tipo) {
    divMensaje.textContent = texto;
    divMensaje.className = 'mensaje ' + tipo;
  }

  form.addEventListener('submit', async (evento) => {
    evento.preventDefault();
    divMensaje.className = 'mensaje';

    const contrasena = document.getElementById('contrasena').value;
    const confirmar = document.getElementById('confirmar_contrasena').value;
    if (contrasena !== confirmar) {
      mostrarMensaje('Las contraseñas no coinciden.', 'error');
      return;
    }

    const telefonos = Array.from(document.querySelectorAll('.telefono'))
      .map(input => input.value.trim())
      .filter(valor => valor !== '');

    const datos = {
      ci: document.getElementById('ci').value.trim(),
      nombre_completo: document.getElementById('nombre_completo').value.trim(),
      email: document.getElementById('email').value.trim(),
      contrasena,
      tipo_usuario: selectTipo.value,
      cargo: campoCargo.value.trim(),
      carnet_salud_vencimiento: document.getElementById('carnet_salud_vencimiento').value,
      licencia_categoria: campoLicenciaCategoria.value.trim(),
      licencia_vencimiento: campoLicenciaVencimiento.value,
      telefonos,
    };

    try {
      const respuesta = await fetch('../api-usuarios/registro.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(datos),
      });
      const resultado = await respuesta.json();

      if (resultado.exito) {
        mostrarMensaje('Usuario registrado correctamente.', 'exito');
        form.reset();
        bloqueCargo.classList.add('oculto');
        bloqueLicencia.classList.add('oculto');
      } else {
        mostrarMensaje(resultado.mensaje || 'No se pudo completar el registro.', 'error');
      }
    } catch (error) {
      mostrarMensaje('Error de conexión con el servidor.', 'error');
    }
  });
});
