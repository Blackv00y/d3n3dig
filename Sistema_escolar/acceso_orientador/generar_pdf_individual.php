<?php
// generar_pdf_individual.php — Diseño tipo SEP (solo sección visual modificada)
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
    SELECT 
        c.nombre_credencial, 
        c.apellidos_credencial, 
        c.ruta_foto, 
        c.ruta_foto2,
        c.grado_credencial, 
        c.grupo_credencial, 
        c.turno_credencial, 
        c.id_escuela,
        e.nombre_escuela,
        e.direccion
    FROM credenciales c
    JOIN escuelas e ON c.id_escuela = e.id_escuela
    WHERE c.id_credencial = ?
");
mysqli_stmt_bind_param($stmt, "i", $id_alumno);
mysqli_stmt_execute($stmt);
$alum = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$alum) die("Alumno no encontrado.");

$direccion = $alum['direccion'] ?? 'Dirección no disponible';

$nombre_completo = $alum['nombre_credencial'] . ' ' . decryptData($alum['apellidos_credencial'], $secretKey);
$grado   = $alum['grado_credencial'];
$grupo   = $alum['grupo_credencial'];
$turno   = $alum['turno_credencial'];
$escuela = $alum['nombre_escuela'];

// ================= FOTO =================
$foto1 = !empty($alum['ruta_foto'])  
    ? $_SERVER['DOCUMENT_ROOT'] . '/sistema_escolar/' . ltrim($alum['ruta_foto'], '/')  
    : '';

$foto2 = !empty($alum['ruta_foto2']) 
    ? $_SERVER['DOCUMENT_ROOT'] . '/sistema_escolar/' . ltrim($alum['ruta_foto2'], '/') 
    : '';

$foto_default = __DIR__ . '/fpdf/R.png';

$foto = file_exists($foto1) ? $foto1 : (file_exists($foto2) ? $foto2 : $foto_default);

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
$pdf->SetMargins(12, 12, 12);
$pdf->SetAutoPageBreak(true, 20);
$pdf->AddPage();

// === ENCABEZADO OFICIAL ===
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(0, 5, utf8_decode('PRUEBA DE BOLETA'), 0, 1, 'C');
$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(0, 6, utf8_decode('SISTEMA EDUCATIVO DENEDIG'), 0, 1, 'C');
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 6, utf8_decode('REPORTE DE EVALUACIÓN'), 0, 1, 'C');
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 5, utf8_decode('1er GRADO DE EDUCACIÓN SECUNDARIA  -  CICLO ESCOLAR 2025-2026'), 0, 1, 'C');
$pdf->Ln(15);

// === DATOS DEL ALUMNO ===
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetFillColor(220, 220, 220);
$pdf->Cell(0, 6, utf8_decode('DATOS DEL(AL) ALUMNO(A)'), 0, 1, 'C',true);
$pdf->SetFont('Arial', '', 9);

$pdf->Ln(10);

$apellidos = explode(' ', decryptData($alum['apellidos_credencial'], $secretKey));
$apellido1 = $apellidos[0] ?? '';
$apellido2 = $apellidos[1] ?? '';

// ================= DATOS =================
$primer_apellido  = strtoupper($apellido1);
$segundo_apellido = strtoupper($apellido2);
$nombres          = strtoupper($alum['nombre_credencial']);

$x = 12;
$y = $pdf->GetY();

$foto_ancho = 30;
$foto_alto  = 35;
$separacion = 5;

$ancho_nombres = 190 - $foto_ancho - $separacion;

// ====== TÍTULOS ======
$pdf->SetFont('Arial', '', 9);
$pdf->SetXY($x, $y);
$pdf->Cell(45, 4, utf8_decode('PRIMER APELLIDO'), 0, 0, 'C');
$pdf->Cell(45, 4, utf8_decode('SEGUNDO APELLIDO'), 0, 0, 'C');
$pdf->Cell($ancho_nombres - 90, 4, utf8_decode('NOMBRE(S)'), 0, 0, 'C');

// ====== FOTO ======
$foto_y = $y -8;


