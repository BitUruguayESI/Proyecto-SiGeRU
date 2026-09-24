document.getElementById('formRegistro').addEventListener('submit', async function(e) {
    e.preventDefault();

    const datosUsuario = {
        nombre: document.getElementById('nombre').value.trim(),
        CI: document.getElementById('CI').value.trim(),
        email: document.getElementById('email').value.trim(),
        telefono: document.getElementById('telefono').value.trim(),
        password: document.getElementById('password').value.trim()
    };

    try {
        const respuesta = await fetch('../backend/api-usuarios/registro.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(datosUsuario)
        });

        const resultado = await respuesta.json();

        if (resultado.status === 'success') {
            alert(resultado.message);
            window.location.href = 'login.html'; // Redirige al login tras un registro exitoso
        } else {
            alert(resultado.message);
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Ocurrió un error al intentar registrar el usuario.');
    }
});