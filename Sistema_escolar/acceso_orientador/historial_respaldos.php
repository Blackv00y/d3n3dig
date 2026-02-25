<?php
// historial_respaldos.php — HISTORIAL DE RESPALDOS · SOLO NOMBRE DEL ALUMNO
// Arquitectura: respaldos/boletas/{ID_ESCUELA}/{GENERACIÓN}/{TURNO}/grupos/{GRADO GRUPO}/

session_start();
if (!isset($_SESSION['id_credencial'])) {
    header("Location: ../login.php");
    exit();
}

include '../funciones/conexQRConejo.php';

// ============================================================
// CONFIGURACIÓN INICIAL
// ============================================================
$id_usuario = $_SESSION['id_credencial'];

// Obtener id_escuela
$stmt = mysqli_prepare($conexion, "SELECT id_escuela FROM credenciales WHERE id_credencial = ?");
mysqli_stmt_bind_param($stmt, "i", $id_usuario);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$id_escuela = mysqli_fetch_assoc($result)['id_escuela'] ?? 0;

// Obtener nombre de la escuela
$stmt = mysqli_prepare($conexion, "SELECT nombre_escuela FROM escuelas WHERE id_escuela = ?");
mysqli_stmt_bind_param($stmt, "i", $id_escuela);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$nombre_escuela = mysqli_fetch_assoc($result)['nombre_escuela'] ?? 'Escuela no identificada';

// ============================================================
// PARÁMETROS Y FILTROS
// ============================================================
$grado = $_GET['grado'] ?? '';
$grupo = $_GET['grupo'] ?? '';
$turno = $_GET['turno'] ?? '';
$filtroGeneracion = $_GET['generacion'] ?? '';
$filtroAnio = $_GET['anio'] ?? '';
$filtroTurno = $_GET['turno'] ?? '';      // ← NUEVO: Filtro de turno
$filtroGrado = $_GET['grado'] ?? '';      // ← NUEVO: Filtro de grado
$filtroGrupo = $_GET['grupo'] ?? '';      // ← NUEVO: Filtro de grupo

// ============================================================
// FUNCIONES AUXILIARES
// ============================================================

function convertirGrupoARomano($g) {
    $m = [
        'A'=>'I','B'=>'II','C'=>'III','D'=>'IV','E'=>'V','F'=>'VI',
        'G'=>'VII','H'=>'VIII','I'=>'IX','J'=>'X','K'=>'XI','L'=>'XII',
        'M'=>'XIII','N'=>'XIV','O'=>'XV','P'=>'XVI','Q'=>'XVII',
        'R'=>'XVIII','S'=>'XIX','T'=>'XX'
    ];
    $g = strtoupper(trim($g));
    return $m[$g] ?? $g;
}

function normalizarGrado($grado) {
    $m = [
        '1'=>'Primero','2'=>'Segundo','3'=>'Tercero','4'=>'Cuarto','5'=>'Quinto','6'=>'Sexto',
        '1°'=>'Primero','2°'=>'Segundo','3°'=>'Tercero','4°'=>'Cuarto','5°'=>'Quinto','6°'=>'Sexto',
        'primero'=>'Primero','segundo'=>'Segundo','tercero'=>'Tercero',
        'cuarto'=>'Cuarto','quinto'=>'Quinto','sexto'=>'Sexto',
        'PRIMERO'=>'Primero','SEGUNDO'=>'Segundo','TERCERO'=>'Tercero',
        'CUARTO'=>'Cuarto','QUINTO'=>'Quinto','SEXTO'=>'Sexto',
    ];
    $grado = trim($grado);
    return $m[$grado] ?? ucfirst(strtolower($grado));
}

function pesoGrado($nombre) {
    $pesos = ['Primero'=>1,'Segundo'=>2,'Tercero'=>3,'Cuarto'=>4,'Quinto'=>5,'Sexto'=>6];
    return $pesos[$nombre] ?? 99;
}

// ============================================================
// FUNCIÓN: OBTENER SOLO NOMBRE DEL ALUMNO (SIN DESENCRIPTAR)
// ============================================================
$cacheNombres = []; // Cache global

function obtenerNombreAlumno($id_alumno, $id_escuela, $conexion, &$cache) {
    // Verificar cache primero
    $cacheKey = "{$id_escuela}_{$id_alumno}";
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }
    
    // Consultar BD - SOLO nombre_credencial (ya está en texto plano)
    $stmt = mysqli_prepare($conexion, 
        "SELECT nombre_credencial FROM credenciales 
         WHERE id_credencial = ? AND id_escuela = ?");
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ii", $id_alumno, $id_escuela);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($row = mysqli_fetch_assoc($result)) {
            $nombre = trim($row['nombre_credencial']);
            $cache[$cacheKey] = $nombre;
            mysqli_stmt_close($stmt);
            return $nombre;
        }
        mysqli_stmt_close($stmt);
    }
    
    // Fallback
    $cache[$cacheKey] = "Alumno #$id_alumno";
    return $cache[$cacheKey];
}

// ══════════════════════════════════════════════════════════════════════
// FUNCIONES NUEVAS: GENERACIONES DE 3 AÑOS (de VERSION_B)
// Modelo de preparatoria en México: ciclos de 3 años
// ══════════════════════════════════════════════════════════════════════

/**
 * Calcula el rango de generación de 3 años al que pertenece un año
 * Ejemplo: 2024 → "2024 - 2027", 2025 → "2024 - 2027", 2026 → "2024 - 2027"
 */
function calcularGeneracion($anio) {
    $anio = (int)$anio;
    $base = 2024;
    $diferencia = $anio - $base;
    $ciclo = floor($diferencia / 3);
    $anioInicio = $base + ($ciclo * 3);
    $anioFin = $anioInicio + 3;
    
    return "$anioInicio - $anioFin";
}

/**
 * Extrae el año de inicio de un string de generación
 * Ejemplo: "2024 - 2027" → 2024
 */
function extraerAnioInicioGeneracion($generacion) {
    if (preg_match('/^(\d{4})\s*-\s*\d{4}$/', $generacion, $matches)) {
        return (int)$matches[1];
    }
    return 0;
}

/**
 * Extrae el año de fin de un string de generación
 * Ejemplo: "2024 - 2027" → 2027
 */
function extraerAnioFinGeneracion($generacion) {
    if (preg_match('/^\d{4}\s*-\s*(\d{4})$/', $generacion, $matches)) {
        return (int)$matches[1];
    }
    return 0;
}

