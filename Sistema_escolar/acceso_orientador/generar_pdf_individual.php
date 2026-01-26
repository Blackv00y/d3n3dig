<?php
session_start();
if (!isset($_SESSION['id_credencial'])) die("Acceso denegado.");

include '../funciones/conexQRConejo.php';
$secretKey = 'your-secret-key';

$id_alumno = $_GET['id'] ?? die('ID no válido');

/* ================= FUNCION DESENCRIPTAR ================= */
function decryptData($data, $key) {
    if (empty($data)) return '';
    $parts = explode('::', base64_decode($data), 2);
    if (count($parts) !== 2) return '—';
    return openssl_decrypt($parts[0], 'aes-256-cbc', $key, 0, base64_decode($parts[1]));
}

/* ================= DATOS ALUMNO ================= */
$stmt = mysqli_prepare($conexion, "
    SELECT c.nombre_credencial, c.apellidos_credencial, c.ruta_foto, c.ruta_foto2,
           c.grado_credencial, c.grupo_credencial, c.turno_credencial,
           c.id_escuela, e.nombre_escuela
    FROM credenciales c
    JOIN escuelas e ON c.id_escuela = e.id_escuela
    WHERE c.id_credencial = ?
");
mysqli_stmt_bind_param($stmt, "i", $id_alumno);
mysqli_stmt_execute($stmt);
$alum = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
if (!$alum) die("Alumno no encontrado.");

$nombre_completo = $alum['nombre_credencial'].' '.decryptData($alum['apellidos_credencial'], $secretKey);
$grado = $alum['grado_credencial'];
$grupo = $alum['grupo_credencial'];
$turno = $alum['turno_credencial'];
$escuela = $alum['nombre_escuela'];

/* ================= FOTO ================= */
$foto1 = $_SERVER['DOCUMENT_ROOT'].'/sistema_escolar/'.ltrim($alum['ruta_foto'],'/');
$foto2 = $_SERVER['DOCUMENT_ROOT'].'/sistema_escolar/'.ltrim($alum['ruta_foto2'],'/');
$foto_default = __DIR__.'/fpdf/foto_placeholder.png';
$foto_usar = file_exists($foto1) ? $foto1 : (file_exists($foto2) ? $foto2 : $foto_default);

/* ================= MATERIAS ================= */
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
$res = mysqli_stmt_get_result($stmt);
while ($r = mysqli_fetch_assoc($res)) $materias[] = $r;

/* ================= CALIFICACIONES ================= */
$calificaciones = [];
$stmt = mysqli_prepare($conexion, "
    SELECT id_materia, primer_parcial, segundo_parcial, tercer_parcial
    FROM calificaciones
    WHERE id_alumno = ?
");
mysqli_stmt_bind_param($stmt, "i", $id_alumno);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
while ($r = mysqli_fetch_assoc($res)) {
    $calificaciones[(int)$r['id_materia']] = $r;
}

/* ================= PDF ================= */
require_once 'fpdf/fpdf.php';
$pdf = new FPDF('P','mm','Letter');
$pdf->SetMargins(12,12,12);
$pdf->SetAutoPageBreak(false);
$pdf->AddPage();

/* ===== BARRA SUPERIOR ===== */
$pdf->SetFillColor(0,102,204);
$pdf->Rect(0,0,216,18,'F');
$pdf->SetFont('Arial','B',14);
$pdf->SetTextColor(255);
$pdf->SetY(6);
$pdf->Cell(0,6,utf8_decode('BOLETA DE CALIFICACIONES'),0,1,'C');
$pdf->SetTextColor(0);
$pdf->Ln(12);

/// === Datos del alumno (izquierda) y foto (derecha) ===
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
/* ================= TABLA ================= */
$pdf->SetFont('Arial','B',9);
$pdf->SetFillColor(220,235,250);
$pdf->SetDrawColor(0,102,204);

$pdf->Cell(95,8,utf8_decode('Módulo'),1,0,'C',true);
$pdf->Cell(15,8,'1°',1,0,'C',true);
$pdf->Cell(15,8,'2°',1,0,'C',true);
$pdf->Cell(15,8,'3°',1,0,'C',true);
$pdf->Cell(25,8,'Acredita',1,1,'C',true);

$pdf->SetFont('Arial','',9);

foreach ($materias as $m) {
    $c = $calificaciones[$m['id_materia']] ?? [];
    $p1 = $c['primer_parcial'] ?? '—';
    $p2 = $c['segundo_parcial'] ?? '—';
    $p3 = $c['tercer_parcial'] ?? '—';

    $ac = (is_numeric($p3) && $p3 >= 7) ? 'ACREDITA' : 'NO ACREDITA';
    $pdf->Cell(95,7,utf8_decode($m['nombre_materia']),1);
    $pdf->Cell(15,7,$p1,1,0,'C');
    $pdf->Cell(15,7,$p2,1,0,'C');
    $pdf->Cell(15,7,$p3,1,0,'C');
    $pdf->SetTextColor($ac==='ACREDITA'?0:200,$ac==='ACREDITA'?120:0,0);
    $pdf->Cell(25,7,$ac,1,1,'C');
    $pdf->SetTextColor(0);
}

$pdf->Ln(12);

// ================= FIRMAS =================
/* ================= FIRMAS ================= */
$pdf->Ln(14);
$pdf->SetFont('Arial','',9);

$pdf->Cell(60,5,'________',0,0,'C');
$pdf->Cell(60,5,'________',0,0,'C');
$pdf->Cell(60,5,'________',0,1,'C');

$pdf->Cell(60,5,'Parcial 1',0,0,'C');
$pdf->Cell(60,5,'Parcial 2',0,0,'C');
$pdf->Cell(60,5,'Parcial 3',0,1,'C');
/* ================= PIE ================= */
$pdf->SetY(-15);
$pdf->SetFont('Arial','I',7);
$pdf->SetTextColor(120);
$pdf->Cell(0,5,utf8_decode('Documento oficial • '.date('d/m/Y')),0,0,'C');

$pdf->Output('I','Boleta_'.$id_alumno.'.pdf');
mysqli_close($conexion);
?>