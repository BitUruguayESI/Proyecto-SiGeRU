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
 */

declare(strict_types=1);

final class ApiClient
{
    private string $baseUrl;
    private string $cookieJar;

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
        $ch = curl_init($this->baseUrl . $ruta);
        $opciones = [
            CURLOPT_CUSTOMREQUEST  => $metodo,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_COOKIEJAR      => $this->cookieJar,
            CURLOPT_COOKIEFILE     => $this->cookieJar,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 10,
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

        $decodificado = json_decode($respuesta, true);

        return ['codigo' => $codigo, 'cuerpo' => is_array($decodificado) ? $decodificado : []];
    }
}
