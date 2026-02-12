<?php
// generar_respaldo_grupal.php — Basado en generar_zip_boletas.php
// Guarda PDFs individuales en el servidor en lugar de generar ZIP
session_start();
if (!isset($_SESSION['id_credencial'])) exit;

include '../funciones/conexQRConejo.php';
$secretKey = 'your-secret-key';

// ============================================================
// FUNCIONES AUXILIARES (IDÉNTICAS AL ZIP)
// ============================================================

function decryptData($data, $key) {
    if (empty($data)) return '';
    $parts = explode('::', base64_decode($data), 2);
    if (count($parts) !== 2) return '—';
    [$cipher, $iv] = $parts;
    return openssl_decrypt($cipher, 'aes-256-cbc', $key, 0, base64_decode($iv));
}

function calcularPromedio($p1, $p2, $p3) {
    if (!is_numeric($p1) || !is_numeric($p2) || !is_numeric($p3)) {
        return '--';
    }
    $promedio = ($p1 + $p2 + $p3) / 3;
    $entero = floor($promedio);
    $decimal = $promedio - $entero;
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
        $pdf->SetTextColor(220, 53, 69); // rojo
    } else {
        $pdf->SetTextColor(0, 0, 0); // negro
    }
}

function convertirGrupoARomano($grupo) {
    $grupo = strtoupper(trim($grupo));
    $mapeo = [
        'A' => 'I',    'B' => 'II',   'C' => 'III',  'D' => 'IV',
        'E' => 'V',    'F' => 'VI',   'G' => 'VII',  'H' => 'VIII',
        'I' => 'IX',   'J' => 'X',    'K' => 'XI',   'L' => 'XII',
        'M' => 'XIII', 'N' => 'XIV',  'O' => 'XV',   'P' => 'XVI',
        'Q' => 'XVII', 'R' => 'XVIII','S' => 'XIX',  'T' => 'XX',
    ];
    return isset($mapeo[$grupo]) ? $mapeo[$grupo] : $grupo;
}

function normalizarGrado($grado) {
    $grado = trim($grado);
    $mapeoGrados = [
        '1' => 'Primero',  '2' => 'Segundo', '3' => 'Tercero',
        '4' => 'Cuarto',   '5' => 'Quinto',  '6' => 'Sexto',
        '1°' => 'Primero', '2°' => 'Segundo','3°' => 'Tercero',
        '4°' => 'Cuarto',  '5°' => 'Quinto', '6°' => 'Sexto',
        'primero' => 'Primero', 'segundo' => 'Segundo', 'tercero' => 'Tercero',
        'cuarto' => 'Cuarto',   'quinto' => 'Quinto',   'sexto' => 'Sexto',
        'PRIMERO' => 'Primero', 'SEGUNDO' => 'Segundo', 'TERCERO' => 'Tercero',
        'CUARTO' => 'Cuarto',   'QUINTO' => 'Quinto',   'SEXTO' => 'Sexto',
    ];
    return isset($mapeoGrados[$grado]) ? $mapeoGrados[$grado] : ucfirst(strtolower($grado));
}

// ============================================================
// PARÁMETROS Y VALIDACIÓN
// ============================================================

$grado = $_GET['grado'] ?? '';
$grupo = $_GET['grupo'] ?? '';
$turno = $_GET['turno'] ?? '';

if (!$grado || !$grupo || !$turno) die("Parámetros incompletos.");

// Obtener escuela del usuario
$id_usuario = $_SESSION['id_credencial'];
$stmt = mysqli_prepare($conexion, "SELECT id_escuela FROM credenciales WHERE id_credencial = ?");
mysqli_stmt_bind_param($stmt, "i", $id_usuario);
mysqli_stmt_execute($stmt);
$id_escuela = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['id_escuela'];

// ============================================================
// CONSTRUIR RUTA DE RESPALDO
// ============================================================

$rutaBase = __DIR__ . '/respaldos/boletas/';
$gradoNormalizado = normalizarGrado($grado);
$grupoRomano = convertirGrupoARomano($grupo);
$nombreCarpetaGrupo = $gradoNormalizado . ' ' . $grupoRomano;
$rutaCompleta = $rutaBase . $id_escuela . '/grupos/' . $nombreCarpetaGrupo . '/';

// Crear carpeta si no existe
if (!file_exists($rutaCompleta)) {
    mkdir($rutaCompleta, 0755, true);
}

// ============================================================
// OBTENER ALUMNOS DEL GRUPO (IDÉNTICO AL ZIP)
// ============================================================

