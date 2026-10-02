<?php
/**
 * api-gestion/predicciones.php
 * Nivel avanzado 3.4 — proyección de cuándo se va a llenar cada
 * contenedor (a partir del historial en LECTURA_NIVEL_CONTENEDOR) y una
 * estimación de generación de residuos por zona (a partir de DESCARGA,
 * cruzada con la asignación diaria del camión ese día para inferir de
 * qué ruta/zona vino esa carga). Es una heurística simple (tasa
 * promedio de los últimos días desde el último vaciado), no un modelo
 * predictivo real — pensada para un proyecto académico.
 *
 * GET — cualquier sesión activa
 */

declare(strict_types=1);

require_once __DIR__ . '/../Comun/conexion.php';
require_once __DIR__ . '/../Comun/funciones.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responderJson(['exito' => false, 'mensaje' => 'Método no permitido.'], 405);
}

requerirSesionActiva();

$pdo = obtenerConexion();

/**
 * A partir de las lecturas de un contenedor (ordenadas por fecha, más
 * vieja primero), calcula una tasa de llenado diaria usando solo el
 * tramo desde el último "vaciado" (una lectura menor que la anterior,
 * lo que indica que se vació). Devuelve null si no hay datos
 * suficientes (menos de 2 lecturas en el tramo, menos de medio día
 * entre la primera y la última, o la tendencia no es creciente).
 */
function calcularTasaDiaria(array $lecturas): ?float
{
    if (count($lecturas) < 2) {
        return null;
    }
    $inicio = 0;
    for ($i = 1; $i < count($lecturas); $i++) {
        if ((int) $lecturas[$i]['nivel_llenado'] < (int) $lecturas[$i - 1]['nivel_llenado']) {
            $inicio = $i;
        }
    }
    $tramo = array_slice($lecturas, $inicio);
    if (count($tramo) < 2) {
        return null;
    }

    $primera = $tramo[0];
    $ultima = $tramo[count($tramo) - 1];
    $dias = (strtotime($ultima['fecha_hora']) - strtotime($primera['fecha_hora'])) / 86400;
    if ($dias < 0.5) {
        return null;
    }

    $tasa = ((int) $ultima['nivel_llenado'] - (int) $primera['nivel_llenado']) / $dias;
    return $tasa > 0 ? $tasa : null;
}

// ---- 1) Predicción de llenado por contenedor, y promedio por zona ----
$contenedores = $pdo->query(
    'SELECT c.id_contenedor, c.direccion, c.estado, c.nivel_llenado, r.nombre_zona
     FROM CONTENEDOR c LEFT JOIN RUTA r ON r.id_ruta = c.id_ruta
     ORDER BY c.id_contenedor'
)->fetchAll();

$lecturasStmt = $pdo->prepare(
    'SELECT nivel_llenado, fecha_hora FROM LECTURA_NIVEL_CONTENEDOR
     WHERE id_contenedor = :id ORDER BY fecha_hora ASC'
);

$resultadoContenedores = [];
$diasPorZona = [];

foreach ($contenedores as $c) {
    $lecturasStmt->execute(['id' => $c['id_contenedor']]);
    $lecturas = $lecturasStmt->fetchAll();

    $tasa = calcularTasaDiaria($lecturas);
    $diasEstimados = $tasa !== null ? max(0, (int) ceil((100 - (int) $c['nivel_llenado']) / $tasa)) : null;

    $resultadoContenedores[] = [
        'id_contenedor' => (int) $c['id_contenedor'],
        'direccion' => $c['direccion'],
        'estado' => $c['estado'],
        'nivel_llenado' => (int) $c['nivel_llenado'],
        'nombre_zona' => $c['nombre_zona'],
        'tasa_diaria_pct' => $tasa !== null ? round($tasa, 1) : null,
        'dias_estimados_para_lleno' => $diasEstimados,
    ];

    if ($diasEstimados !== null && $c['nombre_zona']) {
        $diasPorZona[$c['nombre_zona']][] = $diasEstimados;
    }
}

// ---- 2) Generación de residuos por zona (últimos 30 días) ----
// Se infiere la zona de cada descarga cruzando el camión y la fecha con
// ASIGNACION_DIARIA (qué ruta tenía asignada ese camión ese día).
$descargasPorZona = $pdo->query(
    "SELECT COALESCE(r.nombre_zona, 'Sin zona identificada') AS nombre_zona,
            SUM(d.peso) AS peso_total
     FROM DESCARGA d
     LEFT JOIN ASIGNACION_DIARIA a ON a.id_camion = d.id_camion AND a.fecha = d.fecha_descarga
     LEFT JOIN RUTA r ON r.id_ruta = a.id_ruta
     WHERE d.fecha_descarga >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
     GROUP BY nombre_zona
     ORDER BY peso_total DESC"
)->fetchAll();

$pesoPorZona = [];
foreach ($descargasPorZona as $fila) {
    $pesoPorZona[$fila['nombre_zona']] = (float) $fila['peso_total'];
}

$nombresZona = array_unique(array_merge(array_keys($diasPorZona), array_keys($pesoPorZona)));

$zonas = [];
foreach ($nombresZona as $zona) {
    $dias = $diasPorZona[$zona] ?? [];
    $pesoTotal30d = $pesoPorZona[$zona] ?? 0.0;
    $promedioDiario = round($pesoTotal30d / 30, 1);

    $zonas[] = [
        'nombre_zona' => $zona,
        'promedio_dias_para_lleno' => $dias ? round(array_sum($dias) / count($dias), 1) : null,
        'generacion_promedio_diaria_kg' => $promedioDiario,
        'proyeccion_proximos_30_dias_kg' => round($promedioDiario * 30, 1),
    ];
}
usort($zonas, fn($a, $b) => $b['proyeccion_proximos_30_dias_kg'] <=> $a['proyeccion_proximos_30_dias_kg']);

responderJson([
    'exito' => true,
    'contenedores' => $resultadoContenedores,
    'zonas' => $zonas,
]);
