<?php
/**
 * Comun/correo.php
 * Envío de emails reales por SMTP, hablando el protocolo directo por
 * sockets — sin PHPMailer ni ninguna librería externa, para no depender
 * de tener Composer instalado (el proyecto no lo usa hasta ahora: los
 * tests corren con el runner incluido, no con vendor/bin/phpunit).
 * Sirve para cualquier proveedor con SMTP + STARTTLS + AUTH LOGIN, que
 * es el caso más común (Gmail, Outlook, Mailtrap, etc.).
 *
 * Configuración por variables de entorno (mismo patrón que la conexión
 * a la base — ver Comun/conexion.php):
 *
 *   SIGERU_SMTP_HOST   ej. smtp.gmail.com
 *   SIGERU_SMTP_PORT   ej. 587
 *   SIGERU_SMTP_USER   la cuenta que envía
 *   SIGERU_SMTP_PASS   su contraseña de aplicación (Gmail exige una
 *                      "contraseña de aplicación" de 16 caracteres, no
 *                      la contraseña normal de la cuenta — ver README)
 *   SIGERU_SMTP_FROM   remitente que ve el destinatario (por defecto,
 *                      el mismo SIGERU_SMTP_USER)
 *
 * Si no están configuradas, enviarCorreo() devuelve un error claro en
 * vez de fallar silenciosamente o tirar un error 500 sin explicación.
 */

declare(strict_types=1);

/** Lee la respuesta del servidor (puede venir en varias líneas "250-..." terminando en "250 ..."). */
function smtpLeerRespuesta($socket): string
{
    $respuesta = '';
    while (($linea = fgets($socket, 515)) !== false) {
        $respuesta .= $linea;
        if (!isset($linea[3]) || $linea[3] !== '-') {
            break;
        }
    }
    return $respuesta;
}

/** Manda un comando y valida que la respuesta empiece con el código esperado. */
function smtpComando($socket, string $comando, string $codigoEsperado): string
{
    fwrite($socket, $comando . "\r\n");
    $respuesta = smtpLeerRespuesta($socket);
    if (substr($respuesta, 0, 3) !== $codigoEsperado) {
        throw new RuntimeException('El servidor SMTP respondió: ' . trim($respuesta));
    }
    return $respuesta;
}

/**
 * Envía un email de texto plano. Devuelve ['exito' => bool, 'mensaje' => string].
 */
function enviarCorreo(string $destinatario, string $asunto, string $cuerpo): array
{
    $host = getenv('SIGERU_SMTP_HOST') ?: '';
    $puerto = (int) (getenv('SIGERU_SMTP_PORT') ?: 587);
    $usuario = getenv('SIGERU_SMTP_USER') ?: '';
    $clave = getenv('SIGERU_SMTP_PASS') ?: '';
    $remitente = getenv('SIGERU_SMTP_FROM') ?: $usuario;

    if ($host === '' || $usuario === '' || $clave === '') {
        return [
            'exito' => false,
            'mensaje' => 'El envío de emails no está configurado en este servidor '
                . '(faltan SIGERU_SMTP_HOST/SIGERU_SMTP_USER/SIGERU_SMTP_PASS). Ver README.',
        ];
    }

    $socket = null;
    try {
        $socket = @stream_socket_client("tcp://{$host}:{$puerto}", $errno, $errstr, 10);
        if (!$socket) {
            throw new RuntimeException("No se pudo conectar a {$host}:{$puerto} ({$errstr})");
        }

        smtpLeerRespuesta($socket); // saludo inicial del servidor (220)
        smtpComando($socket, 'EHLO sigeru.local', '250');
        smtpComando($socket, 'STARTTLS', '220');

        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new RuntimeException('No se pudo iniciar TLS con el servidor SMTP.');
        }

        // Después de STARTTLS hay que repetir el EHLO (protocolo SMTP).
        smtpComando($socket, 'EHLO sigeru.local', '250');
        smtpComando($socket, 'AUTH LOGIN', '334');
        smtpComando($socket, base64_encode($usuario), '334');
        smtpComando($socket, base64_encode($clave), '235');

        smtpComando($socket, "MAIL FROM:<{$remitente}>", '250');
        smtpComando($socket, "RCPT TO:<{$destinatario}>", '250');
        smtpComando($socket, 'DATA', '354');

        // "Dot-stuffing": una línea que empieza con "." sola marcaría el
        // fin del mensaje si no se escapa (protocolo SMTP, RFC 5321).
        $cuerpoEscapado = str_replace("\n.", "\n..", $cuerpo);
        $asuntoCodificado = '=?UTF-8?B?' . base64_encode($asunto) . '?=';
        $mensaje = "From: SiGeRU <{$remitente}>\r\n"
            . "To: <{$destinatario}>\r\n"
            . "Subject: {$asuntoCodificado}\r\n"
            . "MIME-Version: 1.0\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n"
            . "\r\n"
            . $cuerpoEscapado . "\r\n.";
        smtpComando($socket, $mensaje, '250');

        fwrite($socket, "QUIT\r\n");
        fclose($socket);

        return ['exito' => true, 'mensaje' => 'Email enviado.'];
    } catch (Throwable $e) {
        if (is_resource($socket)) {
            fclose($socket);
        }
        return ['exito' => false, 'mensaje' => 'No se pudo enviar el email: ' . $e->getMessage()];
    }
}
