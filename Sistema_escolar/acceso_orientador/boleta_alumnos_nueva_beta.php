<?php
// boleta_alumnos_nueva_beta.php — VERSIÓN UNIFICADA Y CORREGIDA
// ✅ Filtro de materias consistente: am.estado = 1
// ✅ Consulta de calificaciones con id_escuela
// ✅ Clasificación: aprobado/reprobado/incompleto
// ✅ Gráfica Chart.js con contadores
// ✅ Variables estado_final y promedio_final para badges en vista

ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

if (!isset($_SESSION['id_credencial'])) {
    header("Location: login.php");
    exit();
}

include '../funciones/conexQRConejo.php';
$id_usuario = $_SESSION['id_credencial'];
$secretKey = 'your-secret-key';

// ============================================================
// 1. OBTENER DATOS DEL USUARIO Y ESCUELA
// ============================================================
$stmt = mysqli_prepare($conexion, "SELECT id_escuela FROM credenciales WHERE id_credencial = ?");
mysqli_stmt_bind_param($stmt, "i", $id_usuario);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$id_escuela = (int)mysqli_fetch_assoc($result)['id_escuela'];

$stmt = mysqli_prepare($conexion, "SELECT nombre_escuela FROM escuelas WHERE id_escuela = ?");
mysqli_stmt_bind_param($stmt, "i", $id_escuela);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$nombre_escuela = mysqli_fetch_assoc($result)['nombre_escuela'];

// ============================================================
// 2. PARÁMETROS DEL GRUPO
// ============================================================
$grado = trim($_GET['grado'] ?? '');
$grupo = trim($_GET['grupo'] ?? '');
$turno = trim($_GET['turno'] ?? '');

if (!$grado || !$grupo || !$turno) {
    die("Faltan parámetros: grado, grupo y turno son requeridos.");
}

