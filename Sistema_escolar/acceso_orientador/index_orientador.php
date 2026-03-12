<?php
include '../funciones/conexQRConejo.php';
session_start();
//index_orientador.php
if (!isset($_SESSION['id_credencial']) || !isset($_SESSION['nivel_usuario']) || $_SESSION['nivel_usuario'] != 4) {
    echo "Acceso denegado. Detalles de la sesión:";
    var_dump($_SESSION);
    exit;
}

$id_credencial_orientador = $_SESSION['id_credencial'];
date_default_timezone_set('America/Mexico_City');

// Obtener información del orientador (escuela, grupos asignados y turno)
$query_orientador = "SELECT id_escuela, grados_grupos_orientador, turno_credencial FROM credenciales WHERE id_credencial = ?";
$stmt_orientador = mysqli_prepare($conexion, $query_orientador);
mysqli_stmt_bind_param($stmt_orientador, "i", $id_credencial_orientador);
mysqli_stmt_execute($stmt_orientador);
$resultado_orientador = mysqli_stmt_get_result($stmt_orientador);

if ($fila_orientador = mysqli_fetch_assoc($resultado_orientador)) {
    $id_escuela_orientador = $fila_orientador['id_escuela'];
    $grados_grupos_orientador = $fila_orientador['grados_grupos_orientador'];
    $turno_orientador = $fila_orientador['turno_credencial'];

} else {
    die("Error al obtener los datos del orientador.");
}

// Procesar los grupos asignados al orientador
$condiciones_grupos = [];
if (!empty($grados_grupos_orientador)) {
    $grupos_array = explode(',', str_replace(' ', '', $grados_grupos_orientador));
    
    foreach ($grupos_array as $grupo) {
        if (preg_match('/^(Primero|Segundo|Tercero|Cuarto|Quinto|Sexto)([A-J])$/i', $grupo, $matches)) {
            $grado = ucfirst(strtolower($matches[1]));
            $grupo_letra = strtoupper($matches[2]);
            $condiciones_grupos[] = "(c.grado_credencial = '$grado' AND c.grupo_credencial = '$grupo_letra')";
        }
    }
}

// Construir cláusula WHERE para consultas (incluyendo turno)
if (empty($condiciones_grupos)) {
    $where_clause = "c.id_escuela = '$id_escuela_orientador' AND c.nivel_usuario = 7 AND c.status = 'activo' AND c.turno_credencial = '$turno_orientador'";
} else {
    $where_clause = "c.id_escuela = '$id_escuela_orientador' AND c.nivel_usuario = 7 AND c.status = 'activo' AND c.turno_credencial = '$turno_orientador' AND (" . implode(' OR ', $condiciones_grupos) . ")";
}

// Obtener información del usuario para mostrar en el panel
$query_usuario = "SELECT c.nombre_credencial, e.nombre_escuela 
                 FROM credenciales c
                 JOIN escuelas e ON c.id_escuela = e.id_escuela
                 WHERE c.id_credencial = ?";
$stmt_usuario = $conexion->prepare($query_usuario);
$stmt_usuario->bind_param("i", $id_credencial_orientador);
$stmt_usuario->execute();
$result_usuario = $stmt_usuario->get_result();

if ($result_usuario->num_rows > 0) {
    $user = $result_usuario->fetch_assoc();
    $nombre_credencial = $user['nombre_credencial'];
    $nombre_escuela = $user['nombre_escuela'];
} else {
    header("Location: login.php");
    exit();
}

// Consulta para contar alumnos registrados en los grupos del orientador
$query_alumnos = "SELECT COUNT(*) AS cantidad FROM credenciales c WHERE $where_clause";
$resultado_alumnos = mysqli_query($conexion, $query_alumnos);
$alumnos_registrados = ($fila = mysqli_fetch_assoc($resultado_alumnos)) ? $fila['cantidad'] : 0;

// Consulta CORREGIDA para contar asistencias del día (con hora_entrada)
$fecha_hoy = date('Y-m-d');
$fecha_hoy1 = date('d-m-Y');

$query_asistencias_hoy = "SELECT COUNT(DISTINCT esa.id_credencial) AS cantidad 
                         FROM entrada_salida_alumno esa
                         JOIN credenciales c ON esa.id_credencial = c.id_credencial
                         WHERE $where_clause
                         AND esa.fecha = '$fecha_hoy'
                         AND esa.hora_entrada IS NOT NULL";
$resultado_asistencias_hoy = mysqli_query($conexion, $query_asistencias_hoy);
$asistencias_hoy = ($fila = mysqli_fetch_assoc($resultado_asistencias_hoy)) ? $fila['cantidad'] : 0;

// Calcular porcentajes
$porcentaje_asistencia = ($alumnos_registrados > 0) ? round(($asistencias_hoy * 100) / $alumnos_registrados, 2) : 0;
$inasistencias_hoy = $alumnos_registrados - $asistencias_hoy;
$porcentaje_inasistencia = ($alumnos_registrados > 0) ? round(($inasistencias_hoy * 100) / $alumnos_registrados, 2) : 0;

// Consulta para distribución por grados
$query_grados = "SELECT grado_credencial, COUNT(*) AS cantidad 
                FROM credenciales c
                WHERE $where_clause
                GROUP BY grado_credencial";
$resultado_grados = mysqli_query($conexion, $query_grados);
$grados_estudiantes = [];
while ($fila_grado = mysqli_fetch_assoc($resultado_grados)) {
    $grados_estudiantes[] = $fila_grado;
}

// Consulta para distribución por género de alumnos
$query_genero_alumnos = "SELECT UPPER(genero) AS genero, COUNT(*) AS cantidad
                        FROM credenciales c
                        WHERE $where_clause
                        GROUP BY genero";
$resultado_genero_alumnos = mysqli_query($conexion, $query_genero_alumnos);

$conteo_genero = ['MASCULINO' => 0, 'FEMENINO' => 0];
while ($fila_genero = mysqli_fetch_assoc($resultado_genero_alumnos)) {
    $genero = strtoupper($fila_genero['genero']);
    if ($genero === 'MASCULINO' || $genero === 'FEMENINO') {
        $conteo_genero[$genero] = $fila_genero['cantidad'];
    }
}

// Consulta para distribución por género de maestros
$query_genero_maestros = "SELECT UPPER(genero) AS genero, COUNT(*) AS cantidad
                         FROM credenciales
                         WHERE id_escuela = '$id_escuela_orientador' AND nivel_usuario = 6
                         GROUP BY genero";
$resultado_genero_maestros = mysqli_query($conexion, $query_genero_maestros);

$conteo_maestros_genero = ['MASCULINO' => 0, 'FEMENINO' => 0];
while ($fila_genero = mysqli_fetch_assoc($resultado_genero_maestros)) {
    $genero = strtoupper($fila_genero['genero']);
    if ($genero === 'MASCULINO' || $genero === 'FEMENINO') {
        $conteo_maestros_genero[$genero] = $fila_genero['cantidad'];
    }
}

$maestros_genero = json_encode($conteo_maestros_genero);

// Consulta para contar incidencias activas (estatus = 1) del orientador
$query_incidencias = "
    SELECT COUNT(*) AS cantidad 
    FROM incidencias i
    JOIN credenciales c ON i.id_credencial = c.id_credencial
    WHERE $where_clause
    AND i.estatus = 1";