// Imagen
// Marco de la foto
$pdf->Rect(
$x + $ancho_nombres + $separacion,
$foto_y,
$foto_ancho,
$foto_alto
);
$pdf->Image(
    $foto,
    $x + $ancho_nombres + $separacion,
    $foto_y,
    $foto_ancho,
    $foto_alto
);

// ====== LÍNEA ======
$pdf->Line($x, $y + 6, $x + $ancho_nombres, $y + 6);
// ====== TEXTO ======
$pdf->SetFont('Arial', '', 9);
$pdf->SetXY($x, $y + 7);
$pdf->Cell(45, 6, utf8_decode($primer_apellido), 0, 0, 'C');
$pdf->Cell(45, 6, utf8_decode($segundo_apellido), 0, 0, 'C');
$pdf->Cell($ancho_nombres - 90, 6, utf8_decode($nombres), 0, 1, 'C');

$pdf->Ln(15);

// === DATOS DE LA ESCUELA ===
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(0, 6, utf8_decode('DATOS DE LA ESCUELA'), 0, 1, 'C',true);
$pdf->Ln(4);
$pdf->SetFont('Arial', '', 9);

$x = 12;
$y = $pdf->GetY();

// Línea superior
$pdf->Line($x, $y+5, $x +190, $y+5);

// Texto
$pdf->SetFont('Arial', '', 9);
$pdf->SetXY($x, $y );
$pdf->Cell(60, 4, utf8_decode('NOMBRE DE LA ESCUELA'), 0, 0, 'C');
$pdf->Cell(100, 4, utf8_decode('DIRECCIÓN'), 0, 1, 'C');
$pdf->SetFont('Arial', '', 9);
$pdf->SetXY($x, $y + 7);
$pdf->Cell(100, 6, utf8_decode($escuela), 0, 0);
$pdf->Cell(100, 6, utf8_decode($direccion), 0, 1);

$pdf->Ln(8);

// ================== TABLA DE CALIFICACIONES ==================
$pdf->SetFont('Arial', 'B', 9);
$pdf->SetFillColor(220, 220, 220);
$pdf->Cell(90, 7, utf8_decode('Materias'), 1, 0, 'C', true);
$pdf->Cell(15, 7, 'I', 1, 0, 'C', true);
$pdf->Cell(15, 7, 'II', 1, 0, 'C', true);
$pdf->Cell(15, 7, 'III', 1, 0, 'C', true);
$pdf->Cell(30, 7, utf8_decode('PROMEDIO FINAL'), 1, 1, 'C', true);

$pdf->SetFont('Arial', '', 9);

