<?php
// verificar_estado_grupo.php
// Endpoint AJAX: Analiza el grupo y devuelve conteos de alumnos listos/pendientes
// Responde SIEMPRE con JSON — no genera HTML ni redirige.

session_start();
header('Content-Type: application/json; charset=utf-8');

// Seguridad: solo usuarios autenticados
if (!isset($_SESSION['id_credencial'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

include '../funciones/conexQRConejo.php';
$secretKey = 'your-secret-key';

// ── Función de verificación de boleta completa (misma lógica que generar_pdf_individual.php) ──
function boletaEstaCompleta(array $materias, array $calificaciones): bool {
    $totalMaterias = count($materias);
    if ($totalMaterias === 0) return false;

    $materiasCompletas = 0;
    foreach ($materias as $mat) {
        $id = (int)$mat['id_materia'];
        if (!isset($calificaciones[$id])) continue;

        $cal = $calificaciones[$id];
        if (
            is_numeric($cal['primer_parcial'])  &&
            is_numeric($cal['segundo_parcial']) &&
            is_numeric($cal['tercer_parcial'])
        ) {
            $materiasCompletas++;
        }
    }
    return ($materiasCompletas === $totalMaterias);
}

// ── Parámetros ──
$grado = trim($_GET['grado'] ?? '');
$grupo = trim($_GET['grupo'] ?? '');
$turno = trim($_GET['turno'] ?? '');

if (!$grado || !$grupo || !$turno) {
    http_response_code(400);
    echo json_encode(['error' => 'Parámetros incompletos (grado, grupo, turno requeridos)']);
    exit;
}

// ── Obtener id_escuela del usuario activo ──
$id_usuario = (int)$_SESSION['id_credencial'];
$stmt = mysqli_prepare($conexion, "SELECT id_escuela FROM credenciales WHERE id_credencial = ?");
mysqli_stmt_bind_param($stmt, "i", $id_usuario);
mysqli_stmt_execute($stmt);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$row) {
    http_response_code(500);
    echo json_encode(['error' => 'No se pudo determinar la escuela del usuario']);
    exit;
}
$id_escuela = (int)$row['id_escuela'];

// ── Obtener alumnos del grupo ──
$stmt = mysqli_prepare($conexion, "
    SELECT id_credencial, nombre_credencial, apellidos_credencial,
           grado_credencial, grupo_credencial, turno_credencial, id_escuela
    FROM credenciales
    WHERE grado_credencial  = ?
      AND grupo_credencial  = ?
      AND turno_credencial  = ?
      AND id_escuela        = ?
      AND nivel_usuario     = 7
    ORDER BY nombre_credencial ASC
");
mysqli_stmt_bind_param($stmt, "sssi", $grado, $grupo, $turno, $id_escuela);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$alumnos = [];
while ($row = mysqli_fetch_assoc($result)) {
    $alumnos[] = $row;
}

$total = count($alumnos);

if ($total === 0) {
    echo json_encode([
        'total'     => 0,
        'listos'    => 0,
        'pendientes'=> 0,
        'detalle'   => []
    ]);
    exit;
}

// ── Obtener materias del grupo (una consulta; comparten grado/grupo/turno/escuela) ──
$stmtMat = mysqli_prepare($conexion, "
    SELECT m.id_materia, m.nombre_materia
    FROM asignacion_materias am
    JOIN materias m ON am.id_materia = m.id_materia
    WHERE am.grado_credencial = ?
      AND am.grupo_credencial = ?
      AND am.turno_credencial = ?
      AND am.id_escuela       = ?
      AND am.estado           = 1
    ORDER BY m.N_orden_materia ASC
");
mysqli_stmt_bind_param($stmtMat, "sssi", $grado, $grupo, $turno, $id_escuela);
mysqli_stmt_execute($stmtMat);
$resMat = mysqli_stmt_get_result($stmtMat);
$materias = [];
while ($row = mysqli_fetch_assoc($resMat)) {
    $materias[] = $row;
}

// ── Analizar cada alumno ──
$listos     = 0;
$pendientes = 0;
$detalle    = []; // array opcional para el modal si se quiere expandir

foreach ($alumnos as $alum) {
    $id_alumno = (int)$alum['id_credencial'];

    // Calificaciones del alumno
    $stmtCal = mysqli_prepare($conexion, "
        SELECT id_materia, primer_parcial, segundo_parcial, tercer_parcial
        FROM calificaciones
        WHERE id_alumno = ?
    ");
    mysqli_stmt_bind_param($stmtCal, "i", $id_alumno);
    mysqli_stmt_execute($stmtCal);
    $resCal = mysqli_stmt_get_result($stmtCal);

    $calificaciones = [];
    while ($row = mysqli_fetch_assoc($resCal)) {
        $calificaciones[(int)$row['id_materia']] = $row;
    }

    $completa = boletaEstaCompleta($materias, $calificaciones);

    if ($completa) {
        $listos++;
        $estado = 'listo';
    } else {
        $pendientes++;
        $estado = 'pendiente';
    }

    // Nombre limpio para el detalle (sin desencriptar apellidos — solo nombre para lista rápida)
    $detalle[] = [
        'id'     => $id_alumno,
        'nombre' => htmlspecialchars($alum['nombre_credencial']),
        'estado' => $estado
    ];
}

mysqli_close($conexion);

echo json_encode([
    'total'      => $total,
    'listos'     => $listos,
    'pendientes' => $pendientes,
    'detalle'    => $detalle   // Útil si en el futuro quieres listar nombres en el modal
]);
exit;
?>
