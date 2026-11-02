/**
 * toast.js
 * Ventana flotante de confirmación/aviso (RN de UX: el usuario tiene que
 * ver con claridad que una acción -guardar, enviar, eliminar, etc.- se
 * hizo bien o mal, sin depender solo del textito fijo que ya mostraba
 * cada formulario). Se carga en todas las páginas, antes del script
 * propio de cada una, y expone mostrarToast() de forma global.
 *
 * Uso: mostrarToast('Contenedor guardado correctamente.', 'exito');
 *      mostrarToast('No se pudo guardar.', 'error');
 */
function mostrarToast(mensaje, tipo = 'info', duracionMs = 4000) {
  if (!mensaje) return;

  let contenedor = document.getElementById('contenedorToasts');
  if (!contenedor) {
    contenedor = document.createElement('div');
    contenedor.id = 'contenedorToasts';
    contenedor.className = 'contenedor-toasts';
    document.body.appendChild(contenedor);
  }

  const toast = document.createElement('div');
  toast.className = `toast toast-${tipo}`;
  toast.textContent = mensaje;
  contenedor.appendChild(toast);

  // Doble rAF para que la transición de entrada se note (si se agrega
  // la clase "visible" en el mismo frame en que se inserta el nodo, el
  // navegador puede saltarse la transición).
  requestAnimationFrame(() => {
    requestAnimationFrame(() => toast.classList.add('toast-visible'));
  });

  const cerrar = () => {
    toast.classList.remove('toast-visible');
    setTimeout(() => toast.remove(), 300);
  };

  toast.addEventListener('click', cerrar);
  setTimeout(cerrar, duracionMs);
}
