// ===== LOGIN.JS - Lógica de Login =====

document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.getElementById('loginForm');
    
    if (loginForm) {
        loginForm.addEventListener('submit', async function(e) {
            e.preventDefault();

            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            if (!email || !password) {
                alert('Por favor completa todos los campos.');
                return;
            }

            try {
                // Llamar a la API de login
                const response = await fetch('../../backend/api/usuarios.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        accion: 'login',
                        email: email,
                        password: password
                    })
                });

                const resultado = await response.json();

                if (resultado.exito) {
                    const usuario = resultado.datos;

                    // Guardar sesión
                    sessionStorage.setItem('usuario', JSON.stringify(usuario));

                    alert('¡Bienvenido ' + usuario.nombre + '!');
                    window.location.href = 'dashboard.html';
                } else {
                    alert('Error en el login: ' + resultado.mensaje);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('No se pudo conectar con la API. Verifica que Apache y MySQL estén iniciados en XAMPP.');
            }
        });
    }
});