$alumnos = [];
$stmt = mysqli_prepare($conexion, "
    SELECT id_credencial, nombre_credencial, apellidos_credencial, ruta_foto, ruta_foto2, 
           grado_credencial, grupo_credencial, turno_credencial, id_escuela, curp_credencial
    FROM credenciales
    WHERE grado_credencial = ? AND grupo_credencial = ? AND turno_credencial = ?
      AND id_escuela = ? AND nivel_usuario = 7
");
mysqli_stmt_bind_param($stmt, "sssi", $grado, $grupo, $turno, $id_escuela);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) {
    $row['apellidos_decrypted'] = decryptData($row['apellidos_credencial'], $secretKey);
    $row['curp_decrypted'] = decryptData($row['curp_credencial'], $secretKey);
    $alumnos[] = $row;
}

if (empty($alumnos)) die("No hay alumnos en este grupo.");

// ============================================================
// CARGAR FPDF EXTENDIDO (IDÉNTICO AL ZIP)
// ============================================================

require_once 'fpdf/fpdf.php';

class BoletaPDF extends FPDF {
    function Circle($x, $y, $r, $style='D') {
        $this->_Ellipse($x, $y, $r, $r, $style);
    }
    function _Ellipse($x, $y, $rx, $ry, $style='D') {
        if($style=='F') $op='f'; elseif($style=='FD' || $style=='DF') $op='B'; else $op='S';
        $lx=4/3*(M_SQRT2-1)*$rx; $ly=4/3*(M_SQRT2-1)*$ry;
        $k=$this->k; $h=$this->h;
        $this->_out(sprintf('%.2F %.2F m %.2F %.2F %.2F %.2F %.2F %.2F c', ($x+$rx)*$k, ($h-$y)*$k, ($x+$rx)*$k, ($h-($y-$ly))*$k, ($x+$lx)*$k, ($h-($y-$ry))*$k, $x*$k, ($h-($y-$ry))*$k));
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c', ($x-$lx)*$k, ($h-($y-$ry))*$k, ($x-$rx)*$k, ($h-($y-$ly))*$k, ($x-$rx)*$k, ($h-$y)*$k));
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c', ($x-$rx)*$k, ($h-($y+$ly))*$k, ($x-$lx)*$k, ($h-($y+$ry))*$k, $x*$k, ($h-($y+$ry))*$k));
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c %s', ($x+$lx)*$k, ($h-($y+$ry))*$k, ($x+$rx)*$k, ($h-($y-$ly))*$k, ($x+$rx)*$k, ($h-$y)*$k, $op));
    }
}

// ============================================================
// BUCLE: PROCESAR CADA ALUMNO (DISEÑO IDÉNTICO AL ZIP)
// ============================================================

$cantidad_generada = 0;