$resultado_incidencias = mysqli_query($conexion, $query_incidencias);
$incidencias_activas = ($fila = mysqli_fetch_assoc($resultado_incidencias)) ? $fila['cantidad'] : 0;

// Consulta para obtener promedios por parcial de cada grupo
$promedios_por_grupo = [];
$colores_grafico = [
    'rgba(0, 188, 212, 0.7)',   // Cyan vibrante
    'rgba(255, 87, 34, 0.7)',   // Naranja intenso
    'rgba(139, 195, 74, 0.7)',  // Verde lima
    'rgba(244, 67, 54, 0.7)',   // Rojo vibrante
    'rgba(156, 39, 176, 0.7)',  // Morado intenso
    'rgba(255, 235, 59, 0.7)',  // Amarillo brillante
    'rgba(3, 169, 244, 0.7)',   // Azul claro vibrante
    'rgba(233, 30, 99, 0.7)',   // Rosa intenso
    'rgba(76, 175, 80, 0.7)',   // Verde esmeralda
    'rgba(255, 152, 0, 0.7)'    // Naranja dorado
];

$colores_borde = [
    'rgba(0, 188, 212, 1)',
    'rgba(255, 87, 34, 1)',
    'rgba(139, 195, 74, 1)',
    'rgba(244, 67, 54, 1)',
    'rgba(156, 39, 176, 1)',
    'rgba(255, 235, 59, 1)',
    'rgba(3, 169, 244, 1)',
    'rgba(233, 30, 99, 1)',
    'rgba(76, 175, 80, 1)',
    'rgba(255, 152, 0, 1)'
];

$color_index = 0;

if (!empty($grupos_array)) {
    foreach ($grupos_array as $grupo) {
        if (preg_match('/^(Primero|Segundo|Tercero|Cuarto|Quinto|Sexto)([A-J])$/i', $grupo, $matches)) {
            $grado = ucfirst(strtolower($matches[1]));
            $grupo_letra = strtoupper($matches[2]);
            
            // Consultar promedios por parcial
            $query_promedios = "
                SELECT 
                    AVG(CAST(primer_parcial AS DECIMAL(5,2))) as promedio_p1,
                    AVG(CAST(segundo_parcial AS DECIMAL(5,2))) as promedio_p2,
                    AVG(CAST(tercer_parcial AS DECIMAL(5,2))) as promedio_p3
                FROM calificaciones cal
                JOIN credenciales c ON cal.id_alumno = c.id_credencial
                WHERE c.id_escuela = '$id_escuela_orientador'
                AND c.grado_credencial = '$grado'
                AND c.grupo_credencial = '$grupo_letra'
                AND c.turno_credencial = '$turno_orientador'
                AND c.nivel_usuario = 7
                AND c.status = 'activo'
                AND cal.primer_parcial IS NOT NULL
                AND cal.segundo_parcial IS NOT NULL
                AND cal.tercer_parcial IS NOT NULL";
            
            $resultado_promedios = mysqli_query($conexion, $query_promedios);
            
            if ($fila_prom = mysqli_fetch_assoc($resultado_promedios)) {
                $promedios_por_grupo[] = [
                    'grupo' => $grado . ' ' . $grupo_letra,
                    'parcial1' => round($fila_prom['promedio_p1'] ?? 0, 2),
                    'parcial2' => round($fila_prom['promedio_p2'] ?? 0, 2),
                    'parcial3' => round($fila_prom['promedio_p3'] ?? 0, 2),
                    'color' => $colores_grafico[$color_index % count($colores_grafico)],
                    'color_borde' => $colores_borde[$color_index % count($colores_borde)]
                ];
                $color_index++;
            }
        }
    }
}

// Consulta para contar alumnos con calificaciones reprobatorias (promedio < 6 o cualquier parcial < 6)
$query_reprobatorios = "
    SELECT COUNT(DISTINCT cal.id_alumno) AS cantidad
    FROM calificaciones cal
    JOIN credenciales c ON cal.id_alumno = c.id_credencial
    WHERE $where_clause
    AND (
        cal.primer_parcial < 6 
        OR cal.segundo_parcial < 6 
        OR cal.tercer_parcial < 6
    )";
$resultado_reprobatorios = mysqli_query($conexion, $query_reprobatorios);
$alumnos_reprobatorios = ($fila = mysqli_fetch_assoc($resultado_reprobatorios)) ? $fila['cantidad'] : 0;

// Consulta detallada para obtener materias con más reprobados
$query_materias_criticas = "
    SELECT m.nombre_materia, COUNT(DISTINCT cal.id_alumno) AS total_reprobados
    FROM calificaciones cal
    JOIN materias m ON cal.id_materia = m.id_materia
    JOIN credenciales c ON cal.id_alumno = c.id_credencial
    WHERE $where_clause
    AND (
        cal.primer_parcial < 6 
        OR cal.segundo_parcial < 6 
        OR cal.tercer_parcial < 6
    )
    GROUP BY m.id_materia
    ORDER BY total_reprobados DESC
    LIMIT 3";
$resultado_materias_criticas = mysqli_query($conexion, $query_materias_criticas);
$materias_criticas = [];
while ($fila = mysqli_fetch_assoc($resultado_materias_criticas)) {
    $materias_criticas[] = $fila;
}

// Consulta para calcular el promedio general de todos los grupos del orientador
$query_promedio_general = "
    SELECT 
        AVG((cal.primer_parcial + cal.segundo_parcial + cal.tercer_parcial) / 3) AS promedio_general
    FROM calificaciones cal
    JOIN credenciales c ON cal.id_alumno = c.id_credencial
    WHERE $where_clause
    AND cal.primer_parcial IS NOT NULL 
    AND cal.segundo_parcial IS NOT NULL 
    AND cal.tercer_parcial IS NOT NULL";
$resultado_promedio = mysqli_query($conexion, $query_promedio_general);
$promedio_general = 0;
if ($fila = mysqli_fetch_assoc($resultado_promedio)) {
    $promedio_general = $fila['promedio_general'] ? round($fila['promedio_general'], 2) : 0;
}

// Consulta para contar alumnos en riesgo (promedio < 6 o más de 3 materias reprobadas)
$query_alumnos_riesgo = "
    SELECT COUNT(DISTINCT cal.id_alumno) AS cantidad
    FROM calificaciones cal
    JOIN credenciales c ON cal.id_alumno = c.id_credencial
    WHERE $where_clause
    AND (
        (cal.primer_parcial + cal.segundo_parcial + cal.tercer_parcial) / 3 < 6
        OR cal.primer_parcial < 6 
        OR cal.segundo_parcial < 6 
        OR cal.tercer_parcial < 6
    )";
