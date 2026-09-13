/**
 * sesion.js
 * Utilidad compartida para obtener los datos de la sesión activa (ci,
 * nombre_completo, tipo_usuario, cargo) sin duplicar el fetch a
 * quien_soy.php en cada página. La usan nav_admin.js (para armar el menú
 * según el rol) y las páginas que necesitan saber quién está logueado
 * antes de decidir qué mostrar (dashboard, panel de gestión, mi_dia, etc.)
 *
 * Tiene que cargarse ANTES que nav_admin.js y que el script propio de
 * cada página: <script src="js/sesion.js"></script>
 */

async function obtenerSesion() {
  try {
    const respuesta = await fetch('../api-usuarios/quien_soy.php');
    if (respuesta.status === 401) {
      window.location.href = 'login.html';
      return null;
    }
    const datos = await respuesta.json();
    return datos.exito ? datos : null;
  } catch (error) {
    return null;
  }
}

/** 'ADMINISTRADOR' | 'CHOFER' | 'RECOLECTOR' a partir de los datos de obtenerSesion(). */
function rolDeSesion(sesion) {
  if (!sesion) return null;
  return sesion.tipo_usuario === 'ADMINISTRADOR' ? 'ADMINISTRADOR' : sesion.cargo;
}

/**
 * Para páginas exclusivas de Administrador (Dashboard, Panel de gestión):
 * si la sesión no es de Administrador, redirige a "Mi día" y devuelve
 * false. Las páginas deben cortar su propia inicialización si esto
 * devuelve false.
 */
function exigirAdministrador(sesion) {
  if (!sesion || sesion.tipo_usuario !== 'ADMINISTRADOR') {
    window.location.href = 'mi_dia.html';
    return false;
  }
  return true;
}