foreach ($alumnos as $alum) {
    $nombre_completo = $alum['nombre_credencial'] . ' ' . $alum['apellidos_decrypted'];
    $id_alumno = $alum['id_credencial'];
    $id_escuela_alum = $alum['id_escuela'];

    // --- Obtener datos completos del alumno ---
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
            c.curp_credencial,
            e.nombre_escuela,
            e.direccion,
            e.N_SEP AS cct_escuela  
        FROM credenciales c
        JOIN escuelas e ON c.id_escuela = e.id_escuela
        WHERE c.id_credencial = ?
    ");
    mysqli_stmt_bind_param($stmt, "i", $id_alumno);
    mysqli_stmt_execute($stmt);
    $alum_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    
    $direccion = $alum_data['direccion'] ?? 'Dirección no disponible';
    $nombre_completo = $alum_data['nombre_credencial'] . ' ' . decryptData($alum_data['apellidos_credencial'], $secretKey);
    $grado_alum = $alum_data['grado_credencial'];
    $grupo_alum = $alum_data['grupo_credencial'];
    $turno_alum = $alum_data['turno_credencial'];
    $escuela = $alum_data['nombre_escuela'];
    $cct = $alum_data['cct_escuela'];
    $curp_des = decryptData($alum_data['curp_credencial'], $secretKey) ?: '—';

    // ================= FOTO =================
    $foto1 = !empty($alum_data['ruta_foto'])  
        ? $_SERVER['DOCUMENT_ROOT'] . '/sistema_escolar/' . ltrim($alum_data['ruta_foto'], '/')  
        : '';
    $foto2 = !empty($alum_data['ruta_foto2']) 
        ? $_SERVER['DOCUMENT_ROOT'] . '/sistema_escolar/' . ltrim($alum_data['ruta_foto2'], '/') 
        : '';
    $foto_default = __DIR__ . '/fpdf/R.png';
    $foto = file_exists($foto1) ? $foto1 : (file_exists($foto2) ? $foto2 : $foto_default);

    // --- Materias ---
    $materias = [];
    $stmt = mysqli_prepare($conexion, "
        SELECT m.id_materia, m.nombre_materia
        FROM asignacion_materias am
        JOIN materias m ON am.id_materia = m.id_materia
        WHERE am.grado_credencial = ? AND am.grupo_credencial = ? AND am.turno_credencial = ? AND am.id_escuela = ?
        ORDER BY m.N_orden_materia
    ");
    mysqli_stmt_bind_param($stmt, "sssi", $grado_alum, $grupo_alum, $turno_alum, $id_escuela_alum);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) $materias[] = $row;

    // --- Calificaciones ---
    $calificaciones = [];
    $stmt = mysqli_prepare($conexion, "SELECT id_materia, primer_parcial, segundo_parcial, tercer_parcial FROM calificaciones WHERE id_alumno = ?");
    mysqli_stmt_bind_param($stmt, "i", $id_alumno);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $calificaciones[(int)$row['id_materia']] = $row;
    }

    // ============================================================
    // GENERAR PDF (DISEÑO 100% IDÉNTICO AL ZIP)
    // ============================================================
    
    $pdf = new BoletaPDF('P', 'mm', 'Letter');
    $pdf->SetMargins(12, 12, 12);
    $pdf->AddPage();

    // ================= ENCABEZADO OFICIAL =================
    $logo_sep = __DIR__ . '/img/logo_sep.png';
    $logo_edomx = __DIR__ . '/img/edomex.png';
    if (file_exists($logo_sep)) $pdf->Image($logo_sep, 12, 8, 50);
    if (file_exists($logo_edomx)) $pdf->Image($logo_edomx, 155, 8, 50);

    $pdf->SetY(8);
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(0, 6, utf8_decode('SISTEMA EDUCATIVO NACIONAL'), 0, 1, 'C');
    $pdf->SetFont('Arial', 'B', 13);
    $pdf->Cell(0, 6, utf8_decode('ESTADO DE MÉXICO'), 0, 1, 'C');
    $pdf->SetFont('Arial', 'I', 12);
    $pdf->Cell(0, 6, utf8_decode('BOLETA DE EVALUACIÓN'), 0, 1, 'C');
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell(0, 5, utf8_decode('CICLO ESCOLAR 2025-2026'), 0, 1, 'C');
    $pdf->Ln(10);

    // === DATOS DEL ALUMNO ===
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetFillColor(220, 220, 220);
    $pdf->Cell(0, 6, utf8_decode('DATOS DEL ALUMNO(A)'), 0, 1, 'C', true);
    $pdf->Ln(4);

    $apellidos = explode(' ', decryptData($alum_data['apellidos_credencial'], $secretKey));
    $primer_apellido = strtoupper($apellidos[0] ?? '');
    $segundo_apellido = strtoupper($apellidos[1] ?? '');
    $nombres = strtoupper($alum_data['nombre_credencial']);

    $yActual = $pdf->GetY();
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetXY(12, $yActual);
    $pdf->Cell(40, 6, 'Primer apellido:', 0, 0); $pdf->SetFont('Arial', '', 10); $pdf->Cell(60, 6, utf8_decode($primer_apellido), 0, 1);
    $pdf->SetX(12); $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(40, 6, 'Segundo apellido:', 0, 0); $pdf->SetFont('Arial', '', 10); $pdf->Cell(60, 6, utf8_decode($segundo_apellido), 0, 1);
    $pdf->SetX(12); $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(40, 6, 'Nombre:', 0, 0); $pdf->SetFont('Arial', '', 10); $pdf->Cell(60, 6, utf8_decode($nombres), 0, 1);
    $pdf->SetX(12); $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(40, 6, 'CURP:', 0, 0); $pdf->SetFont('Arial', '', 10); $pdf->Cell(60, 6, utf8_decode($curp_des), 0, 1);

    $pdf->SetXY(110, $yActual);
    $pdf->SetFont('Arial', 'B', 10); $pdf->Cell(20, 6, 'Grado:', 0, 0); $pdf->SetFont('Arial', '', 10); $pdf->Cell(20, 6, $grado_alum, 0, 1);
    $pdf->SetXY(110, $yActual+6);
    $pdf->SetFont('Arial', 'B', 10); $pdf->Cell(20, 6, 'Grupo:', 0, 0); $pdf->SetFont('Arial', '', 10); $pdf->Cell(20, 6, $grupo_alum, 0, 1);
    $pdf->SetXY(110, $yActual+12);
    $pdf->SetFont('Arial', 'B', 10); $pdf->Cell(20, 6, 'Turno:', 0, 0); $pdf->SetFont('Arial', '', 10); $pdf->Cell(20, 6, $turno_alum, 0, 1);

    $pdf->Rect(172, $yActual, 30, 35);
    $pdf->Image($foto, 172, $yActual, 30, 35);

    $pdf->SetY($yActual + 38);

    // === DATOS DE LA ESCUELA ===
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 6, utf8_decode('DATOS DE LA ESCUELA'), 0, 1, 'C', true);
    $pdf->Ln(2);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(45, 6, 'Nombre de la escuela:', 0, 0); $pdf->SetFont('Arial', '', 10); $pdf->Cell(0, 6, utf8_decode($escuela), 0, 1);
    $pdf->Ln(3);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(45, 6, utf8_decode('Dirección:'), 0, 0); 
    $pdf->SetFont('Arial', '', 10); 
    $pdf->Cell(80, 6, utf8_decode($direccion), 0, 0);
    $pdf->SetFont('Arial', 'B', 10); 
    $pdf->Cell(10, 6, 'CCT:', 0, 0);
    $pdf->SetFont('Arial', '', 10); 
    $pdf->Cell(0, 6, $cct, 0, 1);
    $pdf->Ln(5);

    // ================== TABLA DE CALIFICACIONES ==================
    $yInicioTabla = $pdf->GetY();
    $an_m = 90; $an_p = 15; $an_pf = 30; $an_st = 25;

    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetFillColor(220, 220, 220);
    $pdf->Cell($an_m, 7, 'MATERIAS', 1, 0, 'C', true);
    $pdf->Cell($an_p, 7, 'I', 1, 0, 'C', true);
    $pdf->Cell($an_p, 7, 'II', 1, 0, 'C', true);
    $pdf->Cell($an_p, 7, 'III', 1, 0, 'C', true);
    $pdf->Cell($an_pf, 7, 'PROMEDIO FINAL', 1, 0, 'C', true);
    $pdf->Cell($an_st, 7, 'RENDIMIENTO', 1, 1, 'C', true);

    $suma_prom = 0; $total_m = 0;
    $pdf->SetFont('Arial', '', 9);

    foreach ($materias as $mat) {
        $cal = $calificaciones[(int)$mat['id_materia']] ?? [];
        $p1 = $cal['primer_parcial'] ?? '--';
        $p2 = $cal['segundo_parcial'] ?? '--';
        $p3 = $cal['tercer_parcial'] ?? '--';
        
        $prom = '--';
        if(is_numeric($p1) && is_numeric($p2) && is_numeric($p3)) {
            $val = ($p1+$p2+$p3)/3;
            $prom = ($val - floor($val) >= 0.6) ? ceil($val) : floor($val);
            $suma_prom += $prom; $total_m++;
        }
        $pdf->SetFont('Arial','',8);
        $pdf->Cell($an_m, 6, utf8_decode($mat['nombre_materia']), 1);
        $pdf->Cell($an_p, 6, $p1, 1, 0, 'C');
        $pdf->Cell($an_p, 6, $p2, 1, 0, 'C');
        $pdf->Cell($an_p, 6, $p3, 1, 0, 'C');
        
        if(is_numeric($prom) && $prom >= 9) $pdf->SetTextColor(25, 135, 84);
        elseif(is_numeric($prom) && $prom <= 5) $pdf->SetTextColor(220, 53, 69);
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell($an_pf, 6, $prom, 1, 0, 'C');
        $pdf->SetTextColor(0,0,0); $pdf->SetFont('Arial', '', 9);
        $pdf->Cell($an_st, 6, '', 'LR', 1);
    }

    $prom_gr = ($total_m > 0) ? round($suma_prom / $total_m) : 0;
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell($an_m + ($an_p*3), 8, 'PROMEDIO GENERAL', 1, 0, 'R', true);
    $pdf->Cell($an_pf, 8, $prom_gr, 1, 0, 'C', true);
    $pdf->Cell($an_st, 8, '', 'LRB', 1);

    // DIBUJAR BLOQUE STATUS (Círculos)
    $yFinTabla = $pdf->GetY();
    $altoContenidoStatus = $yFinTabla - ($yInicioTabla + 7);
    $altoCeldaSt = $altoContenidoStatus / 3;
    $xSt = 12 + $an_m + ($an_p*3) + $an_pf;
    $letra_st = ($prom_gr >= 8) ? 'S' : (($prom_gr >= 7) ? 'R' :  'B');

    $st_config = [
        'S' => ['color'=>[40, 167, 69], 'y'=>$yInicioTabla+7],
        'R' => ['color'=>[255, 193, 7], 'y'=>$yInicioTabla+7+$altoCeldaSt],
        'B' => ['color'=>[220, 53, 69], 'y'=>$yInicioTabla+7+($altoCeldaSt*2)]
    ];

    foreach ($st_config as $key => $cfg) {
        if ($key == $letra_st) {
            $pdf->SetFillColor($cfg['color'][0], $cfg['color'][1], $cfg['color'][2]);
            $pdf->Circle($xSt + 8, $cfg['y'] + ($altoCeldaSt/2), 2.5, 'FD');
        } else {
            $pdf->SetFillColor(255, 255, 255);
            $pdf->Circle($xSt + 8, $cfg['y'] + ($altoCeldaSt/2), 2.5, 'D');
        }
        $pdf->SetXY($xSt + 12, $cfg['y'] + ($altoCeldaSt/2) - 2);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(10, 3, "- " . $key, 0, 0, 'L');
        if ($key != 'M') $pdf->Line($xSt, $cfg['y']+$altoCeldaSt, $xSt+$an_st, $cfg['y']+$altoCeldaSt);
    }

    // === EXPLICACIÓN DE STATUS ===
    $pdf->Ln(15);
    $pdf->SetFillColor(220, 220, 220);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(40, 8, 'RENDIMIENTO', 1, 0, 'C', true);

    $legend = [
        ['ini' => 'S', 'res' => 'obresaliente', 'col' => [25, 135, 84]],
        ['ini' => 'R', 'res' => 'egular', 'col' => [255, 193, 7]],
        ['ini' => 'B', 'res' => 'ajo', 'col' => [220, 53, 69]]
    ];

    foreach($legend as $item) {
        $xX = $pdf->GetX(); $yY = $pdf->GetY();
        $pdf->Cell(50, 8, '', 1, 0); 
        $pdf->SetXY($xX + 15, $yY + 1.5);
        $pdf->SetFont('Arial', 'B', 10); 
        $pdf->SetTextColor($item['col'][0], $item['col'][1], $item['col'][2]); 
        $pdf->Write(5, $item['ini']);
        $pdf->SetFont('Arial', '', 10); 
        $pdf->SetTextColor($item['col'][0], $item['col'][1], $item['col'][2]); 
        $pdf->Write(5, $item['res']);
        $pdf->SetXY($xX + 50, $yY);
    }
    $pdf->SetTextColor(0);

    // === BLOQUE FIRMAS (VERTICAL) Y SUGERENCIAS ===
    $pdf->Ln(12);
    $yF = $pdf->GetY();

    // --- Firmas (Lado Izquierdo) ---
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetFillColor(220, 220, 220);
    $pdf->Cell(90, 6, 'FIRMA DEL TUTOR(A)', 1, 1, 'C', true);
    $periodos = ['1er Parcial', '2do Parcial', '3er Parcial'];
    foreach($periodos as $p) {
        $pdf->SetX(12);
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(25, 13, $p, 1, 0, 'C', true);
        $pdf->Cell(65, 13, '', 1, 1);
    }

    // --- Sugerencias (Lado Derecho) ---
    $pdf->SetXY(110, $yF);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(92, 6, 'SUGERENCIAS / OBSERVACIONES', 1, 1, 'C', true);
    foreach($periodos as $p) {
        $pdf->SetX(110);
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(25, 13, $p, 1, 0, 'C', true);
        $pdf->Cell(67, 13, '', 1, 1);
    }

    // ============================================================
    // GUARDAR PDF EN SERVIDOR (EN LUGAR DE AGREGAR AL ZIP)
    // ============================================================
    
    $fecha = date('Y-m-d_H-i-s');
    $nombreArchivo = "Boleta_Final_{$id_alumno}_{$fecha}.pdf";
    $rutaArchivo = $rutaCompleta . $nombreArchivo;
    
    try {
        $pdf->Output('F', $rutaArchivo);
        $cantidad_generada++;
        error_log("INFO: PDF guardado - $nombreArchivo");
    } catch (Exception $e) {
        error_log("ERROR: Fallo al guardar PDF de alumno $id_alumno - " . $e->getMessage());
    }
}

mysqli_close($conexion);

// ============================================================
// REDIRECCIÓN CON MENSAJE DE ÉXITO
// ============================================================

header("Location: boleta_alumnos_nueva.php?grado=" . urlencode($grado) . "&grupo=" . urlencode($grupo) . "&turno=" . urlencode($turno) . "&exito=" . urlencode($cantidad_generada));
exit();
?>
