<?php
// generar_pdf_individual.php — Diseño limpio y espaciado
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
$foto_default = __DIR__ . '/fpdf/foto_placeholder.png';

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
$pdf->SetMargins(20, 20, 20); // Márgenes más generosos
$pdf->SetAutoPageBreak(true, 30); // Espacio para pie de página
$pdf->AddPage();

// === Título principal ===
$pdf->SetFont('Arial', 'B', 18);
$pdf->Cell(0, 15, utf8_decode('BOLETA DE CALIFICACIONES'), 0, 1, 'C');
$pdf->Ln(10);

// === Información de la escuela ===
$pdf->SetFont('Arial', '', 11);
$pdf->Cell(0, 8, utf8_decode("Escuela: $escuela"), 0, 1, 'C');
$pdf->Cell(0, 8, utf8_decode("Grado: $grado - Grupo: $grupo - Turno: $turno"), 0, 1, 'C');
$pdf->Ln(15);

// === Foto + Nombre ===
$x_foto = 20;
$y_foto = $pdf->GetY();

if (file_exists($foto_usar)) {
    list($w, $h) = getimagesize($foto_usar);
    $ratio = $h / $w;
    $ancho = 30;
    $alto  = min(40, 30 * $ratio);
    $pdf->Image($foto_usar, $x_foto, $y_foto, $ancho, $alto);
    $x_nombre = $x_foto + $ancho + 15;
} else {
    $x_nombre = $x_foto;
}

$pdf->SetXY($x_nombre, $y_foto + 20); // Alineado verticalmente
$pdf->SetFont('Arial', 'B', 14);
$pdf->MultiCell(0, 8, utf8_decode("Estudiante: $nombre_completo"), 0, 'L');
$pdf->Ln(15);

// ================== TABLA DE CALIFICACIONES ==================

// --- Encabezado AZUL ---
$pdf->SetFont('Arial', 'B', 11);
$pdf->SetFillColor(0, 102, 204);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetDrawColor(0, 51, 153);

$pdf->Cell(85, 10, 'Materia', 1, 0, 'C', true);
$pdf->Cell(25, 10, '1° P', 1, 0, 'C', true);
$pdf->Cell(25, 10, '2° P', 1, 0, 'C', true);
$pdf->Cell(25, 10, '3° P', 1, 1, 'C', true);

// --- Filas ---
$pdf->SetFont('Arial', '', 10);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFillColor(255, 255, 255);

foreach ($materias as $mat) {
    $calif = $calificaciones[$mat['id_materia']] ?? [];

    $p1 = $calif['primer_parcial']  ?? 'NA';
    $p2 = $calif['segundo_parcial'] ?? 'NA';
    $p3 = $calif['tercer_parcial']  ?? 'NA';

    $pdf->Cell(85, 9, utf8_decode($mat['nombre_materia']), 1);
    $pdf->Cell(25, 9, $p1, 1, 0, 'C');
    $pdf->Cell(25, 9, $p2, 1, 0, 'C');
    $pdf->Cell(25, 9, $p3, 1, 1, 'C');
}

$pdf->Ln(10); // Espacio antes de las firmas

// ================= FIRMAS =================
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 8, utf8_decode('FIRMAS DE ENTERADO'), 0, 1, 'C');
$pdf->Ln(4);

$pdf->SetFont('Arial', '', 9);

$firmas = ['Primer Parcial', 'Segundo Parcial', 'Tercer Parcial'];

foreach ($firmas as $f) {
    $pdf->Cell(0, 6, '________________', 0, 1, 'C');
    $pdf->Cell(0, 6, utf8_decode("Firma - $f"), 0, 1, 'C');
    $pdf->Ln(4);
}
// === Pie de página ===
$pdf->SetY(-25);
$pdf->SetFont('Arial', 'I', 8);
$pdf->SetTextColor(120,120,120);
$pdf->Cell(0, 8, utf8_decode('Documento oficial • Generado el ' . date('d/m/Y H:i')), 0, 0, 'C');

// Salida
$filename = "Boleta_" . preg_replace('/[^a-zA-Z0-9_]/', '_', $nombre_completo) . ".pdf";
$pdf->Output('I', $filename);

mysqli_close($conexion);
?>