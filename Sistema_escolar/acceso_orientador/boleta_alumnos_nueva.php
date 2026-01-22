<?php
// boleta_alumnos_nueva.php — Solo lógica (sincronizada con info_grupo.php)
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
if (!isset($_SESSION['id_credencial'])) {
    header("Location: ../login.php");
    exit();
}

include '../funciones/conexQRConejo.php';

$id_usuario = $_SESSION['id_credencial'];
$secretKey = 'your-secret-key';

// --- 1. Obtener escuela ---
$stmt = mysqli_prepare($conexion, "SELECT id_escuela FROM credenciales WHERE id_credencial = ?");
mysqli_stmt_bind_param($stmt, "i", $id_usuario);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$id_escuela = mysqli_fetch_assoc($result)['id_escuela'];

// --- 2. Nombre de la escuela ---
$stmt = mysqli_prepare($conexion, "SELECT nombre_escuela FROM escuelas WHERE id_escuela = ?");
mysqli_stmt_bind_param($stmt, "i", $id_escuela);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$nombre_escuela = mysqli_fetch_assoc($result)['nombre_escuela'];

// --- 3. Parámetros ---
$grado = $_GET['grado'] ?? '';
$grupo = $_GET['grupo'] ?? '';
$turno = $_GET['turno'] ?? '';

if (!$grado || !$grupo || !$turno) die("Faltan parámetros.");

// --- 4. Función desencriptar ---
function decryptData($data, $key) {
    if (empty($data)) return '';
    $parts = explode('::', base64_decode($data), 2);
    if (count($parts) !== 2) return '—';
    [$cipher, $iv] = $parts;
    return openssl_decrypt($cipher, 'aes-256-cbc', $key, 0, base64_decode($iv));
}

// --- 5. ✅ MATERIAS ASIGNADAS AL GRUPO (sincronización con info_grupo.php) ---
$materias = [];
$stmt = mysqli_prepare($conexion, "
    SELECT m.id_materia, m.nombre_materia
    FROM asignacion_materias am
    JOIN materias m ON am.id_materia = m.id_materia
    WHERE am.grado_credencial = ?
      AND am.grupo_credencial = ?
      AND am.turno_credencial = ?
      AND am.id_escuela = ?
      AND m.estado_materia = 0
    ORDER BY m.N_orden_materia
");
mysqli_stmt_bind_param($stmt, "sssi", $grado, $grupo, $turno, $id_escuela);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) $materias[] = $row;

// --- 6. Alumnos (desencriptados y ordenados) ---
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

usort($alumnos_raw, function($a, $b) {
    $cmp = strcmp($a['apellidos_decrypted'], $b['apellidos_decrypted']);
    return $cmp !== 0 ? $cmp : strcmp($a['nombre_credencial'], $b['nombre_credencial']);
});

// --- 7. Calificaciones ---
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
    $calificaciones[$row['id_alumno']][$row['id_materia']] = $row;
}

// --- 8. Grupo a romano ---
function grupoToRomano($letra) {
    $map = ['A'=>'I','B'=>'II','C'=>'III','D'=>'IV','E'=>'V','F'=>'VI','G'=>'VII','H'=>'VIII','I'=>'IX','J'=>'X'];
    return $map[strtoupper($letra)] ?? $letra;
}
$grupo_romano = grupoToRomano($grupo);

// --- 9. Pasar variables a la vista ---
$alumnos = $alumnos_raw;

include 'header_orientador.php'; 
include 'vista/boleta_vista.php'; 
mysqli_close($conexion);
?>