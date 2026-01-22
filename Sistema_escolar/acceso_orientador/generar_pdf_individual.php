<?php
// generar_pdf_individual.php
session_start();
if (!isset($_SESSION['id_credencial'])) {
    die("Acceso denegado.");
}

include '../funciones/conexQRConejo.php';
$secretKey = 'your-secret-key';

$id_alumno = $_GET['id'] ?? die('ID de alumno no proporcionado');

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
    SELECT c.nombre_credencial, c.apellidos_credencial, c.ruta_foto, 
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

$nombre_completo = htmlspecialchars($alum['nombre_credencial'] . ' ' . decryptData($alum['apellidos_credencial'], $secretKey));
$grado = $alum['grado_credencial'];
$grupo = $alum['grupo_credencial'];
$turno = $alum['turno_credencial'];
$escuela = $alum['nombre_escuela'];
$foto = !empty($alum['ruta_foto']) ? $alum['ruta_foto'] : '';

// --- ✅ MATERIAS ASIGNADAS AL GRUPO (igual que info_grupo.php) ---
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
mysqli_stmt_bind_param($stmt, "sssi", $grado, $grupo, $turno, $alum['id_escuela']);
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

// --- Cargar DomPDF (vía Composer) ---
require_once __DIR__ . '../vendor/autoload.php';
use Dompdf\Dompdf;
use Dompdf\Options;

$options = new Options();
$options->set('defaultFont', 'Arial');
$options->set('isRemoteEnabled', true); // Para cargar fotos
$dompdf = new Dompdf($options);

// --- HTML del PDF ---
$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h2 { color: #1a355e; margin: 0; }
        .student-info { display: flex; align-items: center; margin: 20px 0; gap: 15px; }
        .student-photo { width: 60px; height: 60px; border-radius: 50%; object-fit: cover; border: 1px solid #ddd; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #333; padding: 8px; text-align: center; }
        th { background-color: #f0f0f0; }
        .footer { margin-top: 30px; text-align: center; font-size: 0.9em; color: #777; }
    </style>
</head>
<body>
    <div class="header">
        <h2>BOLETA DE CALIFICACIONES</h2>
        <p><strong>Escuela:</strong> ' . $escuela . '</p>
        <p><strong>Grado:</strong> ' . $grado . ' - <strong>Grupo:</strong> ' . $grupo . ' - <strong>Turno:</strong> ' . $turno . '</p>
    </div>

    <div class="student-info">
        ' . ($foto ? '<img src="' . $foto . '" class="student-photo">' : '') . '
        <div><strong>' . $nombre_completo . '</strong></div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Materia</th>
                <th>1° Parcial</th>
                <th>2° Parcial</th>
                <th>3° Parcial</th>
            </tr>
        </thead>
        <tbody>';

foreach ($materias as $mat) {
    $calif = $calificaciones[$mat['id_materia']] ?? null;
    $p1 = $calif['primer_parcial'] ?? '–';
    $p2 = $calif['segundo_parcial'] ?? '–';
    $p3 = $calif['tercer_parcial'] ?? '–';
    $html .= '<tr>
        <td>' . htmlspecialchars($mat['nombre_materia']) . '</td>
        <td>' . $p1 . '</td>
        <td>' . $p2 . '</td>
        <td>' . $p3 . '</td>
    </tr>';
}

$html .= '
        </tbody>
    </table>
    <div class="footer">
        Documento generado automáticamente — ' . date('d/m/Y H:i') . '
    </div>
</body>
</html>';

$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$filename = "Boleta_" . preg_replace('/[^a-zA-Z0-9_]/', '_', $nombre_completo) . ".pdf";
$dompdf->stream($filename, ["Attachment" => false]);

mysqli_close($conexion);
?>