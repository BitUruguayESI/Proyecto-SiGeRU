<?php
/**
 * tests/support/ApiClient.php
 * Cliente HTTP mínimo (sobre curl) para los tests de la API. Cada
 * instancia tiene su propio "cookie jar" en un archivo temporal, así
 * que cada test puede loguearse con un usuario distinto sin pisar la
 * sesión de otro test.
 *
 * Los tests son de integración (pegan por HTTP contra un servidor PHP
 * real corriendo sobre una base de datos de test) en vez de unitarios
 * puros, porque los endpoints de la API son scripts que llaman a
 * exit()/header() directamente — no hay forma de instanciarlos ni de
 * llamarlos como funciones aisladas. Es el enfoque estándar para
 * probar una API HTTP "mediante código".
 *
 * Autologueo para registro.php: desde que esa ruta exige sesión de
 * Administrador (RN-22), si un test llama a registro.php con un
 * cliente que todavía no inició sesión, este cliente se loguea
 * primero, en silencio, con el Administrador "semilla" que crea
 * bootstrap.php. Así ningún archivo de test necesita saber de la
 * semilla ni loguearse a mano solo para poder registrar usuarios de
 * prueba; si el test después hace su propio login.php en este mismo
 * cliente, esa sesión de Administrador semilla se reemplaza por la
 * del usuario real que el test está probando, como siempre.
 */

declare(strict_types=1);

final class ApiClient
{
    private string $baseUrl;
    private string $cookieJar;
    private bool $autenticado = false;

    public function __construct(?string $baseUrl = null)
    {
        $this->baseUrl = rtrim($baseUrl ?? getenv('SIGERU_TEST_BASE_URL') ?: 'http://127.0.0.1:8988', '/');
        $this->cookieJar = tempnam(sys_get_temp_dir(), 'sigeru_cookies_');
    }

    public function __destruct()
    {
        if (is_file($this->cookieJar)) {
            unlink($this->cookieJar);
        }
    }

    /**
     * @return array{codigo:int, cuerpo:array}
     */
    public function get(string $ruta): array
    {
        return $this->llamar('GET', $ruta, null);
    }

    public function post(string $ruta, array $cuerpo = []): array
    {
        return $this->llamar('POST', $ruta, $cuerpo);
    }

    public function put(string $ruta, array $cuerpo = []): array
    {
        return $this->llamar('PUT', $ruta, $cuerpo);
    }

    public function delete(string $ruta, array $cuerpo = []): array
    {
        return $this->llamar('DELETE', $ruta, $cuerpo);
    }

    private function llamar(string $metodo, string $ruta, ?array $cuerpo): array
    {
        if ($ruta === '/api-usuarios/registro.php' && !$this->autenticado) {
            // El propio llamar() recursivo marca $this->autenticado = true
            // más abajo si este login sale bien (código 200); si falla, se
            // sigue intentando en la próxima llamada a registro.php.
            $this->llamar('POST', '/api-usuarios/login.php', [
                'ci'         => SIGERU_SEMILLA_CI,
                'contrasena' => SIGERU_SEMILLA_CONTRASENA,
            ]);
        }

        $ch = curl_init($this->baseUrl . $ruta);
        $opciones = [
            CURLOPT_CUSTOMREQUEST  => $metodo,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_COOKIEJAR      => $this->cookieJar,
            CURLOPT_COOKIEFILE     => $this->cookieJar,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            // 30s: con cientos de corridas de tests acumuladas (cada una
            // crea su propio Administrador de prueba sin borrarlo),
            // generarNotificacionesPendientes() recorre cada admin × cada
            // alerta y se vuelve lenta, no incorrecta — 10s no alcanza.
            CURLOPT_TIMEOUT        => 30,
        ];
        if ($cuerpo !== null) {
            $opciones[CURLOPT_POSTFIELDS] = json_encode($cuerpo, JSON_UNESCAPED_UNICODE);
        }
        curl_setopt_array($ch, $opciones);

        $respuesta = curl_exec($ch);
        if ($respuesta === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException("Error de curl llamando a {$ruta}: {$error}");
        }
        $codigo = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($ruta === '/api-usuarios/login.php' && $codigo === 200) {
            $this->autenticado = true;
        }

        $decodificado = json_decode($respuesta, true);

        return ['codigo' => $codigo, 'cuerpo' => is_array($decodificado) ? $decodificado : []];
    }
}