$resultado_riesgo = mysqli_query($conexion, $query_alumnos_riesgo);
$alumnos_en_riesgo = ($fila = mysqli_fetch_assoc($resultado_riesgo)) ? $fila['cantidad'] : 0;
?>

    <?php include 'header_orientador.php'; ?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Bienvenida - Director</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        /* Estilos generales */
        body, html {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            background-color: #f8f9fa;
            overflow-x: hidden;
            width: 100%;
        }

        /* Prevenir scroll horizontal */
        * {
            box-sizing: border-box;
        }

        /* Container con máximo ancho pero centrado */
        .container {
            max-width: 1320px;
            margin-left: auto;
            margin-right: auto;
            padding-left: 15px;
            padding-right: 15px;
        }

        /* Sección de bienvenida */
        .welcome-section {
            background-color: #1a355e;
            color: white;
            padding: 80px 20px 10px; 
            text-align: center;
            width: 100%;
            overflow-x: hidden;
        }


        .welcome-section h1 {
            font-size: 2.8rem;
            font-weight: bold;
            margin-bottom: 20px;
        }

        .welcome-section p {
            font-size: 1.3rem;
            margin-bottom: 40px;
        }

        .welcome-btn {
            background-color: #ffffff;
            color: #1a355e;
            padding: 15px 30px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 1.1rem;
        }

        .welcome-btn:hover {
            background-color: #f0f0f0;
        }

        /* Sección de notas informativas */
        .stats-card {
            background-color: #ffffff;
            border-radius: 15px;
            padding: 10px;
            text-align: center;
            box-shadow: 0px 8px 20px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            transition: transform 0.3s, box-shadow 0.3s;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0px 12px 30px rgba(0, 0, 0, 0.15);
        }

        .stats-card h3 {
            font-size: 2.5rem;
            color: #1a355e;
            font-weight: bold;
            margin: 0;
        }

        .stats-card p {
            font-size: 1.2rem;
            color: #555;
            margin-top: 5px;
            margin-bottom: 0;
        }

      
        /* Nueva sección de notas adicionales */
        .notes-section {
            background-color: #f8f9fa;
            color: #1a355e;
            padding: 60px 20px;
            text-align: center;
        }
        
        .notes-section h2 {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 20px;
        }
        
        .notes-section p {
            font-size: 1.1rem;
            margin-bottom: 40px;
        }
        
        /* Estilos mejorados para las tarjetas */
        .info-card {
            background-color: white;
            border: 1px solid #ddd;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .info-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
        }
        
        .info-card h3 {
            font-size: 1.3rem;
            color: #1a355e;
            margin-bottom: 10px;
            font-weight: bold;
        }
        
        .info-card p {
            font-size: 1.1rem;
            color: #333;
        }
        
        /* Diseño responsive */
        @media (max-width: 768px) {
            .info-card {
                text-align: center;
            }
        }
        /* Nueva sección azul intermedia */
        .mid-section {
            background-color: #2b4a7e;
            color: white;
            padding: 60px 20px;
            text-align: center;
            width: 100%;
            overflow-x: hidden;
        }
        
        .mid-section h2 {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 20px;
        }
        
        .mid-section p {
            font-size: 1.1rem;
            margin-bottom: 40px;
        }
        
        /* Estilos de la sección de notas adicionales */
        .notes-section {
            background-color: #f8f9fa;
            color: #1a355e;
            padding: 60px 20px;
            text-align: center;
            width: 100%;
            overflow-x: hidden;
        }
        
        /* Mejoras en el diseño de las tarjetas */
        .info-card {
            background-color: white;
            border: 1px solid #ddd;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .info-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
        }
        
        .info-card h3 {
            font-size: 1.3rem;
            color: #1a355e;
            margin-bottom: 10px;
            font-weight: bold;
        }
        
        .info-card p {
            font-size: 1.1rem;
            color: #333;
        }
        
                /* Calendario */
        #calendar {
            width: 100%;
            margin: 10px 0 0 0;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: stretch;
        }

        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 5px;
            width: 100%;
        }

        .calendar-header h4 {
            color: #1a355e;
            margin: 0;
            font-size: 1.3rem;
            font-weight: bold;
        }

        .calendar-header button {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 8px 15px;
            cursor: pointer;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: bold;
        }

        .calendar-header button:hover {
            background-color: #0056b3;
        }

        .days-row,
        .dates-row {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 5px;
            margin: 10px 0;
            width: 100%;
        }

        .days-row span {
            padding: 12px 0;
            font-size: 1rem;
            color: #1a355e;
            font-weight: bold;
        }

        .date,
        .empty-day {
            padding: 15px;
            font-size: 1rem;
            color: #1a355e;
            text-align: center;
        }

        .date {
            background-color: #e6e8ed;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 50px;
        }

        .date:hover {
            background-color: #d0d4dc;
        }

        .date .circle-day {
            display: inline-block;
            min-width: 2.2em;
            min-height: 2.2em;
            line-height: 2.2em;
            background: rgba(255, 255, 255, 0.9);
            color: #222;
            font-weight: bold;
            border-radius: 50%;
            text-align: center;
            font-size: 1em;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(0, 0, 0, 0.1);
        }

        .date.current-date .circle-day {
            border: 3px solid #00695C;
            box-shadow: 0 0 10px #00695C;
            background: #fff !important;
            color: #00695C !important;
            font-weight: bold;
        }

        .empty-day {
            visibility: hidden;
        }

        /* Diseño responsive */
        @media (max-width: 768px) {
            .info-card {
                text-align: center;
            }
        }
        /* Estilos específicos para el número dentro del h3 */
        .big-number {
            font-size: 2rem; /* Ajusta el tamaño para que sea más grande */
            font-weight: bold;
            color: #1a355e;
            margin-left: 10px;
        }
        
        /* Estilos para la tabla dentro de stats-card */
        .stats-card table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .stats-card th {
            background-color: #007bff; /* Azul brilloso */
            color: white;
            padding: 10px;
            font-weight: bold;
        }
        
        .stats-card td {
            background-color: white;
            color: black;
            padding: 10px;
            border: 1px solid #007bff; /* Azul brilloso */
            text-align: center;
        }
        
        .stats-card tr:hover td {
            background-color: #f1f1f1;
        }
        /* Calendario */
        #calendar {
            max-width: 100%; /* Limitar el ancho al contenedor */
            margin-top: 20px;
            text-align: center;
        }
        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 10px;
        }
        .calendar-header h4 {
            color: #1a355e;
            margin: 0;
            font-size: 1rem;
        }
        .calendar-header button {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 5px 8px;
            cursor: pointer;
            border-radius: 5px;
            font-size: 0.8rem;
        }
        .calendar-header button:hover {
            background-color: #0056b3;
        }
        .days-row, .dates-row {
            display: grid;
            grid-template-columns: repeat(7, 1fr); /* 7 columnas para los días de la semana */
            gap: 2px;
            margin: 10px 0;
        }
        .days-row span {
            padding: 8px 0; /* Consistente con .date */
            font-size: 0.85rem;
            color: #1a355e;
            font-weight: bold; /* Para diferenciar de las fechas */
        }
        .date, .empty-day {
            padding: 8px;
            font-size: 0.85rem;
            color: #1a355e;
            text-align: center;
        }
        .date {
            background-color: #e6e8ed;
            border-radius: 4px;
            cursor: pointer;
        }
        .date:hover {
            background-color: #d0d4dc;
        }
        .empty-day {
            visibility: hidden;
        }
        /* Estilo para la imagen dentro de .stats-card */
        .stats-card .event-image {
            width: 100%; /* Ajusta al ancho de la tarjeta */
            height: auto; /* Mantiene la proporción de la imagen */
            border-radius: 8px; /* Esquinas redondeadas */
            margin-bottom: 0; /* Sin espacio debajo de la imagen */
        }

        /* Ajustes específicos para las tarjetas del calendario y eventos */
        .notes-section .stats-card {
            padding: 20px 10px;
        }

        /* Asegurar que el calendario ocupe todo el ancho */
        .notes-section #calendar {
            padding: 0;
            margin: 0;
        }

        /* ======================================= MEDIA QUERIES PARA RESPONSIVIDAD ======================================= */
        
        /* Tablets y pantallas medianas (hasta 992px) */
        @media (max-width: 992px) {
            .welcome-section {
                padding: 90px 20px 30px;
            }

            .welcome-section h1 {
                font-size: 2.2rem;
            }

            .welcome-section h2 {
                font-size: 1.8rem;
            }

            .welcome-section p {
                font-size: 1.1rem;
            }

            .stats-card h3 {
                font-size: 2rem;
            }

            .stats-card p,
            .stats-card a {
                font-size: 1rem;
            }

            .chart-container {
                height: 180px;
            }

            .mid-section h2 {
                font-size: 1.8rem;
            }

            .notes-section h2 {
                font-size: 1.8rem;
            }

            #calendar {
                max-width: 90%;
            }

            .calendar-header h4 {
                font-size: 0.95rem;
            }

            .days-row span,
            .date,
            .empty-day {
                font-size: 0.8rem;
                padding: 6px;
            }
        }

        /* Tablets pequeñas y móviles grandes (hasta 768px) */
        @media (max-width: 768px) {
            /* Ajustar container en móviles */
            .container {
                max-width: 100%;
                padding-left: 10px;
                padding-right: 10px;
            }

            .row {
                margin-left: -5px;
                margin-right: -5px;
            }

            [class*="col-"] {
                padding-left: 5px;
                padding-right: 5px;
            }

            .welcome-section {
                padding: 80px 15px 25px;
                margin-top: 50px;
            }

            .welcome-section h1 {
                font-size: 2rem;
            }

            .welcome-section h2 {
                font-size: 1.5rem;
            }

            .welcome-section p {
                font-size: 1rem;
            }

            .stats-card {
                padding: 20px;
                margin-bottom: 20px;
            }

            .stats-card h3 {
                font-size: 1.8rem;
            }

            .stats-card p,
            .stats-card a {
                font-size: 0.95rem;
            }

            .big-number {
                font-size: 1.5rem;
            }

            .chart-container {
                height: 160px;
            }

            .mid-section {
                padding: 40px 15px;
            }

            .mid-section h2 {
                font-size: 1.6rem;
            }

            .mid-section p {
                font-size: 1rem;
            }

            .notes-section {
                padding: 40px 15px;
            }

            .notes-section h2 {
                font-size: 1.8rem;
            }

            .info-card {
                text-align: center;
                padding: 15px;
                margin-bottom: 15px;
            }

            .info-card h3 {
                font-size: 1.2rem;
            }

            .info-card p {
                font-size: 1rem;
            }

            #calendar {
                max-width: 100%;
            }

            .calendar-header h4 {
                font-size: 0.9rem;
            }

            .calendar-header button {
                padding: 4px 6px;
                font-size: 0.75rem;
            }

            .days-row span,
            .date,
            .empty-day {
                font-size: 0.75rem;
                padding: 5px;
            }

            .date .circle-day {
                min-width: 1.5em;
                min-height: 1.5em;
                line-height: 1.5em;
                font-size: 0.75rem;
            }

            .stats-card table {
                font-size: 0.9rem;
            }

            .stats-card th,
            .stats-card td {
                padding: 8px;
            }
        }

        /* Móviles pequeños (hasta 576px) */
        @media (max-width: 576px) {
            /* Ajustar aún más en móviles pequeños */
            .container {
                padding-left: 8px;
                padding-right: 8px;
            }

            .row {
                margin-left: -4px;
                margin-right: -4px;
            }

            [class*="col-"] {
                padding-left: 4px;
                padding-right: 4px;
            }

            .welcome-section {
                padding: 70px 10px 20px;
                margin-top: 40px;
            }

            .welcome-section h1 {
                font-size: 1.8rem;
                margin-bottom: 10px;
            }

            .welcome-section h2 {
                font-size: 1.3rem;
                margin-bottom: 15px;
            }

            .welcome-section p {
                font-size: 0.95rem;
                margin-bottom: 20px;
            }

            .stats-card {
                padding: 15px;
                margin-bottom: 15px;
            }

            .stats-card h3 {
                font-size: 1.5rem;
            }

            .stats-card p,
            .stats-card a {
                font-size: 0.9rem;
            }

            .big-number {
                font-size: 1.3rem;
            }

            .chart-container {
                height: 140px;
            }

            .mid-section {
                padding: 30px 10px;
            }

            .mid-section h2 {
                font-size: 1.4rem;
            }

            .mid-section p {
                font-size: 0.9rem;
            }

            .notes-section {
                padding: 30px 10px;
            }

            .notes-section h2 {
                font-size: 1.5rem;
            }

            .info-card {
                padding: 12px;
                margin-bottom: 12px;
            }

            .info-card h3 {
                font-size: 1.1rem;
            }

            .info-card p {
                font-size: 0.9rem;
            }

            #calendar {
                max-width: 100%;
                margin: 15px auto 0;
            }

            .calendar-header {
                padding: 0 5px;
            }

            .calendar-header h4 {
                font-size: 0.85rem;
            }

            .calendar-header button {
                padding: 3px 5px;
                font-size: 0.7rem;
            }

            .days-row,
            .dates-row {
                gap: 1px;
                margin: 5px 0;
            }

            .days-row span,
            .date,
            .empty-day {
                font-size: 0.7rem;
                padding: 4px;
            }

            .date .circle-day {
                min-width: 1.3em;
                min-height: 1.3em;
                line-height: 1.3em;
                font-size: 0.7rem;
            }

            .stats-card table {
                font-size: 0.8rem;
            }

            .stats-card th,
            .stats-card td {
                padding: 6px;
                font-size: 0.8rem;
            }

            .stats-card .event-image {
                margin-bottom: 10px;
            }
        }

        /* Móviles muy pequeños (hasta 400px) */
        @media (max-width: 400px) {
            .welcome-section h1 {
                font-size: 1.5rem;
            }

            .welcome-section h2 {
                font-size: 1.1rem;
            }

            .welcome-section p {
                font-size: 0.85rem;
            }

            .stats-card h3 {
                font-size: 1.3rem;
            }

            .stats-card p,
            .stats-card a {
                font-size: 0.85rem;
            }

            .big-number {
                font-size: 1.1rem;
            }

            .chart-container {
                height: 120px;
            }

            .calendar-header h4 {
                font-size: 0.75rem;
            }

            .days-row span,
            .date,
            .empty-day {
                font-size: 0.65rem;
                padding: 3px;
            }
        }

    </style>