/**
 * Verifica si un año pertenece a una generación
 */
function anioPerteneceAGeneracion($anio, $generacion) {
    $inicio = extraerAnioInicioGeneracion($generacion);
    $fin = extraerAnioFinGeneracion($generacion);
    $anio = (int)$anio;
    
    return $anio >= $inicio && $anio < $fin;
}

// ============================================================
// ESCANEAR CARPETAS CON NUEVA ESTRUCTURA
// ============================================================
$rutaBase = __DIR__ . '/respaldos/boletas/' . $id_escuela . '/generación/';
$carpetas = [];
$generacionesEncontradas = [];
$aniosEncontrados = [];

// ═══════════════════════════════════════════════════════════════════
// NUEVAS ESTRUCTURAS PARA FILTROS DINÁMICOS (de VERSION_B)
// ═══════════════════════════════════════════════════════════════════
$filtrosData = [
    'generaciones' => [],  // Rangos de 3 años (ej: "2024 - 2027")
    'anios' => [],         // Años individuales encontrados
    'turnos' => [],
    'grados' => [],
    'grupos' => [],
];

$mapeoGeneracionAnios = []; // Mapeo de generación → años disponibles

// DEBUG: Mostrar ruta de exploración
echo '<div style="background:#fff3cd; border:2px solid #ffc107; border-radius:8px; padding:12px; margin:15px 0; font-size:0.9rem;">';
echo '🔍 <strong>Buscando en:</strong> ' . realpath($rutaBase ?: __DIR__) . '<br>';
echo '📁 <strong>Ruta existe:</strong> ' . (is_dir($rutaBase) ? '✅ SÍ' : '❌ NO');
echo '</div>';

if (is_dir($rutaBase)) {
    // NIVEL 1: Explorar AÑOS dentro de /generación/
    $aniosDetectados = glob($rutaBase . '*', GLOB_ONLYDIR);
    
    foreach ($aniosDetectados as $rutaAnio) {
        $nombreAnio = basename($rutaAnio);
        $generacion = $nombreAnio; // Usar año como generación
        
        // ═══ CORRECCIÓN: Verificar si el año pertenece a la generación seleccionada ═══
        // Si hay filtro de generación, verificar que el año esté dentro del rango
        if (!empty($filtroGeneracion)) {
            // El filtro viene como año inicial (ej: "2024" de "2024 - 2027")
            // Calcular el rango completo y verificar si nombreAnio está dentro
            $generacionRangoDelAnio = calcularGeneracion($nombreAnio);
            $generacionRangoFiltrada = calcularGeneracion($filtroGeneracion);
            
            // Comparar rangos completos
            if ($generacionRangoDelAnio !== $generacionRangoFiltrada) {
                continue; // Saltar si no pertenece a la generación seleccionada
            }
        }
        // ═══ FIN CORRECCIÓN ═══
        
        // NIVEL 2: Explorar TURNOS
        $turnos = glob($rutaAnio . '/*', GLOB_ONLYDIR);
        
        foreach ($turnos as $rutaTurnoCompleta) {
            $turno = basename($rutaTurnoCompleta);
            
            // ═══ FILTRO DE TURNO ═══
            if (!empty($filtroTurno) && $turno !== $filtroTurno) continue;
            // ═══ FIN FILTRO TURNO ═══
            
            // NIVEL 3: Buscar "Grupos" o "grupos"
            $rutaGrupos = $rutaTurnoCompleta . '/Grupos/';
            if (!is_dir($rutaGrupos)) {
                $rutaGrupos = $rutaTurnoCompleta . '/grupos/';
            }
            if (!is_dir($rutaGrupos)) continue;
            
            $gruposEnTurno = array_diff(scandir($rutaGrupos), ['.', '..']);
            
            foreach ($gruposEnTurno as $nombreCarpetaGrupo) {
                $rutaCarpeta = $rutaGrupos . $nombreCarpetaGrupo . '/';
                if (!is_dir($rutaCarpeta)) continue;
                
                $archivosEnCarpeta = [];
                $aniosEnCarpeta = [];
                $archivos = array_diff(scandir($rutaCarpeta), ['.', '..']);
                
                foreach ($archivos as $file) {
                    if (pathinfo($file, PATHINFO_EXTENSION) !== 'pdf') continue;
                    
                    $rutaArchivo = $rutaCarpeta . $file;
                    $idAlumno = '';
                    $anio = '';
                    
                    // Extraer ID y año: Boleta_Tipo_ID_YYYY-MM-DD_HH-MM-SS.pdf
                    if (preg_match('/Boleta_(?:Final|Parcial|Manual)_(\d+)_(\d{4})-\d{2}-\d{2}_/', $file, $matches)) {
                        $idAlumno = $matches[1];
                        $anio = $matches[2];
                        if (!in_array($anio, $aniosEnCarpeta)) $aniosEnCarpeta[] = $anio;
                        if (!in_array($anio, $aniosEncontrados)) $aniosEncontrados[] = $anio;
                    }
                    
                    if (!empty($filtroAnio) && $anio !== $filtroAnio) continue;
                    
                    // ── OBTENER SOLO NOMBRE DEL ALUMNO (SIN APELLIDOS) ──
                    $nombreAlumno = !empty($idAlumno) && is_numeric($idAlumno)
                        ? obtenerNombreAlumno($idAlumno, $id_escuela, $conexion, $cacheNombres)
                        : 'Desconocido';
                    
                    $archivosEnCarpeta[] = [
                        'nombre_archivo' => $file,
                        'nombre_alumno'  => $nombreAlumno,  // ← Solo nombre, sin apellidos
                        'id_alumno'      => $idAlumno,
                        'tamano'         => filesize($rutaArchivo),
                        'fecha'          => filemtime($rutaArchivo),
                        'tipo'           => str_contains($file, 'Boleta_Final_') ? 'Final' 
                                       : (str_contains($file, 'Boleta_Parcial_') ? 'Parcial' : 'Otro'),
                        'anio'           => $anio,
                        'generacion'     => $generacion,
                        'turno'          => $turno,
                        'carpeta'        => $nombreCarpetaGrupo,
                    ];
                }
                
                if (empty($archivosEnCarpeta)) continue;
                
                usort($archivosEnCarpeta, fn($a,$b) => $b['fecha'] - $a['fecha']);
                rsort($aniosEnCarpeta);
                
                $partes = explode(' ', $nombreCarpetaGrupo, 2);
                $gradoCarpeta = normalizarGrado($partes[0] ?? $nombreCarpetaGrupo);
                $grupoCarpeta = $partes[1] ?? '';
                
                // ═══ FILTROS DE GRADO Y GRUPO ═══
                if (!empty($filtroGrado) && $gradoCarpeta !== normalizarGrado($filtroGrado)) continue;
                if (!empty($filtroGrupo) && $grupoCarpeta !== $filtroGrupo) continue;
                // ═══ FIN FILTROS GRADO Y GRUPO ═══
                
                $claveUnica = $generacion . '|' . $turno . '|' . $nombreCarpetaGrupo;
                
                if (!in_array($generacion, $generacionesEncontradas)) {
                    $generacionesEncontradas[] = $generacion;
                }
                
                // ═══ NUEVAS: Poblar datos de filtros (de VERSION_B) ═══
                // Calcular generación de 3 años
                $generacionRango = calcularGeneracion($nombreAnio);
                
                // Agregar a filtrosData
                if (!in_array($generacionRango, $filtrosData['generaciones'])) {
                    $filtrosData['generaciones'][] = $generacionRango;
                }
                if (!in_array($nombreAnio, $filtrosData['anios'])) {
                    $filtrosData['anios'][] = $nombreAnio;
                }
                if (!in_array($turno, $filtrosData['turnos'])) {
                    $filtrosData['turnos'][] = $turno;
                }
                if (!in_array($gradoCarpeta, $filtrosData['grados'])) {
                    $filtrosData['grados'][] = $gradoCarpeta;
                }
                if (!in_array($grupoCarpeta, $filtrosData['grupos'])) {
                    $filtrosData['grupos'][] = $grupoCarpeta;
                }
                
                // Mapear generación → años
                if (!isset($mapeoGeneracionAnios[$generacionRango])) {
                    $mapeoGeneracionAnios[$generacionRango] = [];
                }
                if (!in_array($nombreAnio, $mapeoGeneracionAnios[$generacionRango])) {
                    $mapeoGeneracionAnios[$generacionRango][] = $nombreAnio;
                }
                // ═══ FIN NUEVAS ═══
                
                $carpetas[$claveUnica] = [
                    'label'        => $nombreCarpetaGrupo . ' (' . $turno . ' · ' . $generacion . ')',
                    'grado_texto'  => $gradoCarpeta,
                    'grupo_texto'  => $grupoCarpeta,
                    'grado_peso'   => pesoGrado($gradoCarpeta),
                    'generacion'   => $generacion,
                    'turno'        => $turno,
                    'archivos'     => $archivosEnCarpeta,
                    'anios'        => $aniosEnCarpeta,
                    'total'        => count($archivosEnCarpeta),
                    'finales'      => count(array_filter($archivosEnCarpeta, fn($a) => $a['tipo'] === 'Final')),
                    'parciales'    => count(array_filter($archivosEnCarpeta, fn($a) => $a['tipo'] === 'Parcial')),
                ];
            }
        }
    }
}