// ============================================================
// 3. CONTADOR TOTAL DE ALUMNOS
// ============================================================
$stmt = mysqli_prepare($conexion, "
    SELECT COUNT(*) as total_alumnos
    FROM credenciales
    WHERE grado_credencial = ?
    AND grupo_credencial = ?
    AND turno_credencial = ?
    AND id_escuela = ?
    AND nivel_usuario = 7
");
mysqli_stmt_bind_param($stmt, "sssi", $grado, $grupo, $turno, $id_escuela);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$total_alumnos = (int)mysqli_fetch_assoc($result)['total_alumnos'];

// ============================================================
// 4. FUNCIÓN DE DESENCRIPTADO
// ============================================================
function decryptData($data, $key) {
    if (empty($data)) return '';
    $parts = explode('::', base64_decode($data), 2);
    if (count($parts) !== 2) return '—';
    [$cipher, $iv] = $parts;
    return openssl_decrypt($cipher, 'aes-256-cbc', $key, 0, base64_decode($iv));
}

// ============================================================
// 5. MATERIAS ASIGNADAS AL GRUPO ✅ FILTRO UNIFICADO
// ============================================================
$materias = [];
$stmt = mysqli_prepare($conexion, "
    SELECT m.id_materia, m.nombre_materia
    FROM asignacion_materias am
    JOIN materias m ON am.id_materia = m.id_materia
    WHERE am.grado_credencial = ?
    AND am.grupo_credencial = ?
    AND am.turno_credencial = ?
    AND am.id_escuela = ?
    AND am.estado = 1
    ORDER BY m.N_orden_materia ASC
");
mysqli_stmt_bind_param($stmt, "sssi", $grado, $grupo, $turno, $id_escuela);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) {
    $materias[] = [
        'id_materia' => (int)$row['id_materia'],
        'nombre_materia' => $row['nombre_materia']
    ];
}
$total_materias = count($materias);

// ============================================================
// 6. ALUMNOS DEL GRUPO (DESENCRIPTADOS Y ORDENADOS)
// ============================================================
$alumnos_raw = [];
$stmt = mysqli_prepare($conexion, "
    SELECT id_credencial, nombre_credencial, apellidos_credencial, ruta_foto
    FROM credenciales
    WHERE grado_credencial = ?
    AND grupo_credencial = ?
    AND turno_credencial = ?
    AND id_escuela = ?
    AND nivel_usuario = 7
");
mysqli_stmt_bind_param($stmt, "sssi", $grado, $grupo, $turno, $id_escuela);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) {
    $row['apellidos_decrypted'] = decryptData($row['apellidos_credencial'], $secretKey);
    $alumnos_raw[] = $row;
}

// Ordenar por apellidos + nombre
usort($alumnos_raw, function($a, $b) {
    $cmp = strcmp($a['apellidos_decrypted'], $b['apellidos_decrypted']);
    return $cmp !== 0 ? $cmp : strcmp($a['nombre_credencial'], $b['nombre_credencial']);
});

// ============================================================
// 7. CALIFICACIONES ✅ SIN id_escuela (columna no existe)
// ============================================================
$calificaciones = [];
$stmt = mysqli_prepare($conexion, "
    SELECT id_alumno, id_materia, primer_parcial, segundo_parcial, tercer_parcial
    FROM calificaciones
    WHERE grado_credencial = ?
    AND grupo_credencial = ?
    AND turno_credencial = ?
");
mysqli_stmt_bind_param($stmt, "sss", $grado, $grupo, $turno);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) {
    $id_alumno = (int)$row['id_alumno'];
    $id_materia = (int)$row['id_materia'];
    $calificaciones[$id_alumno][$id_materia] = [
        'primer_parcial'  => $row['primer_parcial'],
        'segundo_parcial' => $row['segundo_parcial'],
        'tercer_parcial'  => $row['tercer_parcial']
    ];
}
// ============================================================
// 8. FUNCIÓN DE CLASIFICACIÓN: APROBADO/REPROBADO/INCOMPLETO
// ============================================================
define('PROMEDIO_APROBATORIO', 6.0);

function clasificarEstadoAlumno($id_alumno, $materias, $calificaciones) {
    $id_alumno = (int)$id_alumno;
    $total_materias = count($materias);
    $materias_completas = 0;
    $suma_promedios = 0;
    
    // Si no hay calificaciones registradas para este alumno
    if (!isset($calificaciones[$id_alumno])) {
        return ['estado' => 'incompleto', 'promedio' => 0, 'materias_completas' => 0];
    }
    
    foreach ($materias as $mat) {
        $id_materia = (int)$mat['id_materia'];
        
        if (isset($calificaciones[$id_alumno][$id_materia])) {
            $cal = $calificaciones[$id_alumno][$id_materia];
            
            // Validar que los 3 parciales existan y sean numéricos (no vacíos)
            $p1 = ($cal['primer_parcial'] !== '' && $cal['primer_parcial'] !== null) ? (float)$cal['primer_parcial'] : null;
            $p2 = ($cal['segundo_parcial'] !== '' && $cal['segundo_parcial'] !== null) ? (float)$cal['segundo_parcial'] : null;
            $p3 = ($cal['tercer_parcial'] !== '' && $cal['tercer_parcial'] !== null) ? (float)$cal['tercer_parcial'] : null;
            
            if (is_numeric($p1) && is_numeric($p2) && is_numeric($p3)) {
                $materias_completas++;
                $promedio_materia = ($p1 + $p2 + $p3) / 3;
                $suma_promedios += $promedio_materia;
            }
        }
    }
    
    // Si no todas las materias están completas → INCOMPLETO
    if ($materias_completas !== $total_materias || $total_materias === 0) {
        return ['estado' => 'incompleto', 'promedio' => 0, 'materias_completas' => $materias_completas];
    }
    
    // Calcular promedio general
    $promedio_general = $suma_promedios / $total_materias;
    
    if ($promedio_general >= PROMEDIO_APROBATORIO) {
        return ['estado' => 'aprobado', 'promedio' => $promedio_general, 'materias_completas' => $total_materias];
    } else {
        return ['estado' => 'reprobado', 'promedio' => $promedio_general, 'materias_completas' => $total_materias];
    }
}

// ============================================================
// 9. PROCESAR ESTADO DE CADA ALUMNO + CONTADORES PARA GRÁFICA
// ============================================================
$alumnos_con_estado = [];
$count_aprobados = 0;
$count_reprobados = 0;
$count_incompletos = 0;

foreach ($alumnos_raw as $alumno) {
    $id_alumno = (int)$alumno['id_credencial'];
    $clasificacion = clasificarEstadoAlumno($id_alumno, $materias, $calificaciones);
    
    $alumno['estado_final'] = $clasificacion['estado'];
    $alumno['promedio_final'] = round($clasificacion['promedio'], 2);
    $alumno['boleta_completa'] = ($clasificacion['estado'] !== 'incompleto');
    $alumno['materias_completas'] = $clasificacion['materias_completas'];
    
    // Contadores para la gráfica
    switch ($clasificacion['estado']) {
        case 'aprobado': $count_aprobados++; break;
        case 'reprobado': $count_reprobados++; break;
        default: $count_incompletos++; break;
    }
    
    $alumnos_con_estado[] = $alumno;
}

// ============================================================
// 10. GRUPO A NÚMERO ROMANO
// ============================================================
function grupoToRomano($letra) {
    $map = ['A'=>'I','B'=>'II','C'=>'III','D'=>'IV','E'=>'V','F'=>'VI','G'=>'VII','H'=>'VIII','I'=>'IX','J'=>'X'];
    return $map[strtoupper($letra)] ?? $letra;
}
$grupo_romano = grupoToRomano($grupo);

// ============================================================
// 11. PASAR VARIABLES A LA VISTA
// ============================================================
$alumnos = $alumnos_con_estado;


// ============================================================
// 12. RENDERIZAR GRÁFICA ANALÍTICA (HTML + Chart.js)
// ============================================================
?>

<?php

include 'header_orientador.php';
include 'vista/boleta_vista_beta.php';

// ============================================================
// 13. SCRIPT CHART.JS (al final para asegurar carga del DOM)
// ============================================================
?>
<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    const ctx = document.getElementById('boletaChart');
    if (!ctx) return;
    
    const total = <?php echo $count_aprobados + $count_reprobados + $count_incompletos; ?>;
    
    new Chart(ctx.getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: ['Aprobados', 'Reprobados', 'Incompletos'],
            datasets: [{
                data: [
                    <?php echo (int)$count_aprobados; ?>,
                    <?php echo (int)$count_reprobados; ?>,
                    <?php echo (int)$count_incompletos; ?>
                ],
                backgroundColor: [
                    '#28a745', // Verde - Aprobados
                    '#dc3545', // Rojo - Reprobados  
                    '#ffc107'  // Amarillo - Incompletos
                ],
                borderColor: '#ffffff',
                borderWidth: 3,
                hoverOffset: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '65%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 15,
                        font: { size: 11 },
                        usePointStyle: true
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.raw || 0;
                            const percentage = total > 0 ? Math.round((value/total)*100) : 0;
                            return `${label}: ${value} alumno(s) (${percentage}%)`;
                        }
                    }
                }
            },
            animation: {
                animateScale: true,
                animateRotate: true
            }
        }
    });
});
</script>
<?php
mysqli_close($conexion);
?>