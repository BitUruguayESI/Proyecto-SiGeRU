// ===== REGISTRO.JS - Lógica de Registro =====

document.addEventListener('DOMContentLoaded', function() {
    const registroForm = document.getElementById('registroForm');
    
    if (registroForm) {
        registroForm.addEventListener('submit', async function(e) {
            e.preventDefault();

            // Obtener datos del formulario
            const formData = new FormData(registroForm);
            const nombre = formData.get('nombre');
            const email = formData.get('email');
            const password = formData.get('password');
            const passwordConfirm = formData.get('password-confirm');
            const empresa = formData.get('empresa');
            const telefono = formData.get('telefono');

            // Validaciones
            if (!nombre || !email || !password || !passwordConfirm) {
                alert('Por favor completa todos los campos obligatorios.');
                return;
            }

            if (password !== passwordConfirm) {
                alert('Las contraseñas no coinciden.');
                return;
            }

            if (password.length < 6) {
                alert('La contraseña debe tener al menos 6 caracteres.');
                return;
            }

            // Datos del usuario
            const datosUsuario = {
                nombre: nombre,
                email: email,
                password: password,
                empresa: empresa,
                telefono: telefono,
                fechaRegistro: new Date().toISOString()
            };

            try {
                // Llamar a la API de registro
                const response = await fetch('../../backend/api/usuarios.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        accion: 'registrar',
                        datos: datosUsuario
                    })
                });

                const resultado = await response.json();

                if (resultado.exito) {
                    alert('¡Registro exitoso! Ahora puedes iniciar sesión.');
                    window.location.href = 'login.html';
                } else {
                    alert('Error en el registro: ' + resultado.mensaje);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('No se pudo conectar con la API. Verifica que Apache y MySQL estén iniciados en XAMPP.');
            }
        });
    }
});
