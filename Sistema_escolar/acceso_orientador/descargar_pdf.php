<?php
// descargar_pdf.php — Endpoint seguro para servir PDFs de respaldo
session_start();
if (!isset($_SESSION['id_credencial'])) {
    http_response_code(401);
    die('No autorizado');
}

include '../funciones/conexQRConejo.php';

// ── Obtener id_escuela del usuario autenticado ──
$stmt = mysqli_prepare($conexion, "SELECT id_escuela FROM credenciales WHERE id_credencial = ?");
mysqli_stmt_bind_param($stmt, "i", $_SESSION['id_credencial']);
mysqli_stmt_execute($stmt);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
$id_escuela = $row['id_escuela'] ?? 0;
mysqli_close($conexion);

if (!$id_escuela) {
    http_response_code(403);
    die('Acceso denegado');
}

// ── Parámetros ──
$archivo = basename($_GET['archivo'] ?? '');  // nombre del PDF
$carpeta = basename($_GET['carpeta'] ?? '');  // nombre de la carpeta (ej. "Primero I")
$accion  = $_GET['accion'] ?? 'descargar';    // 'descargar' | 'visualizar'

if (!$archivo || !$carpeta) {
    http_response_code(400);
    die('Parámetros incompletos');
}

// ── Validar extensión ──
if (strtolower(pathinfo($archivo, PATHINFO_EXTENSION)) !== 'pdf') {
    http_response_code(403);
    die('Tipo de archivo no permitido');
}

// ── Construir y verificar ruta ──
$rutaBase    = __DIR__ . '/respaldos/boletas/' . intval($id_escuela) . '/grupos/';
$rutaArchivo = $rutaBase . $carpeta . '/' . $archivo;

// realpath() resuelve .. y symlinks; comprobamos que el resultado
// siga dentro de la carpeta de la escuela (previene path traversal)
$rutaReal     = realpath($rutaArchivo);
$rutaBaseFull = realpath($rutaBase);

if (!$rutaReal || !$rutaBaseFull || strpos($rutaReal, $rutaBaseFull) !== 0) {
    http_response_code(404);
    die('Archivo no encontrado');
}

if (!is_readable($rutaReal)) {
    http_response_code(403);
    die('Sin permisos de lectura');
}

// ── Servir ──
if (ob_get_level()) ob_end_clean();

header('Content-Type: application/pdf');
header('Content-Length: ' . filesize($rutaReal));
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');
header('X-Content-Type-Options: nosniff');

if ($accion === 'descargar') {
    header('Content-Disposition: attachment; filename="' . $archivo . '"');
} else {
    header('Content-Disposition: inline; filename="' . $archivo . '"');
}

readfile($rutaReal);
exit;