</head>
<body>


    <!-- Sección de bienvenida -->
    <section class="welcome-section">
        <h1>¡Bienvenid@ <?php echo htmlspecialchars($nombre_credencial); ?>!</h1>
        <h2><?php echo htmlspecialchars($nombre_escuela); ?> </h2>
        <p>Accede a un resumen rápido de tus equipos y de la institución. Estamos aquí para apoyar tu gestión y facilitar la toma de decisiones estratégicas.</p>
        
    </section>

    <!-- Sección de notas informativas -->
    <section class="notes-section">
        <h2>Panel Rápido</h2>
        <div class="container">
            <div class="row">
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="stats-card" style="min-height: 150px;">
                        <h3><?php echo $alumnos_registrados; ?></h3>
                        <a href="tabla_alumnos.php" style="color: #505050; font-size: 18px;">Alumnos Registrados</a>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="stats-card" style="min-height: 150px;">
                        <h3><?php echo $incidencias_activas; ?></h3>
                        <a href="lista_incidencias.php" style="color: #505050; font-size: 18px;">Incidencias Activas</a>
                    </div>
                </div>

                <?php
                // Crear subcontenedores para cada grupo asignado
                if (!empty($grados_grupos_orientador)) {
                    $grupos_array = explode(',', str_replace(' ', '', $grados_grupos_orientador));
                    $total_grupos = count($grupos_array);
                    
                    // Calcular el tamaño de columna según cantidad de grupos
                    if ($total_grupos == 1) {
                        $col_size = 'col-lg-6 col-md-12'; // Un solo grupo ocupa el espacio restante
                    } elseif ($total_grupos == 2) {
                        $col_size = 'col-lg-3 col-md-6'; // Dos grupos
                    } else {
                        $col_size = 'col-lg-3 col-md-6'; // Tres o más grupos
                    }
                    
                    foreach ($grupos_array as $grupo) {
                        if (preg_match('/^(Primero|Segundo|Tercero|Cuarto|Quinto|Sexto)([A-J])$/i', $grupo, $matches)) {
                            $grado = ucfirst(strtolower($matches[1]));
                            $grupo_letra = strtoupper($matches[2]);
                            
                            // Contar alumnos en este grupo específico
                            $query_grupo = "SELECT COUNT(*) AS total FROM credenciales 
                                           WHERE id_escuela = '$id_escuela_orientador' 
                                           AND nivel_usuario = 7 
                                           AND status = 'activo'
                                           AND turno_credencial = '$turno_orientador'
                                           AND grado_credencial = '$grado' 
                                           AND grupo_credencial = '$grupo_letra'";
                            $resultado_grupo = mysqli_query($conexion, $query_grupo);
                            $total_alumnos = 0;
                            
                            if ($fila = mysqli_fetch_assoc($resultado_grupo)) {
                                $total_alumnos = $fila['total'];
                            }
                            
                            // Crear el subcontenedor para este grupo
                            echo '<div class="' . $col_size . ' mb-4">';
                            echo '<div class="stats-card" style="min-height: 150px;">';
                            echo '<h3>' . htmlspecialchars($grado) . ' ' . htmlspecialchars($grupo_letra) . '</h3>';
                            echo '<p style="color: #505050; font-size: 18px; margin: 5px 0;">' . $total_alumnos . ' alumnos</p>';
                            echo '<a href="F_tabla_asistencia.php?grado=' . urlencode($grado) . '&grupo=' . urlencode($grupo_letra) . '" class="btn btn-sm btn-primary mt-2">';
                            echo '<i class="bi bi-calendar-check"></i> Ver Asistencias';
                            echo '</a>';
                            echo '</div>';
                            echo '</div>';
                        }
                    }
                }
                ?>
            </div>
        </div>
    </section>