uasort($carpetas, function($a, $b) {
    if ($a['grado_peso'] !== $b['grado_peso']) {
        return $a['grado_peso'] - $b['grado_peso'];
    }
    return strcmp($a['grupo_texto'], $b['grupo_texto']);
});

rsort($generacionesEncontradas);
rsort($aniosEncontrados);

// ═══ NUEVAS: Ordenar datos de filtros (de VERSION_B) ═══
sort($filtrosData['generaciones']);
sort($filtrosData['anios']);
sort($filtrosData['turnos']);
usort($filtrosData['grados'], fn($a, $b) => pesoGrado($a) - pesoGrado($b));
sort($filtrosData['grupos']);

foreach ($mapeoGeneracionAnios as &$anios) {
    sort($anios);
}
// ═══ FIN NUEVAS ═══

$totalCarpetas        = count($carpetas);
$totalArchivosGlobal  = array_sum(array_column($carpetas, 'total'));
$totalFinalesGlobal   = array_sum(array_column($carpetas, 'finales'));
$totalParcialesGlobal = array_sum(array_column($carpetas, 'parciales'));

// ═══════════════════════════════════════════════════════════════════
// DATOS PARA GRÁFICA: Histórico de respaldos por año y tipo
// ═══════════════════════════════════════════════════════════════════
$datosGrafica = [];
foreach ($carpetas as $carpeta) {
    $anio = $carpeta['generacion'];
    
    if (!isset($datosGrafica[$anio])) {
        $datosGrafica[$anio] = [
            'completas' => 0,    // Finales
            'incompletas' => 0   // Parciales + Manual
        ];
    }
    
    $datosGrafica[$anio]['completas'] += $carpeta['finales'];
    $datosGrafica[$anio]['incompletas'] += $carpeta['parciales'];
}

// Ordenar por año
ksort($datosGrafica);

// Convertir a arrays para Chart.js
$aniosGrafica = array_keys($datosGrafica);
$completasGrafica = array_column($datosGrafica, 'completas');
$incompletasGrafica = array_column($datosGrafica, 'incompletas');

