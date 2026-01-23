<?php
// generar_pdf_individual.php — Boleta con encabezado azul
session_start();
if (!isset($_SESSION['id_credencial'])) die("Acceso denegado.");

include '../funciones/conexQRConejo.php';
$secretKey = 'your-secret-key';

$id_alumno = $_GET['id'] ?? die('ID no válido');

// --- Función para desencriptar ---
function decryptData($data, $key) {
    if (empty($data)) return '';
    $parts = explode('::', base64_decode($data), 2);
    if (count($parts) !== 2) return '—';
    [$cipher, $iv] = $parts;
    return openssl_decrypt($cipher, 'aes-256-cbc', $key, 0, base64_decode($iv));
}

// --- Datos del alumno ---
$stmt = mysqli_prepare($conexion, "
    SELECT c.nombre_credencial, c.apellidos_credencial, c.ruta_foto, c.ruta_foto2,
           c.grado_credencial, c.grupo_credencial, c.turno_credencial, c.id_escuela,
           e.nombre_escuela
    FROM credenciales c
    JOIN escuelas e ON c.id_escuela = e.id_escuela
    WHERE c.id_credencial = ?
");
mysqli_stmt_bind_param($stmt, "i", $id_alumno);
mysqli_stmt_execute($stmt);
$alum = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$alum) die("Alumno no encontrado.");

$nombre_completo = $alum['nombre_credencial'] . ' ' . decryptData($alum['apellidos_credencial'], $secretKey);
$grado   = $alum['grado_credencial'];
$grupo   = $alum['grupo_credencial'];
$turno   = $alum['turno_credencial'];
$escuela = $alum['nombre_escuela'];

// --- Fotos ---
$foto1 = !empty($alum['ruta_foto'])  ? $_SERVER['DOCUMENT_ROOT'] . '/sistema_escolar/' . ltrim($alum['ruta_foto'], '/')  : '';
$foto2 = !empty($alum['ruta_foto2']) ? $_SERVER['DOCUMENT_ROOT'] . '/sistema_escolar/' . ltrim($alum['ruta_foto2'], '/') : '';
$foto_default = __DIR__ . '/fpdf/placeholder.png';

if (file_exists($foto1))      $foto_usar = $foto1;
elseif (file_exists($foto2))  $foto_usar = $foto2;
else                          $foto_usar = $foto_default;

// --- Materias ---
$materias = [];
$stmt = mysqli_prepare($conexion, "
    SELECT m.id_materia, m.nombre_materia
    FROM asignacion_materias am
    JOIN materias m ON am.id_materia = m.id_materia
    WHERE am.grado_credencial = ?
      AND am.grupo_credencial = ?
      AND am.turno_credencial = ?
      AND am.id_escuela = ?
    ORDER BY m.N_orden_materia
");
mysqli_stmt_bind_param($stmt, "sssi", $grado, $grupo, $turno, $alum['id_escuela']);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) $materias[] = $row;

// --- Calificaciones ---
$calificaciones = [];
$stmt = mysqli_prepare($conexion, "
    SELECT id_materia, primer_parcial, segundo_parcial, tercer_parcial
    FROM calificaciones
    WHERE id_alumno = ?
");
mysqli_stmt_bind_param($stmt, "i", $id_alumno);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) {
    $calificaciones[$row['id_materia']] = $row;
}

// --- FPDF ---
require_once 'fpdf/fpdf.php';

class BoletaPDF extends FPDF {
    function Header() {}
    function Footer() {}
}

$pdf = new BoletaPDF('P', 'mm', 'Letter');
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(true, 25);
$pdf->AddPage();

// === Título ===
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 10, utf8_decode('BOLETA DE CALIFICACIONES'), 0, 1, 'C');
$pdf->Ln(5);

// === Datos escuela ===
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 6, utf8_decode("Escuela: $escuela"), 0, 1, 'C');
$pdf->Cell(0, 6, utf8_decode("Grado: $grado - Grupo: $grupo - Turno: $turno"), 0, 1, 'C');
$pdf->Ln(10);

// === Foto + nombre ===
$x_foto = 20;
$y_foto = $pdf->GetY();

if (file_exists($foto_usar)) {
    list($w, $h) = getimagesize($foto_usar);
    $ratio = $h / $w;
    $ancho = 25;
    $alto  = min(30, 25 * $ratio);
    $pdf->Image($foto_usar, $x_foto, $y_foto, $ancho, $alto);
    $x_nombre = $x_foto + $ancho + 10;
} else {
    $x_nombre = $x_foto;
}

$pdf->SetXY($x_nombre, $y_foto);
$pdf->SetFont('Arial', 'B', 12);
$pdf->MultiCell(0, 6, utf8_decode("Estudiante: $nombre_completo"));
$pdf->Ln(10);

// ================== TABLA ==================

// --- Encabezado AZUL ---
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetFillColor(0, 102, 204);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetDrawColor(0, 51, 153);

$pdf->Cell(70, 7, 'Materia', 1, 0, 'C', true);
$pdf->Cell(20, 7, '1° P',   1, 0, 'C', true);
$pdf->Cell(20, 7, '2° P',   1, 0, 'C', true);
$pdf->Cell(20, 7, '3° P',   1, 1, 'C', true);

// --- Filas ---
$pdf->SetFont('Arial', '', 10);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFillColor(255, 255, 255);

foreach ($materias as $mat) {
    $calif = $calificaciones[$mat['id_materia']] ?? [];

    $p1 = $calif['primer_parcial']  ?? 'NA';
    $p2 = $calif['segundo_parcial'] ?? 'NA';
    $p3 = $calif['tercer_parcial']  ?? 'NA';

    $pdf->Cell(70, 6, utf8_decode($mat['nombre_materia']), 1);
    $pdf->Cell(20, 6, $p1, 1, 0, 'C');
    $pdf->Cell(20, 6, $p2, 1, 0, 'C');
    $pdf->Cell(20, 6, $p3, 1, 1, 'C');
}

$pdf->Ln(10);

// === Firmas ===
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(0, 6, utf8_decode('FIRMAS DE ENTERADO POR PARCIAL'), 0, 1, 'C');
$pdf->Ln(5);

$pdf->SetFont('Arial', '', 9);
$parciales = ['Primer Parcial', 'Segundo Parcial', 'Tercer Parcial'];
foreach ($parciales as $p) {
    $pdf->Cell(0, 6, utf8_decode("______________________________________________"), 0, 1, 'C');
    $pdf->Cell(0, 6, utf8_decode("Firma de Enterado - $p"), 0, 1, 'C');
    $pdf->Ln(8);
}

// === Pie ===
$pdf->SetY(-20);
$pdf->SetFont('Arial', 'I', 8);
$pdf->Cell(0, 10, utf8_decode('Documento generado automáticamente ' . date('d/m/Y H:i')), 0, 0, 'C');

// Salida
$filename = "Boleta_" . preg_replace('/[^a-zA-Z0-9_]/', '_', $nombre_completo) . ".pdf";
$pdf->Output('I', $filename);

mysqli_close($conexion);
?>