<!-- Sección de notas informativas -->
<section class="notes-section" style="background-color: #bababa;">
    <h2>Panel Rápido de Asistencia <?php echo $fecha_hoy1; ?></h2>

    <div class="container">
        <div class="row">
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="stats-card" style="min-height: 150px;">
                    <h3><?php echo $alumnos_registrados; ?></h3>
                    <a href="registro_asistencia_diario.php" style="color: #505050; font-size: 18px;">Asistencias Esperadas</a>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="stats-card" style="min-height: 150px;">
                    <h3><?php echo $asistencias_hoy; ?></h3>
                    <a href="registro_inasistencia.php" style="color: #505050; font-size: 18px;">Asistencias Registradas Hoy</a>
                </div>
            </div>

            <!-- Contenedor único -->
            <div class="col-lg-6 col-md-12 mb-4">
                <div class="stats-card" onclick="mostrarGrafico()" style="cursor: pointer; min-height: 150px;">
                    <h3><?php echo number_format($porcentaje_asistencia, 2) . "%"; ?> / <?php echo number_format($porcentaje_inasistencia, 2) . "%"; ?></h3>
                    <a style="color: #505050; font-size: 18px;">Asistencias / Inasistencias</a>
                </div>
            </div>
        </div>

        <!-- Tarjetas por grupo -->
        <h3 style="text-align: center; color: #1a355e; margin-top: 30px; margin-bottom: 20px;">Asistencias por Grupo</h3>
        <div class="row">
            <?php
            // Crear tarjetas de asistencia para cada grupo asignado
            if (!empty($grados_grupos_orientador)) {
                $grupos_array = explode(',', str_replace(' ', '', $grados_grupos_orientador));
                $total_grupos = count($grupos_array);
                
                // Determinar el tamaño de columna según la cantidad de grupos
                if ($total_grupos == 1) {
                    $col_size = 'col-12';
                } elseif ($total_grupos == 2) {
                    $col_size = 'col-lg-6 col-md-6';
                } elseif ($total_grupos == 3) {
                    $col_size = 'col-lg-4 col-md-6';
                } else {
                    $col_size = 'col-lg-3 col-md-6';
                }
                
                foreach ($grupos_array as $grupo) {
                    if (preg_match('/^(Primero|Segundo|Tercero|Cuarto|Quinto|Sexto)([A-J])$/i', $grupo, $matches)) {
                        $grado = ucfirst(strtolower($matches[1]));
                        $grupo_letra = strtoupper($matches[2]);
                        
                        // Contar alumnos esperados en este grupo
                        $query_esperados_grupo = "SELECT COUNT(*) AS total FROM credenciales 
                                                  WHERE id_escuela = '$id_escuela_orientador' 
                                                  AND nivel_usuario = 7 
                                                  AND status = 'activo'
                                                  AND turno_credencial = '$turno_orientador'
                                                  AND grado_credencial = '$grado' 
                                                  AND grupo_credencial = '$grupo_letra'";
                        $resultado_esperados = mysqli_query($conexion, $query_esperados_grupo);
                        $esperados_grupo = 0;
                        
                        if ($fila = mysqli_fetch_assoc($resultado_esperados)) {
                            $esperados_grupo = $fila['total'];
                        }
                        
                        // Contar asistencias registradas hoy para este grupo
                        $query_asistencias_grupo = "SELECT COUNT(DISTINCT esa.id_credencial) AS total 
                                                   FROM entrada_salida_alumno esa
                                                   JOIN credenciales c ON esa.id_credencial = c.id_credencial
                                                   WHERE c.id_escuela = '$id_escuela_orientador'
                                                   AND c.nivel_usuario = 7
                                                   AND c.status = 'activo'
                                                   AND c.turno_credencial = '$turno_orientador'
                                                   AND c.grado_credencial = '$grado'
                                                   AND c.grupo_credencial = '$grupo_letra'
                                                   AND esa.fecha = '$fecha_hoy'
                                                   AND esa.hora_entrada IS NOT NULL";
                        $resultado_asistencias_grupo = mysqli_query($conexion, $query_asistencias_grupo);
                        $asistencias_grupo = 0;
                        
                        if ($fila = mysqli_fetch_assoc($resultado_asistencias_grupo)) {
                            $asistencias_grupo = $fila['total'];
                        }
                        
                        // Calcular inasistencias
                        $inasistencias_grupo = $esperados_grupo - $asistencias_grupo;
                        
                        // Calcular porcentajes
                        $porcentaje_asist_grupo = ($esperados_grupo > 0) ? round(($asistencias_grupo * 100) / $esperados_grupo, 1) : 0;
                        $porcentaje_inasist_grupo = ($esperados_grupo > 0) ? round(($inasistencias_grupo * 100) / $esperados_grupo, 1) : 0;
                        
                        // Crear la tarjeta para este grupo
                        echo '<div class="' . $col_size . ' mb-4">';
                        echo '<div class="stats-card">';
                        echo '<h3 style="color: #1a355e; margin-bottom: 5px; font-size: 1.8rem;">' . htmlspecialchars($grado) . ' ' . htmlspecialchars($grupo_letra) . '</h3>';
                        echo '<div style="display: flex; justify-content: space-around; margin: 5px 0;">';
                        echo '<div style="text-align: center;">';
                        echo '<p style="font-size: 1.8rem; color: #4CAF50; font-weight: bold; margin: 0;">' . $asistencias_grupo . '</p>';
                        echo '<p style="font-size: 0.85rem; color: #666; margin: 0;">Presentes</p>';
                        echo '<p style="font-size: 0.75rem; color: #888; margin: 0;">(' . $porcentaje_asist_grupo . '%)</p>';
                        echo '</div>';
                        echo '<div style="text-align: center;">';
                        echo '<p style="font-size: 1.8rem; color: #1a355e; font-weight: bold; margin: 0;">' . $esperados_grupo . '</p>';
                        echo '<p style="font-size: 0.85rem; color: #666; margin: 0;">Alumnos esperados</p>';
                        echo '</div>';
                        echo '<div style="text-align: center;">';
                        echo '<p style="font-size: 1.5rem; color: #1a355e; font-weight: bold; margin: 0;">' . number_format($porcentaje_asist_grupo, 1) . ' % / ' . number_format($porcentaje_inasist_grupo, 1) . '%</p>';
                        echo '<p style="font-size: 0.85rem; color: #666; margin: 0;">Asistencias / Inasistencias</p>';
                        echo '</div>';
                        echo '</div>';
                        echo '</div>';
                        echo '</div>';
                    }
                }
            }
            ?>
        </div>
    </div>
