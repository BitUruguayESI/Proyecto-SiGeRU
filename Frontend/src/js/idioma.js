/**
 * js/idioma.js
 * Lógica compartida del selector de idioma (ES/EN) para las páginas
 * públicas (landing, reportar incidencia, solicitar retiro, consulta).
 * Cada página define su propio objeto TRADUCCIONES (textos en "es" y
 * "en") y llama a inicializarIdioma(TRADUCCIONES) al final de su script.
 *
 * El idioma elegido se guarda en localStorage (clave "sigeru_idioma"),
 * así que si lo cambiás en la Landing Page, se mantiene automáticamente
 * al entrar a cualquiera de las otras páginas públicas.
 *
 * Elementos que se traducen automáticamente, según el atributo que tengan:
 *   data-i18n="clave"             -> texto visible (textContent)
 *   data-i18n-placeholder="clave" -> placeholder de un input/textarea
 *   data-i18n-alt="clave"         -> atributo alt de una imagen
 */
function inicializarIdioma(traducciones, alCambiarIdioma) {
  const botonesIdioma = document.querySelectorAll('.btn-idioma');

  function aplicarTextosEstaticos(idioma) {
    const diccionario = traducciones[idioma] || {};

    document.querySelectorAll('[data-i18n]').forEach((elemento) => {
      const clave = elemento.getAttribute('data-i18n');
      if (diccionario[clave]) {
        elemento.textContent = diccionario[clave];
      }
    });
    document.querySelectorAll('[data-i18n-placeholder]').forEach((elemento) => {
      const clave = elemento.getAttribute('data-i18n-placeholder');
      if (diccionario[clave]) {
        elemento.placeholder = diccionario[clave];
      }
    });
    document.querySelectorAll('[data-i18n-alt]').forEach((elemento) => {
      const clave = elemento.getAttribute('data-i18n-alt');
      if (diccionario[clave]) {
        elemento.alt = diccionario[clave];
      }
    });
  }

  function establecerIdioma(idioma) {
    localStorage.setItem('sigeru_idioma', idioma);
    document.documentElement.lang = idioma;
    aplicarTextosEstaticos(idioma);
    botonesIdioma.forEach((boton) => {
      boton.classList.toggle('activo', boton.dataset.idioma === idioma);
    });
    if (typeof alCambiarIdioma === 'function') {
      alCambiarIdioma(idioma);
    }
  }

  botonesIdioma.forEach((boton) => {
    boton.addEventListener('click', () => establecerIdioma(boton.dataset.idioma));
  });

  establecerIdioma(localStorage.getItem('sigeru_idioma') || 'es');
}