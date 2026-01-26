<?php
// generar_pdf_individual.php — Diseño con acreditación y foto a la derecha
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
    $id_materia = (int)$row['id_materia'];
    $calificaciones[$id_materia] = $row;
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
$pdf->Ln(8);

// === Datos del alumno (izquierda) y foto (derecha) ===
$y_datos = $pdf->GetY();
$x_foto = 160; // Foto a la derecha
$ancho_foto = 35;
$alto_foto = 40;

// Foto
if (file_exists($foto_usar)) {
    list($w, $h) = getimagesize($foto_usar);
    $ratio = $h / $w;
    $alto_real = min($alto_foto, $ancho_foto * $ratio);
    $pdf->Image($foto_usar, $x_foto, $y_datos, $ancho_foto, $alto_real);
    // Marco alrededor de la foto
    $pdf->Rect($x_foto, $y_datos, $ancho_foto, $alto_real);
}

// Datos del alumno
$pdf->SetXY(15, $y_datos);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 6, utf8_decode("Escuela: $escuela"), 0, 1);
$pdf->Cell(0, 6, utf8_decode("Grado: $grado - Grupo: $grupo - Turno: $turno"), 0, 1);
$pdf->Cell(0, 6, utf8_decode("Estudiante: $nombre_completo"), 0, 1);
$pdf->Ln(18);

// ================== TABLA DE CALIFICACIONES ==================

// --- Encabezado ---
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetFillColor(0, 102, 204); // Azul
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(90, 10, 'Módulo', 1, 0, 'C', true);
$pdf->Cell(15, 10, '1er P', 1, 0, 'C', true);
$pdf->Cell(15, 10, '2do P', 1, 0, 'C', true);
$pdf->Cell(15, 10, '3er P', 1, 0, 'C', true);
$pdf->Cell(25, 10, 'Acredita', 1, 1, 'C', true);

// --- Filas ---
$pdf->SetFont('Arial', '', 10);
$pdf->SetTextColor(0, 0, 0);

foreach ($materias as $mat) {
    $id_materia = (int)$mat['id_materia'];
    $calif = $calificaciones[$id_materia] ?? [];
    
    $p1 = $calif['primer_parcial'] ?? 'NA';
    $p2 = $calif['segundo_parcial'] ?? 'NA';
    $p3 = $calif['tercer_parcial'] ?? 'NA';

    // Determinar color de acreditación
    $acredita = 'No acredita';
    $color = [255, 0, 0]; // Rojo
    
    if ($p3 !== 'NA' && is_numeric($p3) && $p3 >= 7) {
        $acredita = 'Acredita';
        $color = [0, 128, 0]; // Verde
    }

    // Materia
    $pdf->Cell(90, 9, utf8_decode($mat['nombre_materia']), 1);
    // 1° Parcial
    $pdf->Cell(15, 9, $p1, 1, 0, 'C');
    // 2° Parcial
    $pdf->Cell(15, 9, $p2, 1, 0, 'C');
    // 3° Parcial
    $pdf->Cell(15, 9, $p3, 1, 0, 'C');
    // Acreditación (con color)
    $pdf->SetTextColor($color[0], $color[1], $color[2]);
    $pdf->Cell(25, 9, $acredita, 1, 1, 'C');
    $pdf->SetTextColor(0, 0, 0); // Restaurar negro
}

$pdf->Ln(12);

// ================= FIRMAS =================
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 8, utf8_decode('FIRMAS DE ENTERADO POR PARCIAL'), 0, 1, 'C');
$pdf->Ln(4);

$pdf->SetFont('Arial', '', 9);
$firmas = ['Primer Parcial', 'Segundo Parcial', 'Tercer Parcial'];
foreach ($firmas as $f) {
    $pdf->Cell(0, 6, '__________________________________________________', 0, 1, 'C');
    $pdf->Cell(0, 6, utf8_decode("Firma de Enterado - $f"), 0, 1, 'C');
    $pdf->Ln(6);
}

// === Pie de página ===
$pdf->SetY(-20);
$pdf->SetFont('Arial', 'I', 8);
$pdf->SetTextColor(120,120,120);
$pdf->Cell(0, 8, utf8_decode('Documento oficial • Generado el ' . date('d/m/Y H:i')), 0, 0, 'C');

// Salida
$filename = "Boleta_" . preg_replace('/[^a-zA-Z0-9_]/', '_', $nombre_completo) . ".pdf";
$pdf->Output('I', $filename);

mysqli_close($conexion);
?>