function calcularPromedio($p1, $p2, $p3) {
    if (!is_numeric($p1) || !is_numeric($p2) || !is_numeric($p3)) {
        return '--';
    }

    $promedio = ($p1 + $p2 + $p3) / 3;
    $entero   = floor($promedio);
    $decimal  = $promedio - $entero;

    return ($decimal >= 0.6) ? $entero + 1 : $entero;
}
function setColorPorPromedio($pdf, $prom) {
    if (!is_numeric($prom)) {
        $pdf->SetTextColor(0, 0, 0);
        return;
    }

    if ($prom > 9) {
        $pdf->SetTextColor(25, 135, 84); // verde
    } elseif ($prom <= 6) {
        $pdf->SetTextcolor(220, 53, 69); // rojo
    } else {
        $pdf->SetTextColor(0, 0, 0); // negro
    }
}
foreach ($materias as $mat) {
    $id_materia = (int)$mat['id_materia'];
    $calif = $calificaciones[$id_materia] ?? [];

    $p1 = $calif['primer_parcial'] ?? '--';
    $p2 = $calif['segundo_parcial'] ?? '--';
    $p3 = $calif['tercer_parcial'] ?? '--';

    $prom = calcularPromedio($p1, $p2, $p3);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(90, 6, utf8_decode($mat['nombre_materia']), 1);
    $pdf->Cell(15, 6, $p1, 1, 0, 'C');
    $pdf->Cell(15, 6, $p2, 1, 0, 'C');
    $pdf->Cell(15, 6, $p3, 1, 0, 'C');
    setColorPorPromedio($pdf, $prom);//Llamamos a la funcion para los colores
    $pdf->SetFont('Arial', 'B', 10);//cambia el ancho de la letra en el promedio
    $pdf->Cell(30, 6, $prom, 1, 1, 'C');
    $pdf->SetFont('Arial', '', 9);//regresa el tamaño normal
    $pdf->SetTextColor(0, 0, 0); // reset
}
/**foreach ($materias as $mat) {
    $id_materia = (int)$mat['id_materia'];
    $calif = $calificaciones[$id_materia] ?? [];
    
    $p1 = $calif['primer_parcial'] ?? '—';
    $p2 = $calif['segundo_parcial'] ?? '—';
    $p3 = $calif['tercer_parcial'] ?? '—';
    
    // Logica para sacar el promedio y sea redondeado
    $prom = '—';

if (is_numeric($p1) && is_numeric($p2) && is_numeric($p3)) {
    $promedio = ($p1 + $p2 + $p3) / 3;

    $entero = floor($promedio);
    $decimal = $promedio - $entero;

    if ($decimal >= 0.6) {
        $prom = $entero + 1;
    } else {
        $prom = $entero;
    }
    // Color según la calificación
    if ($prom > 7) {
    $pdf->SetTextColor(25, 135, 84); //verde
    } elseif ($prom == 6) {
    $pdf->SetTextColor(220, 53, 69); // rojo
    } else {
    $pdf->SetTextColor(0, 0, 0); // negro
    }
}
    
    $pdf->Cell(90, 6, utf8_decode($mat['nombre_materia']), 1);
    $pdf->Cell(15, 6, $p1, 1, 0, 'C');
    $pdf->Cell(15, 6, $p2, 1, 0, 'C');
    $pdf->Cell(15, 6, $p3, 1, 0, 'C');
    $pdf->Cell(30, 6, $prom, 1, 1, 'C');
}
**/
$pdf->Ln(8);

// === PROMEDIO FINAL DE GRADO ===
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(100, 6, utf8_decode('PROMEDIO FINAL DE GRADO ESCOLAR'), 1, 0, 'C', true);
$pdf->Cell(30, 6, '8.1', 1, 1, 'C', true); // Calcula si lo deseas


$pdf->Ln(10);

// === FIRMA DE TUTORÍA ===
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetFillColor(220, 220, 220);
$pdf->Cell(0, 6, utf8_decode('FIRMA DE ENTERADO POR PARCIAL'), 0, 1, 'C', true);
$pdf->Ln(4);

// Ancho de cada columna
$ancho = 60;

// Encabezados
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell($ancho, 6, utf8_decode('Primer Parcial'), 1, 0, 'C',true);
$pdf->Cell($ancho, 6, utf8_decode('Segundo Parcial'), 1, 0, 'C',true);
$pdf->Cell($ancho, 6, utf8_decode('Tercer Parcial'), 1, 1, 'C',true);

// Espacio para firma
$pdf->SetFont('Arial', '', 9);
$pdf->Cell($ancho, 15, '', 1, 0, 'C');
$pdf->Cell($ancho, 15, '', 1, 0, 'C');
$pdf->Cell($ancho, 15, '', 1, 1, 'C');

// Texto "Firma"
$pdf->SetFillColor(230, 230 ,220 );
$pdf->SetTextColor(250, 0, 0 );
$pdf->Cell($ancho, 6, utf8_decode('Firma 1'), 1, 0, 'C',true);
$pdf->Cell($ancho, 6, utf8_decode('Firma 2'), 1, 0, 'C',true);
$pdf->Cell($ancho, 6, utf8_decode('Firma 3'), 1, 1, 'C',true);

$pdf->Ln(8);

// === PIE DE PÁGINA ===
$pdf->SetTextColor(0, 0, 0);       // negro
$pdf->SetFillColor(255, 255, 255); // fondo blanco
$pdf->SetFont('Arial', 'I', 7);
$pdf->SetTextColor(100, 100, 100);
$pdf->Cell(0, 5, utf8_decode('Documento oficial - Generado el ' . date('d/m/Y H:i')), 0, 0, 'C');

// Salida
$filename = "Boleta_" . preg_replace('/[^a-zA-Z0-9_]/', '_', $nombre_completo) . ".pdf";
$pdf->Output('I', $filename);

mysqli_close($conexion);
?>