// ── CERRAR CONEXIÓN ──
mysqli_close($conexion);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Respaldos</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    
    <style>
        body {
            font-family: 'League Spartan', sans-serif;
            background: #f0f4ff;
            padding: 24px 20px;
            min-height: 100vh;
        }
        .container { max-width: 1200px; }

        .page-title {
            text-align: center;
            color: #1a355e;
            font-size: 1.9rem;
            font-weight: 700;
            margin: 10px 0 24px;
        }
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: white;
            border-radius: 16px;
            padding: 18px 24px;
            margin-bottom: 24px;
            box-shadow: 0 2px 12px rgba(26,53,94,.08);
            flex-wrap: wrap;
            gap: 12px;
        }
        .school-info { line-height: 1.7; color: #444; }
        .school-info strong { color: #1a355e; font-size: 1.05rem; }

        .btn-back {
            background: linear-gradient(135deg, #6c757d, #495057);
            border: none;
            color: white;
            font-weight: 600;
            padding: 9px 22px;
            border-radius: 50px;
            text-decoration: none;
            font-size: .88rem;
            transition: all .25s;
            white-space: nowrap;
        }
        .btn-back:hover {
            background: linear-gradient(135deg,#5a6268,#343a40);
            color: white;
            transform: translateY(-1px);
            text-decoration: none;
        }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(170px,1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .stat-card {
            background: white;
            border-radius: 14px;
            padding: 18px 16px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,.07);
            border-top: 4px solid #2b91ff;
        }
        .stat-card.st-grupos  { border-top-color: #6f42c1; }
        .stat-card.st-final   { border-top-color: #28a745; }
        .stat-card.st-parcial { border-top-color: #ffc107; }
        .stat-card.st-grafica { 
            border-top-color: #ff6b6b; 
            cursor: pointer;
            transition: all .3s;
        }
        .stat-card.st-grafica:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(255,107,107,.3);
        }
        .stat-number { font-size: 2.2rem; font-weight: 700; color: #1a355e; line-height: 1; }
        .stat-label  { color: #6c757d; font-size: .82rem; margin-top: 6px; }
        
        /* Modal de gráfica */
        .modal-grafica .modal-content {
            border-radius: 16px;
            border: none;
        }
        .modal-grafica .modal-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-radius: 16px 16px 0 0;
        }
        .modal-grafica .modal-body {
            padding: 30px;
        }
        #chartContainer {
            position: relative;
            height: 400px;
        }

        .filters-wrap {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 22px;
            align-items: flex-end;
        }
        .filter-group { flex: 1; min-width: 180px; }
        .filter-group label {
            font-size: .8rem; font-weight: 600; color: #495057; margin-bottom: 5px; display: block;
        }
        .filter-select {
            width: 100%;
            border: 2px solid #dee2e6;
            border-radius: 10px;
            padding: 8px 14px;
            font-size: .9rem;
            background: white;
            cursor: pointer;
            transition: border-color .25s, box-shadow .25s;
        }
        .filter-select:focus {
            border-color: #2b91ff;
            box-shadow: 0 0 0 3px rgba(43,145,255,.15);
            outline: none;
        }
        .btn-clear-filters {
            background: #e9ecef; border: none; color: #495057;
            padding: 8px 16px; border-radius: 10px; font-size: .85rem;
            cursor: pointer; transition: all .2s; white-space: nowrap;
        }
        .btn-clear-filters:hover { background: #dee2e6; color: #333; }

        .search-wrap { position: relative; margin-bottom: 22px; }
        .search-wrap .form-control {
            border: 2px solid #dee2e6;
            border-radius: 50px;
            padding: 10px 44px 10px 20px;
            font-size: .9rem;
            font-family: 'League Spartan', sans-serif;
            transition: border-color .25s, box-shadow .25s;
        }
        .search-wrap .form-control:focus {
            border-color: #2b91ff;
            box-shadow: 0 0 0 3px rgba(43,145,255,.15);
            outline: none;
        }
        .search-wrap .search-ico {
            position: absolute; right: 18px; top: 50%; transform: translateY(-50%);
            color: #adb5bd; pointer-events: none;
        }

        .folders-list { display: flex; flex-direction: column; gap: 12px; }
        .folder-card {
            background: white; border-radius: 14px; box-shadow: 0 2px 10px rgba(0,0,0,.07);
            overflow: hidden; transition: box-shadow .25s;
        }
        .folder-card:hover { box-shadow: 0 4px 22px rgba(43,145,255,.15); }

        .folder-hdr {
            display: flex; align-items: center; gap: 14px; padding: 15px 20px;
            cursor: pointer; user-select: none; border: none; background: transparent;
            width: 100%; text-align: left; transition: background .2s;
        }
        .folder-hdr:hover { background: #f8f9ff; }
        .folder-hdr.is-open { background: #f0f4ff; border-bottom: 1px solid #dce8ff; }

        .folder-ico {
            width: 46px; height: 46px; border-radius: 12px;
            background: linear-gradient(135deg, #2b91ff, #0f6fff);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.25rem; color: white; flex-shrink: 0;
            box-shadow: 0 3px 10px rgba(43,145,255,.35);
            transition: transform .2s;
        }
        .folder-hdr.is-open .folder-ico { transform: scale(1.07); }

        .folder-label { flex: 1; }
        .folder-name { font-size: 1rem; font-weight: 700; color: #1a355e; margin-bottom: 3px; }
        .folder-years { font-size: .78rem; color: #6c757d; }

        .folder-badges { display: flex; gap: 7px; align-items: center; flex-shrink: 0; }
        .fbadge { font-size: .74rem; font-weight: 600; padding: 4px 10px; border-radius: 20px; }
        .fbadge-total   { background: #e8f0ff; color: #2b91ff; }
        .fbadge-final   { background: linear-gradient(135deg,#28a745,#20c997); color: white; }
        .fbadge-parcial { background: linear-gradient(135deg,#ffc107,#ff9800); color: #333; }

        .folder-chevron { color: #adb5bd; font-size: .85rem; flex-shrink: 0; transition: transform .3s; }
        .folder-hdr.is-open .folder-chevron { transform: rotate(180deg); color: #2b91ff; }

        .folder-body { display: none; }
        .folder-body.is-open { display: block; }

        .inner-table { width: 100%; border-collapse: collapse; }
        .inner-table thead { background: linear-gradient(135deg,#1a355e,#2b91ff); }
        .inner-table thead th {
            color: white; font-size: .78rem; font-weight: 600;
            padding: 9px 14px; letter-spacing: .04em; text-transform: uppercase;
        }
        .inner-table tbody tr { border-bottom: 1px solid #f0f4ff; transition: background .15s; }
        .inner-table tbody tr:last-child { border-bottom: none; }
        .inner-table tbody tr:hover { background: #f8faff; }
        .inner-table tbody td { padding: 10px 14px; font-size: .87rem; color: #333; vertical-align: middle; }

        .fname-cell { display: flex; align-items: center; gap: 10px; }
        .pdf-dot {
            width: 32px; height: 32px; border-radius: 8px; background: #fff0f0; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center;
            color: #dc3545; font-size: .95rem;
        }
        .fname-text { 
            font-weight: 600; color: #1a355e; font-size: .9rem; 
            word-break: break-all;
        }
        .fname-subtext { 
            font-size: .75rem; color: #6c757d; 
            display: block; margin-top: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 400px;
        }

        .tbadge { font-size: .73rem; font-weight: 600; padding: 3px 10px; border-radius: 20px; }
        .tbadge-final   { background: linear-gradient(135deg,#28a745,#20c997); color: white; }
        .tbadge-parcial { background: linear-gradient(135deg,#ffc107,#ff9800); color: #333; }
        .tbadge-otro    { background: #e9ecef; color: #555; }

        .badge-gen {
            background: #f0e6ff; color: #6f42c1; font-size: .75rem; font-weight: 600;
            padding: 3px 9px; border-radius: 12px; white-space: nowrap;
        }

        .fecha-pill {
            background: #f0f4ff; padding: 3px 10px; border-radius: 20px;
            font-size: .76rem; color: #495057; white-space: nowrap;
        }

        .act-btn {
            padding: 5px 12px; border-radius: 8px; border: none;
            font-size: .82rem; cursor: pointer; transition: all .2s;
            margin: 0 2px;
        }
        .act-btn-view { background: #2b91ff; color: white; }
        .act-btn-view:hover { background: #1a78e6; transform: translateY(-1px); }
        .act-btn-dl   { background: #28a745; color: white; }
        .act-btn-dl:hover { background: #218838; transform: translateY(-1px); }

        .empty-global {
            text-align: center; padding: 70px 20px; color: #6c757d;
            background: white; border-radius: 16px; box-shadow: 0 2px 10px rgba(0,0,0,.07);
        }
        .empty-global .ei { font-size: 4rem; color: #dee2e6; margin-bottom: 18px; }
        .folder-empty { padding: 26px; text-align: center; color: #adb5bd; font-size: .88rem; }

        @media (max-width: 576px) {
            .col-kb { display: none; }
            .filters-wrap { flex-direction: column; }
            .filter-group { min-width: 100%; }
            .page-header { flex-direction: column; text-align: center; }
        }
    </style>
</head>
<body>

<div class="container">
    <?php 
    $headerPath = __DIR__ . '/header_orientador.php';
    if (file_exists($headerPath)) {
        include $headerPath;
    } else {
        echo '<nav class="navbar navbar-light bg-white rounded shadow-sm mb-4 px-3">
                <a class="navbar-brand fw-bold text-primary" href="#"><i class="fas fa-school me-2"></i>Sistema Escolar</a>
              </nav>';
    }
    ?>
    
    <br>

    <div class="page-title">
        <i class="fas fa-history me-2"></i>Historial de Respaldos
    </div>

    <div class="page-header">
        <div class="school-info">
            <strong><?= htmlspecialchars($nombre_escuela) ?></strong><br>
            <span style="font-size:.88rem; color:#666;">
                <?php if ($grado && $grupo && $turno): ?>
                    Grado <?= htmlspecialchars($grado) ?> &nbsp;·&nbsp;
                    Grupo <?= htmlspecialchars(convertirGrupoARomano($grupo)) ?> &nbsp;·&nbsp;
                    Turno <?= htmlspecialchars($turno) ?>
                <?php else: ?>
                    <i class="fas fa-globe me-1"></i>Todos los grupos
                <?php endif; ?>
            </span>
        </div>
        <?php if ($grado && $grupo && $turno): ?>
        <a href="boleta_alumnos_nueva.php?grado=<?= urlencode($grado) ?>&grupo=<?= urlencode($grupo) ?>&turno=<?= urlencode($turno) ?>"
           class="btn-back">
            <i class="fas fa-arrow-left me-2"></i>Volver a Boletas
        </a>
        <?php endif; ?>
    </div>

    <div class="stats-row">
        <div class="stat-card st-grupos">
            <div class="stat-number"><?= $totalCarpetas ?></div>
            <div class="stat-label"><i class="fas fa-folder me-1"></i>Grupos</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= $totalArchivosGlobal ?></div>
            <div class="stat-label"><i class="fas fa-file-pdf me-1"></i>Archivos</div>
        </div>
        <div class="stat-card st-final">
            <div class="stat-number"><?= $totalFinalesGlobal ?></div>
            <div class="stat-label"><i class="fas fa-check-circle me-1"></i>Finales</div>
        </div>
        <div class="stat-card st-parcial">
            <div class="stat-number"><?= $totalParcialesGlobal ?></div>
            <div class="stat-label"><i class="fas fa-clock me-1"></i>Parciales</div>
        </div>
        
        <!-- ═══ NUEVA TARJETA INTERACTIVA CON GRÁFICA ═══ -->
        <div class="stat-card st-grafica" onclick="mostrarGrafica()" title="Haz clic para ver la gráfica de respaldos">
            <div class="stat-number"><i class="fas fa-chart-bar"></i></div>
            <div class="stat-label"><i class="fas fa-chart-line me-1"></i>Ver Gráfica</div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════
         MODAL DE GRÁFICA
    ═══════════════════════════════════════════════════════ -->
    <div class="modal fade modal-grafica" id="modalGrafica" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-chart-bar me-2"></i>Histórico de Respaldos
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="chartContainer">
                        <canvas id="respaldosChart"></canvas>
                    </div>
                    <div class="mt-3 text-center text-muted small">
                        <i class="fas fa-info-circle me-1"></i>
                        Gráfica generada con los respaldos encontrados en el sistema
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════
         SECCIÓN DE FILTROS MULTICRITERIO (NUEVA - de VERSION_B)
    ═══════════════════════════════════════════════════════ -->
    <div class="filters-wrap">
        <!-- Filtro: Generación (Rangos de 3 años) -->
        <div class="filter-group">
            <label><i class="fas fa-graduation-cap me-1"></i>Generación (3 años):</label>
            <select id="filtroGeneracionRango" class="filter-select">
                <option value="">Todas las generaciones</option>
                <?php foreach ($filtrosData['generaciones'] as $genRango): ?>
                <option value="<?= htmlspecialchars($genRango) ?>" 
                        <?= $filtroGeneracion && anioPerteneceAGeneracion($filtroGeneracion, $genRango) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($genRango) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Filtro: Año Específico (dentro de la generación) -->
        <div class="filter-group">
            <label><i class="fas fa-calendar-alt me-1"></i>Año Específico:</label>
            <select id="filtroAnioEspecifico" class="filter-select">
                <option value="">Todos los años</option>
                <?php foreach ($filtrosData['anios'] as $anio): ?>
                <option value="<?= htmlspecialchars($anio) ?>" <?= $filtroAnio === $anio ? 'selected' : '' ?>>
                    <?= htmlspecialchars($anio) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <!-- Filtro: Turno -->
        <div class="filter-group">
            <label><i class="fas fa-sun me-1"></i>Turno:</label>
            <select id="filtroTurno" class="filter-select">
                <option value="">Todos los turnos</option>
                <?php foreach ($filtrosData['turnos'] as $t): ?>
                <option value="<?= htmlspecialchars($t) ?>">
                    <?= htmlspecialchars($t) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <!-- Filtro: Grado -->
        <div class="filter-group">
            <label><i class="fas fa-layer-group me-1"></i>Grado:</label>
            <select id="filtroGrado" class="filter-select">
                <option value="">Todos los grados</option>
                <?php foreach ($filtrosData['grados'] as $g): ?>
                <option value="<?= htmlspecialchars($g) ?>">
                    <?= htmlspecialchars($g) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <!-- Filtro: Grupo -->
        <div class="filter-group">
            <label><i class="fas fa-users me-1"></i>Grupo:</label>
            <select id="filtroGrupo" class="filter-select">
                <option value="">Todos los grupos</option>
                <?php foreach ($filtrosData['grupos'] as $gr): ?>
                <option value="<?= htmlspecialchars($gr) ?>">
                    <?= htmlspecialchars($gr) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Botones -->
        <div style="align-self: flex-end; display: flex; gap: 8px;">
            <button class="btn-clear-filters" onclick="aplicarFiltrosMultiples()">
                <i class="fas fa-filter me-1"></i>Filtrar
            </button>
            <button class="btn-clear-filters" onclick="limpiarFiltros()">
                <i class="fas fa-times me-1"></i>Limpiar
            </button>
        </div>
    </div>

    <div class="search-wrap">
        <input type="text" id="searchInput" class="form-control"
               placeholder="🔍 Buscar por nombre de alumno o archivo..."
               oninput="filtrarArchivos(this.value)">
        <i class="fas fa-search search-ico"></i>
    </div>

    <?php if ($totalCarpetas > 0): ?>
    <div class="folders-list" id="foldersContainer">
        <?php foreach ($carpetas as $clave => $carpeta):
            $uid = 'fc-' . md5($clave);
        ?>
        <div class="folder-card" data-folder="<?= htmlspecialchars(strtolower($clave)) ?>">

            <button class="folder-hdr" onclick="toggleFolder(this, '<?= $uid ?>')">
                <div class="folder-ico"><i class="fas fa-folder-open"></i></div>
                <div class="folder-label">
                    <div class="folder-name"><?= htmlspecialchars($carpeta['label']) ?></div>
                    <?php if (!empty($carpeta['anios'])): ?>
                    <div class="folder-years">
                        <i class="far fa-calendar-alt me-1"></i><?= implode(' · ', $carpeta['anios']) ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="folder-badges">
                    <span class="fbadge fbadge-total" title="Total"><?= $carpeta['total'] ?></span>
                    <?php if ($carpeta['finales'] > 0): ?>
                    <span class="fbadge fbadge-final" title="Finales"><?= $carpeta['finales'] ?> F</span>
                    <?php endif; ?>
                    <?php if ($carpeta['parciales'] > 0): ?>
                    <span class="fbadge fbadge-parcial" title="Parciales"><?= $carpeta['parciales'] ?> P</span>
                    <?php endif; ?>
                </div>
                <i class="fas fa-chevron-down folder-chevron"></i>
            </button>

            <div class="folder-body" id="<?= $uid ?>">
                <?php if (empty($carpeta['archivos'])): ?>
                <div class="folder-empty">
                    <i class="fas fa-filter me-2"></i>No hay archivos con los filtros aplicados.
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="inner-table">
                        <thead>
                            <tr>
                                <th style="width:45%">Alumno</th>
                                <th style="width:10%">Gen.</th>
                                <th style="width:9%">Tipo</th>
                                <th class="col-kb" style="width:8%">KB</th>
                                <th style="width:18%">Fecha</th>
                                <th style="width:10%; text-align:center;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($carpeta['archivos'] as $arch): ?>
                            <tr data-nombre="<?= strtolower(htmlspecialchars($arch['nombre_alumno'])) ?>"
                                data-generacion="<?= strtolower(htmlspecialchars($arch['generacion'])) ?>">
                                <td>
                                    <div class="fname-cell">
                                        <div class="pdf-dot"><i class="fas fa-user me-1"></i></div>
                                        <div>
                                            <!-- SOLO NOMBRE DEL ALUMNO (sin apellidos) -->
                                            <span class="fname-text" title="<?= htmlspecialchars($arch['nombre_alumno']) ?>">
                                                <?= htmlspecialchars($arch['nombre_alumno']) ?>
                                            </span>
                                            <!-- Nombre del archivo en texto pequeño -->
                                            <span class="fname-subtext" title="<?= htmlspecialchars($arch['nombre_archivo']) ?>">
                                                <i class="fas fa-file-pdf me-1"></i><?= htmlspecialchars($arch['nombre_archivo']) ?>
                                            </span>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="badge-gen"><?= htmlspecialchars($arch['generacion']) ?></span></td>
                                <td>
                                    <?php
                                    $tc = $arch['tipo'] === 'Final'   ? 'tbadge-final'
                                        : ($arch['tipo'] === 'Parcial' ? 'tbadge-parcial' : 'tbadge-otro');
                                    ?>
                                    <span class="tbadge <?= $tc ?>"><?= $arch['tipo'] ?></span>
                                </td>
                                <td class="col-kb" style="color:#6c757d; font-size:.8rem;">
                                    <?= round($arch['tamano'] / 1024, 1) ?>
                                </td>
                                <td>
                                    <span class="fecha-pill">
                                        <i class="far fa-calendar-alt me-1"></i>
                                        <?= date('d/m/Y H:i', $arch['fecha']) ?>
                                    </span>
                                </td>
                                <td style="text-align:center;">
                                    <button class="act-btn act-btn-view"
                                            data-action="preview"
                                            data-archivo="<?= htmlspecialchars($arch['nombre_archivo']) ?>"
                                            data-carpeta="<?= htmlspecialchars($arch['carpeta']) ?>"
                                            data-generacion="<?= htmlspecialchars($arch['generacion']) ?>"
                                            data-turno="<?= htmlspecialchars($arch['turno']) ?>"
                                            title="Vista previa">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="act-btn act-btn-dl"
                                            data-action="download"
                                            data-archivo="<?= htmlspecialchars($arch['nombre_archivo']) ?>"
                                            data-carpeta="<?= htmlspecialchars($arch['carpeta']) ?>"
                                            data-generacion="<?= htmlspecialchars($arch['generacion']) ?>"
                                            data-turno="<?= htmlspecialchars($arch['turno']) ?>"
                                            title="Descargar">
                                        <i class="fas fa-download"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>

        </div>
        <?php endforeach; ?>
    </div>

    <?php else: ?>
    <div class="empty-global">
        <div class="ei"><i class="fas fa-folder-open"></i></div>
        <h5 style="color:#1a355e; font-weight:700;">
            <?= empty($carpetas) ? 'No hay respaldos generados' : 'No hay respaldos con los filtros aplicados' ?>
        </h5>
        <p class="mb-4 text-muted">
            <?php if (empty($carpetas)): ?>
                Los archivos PDF aparecerán aquí organizados por generación, turno y grupo.
            <?php else: ?>
                Intenta ajustar los filtros o genera un nuevo respaldo.
            <?php endif; ?>
        </p>
        <?php if ($grado && $grupo && $turno): ?>
        <a href="boleta_alumnos_nueva.php?grado=<?= urlencode($grado) ?>&grupo=<?= urlencode($grupo) ?>&turno=<?= urlencode($turno) ?>"
           class="btn text-white fw-semibold px-4 py-2"
           style="background:linear-gradient(135deg,#0f6fff,#14f1f8); border:none; border-radius:50px; text-decoration:none;">
            <i class="fas fa-plus me-2"></i>Generar respaldo
        </a>
        <?php endif; ?>
        <?php if (!empty($filtroGeneracion) || !empty($filtroAnio)): ?>
        <br>
        <button class="btn-clear-filters mt-3" onclick="limpiarFiltros()">
            <i class="fas fa-undo me-1"></i>Restablecer filtros
        </button>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div>

<br>

<?php 
$footerPath = __DIR__ . '/footer_orientador.php';
if (file_exists($footerPath)) {
    include $footerPath;
}
?>

<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.min.js"></script>

<div class="modal fade" id="modalPreviewPDF" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:90%; height:92vh; margin:4vh auto;">
        <div class="modal-content" style="height:100%; border-radius:16px; overflow:hidden;">
            <div class="modal-header border-0" style="background:linear-gradient(135deg,#1a355e,#2b91ff); padding:13px 20px;">
                <h6 class="modal-title fw-bold text-white mb-0">
                    <i class="fas fa-file-pdf me-2"></i><span id="preview-filename"></span>
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-0" style="flex:1; overflow:hidden;">
                <iframe id="pdf-iframe" src="" style="width:100%; height:100%; border:none;"></iframe>
            </div>
        </div>
    </div>
</div>

<script>
function toggleFolder(btn, id) {
    const body = document.getElementById(id);
    const isOpen = body.classList.contains('is-open');
    body.classList.toggle('is-open', !isOpen);
    btn.classList.toggle('is-open', !isOpen);
}

function previsualizarPDF(archivo, carpeta, generacion, turno) {
    // Construir URL correcta según arquitectura: generación/[AÑO]/[TURNO]/Grupos/[CARPETA]/
    const url = `descargar_pdf.php?` +
        `archivo=${encodeURIComponent(archivo)}` +
        `&anio=${encodeURIComponent(generacion)}` +  // generacion es realmente el año
        `&turno=${encodeURIComponent(turno)}` +
        `&carpeta=${encodeURIComponent(carpeta)}` +
        `&accion=visualizar`;
    
    document.getElementById('preview-filename').textContent = archivo;
    document.getElementById('pdf-iframe').src = url;
    new bootstrap.Modal(document.getElementById('modalPreviewPDF')).show();
}

function descargarPDF(archivo, carpeta, generacion, turno) {
    // Construir URL correcta según arquitectura: generación/[AÑO]/[TURNO]/Grupos/[CARPETA]/
    const url = `descargar_pdf.php?` +
        `archivo=${encodeURIComponent(archivo)}` +
        `&anio=${encodeURIComponent(generacion)}` +  // generacion es realmente el año
        `&turno=${encodeURIComponent(turno)}` +
        `&carpeta=${encodeURIComponent(carpeta)}` +
        `&accion=descargar`;
    
    window.location.href = url;
}

document.getElementById('modalPreviewPDF')
    .addEventListener('hidden.bs.modal', () => {
        document.getElementById('pdf-iframe').src = '';
    });

document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-action]');
    if (!btn) return;
    const { action, archivo, carpeta, generacion, turno } = btn.dataset;
    if (action === 'preview')  previsualizarPDF(archivo, carpeta, generacion, turno);
    if (action === 'download') descargarPDF(archivo, carpeta, generacion, turno);
});

// ══════════════════════════════════════════════════════════════
// NUEVAS FUNCIONES: FILTROS EN CASCADA (de VERSION_B)
// ══════════════════════════════════════════════════════════════

// Mapeo de generación → años (desde PHP)
const mapeoGeneracionAnios = <?= json_encode($mapeoGeneracionAnios) ?>;

// Filtro Generación → Año Específico (cascada)
document.getElementById('filtroGeneracionRango').addEventListener('change', function() {
    const selectAnio = document.getElementById('filtroAnioEspecifico');
    const generacionSeleccionada = this.value;
    
    // Limpiar opciones actuales (excepto la primera)
    selectAnio.innerHTML = '<option value="">Todos los años</option>';
    
    if (generacionSeleccionada && mapeoGeneracionAnios[generacionSeleccionada]) {
        // Poblar con años de la generación seleccionada
        mapeoGeneracionAnios[generacionSeleccionada].forEach(anio => {
            const option = document.createElement('option');
            option.value = anio;
            option.textContent = anio;
            selectAnio.appendChild(option);
        });
    } else {
        // Si no hay generación seleccionada, mostrar todos los años
        const todosLosAnios = <?= json_encode($filtrosData['anios']) ?>;
        todosLosAnios.forEach(anio => {
            const option = document.createElement('option');
            option.value = anio;
            option.textContent = anio;
            selectAnio.appendChild(option);
        });
    }
});

// Aplicar todos los filtros seleccionados
function aplicarFiltrosMultiples() {
    const url = new URL(window.location.href);
    
    // Obtener valores de los filtros
    const generacionRango = document.getElementById('filtroGeneracionRango').value;
    const anioEspecifico = document.getElementById('filtroAnioEspecifico').value;
    const turno = document.getElementById('filtroTurno').value;
    const grado = document.getElementById('filtroGrado').value;
    const grupo = document.getElementById('filtroGrupo').value;
    
    // Si hay generación seleccionada, guardar el primer año de ese rango
    if (generacionRango) {
        const anioInicio = generacionRango.split(' - ')[0];
        url.searchParams.set('generacion', anioInicio);
    } else {
        url.searchParams.delete('generacion');
    }
    
    // Año específico
    if (anioEspecifico) {
        url.searchParams.set('anio', anioEspecifico);
    } else {
        url.searchParams.delete('anio');
    }
    
    // Turno
    if (turno) {
        url.searchParams.set('turno', turno);
    } else {
        url.searchParams.delete('turno');
    }
    
    // Grado
    if (grado) {
        url.searchParams.set('grado', grado);
    } else {
        url.searchParams.delete('grado');
    }
    
    // Grupo
    if (grupo) {
        url.searchParams.set('grupo', grupo);
    } else {
        url.searchParams.delete('grupo');
    }
    
    window.location.href = url.toString();
}

// ══════════════════════════════════════════════════════════════
// FUNCIONES ANTIGUAS (mantenidas por compatibilidad)
// Nota: aplicarFiltro() ya no se usa pero se mantiene por si acaso
// ══════════════════════════════════════════════════════════════

function aplicarFiltro(tipo, valor) {
    const url = new URL(window.location.href);
    if (valor) {
        url.searchParams.set(tipo, valor);
    } else {
        url.searchParams.delete(tipo);
    }
    window.location.href = url.toString();
}

function limpiarFiltros() {
    const url = new URL(window.location.href);
    url.searchParams.delete('generacion');
    url.searchParams.delete('anio');
    url.searchParams.delete('turno');
    url.searchParams.delete('grado');
    url.searchParams.delete('grupo');
    window.location.href = url.toString();
}

function filtrarArchivos(q) {
    q = q.trim().toLowerCase();
    document.querySelectorAll('.folder-card').forEach(card => {
        const rows = card.querySelectorAll('tbody tr');
        let vis = 0;
        rows.forEach(row => {
            const nombre = row.dataset.nombre || '';
            const ok = !q || nombre.includes(q);
            row.style.display = ok ? '' : 'none';
            if (ok) vis++;
        });
        if (q) {
            const body = card.querySelector('.folder-body');
            const hdr  = card.querySelector('.folder-hdr');
            if (vis > 0) {
                body.classList.add('is-open');
                hdr.classList.add('is-open');
                card.style.display = '';
            } else {
                card.style.display = 'none';
            }
        } else {
            card.style.display = '';
        }
    });
}

// ══════════════════════════════════════════════════════════════
// INICIALIZACIÓN: Poblar años según generación preseleccionada
// ══════════════════════════════════════════════════════════════

document.addEventListener('DOMContentLoaded', () => {
    const generacionActual = document.getElementById('filtroGeneracionRango').value;
    if (generacionActual) {
        // Disparar evento change para actualizar años
        document.getElementById('filtroGeneracionRango').dispatchEvent(new Event('change'));
        
        // Restaurar año seleccionado si existe
        const anioActual = '<?= htmlspecialchars($filtroAnio) ?>';
        if (anioActual) {
            setTimeout(() => {
                document.getElementById('filtroAnioEspecifico').value = anioActual;
            }, 50);
        }
    }
});

// ══════════════════════════════════════════════════════════════
// FUNCIÓN PARA MOSTRAR GRÁFICA DE RESPALDOS
// ══════════════════════════════════════════════════════════════

let chartInstance = null; // Variable global para guardar la instancia del gráfico

function mostrarGrafica() {
    // Datos desde PHP
    const anios = <?= json_encode($aniosGrafica) ?>;
    const completas = <?= json_encode($completasGrafica) ?>;
    const incompletas = <?= json_encode($incompletasGrafica) ?>;
    
    // Mostrar modal
    const modal = new bootstrap.Modal(document.getElementById('modalGrafica'));
    modal.show();
    
    // Esperar a que el modal se muestre completamente antes de crear el gráfico
    document.getElementById('modalGrafica').addEventListener('shown.bs.modal', function () {
        // Destruir gráfico anterior si existe
        if (chartInstance) {
            chartInstance.destroy();
        }
        
        // Crear nuevo gráfico
        const ctx = document.getElementById('respaldosChart').getContext('2d');
        chartInstance = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: anios,
                datasets: [
                    {
                        label: 'Boletas Completas (Finales)',
                        data: completas,
                        backgroundColor: 'rgba(40, 167, 69, 0.8)',
                        borderColor: 'rgba(40, 167, 69, 1)',
                        borderWidth: 2,
                        borderRadius: 8
                    },
                    {
                        label: 'Boletas Incompletas (Parciales)',
                        data: incompletas,
                        backgroundColor: 'rgba(255, 193, 7, 0.8)',
                        borderColor: 'rgba(255, 193, 7, 1)',
                        borderWidth: 2,
                        borderRadius: 8
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: {
                            font: {
                                size: 14,
                                family: 'League Spartan',
                                weight: '600'
                            },
                            padding: 20,
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    },
                    title: {
                        display: true,
                        text: 'Distribución de Respaldos por Año',
                        font: {
                            size: 18,
                            family: 'League Spartan',
                            weight: '700'
                        },
                        padding: 20,
                        color: '#1a355e'
                    },
                    tooltip: {
                        backgroundColor: 'rgba(26, 53, 94, 0.95)',
                        titleFont: {
                            size: 14,
                            family: 'League Spartan',
                            weight: '600'
                        },
                        bodyFont: {
                            size: 13,
                            family: 'League Spartan'
                        },
                        padding: 12,
                        cornerRadius: 8,
                        displayColors: true,
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                label += context.parsed.y + ' boleta(s)';
                                return label;
                            },
                            footer: function(tooltipItems) {
                                let total = 0;
                                tooltipItems.forEach(function(tooltipItem) {
                                    total += tooltipItem.parsed.y;
                                });
                                return 'Total: ' + total + ' boletas';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1,
                            font: {
                                size: 12,
                                family: 'League Spartan'
                            }
                        },
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)',
                            drawBorder: false
                        }
                    },
                    x: {
                        ticks: {
                            font: {
                                size: 12,
                                family: 'League Spartan',
                                weight: '600'
                            }
                        },
                        grid: {
                            display: false
                        }
                    }
                },
                animation: {
                    duration: 1000,
                    easing: 'easeInOutQuart'
                },
                interaction: {
                    mode: 'index',
                    intersect: false
                }
            }
        });
    }, { once: true }); // Solo ejecutar una vez por apertura de modal
}
</script>

</body>
</html>