<?php
// generar_zip_boletas.php
session_start();
if (!isset($_SESSION['id_credencial'])) exit;

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

        // --- Foto ---
        $foto1 = !empty($alum['ruta_foto']) ? $_SERVER['DOCUMENT_ROOT'] . '/sistema_escolar/' . ltrim($alum['ruta_foto'], '/') : '';
        $foto2 = !empty($alum['ruta_foto2']) ? $_SERVER['DOCUMENT_ROOT'] . '/sistema_escolar/' . ltrim($alum['ruta_foto2'], '/') : '';
        $foto_usar = file_exists($foto1) ? $foto1 : (file_exists($foto2) ? $foto2 : '');

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

        // --- Generar PDF en memoria ---
        $pdf = new BoletaPDF('P', 'mm', 'Letter');
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(true, 25);
        $pdf->AddPage();

        // Título
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->Cell(0, 10, utf8_decode('BOLETA DE CALIFICACIONES'), 0, 1, 'C');
        $pdf->Ln(5);

        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 6, utf8_decode("Grado: $grado - Grupo: $grupo - Turno: $turno"), 0, 1, 'C');
        $pdf->Ln(10);

        // Foto + Nombre
        $x_foto = 20;
        $y_foto = $pdf->GetY();
        if ($foto_usar && file_exists($foto_usar)) {
            list($ancho_orig, $alto_orig) = getimagesize($foto_usar);
            $ratio = $alto_orig / $ancho_orig;
            $ancho = 25;
            $alto = 25 * $ratio;
            if ($alto > 30) { $alto = 30; $ancho = 30 / $ratio; }
            $pdf->Image($foto_usar, $x_foto, $y_foto, $ancho, $alto);
            $x_nombre = $x_foto + $ancho + 10;
        } else {
            $x_nombre = $x_foto;
        }

        $pdf->SetXY($x_nombre, $y_foto);
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->MultiCell(0, 6, utf8_decode("Estudiante: $nombre_completo"), 0, 'L');
        $pdf->Ln(10);

        // Tabla de calificaciones
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(70, 7, 'Materia', 1, 0, 'C');
        $pdf->Cell(20, 7, '1° P', 1, 0, 'C');
        $pdf->Cell(20, 7, '2° P', 1, 0, 'C');
        $pdf->Cell(20, 7, '3° P', 1, 1, 'C');

        $pdf->SetFont('Arial', '', 10);
        foreach ($materias as $mat) {
            $calif = $calificaciones[$mat['id_materia']] ?? null;
            $p1 = (!empty($calif['primer_parcial']) && $calif['primer_parcial'] !== '0') ? $calif['primer_parcial'] : 'N/A';
            $p2 = (!empty($calif['segundo_parcial']) && $calif['segundo_parcial'] !== '0') ? $calif['segundo_parcial'] : 'N/A';
            $p3 = (!empty($calif['tercer_parcial']) && $calif['tercer_parcial'] !== '0') ? $calif['tercer_parcial'] : 'N/A';

            $pdf->Cell(70, 6, utf8_decode($mat['nombre_materia']), 1);
            $pdf->Cell(20, 6, $p1, 1, 0, 'C');
            $pdf->Cell(20, 6, $p2, 1, 0, 'C');
            $pdf->Cell(20, 6, $p3, 1, 1, 'C');
        }

        $pdf->Ln(10);

        // Firmas
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(0, 6, utf8_decode('FIRMAS DE ENTERADO POR PARCIAL'), 0, 1, 'C');
        $pdf->Ln(5);

        $pdf->SetFont('Arial', '', 9);
        $parciales = ['Primer Parcial', 'Segundo Parcial', 'Tercer Parcial'];
        for ($i = 0; $i < 3; $i++) {
            $pdf->Cell(0, 6, utf8_decode("__________________________________________________________"), 0, 1, 'C');
            $pdf->Cell(0, 6, utf8_decode("Firma de Enterado " . $parciales[$i]), 0, 1, 'C');
            $pdf->Ln(8);
        }

        // Pie
        $pdf->SetY(-20);
        $pdf->SetFont('Arial', 'I', 8);
        $pdf->Cell(0, 10, utf8_decode('Documento generado automáticamente – ' . date('d/m/Y H:i')), 0, 0, 'C');

        // Agregar PDF al ZIP
        $filename = "Boleta_" . preg_replace('/[^a-zA-Z0-9_]/', '_', $nombre_completo) . ".pdf";
        $zip->addFromString($filename, $pdf->Output('', 'S'));
    }

    $zip->close();

    // Descargar ZIP
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