</section>

    <!-- Nueva sección de Situación Académica -->
    <section class="mid-section">
        <h1>Situación Académica</h1>
        <p>Seguimiento del aprovechamiento escolar y alumnos que requieren atención:</p>
        <div class="container">
            <div class="row">
                <!-- Tarjeta de Alumnos en Riesgo Académico -->
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="stats-card" style="min-height: 150px; max-height: 150px; <?php echo ($alumnos_reprobatorios > 0) ? 'border: 3px solid #FF5252;' : ''; ?>">
                        <h3 style="<?php echo ($alumnos_reprobatorios > 0) ? 'color: #FF5252;' : 'color: #1a355e;'; ?>">
                            <?php echo $alumnos_reprobatorios; ?>
                        </h3>
                        <a href="alumnos_riesgo_academico.php" style="color: #505050; font-size: 18px; line-height: 1.3; text-decoration: none;">
                            Alumnos en Riesgo Académico
                        </a>
                        <?php if ($alumnos_reprobatorios > 0): ?>
                            <p style="color: #FF5252; font-size: 12px; margin-top: 5px; margin-bottom: 0;">
                                <i class="bi bi-exclamation-triangle-fill"></i> ¡Requiere atención!
                            </p>
                        <?php endif; ?>
                    </div>
                </div>

                <?php
                // Crear subcontenedores de aprovechamiento para cada grupo asignado
                if (!empty($grados_grupos_orientador)) {
                    $grupos_array = explode(',', str_replace(' ', '', $grados_grupos_orientador));
                    
                    foreach ($grupos_array as $grupo) {
                        if (preg_match('/^(Primero|Segundo|Tercero|Cuarto|Quinto|Sexto)([A-J])$/i', $grupo, $matches)) {
                            $grado = ucfirst(strtolower($matches[1]));
                            $grupo_letra = strtoupper($matches[2]);
                            
                            // Calcular el promedio del grupo
                            $query_promedio_grupo = "
                                SELECT 
                                    AVG((cal.primer_parcial + cal.segundo_parcial + cal.tercer_parcial) / 3) AS promedio_grupo
                                FROM calificaciones cal
                                JOIN credenciales c ON cal.id_alumno = c.id_credencial
                                WHERE c.id_escuela = '$id_escuela_orientador'
                                AND c.nivel_usuario = 7
                                AND c.status = 'activo'
                                AND c.turno_credencial = '$turno_orientador'
                                AND c.grado_credencial = '$grado'
                                AND c.grupo_credencial = '$grupo_letra'
                                AND cal.primer_parcial IS NOT NULL 
                                AND cal.segundo_parcial IS NOT NULL 
                                AND cal.tercer_parcial IS NOT NULL";
                            
                            $resultado_promedio_grupo = mysqli_query($conexion, $query_promedio_grupo);
                            $promedio_grupo = 0;
                            
                            if ($fila = mysqli_fetch_assoc($resultado_promedio_grupo)) {
                                $promedio_grupo = $fila['promedio_grupo'] ? round($fila['promedio_grupo'], 2) : 0;
                            }
                            
                            // Crear la tarjeta para este grupo
                            echo '<div class="col-lg-3 col-md-6 mb-4">';
                            echo '    <div class="stats-card" style="min-height: 150px; max-height: 150px;">';
                            echo '        <h3>' . number_format($promedio_grupo, 2) . '</h3>';
                            echo '        <a href="generar_pdf_individual_grupo_beta.php?grado=' . urlencode($grado) . '&grupo=' . urlencode($grupo_letra) . '" style="color: #505050; font-size: 18px; text-decoration: none;" target="_blank">';
                            echo '            Aprovechamiento<br>' . $grado . ' ' . $grupo_letra;
                            echo '        </a>';
                            echo '    </div>';
                            echo '</div>';
                        }
                    }
                }
                ?>
            </div>
            
            <!-- Gráfico de Promedios por Parcial -->
            <?php if (!empty($promedios_por_grupo)): ?>
            <div class="row mt-4">
                <div class="col-12">
                    <div class="stats-card" style="min-height: 400px; max-height: 400px; padding: 20px;">
                        <a href="grafica_promedio_grupo_beta.php" style="text-decoration: none; color: #1a355e;">
                            <h3 style="font-size: 1.5rem; margin-bottom: 20px; cursor: pointer; transition: all 0.3s;" 
                                onmouseover="this.querySelector('i').style.transform='translateX(5px)'" 
                                onmouseout="this.querySelector('i').style.transform='translateX(0)'">
                                Evolución de Promedios por Grupo <i class="bi bi-box-arrow-up-right" style="font-size: 1rem; transition: transform 0.3s;"></i>
                            </h3>
                        </a>
                        <div style="position: relative; height: 300px;">
                            <canvas id="graficoPromediosParciales"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Nueva sección de notas adicionales -->
    <section class="notes-section">
        <h2>Notas Adicionales</h2>
        <p>Indicadores de rendimiento y objetivos:</p>
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-6 col-md-6 mb-4">
                    <div class="stats-card">
                        <h3>Calendario</h3>
                        <p>de eventos</p>
                        <div id="calendar">
                            <div class="calendar-header">
                                <button id="prevMonth">«</button>
                                <h4 id="monthYear"></h4>
                                <button id="nextMonth">»</button>
                            </div>
                            <div class="days-row">
                                <span>Dom</span><span>Lun</span><span>Mar</span><span>Mié</span><span>Jue</span><span>Vie</span><span>Sáb</span>
                            </div>
                            <div class="dates-row" id="dates"></div>
                        </div>
                        <a href="calendario_completo.php" class="btn btn-sm btn-primary" style="margin-top: 10px;">Calendario completo</a>
                    </div>               
                </div>

                <div class="col-lg-6 col-md-6 mb-4">
                    <div class="stats-card">
                        <h3>Eventos</h3>
                        <p style="margin-bottom: 10px;">Nuevos eventos</p>
                        <img src="nuevoseventos.jpg" alt="Nuevos eventos" class="event-image">
                    </div> 
                </div>
            </div>
        </div>
    </section>
