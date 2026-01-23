<?php
// generar_zip_boletas.php — Genera ZIP con boletas en estilo moderno
session_start();
if (!isset($_SESSION['id_credencial'])) exit;

// Verificar que ZipArchive esté disponible
if (!class_exists('ZipArchive')) {
    die("Error: La extensión ZIP de PHP no está habilitada. Actívela en php.ini (extension=zip) y reinicia Apache.");
}

include '../funciones/conexQRConejo.php';
$secretKey = 'your-secret-key';

$grado = $_GET['grado'] ?? '';
$grupo = $_GET['grupo'] ?? '';
$turno = $_GET['turno'] ?? '';

if (!$grado || !$grupo || !$turno) die("Parámetros incompletos.");

// --- Obtener escuela del usuario ---
$id_usuario = $_SESSION['id_credencial'];
$stmt = mysqli_prepare($conexion, "SELECT id_escuela FROM credenciales WHERE id_credencial = ?");
mysqli_stmt_bind_param($stmt, "i", $id_usuario);
mysqli_stmt_execute($stmt);
$id_escuela = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['id_escuela'];

// --- Obtener alumnos del grupo ---
$alumnos = [];
$stmt = mysqli_prepare($conexion, "
    SELECT id_credencial, nombre_credencial, apellidos_credencial, ruta_foto, ruta_foto2
    FROM credenciales
    WHERE grado_credencial = ? AND grupo_credencial = ? AND turno_credencial = ?
      AND id_escuela = ? AND nivel_usuario = 7
");
mysqli_stmt_bind_param($stmt, "sssi", $grado, $grupo, $turno, $id_escuela);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) {
    $row['apellidos_decrypted'] = decryptData($row['apellidos_credencial'], $secretKey);
    $alumnos[] = $row;
}

if (empty($alumnos)) die("No hay alumnos en este grupo.");

// --- Función para desencriptar ---
function decryptData($data, $key) {
    if (empty($data)) return '';
    $parts = explode('::', base64_decode($data), 2);
    if (count($parts) !== 2) return '—';
    [$cipher, $iv] = $parts;
    return openssl_decrypt($cipher, 'aes-256-cbc', $key, 0, base64_decode($iv));
}

// --- Cargar FPDF ---
require_once 'fpdf/fpdf.php';

class BoletaPDF extends FPDF {
    function Header() {}
    function Footer() {}
}

// --- Crear ZIP ---
$zip = new ZipArchive();
$tmp_zip = tempnam(sys_get_temp_dir(), 'boletas_') . '.zip';

if ($zip->open($tmp_zip, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {

    foreach ($alumnos as $alum) {
        // --- Datos básicos ---
        $nombre_completo = $alum['nombre_credencial'] . ' ' . $alum['apellidos_decrypted'];
        $id_alumno = $alum['id_credencial'];

        // --- Fotos ---
        $foto1 = !empty($alum['ruta_foto']) ? $_SERVER['DOCUMENT_ROOT'] . '/sistema_escolar/' . ltrim($alum['ruta_foto'], '/') : '';
        $foto2 = !empty($alum['ruta_foto2']) ? $_SERVER['DOCUMENT_ROOT'] . '/sistema_escolar/' . ltrim($alum['ruta_foto2'], '/') : '';
        $foto_default = __DIR__ . '/fpdf/foto_placeholder.png';

        if (file_exists($foto1))      $foto_usar = $foto1;
        elseif (file_exists($foto2))  $foto_usar = $foto2;
        else                          $foto_usar = $foto_default;

        // --- Materias asignadas al grupo ---
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
        mysqli_stmt_bind_param($stmt, "sssi", $grado, $grupo, $turno, $id_escuela);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) $materias[] = $row;

        // --- Calificaciones del alumno ---
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

        // --- Generar PDF ---
        $pdf = new BoletaPDF('P', 'mm', 'Letter');
        $pdf->SetMargins(20, 20, 20);
        $pdf->SetAutoPageBreak(true, 30);
        $pdf->AddPage();

        // Título
        $pdf->SetFont('Arial', 'B', 18);
        $pdf->Cell(0, 15, utf8_decode('BOLETA DE CALIFICACIONES'), 0, 1, 'C');
        $pdf->Ln(10);

        // Escuela
        $pdf->SetFont('Arial', '', 11);
        $pdf->Cell(0, 8, utf8_decode("Grado: $grado - Grupo: $grupo - Turno: $turno"), 0, 1, 'C');
        $pdf->Ln(15);

        // Foto + Nombre
        $x_foto = 20;
        $y_foto = $pdf->GetY();
        if (file_exists($foto_usar)) {
            list($w, $h) = getimagesize($foto_usar);
            $ratio = $h / $w;
            $ancho = 30;
            $alto = min(40, 30 * $ratio);
            $pdf->Image($foto_usar, $x_foto, $y_foto, $ancho, $alto);
            $x_nombre = $x_foto + $ancho + 15;
        } else {
            $x_nombre = $x_foto;
        }

        $pdf->SetXY($x_nombre, $y_foto + 20);
        $pdf->SetFont('Arial', 'B', 14);
        $pdf->MultiCell(0, 8, utf8_decode("Estudiante: $nombre_completo"), 0, 'L');
        $pdf->Ln(15);

        // Tabla
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->SetFillColor(0, 102, 204);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetDrawColor(0, 51, 153);

        $pdf->Cell(85, 8, 'Materia', 1, 0, 'C', true);
        $pdf->Cell(25, 8, '1° P', 1, 0, 'C', true);
        $pdf->Cell(25, 8, '2° P', 1, 0, 'C', true);
        $pdf->Cell(25, 8, '3° P', 1, 1, 'C', true);

        $pdf->SetFont('Arial', '', 10);
        $pdf->SetTextColor(0, 0, 0);
        foreach ($materias as $mat) {
            $calif = $calificaciones[$mat['id_materia']] ?? [];
            $p1 = $calif['primer_parcial'] ?? 'NA';
            $p2 = $calif['segundo_parcial'] ?? 'NA';
            $p3 = $calif['tercer_parcial'] ?? 'NA';

            $pdf->Cell(85, 7, utf8_decode($mat['nombre_materia']), 1);
            $pdf->Cell(25, 7, $p1, 1, 0, 'C');
            $pdf->Cell(25, 7, $p2, 1, 0, 'C');
            $pdf->Cell(25, 7, $p3, 1, 1, 'C');
        }

        $pdf->Ln(10);

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

        // Pie
        $pdf->SetY(-25);
        $pdf->SetFont('Arial', 'I', 8);
        $pdf->SetTextColor(120,120,120);
        $pdf->Cell(0, 8, utf8_decode('Documento oficial • Generado el ' . date('d/m/Y H:i')), 0, 0, 'C');

        // Agregar al ZIP
        $filename = "Boleta_" . preg_replace('/[^a-zA-Z0-9_]/', '_', $nombre_completo) . ".pdf";
        $zip->addFromString($filename, $pdf->Output('', 'S'));
    }

    $zip->close();

    // Descargar
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="Boletas_' . urlencode($grado . '_' . $grupo) . '.zip"');
    readfile($tmp_zip);
    unlink($tmp_zip);
    exit;
} else {
    die("Error al crear el archivo ZIP.");
}

mysqli_close($conexion);
?>