<!-- Modal -->
<div class="modal fade" id="graficoModal" tabindex="-1" aria-labelledby="graficoModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="graficoModalLabel">Distribución de Asistencia</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <canvas id="graficoPastel"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Modal para gráfica de grupo específico -->
<div class="modal fade" id="graficoGrupoModal" tabindex="-1" aria-labelledby="graficoGrupoModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="graficoGrupoModalLabel">Asistencia del Grupo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <canvas id="graficoGrupo"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Script de Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>

function mostrarGrafico() {
    let asistencia = <?php echo $asistencias_hoy; ?>;
    let inasistencia = <?php echo $alumnos_registrados - $asistencias_hoy; ?>;

    let ctx = document.getElementById('graficoPastel').getContext('2d');

    // Destruir gráfico anterior si existe
    if (window.miGrafico) {
        window.miGrafico.destroy();
    }

    // Crear nuevo gráfico
    window.miGrafico = new Chart(ctx, {
        type: 'pie',
        data: {
            labels: ['Asistencia (' + asistencia + ')', 'Inasistencia (' + inasistencia + ')'],
            datasets: [{
                data: [asistencia, inasistencia],
                backgroundColor: ['#4CAF50', '#FF5252']
            }]
        }
    });

    // Mostrar modal
    var modal = new bootstrap.Modal(document.getElementById('graficoModal'));
    modal.show();
}

// Función para mostrar gráfica de grupo específico
function verDetalleGrupo(grado, grupo, asistencias, inasistencias) {
    let ctx = document.getElementById('graficoGrupo').getContext('2d');

    // Destruir gráfico anterior si existe
    if (window.miGraficoGrupo) {
        window.miGraficoGrupo.destroy();
    }

    // Crear nuevo gráfico
    window.miGraficoGrupo = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Presentes (' + asistencias + ')', 'Ausentes (' + inasistencias + ')'],
            datasets: [{
                data: [asistencias, inasistencias],
                backgroundColor: ['#4CAF50', '#FF5252'],
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom',
                },
                title: {
                    display: true,
                    text: grado + ' ' + grupo,
                    font: {
                        size: 18,
                        weight: 'bold'
                    }
                }
            }
        }
    });

    // Actualizar título del modal
    document.getElementById('graficoGrupoModalLabel').textContent = 'Asistencia - ' + grado + ' ' + grupo;

    // Mostrar modal
    var modal = new bootstrap.Modal(document.getElementById('graficoGrupoModal'));
    modal.show();
}

// Gráfico de Promedios por Parcial
<?php if (!empty($promedios_por_grupo)): ?>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('graficoPromediosParciales');
    if (ctx) {
        // Datos generados desde PHP
        const datosGrupos = <?php echo json_encode($promedios_por_grupo); ?>;
        
        // Crear datasets para gráfica de barras horizontales
        const datasets = [
            {
                label: 'Parcial 1',
                data: datosGrupos.map(g => parseFloat(g.parcial1)),
                backgroundColor: 'rgba(255, 99, 132, 0.7)',
                borderColor: 'rgba(255, 99, 132, 1)',
                borderWidth: 2
            },
            {
                label: 'Parcial 2',
                data: datosGrupos.map(g => parseFloat(g.parcial2)),
                backgroundColor: 'rgba(54, 162, 235, 0.7)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 2
            },
            {
                label: 'Parcial 3',
                data: datosGrupos.map(g => parseFloat(g.parcial3)),
                backgroundColor: 'rgba(75, 192, 192, 0.7)',
                borderColor: 'rgba(75, 192, 192, 1)',
                borderWidth: 2
            }
        ];
        
        const graficoPromedios = new Chart(ctx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: datosGrupos.map(g => g.grupo),
                datasets: datasets
            },
            options: {
                indexAxis: 'y', // Esto hace que las barras sean horizontales
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: {
                            padding: 15,
                            font: {
                                size: 12
                            },
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    },
                    tooltip: {
                        enabled: true,
                        mode: 'nearest',
                        intersect: true,
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: '#fff',
                        borderWidth: 1,
                        padding: 12,
                        displayColors: true,
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + context.parsed.x.toFixed(2);
                            }
                        }
                    },
                    title: {
                        display: false
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        min: 5,
                        max: 10,
                        ticks: {
                            stepSize: 1,
                            font: {
                                size: 12
                            },
                            color: '#666'
                        },
                        grid: {
                            display: true,
                            color: 'rgba(0, 0, 0, 0.1)'
                        },
                        title: {
                            display: true,
                            text: 'Promedio',
                            font: {
                                size: 13,
                                weight: 'bold'
                            },
                            color: '#1a355e'
                        }
                    },
                    y: {
                        ticks: {
                            font: {
                                size: 12,
                                weight: 'bold'
                            },
                            color: '#1a355e'
                        },
                        grid: {
                            display: false
                        }
                    }
                },
                interaction: {
                    mode: 'nearest',
                    intersect: true
                }
            }
        });
    }
});
<?php endif; ?>
</script>
    <?php include 'footer_orientador.php'; ?>
    <script>
        //calendario con eventos (solo lectura)
        let eventosCalendario = {};
        let currentYear, currentMonth;
        const today = new Date();
        
        function cargarEventosCalendario() {
            fetch('../api_eventos_calendario.php')
                .then(res => res.json())
                .then(data => {
                    eventosCalendario = {};
                    data.forEach(ev => {
                        const mes = parseInt(ev.mes);
                        const dia = parseInt(ev.dia);
                        if (!eventosCalendario[mes]) eventosCalendario[mes] = {};
                        if (!eventosCalendario[mes][dia]) eventosCalendario[mes][dia] = [];
                        eventosCalendario[mes][dia].push({ titulo: ev.titulo, color: ev.color });
                    });
                    updateCalendar();
                });
        }

        function updateCalendar() {
            generateCalendar(currentYear, currentMonth);
        }


        function generateCalendar(year, month) {
            const monthYear = document.getElementById("monthYear");
            const dates = document.getElementById("dates");
            dates.innerHTML = "";  // Limpiar el contenido anterior

            const monthNames = ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"];
            const firstDay = new Date(year, month, 1).getDay();
            const daysInMonth = new Date(year, month + 1, 0).getDate();

            // Actualizar el encabezado con el mes y año actuales
            monthYear.textContent = `${monthNames[month]} ${year}`;

            // Crear los espacios en blanco al inicio del calendario
            for (let i = 0; i < firstDay; i++) {
                const emptyDay = document.createElement("span");
                emptyDay.classList.add("empty-day");
                dates.appendChild(emptyDay);
            }

            // Crear los días del mes
            for (let day = 1; day <= daysInMonth; day++) {
                const dateElement = document.createElement("span");
                dateElement.classList.add("date");
                const circle = document.createElement("span");
                circle.className = "circle-day";
                circle.textContent = day;
                dateElement.appendChild(circle);
                
                // Marcar el día actual
                if (year === today.getFullYear() && month === today.getMonth() && day === today.getDate()) {
                    dateElement.classList.add("current-date");
                }
                
                // Si hay eventos en el mes actual y día
                if (typeof eventosCalendario !== 'undefined' && eventosCalendario[month] && eventosCalendario[month][day]) {
                    const eventos = eventosCalendario[month][day];
                    // Si es un solo evento, usar color directo
                    if (eventos.length === 1) {
                        dateElement.style.backgroundColor = eventos[0].color;
                        // Colores claros: #E8E8E8, #00FFFF, #FBC02D, #CC00FF, #C5D9F0, #EC407A
                        const coloresClaros = ["#E8E8E8", "#00FFFF", "#FBC02D", "#CC00FF", "#C5D9F0", "#EC407A"];
                        let colorEvento = eventos[0].color.toUpperCase();
                        if (coloresClaros.includes(colorEvento)) {
                            dateElement.style.color = "#000"; // Negro para fondo claro
                        } else {
                            dateElement.style.color = "#fff"; // Blanco para fondo oscuro
                        }
                    } else {
                        // Si hay varios eventos, usar linear-gradient para mostrar hasta 4 colores
                        const colores = eventos.slice(0, 4).map(ev => ev.color);
                        let grad = '';
                        if (colores.length === 2) {
                            grad = `linear-gradient(90deg, ${colores[0]} 0%, ${colores[0]} 50%, ${colores[1]} 50%, ${colores[1]} 100%)`;
                        } else if (colores.length === 3) {
                            grad = `linear-gradient(90deg, ${colores[0]} 0%, ${colores[0]} 33%, ${colores[1]} 33%, ${colores[1]} 66%, ${colores[2]} 66%, ${colores[2]} 100%)`;
                        } else if (colores.length === 4) {
                            grad = `linear-gradient(90deg, ${colores[0]} 0%, ${colores[0]} 25%, ${colores[1]} 25%, ${colores[1]} 50%, ${colores[2]} 50%, ${colores[2]} 75%, ${colores[3]} 75%, ${colores[3]} 100%)`;
                        }
                        dateElement.style.background = grad;
                        // Si alguno de los colores es claro, usar negro, si todos son oscuros, usar blanco
                        const coloresClaros = ["#E8E8E8", "#00FFFF", "#FBC02D", "#CC00FF", "#C5D9F0", "#EC407A"];
                        let algunClaro = colores.some(c => coloresClaros.includes(c.toUpperCase()));
                        dateElement.style.color = algunClaro ? "#000" : "#fff";
                    }
                    dateElement.style.cursor = "pointer";
                    // Tooltip: mostrar todos los títulos
                    dateElement.addEventListener("mouseenter", function () {
                        showTooltip(dateElement, eventos);
                    });
                    dateElement.addEventListener("mouseleave", function () {
                        hideTooltip();
                    });
                }
                dates.appendChild(dateElement);
            }
        }

        function showTooltip(element, eventos) {
            let tooltip = document.getElementById("calendar-tooltip");
            if (!tooltip) {
                tooltip = document.createElement("div");
                tooltip.id = "calendar-tooltip";
                tooltip.style.position = "absolute";
                tooltip.style.background = "#fff";
                tooltip.style.border = "1px solid #333";
                tooltip.style.borderRadius = "8px";
                tooltip.style.padding = "12px";
                tooltip.style.boxShadow = "0 2px 8px rgba(0,0,0,0.15)";
                tooltip.style.zIndex = "1000";
                tooltip.style.minWidth = "120px";
                document.body.appendChild(tooltip);
            }
            // Mostrar todos los títulos si hay varios eventos
            if (Array.isArray(eventos)) {
                tooltip.innerHTML = eventos.map(ev => `<strong>${ev.titulo}</strong>`).join('<br>');
            } else {
                tooltip.innerHTML = `<strong>${eventos.titulo}</strong>`;
            }
            const rect = element.getBoundingClientRect();
            tooltip.style.top = (rect.bottom + window.scrollY + 8) + "px";
            tooltip.style.left = (rect.left + window.scrollX) + "px";
            tooltip.style.display = "block";
        }

        function hideTooltip() {
            const tooltip = document.getElementById("calendar-tooltip");
            if (tooltip) tooltip.style.display = "none";
        }

        document.addEventListener("DOMContentLoaded", () => {
            currentYear = today.getFullYear();
            currentMonth = today.getMonth();
            document.getElementById("prevMonth").addEventListener("click", () => {
                currentMonth--;
                if (currentMonth < 0) {
                    currentMonth = 11;
                    currentYear--;
                }
                updateCalendar();
            });

            document.getElementById("nextMonth").addEventListener("click", () => {
                currentMonth++;
                if (currentMonth > 11) {
                    currentMonth = 0;
                    currentYear++;
                }
                updateCalendar();
            });

            cargarEventosCalendario();
        });
    </script>

<!-- Modal de Materias Críticas -->
<div class="modal fade" id="materiasCriticasModal" tabindex="-1" aria-labelledby="materiasCriticasLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background-color: #FF9800; color: white;">
                <h5 class="modal-title" id="materiasCriticasLabel">
                    <i class="bi bi-bar-chart-fill"></i> Materias con Más Reprobados
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Materia</th>
                            <th class="text-center">Alumnos Reprobados</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($materias_criticas as $materia): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($materia['nombre_materia']); ?></td>
                            <td class="text-center">
                                <span class="badge bg-danger"><?php echo $materia['total_reprobados']; ?></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script>
function mostrarMateriasCriticas() {
    var modal = new bootstrap.Modal(document.getElementById('materiasCriticasModal'));
    modal.show();
}
</script>

</body>
</html>