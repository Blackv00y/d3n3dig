<?php
// historial_respaldos.php — GESTIÓN DE HISTORIAL DE RESPALDOS (VERSIÓN CORREGIDA HEADER/FOOTER)
// ESTRUCTURA: respaldos/boletas/{ID}/generación/{AÑO}/{TURNO}/Grupos/{GRADO GRUPO}/

session_start();
if (!isset($_SESSION['id_credencial'])) {
    header("Location: ../login.php");
    exit();
}

require_once __DIR__ . '/../funciones/conexQRConejo.php';

$secretKey   = 'your-secret-key';
$grado       = $_GET['grado'] ?? '';
$grupo       = $_GET['grupo'] ?? '';
$turno       = $_GET['turno'] ?? '';
$anioFiltro  = $_GET['anio'] ?? '';
$grupoFiltro = $_GET['grupo_filtro'] ?? '';
$turnoFiltro = $_GET['turno_filtro'] ?? '';

// ── Obtener ID de escuela del usuario ──
$stmt = mysqli_prepare($conexion, "SELECT id_escuela FROM credenciales WHERE id_credencial = ?");
mysqli_stmt_bind_param($stmt, "i", $_SESSION['id_credencial']);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($result);
$id_escuela = $row['id_escuela'] ?? 0;

// ── Obtener nombre de la escuela ──
$stmt = mysqli_prepare($conexion, "SELECT nombre_escuela FROM escuelas WHERE id_escuela = ?");
mysqli_stmt_bind_param($stmt, "i", $id_escuela);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$row_escuela = mysqli_fetch_assoc($result);
$nombre_escuela = $row_escuela['nombre_escuela'] ?? 'Consultar';

// ============================================================
// FUNCIONES AUXILIARES
// ============================================================

function convertirGrupoARomano($grupo) {
    $grupo = strtoupper(trim($grupo));
    $mapeo = [
        'A' => 'I', 'B' => 'II', 'C' => 'III', 'D' => 'IV', 'E' => 'V', 'F' => 'VI',
        'G' => 'VII', 'H' => 'VIII', 'I' => 'IX', 'J' => 'X', 'K' => 'XI', 'L' => 'XII',
        'M' => 'XIII', 'N' => 'XIV', 'O' => 'XV', 'P' => 'XVI', 'Q' => 'XVII',
        'R' => 'XVIII', 'S' => 'XIX', 'T' => 'XX',
    ];
    return isset($mapeo[$grupo]) ? $mapeo[$grupo] : $grupo;
}

function normalizarGrado($grado) {
    $grado = trim($grado);
    $mapeoGrados = [
        '1' => 'Primero', '2' => 'Segundo', '3' => 'Tercero',
        '4' => 'Cuarto', '5' => 'Quinto', '6' => 'Sexto',
        '1°' => 'Primero', '2°' => 'Segundo', '3°' => 'Tercero',
        '4°' => 'Cuarto', '5°' => 'Quinto', '6°' => 'Sexto',
        'primero' => 'Primero', 'segundo' => 'Segundo', 'tercero' => 'Tercero',
        'cuarto' => 'Cuarto', 'quinto' => 'Quinto', 'sexto' => 'Sexto',
        'PRIMERO' => 'Primero', 'SEGUNDO' => 'Segundo', 'TERCERO' => 'Tercero',
        'CUARTO' => 'Cuarto', 'QUINTO' => 'Quinto', 'SEXTO' => 'Sexto',
    ];
    return isset($mapeoGrados[$grado]) ? $mapeoGrados[$grado] : ucfirst(strtolower($grado));
}

// 🔧 FUNCIÓN CLAVE: Convertir ruta de archivo a URL web accesible
function rutaArchivoAUrl($rutaArchivo, $baseDir) {
    $webRoot = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']));
    $rutaNormalizada = str_replace('\\', '/', $rutaArchivo);

    if ($webRoot && strpos($rutaNormalizada, $webRoot) === 0) {
        return str_replace($webRoot, '', $rutaNormalizada);
    }

    $baseDirNormalized = str_replace('\\', '/', $baseDir);
    if (strpos($rutaNormalizada, $baseDirNormalized) === 0) {
        return str_replace($baseDirNormalized, '/acceso_orientador', $rutaNormalizada);
    }

    return basename($rutaArchivo);
}

function decryptData($data, $key) {
    if (empty($data)) return '';
    $decoded = base64_decode($data, true);
    if ($decoded === false) return '';
    $parts = explode('::', $decoded, 2);
    if (count($parts) !== 2) return '';
    [$cipher, $iv] = $parts;
    return openssl_decrypt($cipher, 'aes-256-cbc', $key, 0, base64_decode($iv));
}

// ============================================================
// CONSTRUIR RUTA CON NUEVA ESTRUCTURA
// ============================================================

$rutaBaseRespaldos = __DIR__ . '/respaldos/boletas/';
$gradoNormalizado  = normalizarGrado($grado);
$grupoRomano       = convertirGrupoARomano($grupo);
$turnoNormalizado  = ucfirst(strtolower(trim($turno)));

$anioBusqueda   = !empty($anioFiltro) ? $anioFiltro : date('Y');
$turnoBusqueda  = !empty($turnoFiltro) ? ucfirst(strtolower($turnoFiltro)) : $turnoNormalizado;
$grupoBusqueda  = !empty($grupoFiltro) ? $grupoFiltro : $grupoRomano;

$rutaCompleta = $rutaBaseRespaldos
    . $id_escuela . '/'
    . 'generación/'
    . $anioBusqueda . '/'
    . $turnoBusqueda . '/'
    . 'Grupos/'
    . $gradoNormalizado . ' ' . $grupoBusqueda . '/';

// ── OBTENER ARCHIVOS PDF ──
$archivos   = [];
$idsAlumnos = [];

if (is_dir($rutaCompleta)) {
    $scan = scandir($rutaCompleta);
    foreach ($scan as $file) {
        if ($file !== '.' && $file !== '..' && strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'pdf') {
            $path = $rutaCompleta . $file;

            $idAlumno = '';
            if (preg_match('/Boleta_(?:Final|Parcial|Manual)_(\d+)_/', $file, $matches)) {
                $idAlumno = $matches[1];
                if (!in_array($idAlumno, $idsAlumnos, true)) {
                    $idsAlumnos[] = $idAlumno;
                }
            }

            $archivos[] = [
                'nombre'   => $file,
                'ruta_fs'  => $path,
                'ruta_web' => rutaArchivoAUrl($path, __DIR__),
                'fecha'    => @filemtime($path) ?: time(),
                'tipo'     => (strpos($file, 'Boleta_Final_') !== false) ? 'Final'
                            : ((strpos($file, 'Boleta_Parcial_') !== false) ? 'Parcial' : 'Otro'),
                'id_alumno'=> $idAlumno,
                'existe'   => file_exists($path) && is_readable($path),
            ];
        }
    }
    usort($archivos, function ($a, $b) { return $b['fecha'] <=> $a['fecha']; });
}

// ── OBTENER NOMBRES DE ALUMNOS ──
$nombresAlumnos = [];
if (!empty($idsAlumnos)) {
    $idsStr = implode(',', array_map('intval', $idsAlumnos));
    $stmt = mysqli_prepare($conexion, "
        SELECT id_credencial, nombre_credencial, apellidos_credencial
        FROM credenciales
        WHERE id_credencial IN ($idsStr)
    ");
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($result)) {
        $apellidos = decryptData($row['apellidos_credencial'], $secretKey);
        $nombresAlumnos[$row['id_credencial']] = trim($row['nombre_credencial'] . ' ' . $apellidos);
    }
}

// ============================================================
// 🔹 DATOS PARA GRÁFICA CIRCULAR: MATERIA MÁS APROBADA
// ============================================================
$datosGraficaMateria = [];
$materiasStats = [];

if (!empty($idsAlumnos)) {
    $idsStr = implode(',', array_map('intval', $idsAlumnos));

    $stmt = mysqli_prepare($conexion, "
        SELECT m.nombre_materia, c.primer_parcial, c.segundo_parcial, c.tercer_parcial
        FROM calificaciones c
        JOIN materias m ON c.id_materia = m.id_materia
        WHERE c.id_alumno IN ($idsStr)
          AND c.grado_credencial = ?
          AND c.grupo_credencial = ?
          AND c.turno_credencial = ?
    ");
    mysqli_stmt_bind_param($stmt, "sss", $grado, $grupo, $turno);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($result)) {
        $materia = $row['nombre_materia'];
        $p1 = $row['primer_parcial'];
        $p2 = $row['segundo_parcial'];
        $p3 = $row['tercer_parcial'];

        if (is_numeric($p1) && is_numeric($p2) && is_numeric($p3)) {
            $promedio = ($p1 + $p2 + $p3) / 3;
            $promedioRedondeado = (($promedio - floor($promedio)) >= 0.6) ? ceil($promedio) : floor($promedio);

            if (!isset($materiasStats[$materia])) {
                $materiasStats[$materia] = ['aprobados' => 0, 'reprobados' => 0];
            }
            if ($promedioRedondeado >= 7) $materiasStats[$materia]['aprobados']++;
            else $materiasStats[$materia]['reprobados']++;
        }
    }

    // Materia más aprobada
    $materiaMasAprobada = '';
    $maxAprobados = 0;
    foreach ($materiasStats as $materia => $stats) {
        if ($stats['aprobados'] > $maxAprobados) {
            $maxAprobados = $stats['aprobados'];
            $materiaMasAprobada = $materia;
        }
    }

    if ($materiaMasAprobada && isset($materiasStats[$materiaMasAprobada])) {
        $stats = $materiasStats[$materiaMasAprobada];
        $datosGraficaMateria = [
            'materia'   => $materiaMasAprobada,
            'aprobados' => $stats['aprobados'],
            'reprobados'=> $stats['reprobados'],
            'total'     => $stats['aprobados'] + $stats['reprobados'],
        ];
    }
}

// ── ESTADÍSTICAS GENERALES ──
$totalArchivos   = count($archivos);
$boletasFinales  = count(array_filter($archivos, fn($a) => $a['tipo'] === 'Final'));
$boletasParciales= count(array_filter($archivos, fn($a) => $a['tipo'] === 'Parcial'));

mysqli_close($conexion);

// ============================================================
// ✅ HEADER (SOLO 1 VEZ) — NO DUPLICAR HTML/HEAD/BODY
// ============================================================
require_once __DIR__ . '/header_orientador.php';
?>

<!-- (Opcional) Cargas extra SOLO para esta página -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.4/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@400;500;600;700&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
    :root {
        --primary: #1a355e;
        --secondary: #2b91ff;
        --success: #28a745;
        --warning: #ffc107;
        --danger: #dc3545;
        --light: #f8f9fa;
    }

    body {
        font-family: 'League Spartan', sans-serif;
        background: linear-gradient(135deg, #f5f7fa 0%, #e4e8f0 100%);
    }

    /* ✅ En vez de padding en body (porque el header ya maneja top) */
    .page-wrap{
        padding: 20px;
        min-height: calc(100vh - 80px);
    }

    .container { max-width: 1400px; }

    .header-title {
        text-align: center;
        margin: 2rem 0 2.5rem;
        color: var(--primary);
        font-size: 2rem;
        font-weight: 700;
        position: relative;
        padding-bottom: 15px;
    }
    .header-title::after {
        content: '';
        position: absolute;
        bottom: 0; left: 50%;
        transform: translateX(-50%);
        width: 80px; height: 4px;
        background: linear-gradient(90deg, var(--secondary), var(--success));
        border-radius: 2px;
    }

    .info-header-wrapper {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
        padding: 18px 24px;
        background: white;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        border-left: 4px solid var(--secondary);
    }

    .btn-back {
        background: linear-gradient(135deg, #6c757d, #495057);
        border: none;
        color: white;
        font-weight: 600;
        padding: 10px 24px;
        border-radius: 30px;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.3s ease;
    }
    .btn-back:hover {
        background: linear-gradient(135deg, #5a6268, #343a40);
        transform: translateY(-2px);
    }

    .stats-cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 16px;
        margin-bottom: 25px;
    }
    .stat-card {
        background: white;
        border-radius: 16px;
        padding: 20px;
        text-align: center;
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.06);
        border-top: 4px solid var(--secondary);
    }
    .stat-card.final { border-top-color: var(--success); }
    .stat-card.parcial { border-top-color: var(--warning); }
    .stat-card.danger { border-top-color: var(--danger); }

    .stat-number {
        font-size: 2.2rem;
        font-weight: 700;
        color: var(--primary);
        line-height: 1;
        margin-bottom: 4px;
    }
    .stat-label {
        color: #6c757d;
        font-size: 0.85rem;
        font-weight: 500;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 4px;
    }

    .backup-table {
        background: white;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        margin-bottom: 25px;
    }
    .table { margin-bottom: 0; }
    .table thead {
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        color: white;
        font-weight: 600;
    }
    .table thead th {
        border: none;
        padding: 14px 16px;
        font-size: 0.9rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .table tbody tr {
        transition: background 0.2s;
        border-bottom: 1px solid #f1f3f5;
    }
    .table tbody tr:hover {
        background: linear-gradient(90deg, #f8f9ff, #f0f7ff);
    }
    .table tbody td {
        padding: 14px 16px;
        vertical-align: middle;
        font-size: 0.95rem;
    }

    .badge-tipo {
        padding: 6px 14px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    .badge-final { background: linear-gradient(135deg, var(--success), #20c997); color: white; }
    .badge-parcial { background: linear-gradient(135deg, var(--warning), #ff9800); color: #333; }
    .badge-otro { background: linear-gradient(135deg, #6c757d, #adb5bd); color: white; }

    .btn-action {
        padding: 8px 14px;
        border-radius: 10px;
        margin: 0 3px;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 36px;
        height: 36px;
        font-size: 0.9rem;
        box-shadow: 0 2px 6px rgba(0,0,0,0.1);
    }
    .btn-view { background: linear-gradient(135deg, var(--secondary), #1a78e6); color: white; border: none; }
    .btn-view:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(43,145,255,0.4); }
    .btn-download { background: linear-gradient(135deg, var(--success), #218838); color: white; border: none; }
    .btn-download:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(40,167,69,0.4); }

    .empty-state {
        text-align: center;
        padding: 60px 30px;
        color: #6c757d;
        background: white;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.06);
    }
    .empty-state i { font-size: 4.5rem; margin-bottom: 20px; color: #dee2e6; }

    .search-box { max-width: 450px; margin-bottom: 20px; position: relative; }
    .search-box .form-control {
        padding-left: 45px;
        border-radius: 30px;
        border: 2px solid #e9ecef;
    }
    .search-box .form-control:focus {
        border-color: var(--secondary);
        box-shadow: 0 0 0 4px rgba(43,145,255,0.15);
    }
    .search-box i {
        position: absolute;
        left: 18px; top: 50%;
        transform: translateY(-50%);
        color: #adb5bd;
    }

    .filters-row {
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
        margin-bottom: 20px;
        align-items: flex-end;
        background: white;
        padding: 16px 20px;
        border-radius: 16px;
        box-shadow: 0 2px 12px rgba(0,0,0,0.05);
    }
    .filter-group { flex: 1; min-width: 180px; }
    .filter-group label {
        font-size: 0.8rem;
        font-weight: 600;
        color: #495057;
        margin-bottom: 6px;
        display: block;
    }
    .filter-select {
        width: 100%;
        border: 2px solid #dee2e6;
        border-radius: 12px;
        padding: 10px 16px;
        font-size: 0.9rem;
        background: white;
    }

    .btn-stats {
        background: linear-gradient(135deg, #6f42c1, #a66efa);
        border: none;
        color: white;
        font-weight: 600;
        padding: 10px 24px;
        border-radius: 30px;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .badge-grupo {
        background: linear-gradient(135deg, #6f42c1, #a66efa);
        color: white;
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    .badge-turno {
        background: linear-gradient(135deg, #17a2b8, #20c997);
        color: white;
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }

    .chart-container {
        position: relative;
        height: 300px;
        margin: 20px 0;
        background: white;
        border-radius: 16px;
        padding: 15px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .fecha-badge {
        background: #e9ecef;
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 0.8rem;
        color: #495057;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }

    .modal-content { border-radius: 20px; border: none; box-shadow: 0 10px 40px rgba(0,0,0,0.2); }
    .modal-header { border-radius: 20px 20px 0 0; border: none; padding: 18px 24px; }
    .pdf-preview-container { background: #1a1a2e; border-radius: 12px; overflow: hidden; height: 500px; display: flex; align-items: center; justify-content: center; }
    .pdf-preview-container iframe { width: 100%; height: 100%; border: none; background: white; }
    .pdf-fallback { color: white; text-align: center; padding: 20px; }
    .pdf-fallback a { color: var(--secondary); font-weight: 600; }

    @media (max-width: 768px) {
        .info-header-wrapper { flex-direction: column; gap: 15px; text-align: center; }
        .filters-row { flex-direction: column; }
        .filter-group { width: 100%; }
        .table-responsive { font-size: 0.85rem; }
        .btn-action { width: 32px; height: 32px; }
    }

    @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    .fade-in { animation: fadeIn 0.4s ease forwards; }
</style>
<br>
<div class="page-wrap">
<div class="container">

    <div class="header-title">
        <i class="fas fa-history me-2"></i>Historial de Respaldos
    </div>

    <!-- INFO + BOTÓN VOLVER -->
    <div class="info-header-wrapper fade-in">
        <div>
            <strong><i class="fas fa-school me-1"></i>Escuela:</strong> <?= htmlspecialchars($nombre_escuela) ?><br>
            <strong>Grado:</strong> <?= htmlspecialchars($grado) ?> |
            <strong>Grupo:</strong> <?= htmlspecialchars($grupoRomano) ?> |
            <strong>Turno:</strong> <?= htmlspecialchars($turno) ?> |
            <strong>Año:</strong> <?= htmlspecialchars($anioBusqueda) ?>
        </div>
        <div>
            <a href="boleta_alumnos_nueva.php?grado=<?= urlencode($grado) ?>&grupo=<?= urlencode($grupo) ?>&turno=<?= urlencode($turno) ?>"
               class="btn-back">
                <i class="fas fa-arrow-left"></i> Volver
            </a>
        </div>
    </div>

    <!-- TARJETAS DE ESTADÍSTICAS -->
    <div class="stats-cards">
        <div class="stat-card fade-in">
            <div class="stat-number"><?= $totalArchivos ?></div>
            <div class="stat-label"><i class="fas fa-file-pdf"></i> Total</div>
        </div>
        <div class="stat-card final fade-in">
            <div class="stat-number"><?= $boletasFinales ?></div>
            <div class="stat-label"><i class="fas fa-check-circle"></i> Finales</div>
        </div>
        <div class="stat-card parcial fade-in">
            <div class="stat-number"><?= $boletasParciales ?></div>
            <div class="stat-label"><i class="fas fa-clock"></i> Parciales</div>
        </div>
        <div class="stat-card danger fade-in">
            <div class="stat-number"><?= count($idsAlumnos) ?></div>
            <div class="stat-label"><i class="fas fa-users"></i> Alumnos</div>
        </div>
    </div>

    <!-- FILTROS -->
    <div class="filters-row fade-in">
        <div class="filter-group">
            <label><i class="fas fa-calendar me-1"></i>Año</label>
            <select class="filter-select" onchange="aplicarFiltro('anio', this.value)">
                <option value="">Todos</option>
                <?php for($y = date('Y'); $y >= 2020; $y--): ?>
                <option value="<?= $y ?>" <?= $anioFiltro == $y ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="filter-group">
            <label><i class="fas fa-users me-1"></i>Grupo</label>
            <select class="filter-select" onchange="aplicarFiltro('grupo_filtro', this.value)">
                <option value="">Todos</option>
                <?php foreach(['I','II','III','IV','V','VI','VII','VIII'] as $g): ?>
                <option value="<?= $g ?>" <?= $grupoFiltro == $g ? 'selected' : '' ?>><?= $g ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <label><i class="fas fa-sun me-1"></i>Turno</label>
            <select class="filter-select" onchange="aplicarFiltro('turno_filtro', this.value)">
                <option value="">Todos</option>
                <option value="Matutino" <?= $turnoFiltro == 'Matutino' ? 'selected' : '' ?>>Matutino</option>
                <option value="Vespertino" <?= $turnoFiltro == 'Vespertino' ? 'selected' : '' ?>>Vespertino</option>
            </select>
        </div>
        <div style="align-self: flex-end;">
            <button class="btn btn-outline-secondary" onclick="limpiarFiltros()">
                <i class="fas fa-times me-1"></i>Limpiar
            </button>
        </div>
    </div>

    <!-- BOTÓN DE ESTADÍSTICAS -->
    <div class="mb-3 fade-in">
        <button class="btn btn-stats" onclick="abrirModalGrafica()">
            <i class="fas fa-chart-pie"></i> Ver <?= htmlspecialchars($datosGraficaMateria['materia'] ?? 'Analítica') ?>
        </button>
    </div>

    <!-- BÚSQUEDA -->
    <div class="search-box fade-in">
        <i class="fas fa-search"></i>
        <input type="text" id="searchInput" class="form-control form-control-lg"
               placeholder="Buscar por alumno, archivo o ID..."
               onkeyup="filtrarTabla()">
    </div>

    <!-- TABLA DE ARCHIVOS -->
    <div class="backup-table fade-in">
        <?php if ($totalArchivos > 0): ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0" id="backupTable">
                <thead>
                    <tr>
                        <th style="width: 5%;">#</th>
                        <th style="width: 22%;">Alumno</th>
                        <th style="width: 15%;">Grupo</th>
                        <th style="width: 12%;">Turno</th>
                        <th style="width: 28%;">Archivo</th>
                        <th style="width: 10%;">Tipo</th>
                        <th style="width: 13%;">Fecha</th>
                        <th style="width: 10%;" class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; foreach ($archivos as $archivo):
                        $nombreAlumno = $nombresAlumnos[$archivo['id_alumno']] ?? 'Desconocido';
                    ?>
                    <tr data-nombre="<?= strtolower(htmlspecialchars($archivo['nombre'])) ?>"
                        data-alumno="<?= strtolower(htmlspecialchars($nombreAlumno)) ?>"
                        data-id="<?= htmlspecialchars($archivo['id_alumno']) ?>">
                        <td><strong class="text-muted"><?= $i++ ?></strong></td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <i class="fas fa-user-circle text-secondary fs-5"></i>
                                <div>
                                    <strong class="d-block"><?= htmlspecialchars($nombreAlumno) ?></strong>
                                    <?php if ($archivo['id_alumno']): ?>
                                    <small class="text-muted">ID: <?= htmlspecialchars($archivo['id_alumno']) ?></small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="badge-grupo">
                                <i class="fas fa-users me-1"></i>
                                <?= htmlspecialchars("$gradoNormalizado $grupoRomano") ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge-turno">
                                <i class="fas fa-sun me-1"></i>
                                <?= htmlspecialchars($turnoNormalizado) ?>
                            </span>
                        </td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <i class="fas fa-file-pdf text-danger"></i>
                                <small class="text-truncate" style="max-width: 180px;" title="<?= htmlspecialchars($archivo['nombre']) ?>">
                                    <?= htmlspecialchars($archivo['nombre']) ?>
                                </small>
                            </div>
                        </td>
                        <td>
                            <span class="badge-tipo badge-<?= strtolower($archivo['tipo']) ?>">
                                <i class="fas fa-<?= $archivo['tipo'] === 'Final' ? 'check' : 'clock' ?>"></i>
                                <?= htmlspecialchars($archivo['tipo']) ?>
                            </span>
                        </td>
                        <td>
                            <span class="fecha-badge">
                                <i class="far fa-calendar-alt"></i>
                                <?= date('d/m/Y', (int)$archivo['fecha']) ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <?php if ($archivo['existe']): ?>
                                <button type="button"
                                        class="btn btn-action btn-view"
                                        title="Vista previa"
                                        onclick="abrirPreviewPDF('<?= addslashes($archivo['ruta_web']) ?>', '<?= addslashes($archivo['nombre']) ?>')">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <a href="<?= htmlspecialchars($archivo['ruta_web']) ?>"
                                   download="<?= htmlspecialchars($archivo['nombre']) ?>"
                                   class="btn btn-action btn-download"
                                   title="Descargar PDF">
                                    <i class="fas fa-download"></i>
                                </a>
                            <?php else: ?>
                                <span class="text-muted small" title="Archivo no accesible">
                                    <i class="fas fa-exclamation-triangle"></i>
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state fade-in">
            <i class="fas fa-folder-open"></i>
            <h4 class="mb-3">No hay respaldos disponibles</h4>
            <p class="mb-0 text-muted">
                <?php if (!is_dir($rutaCompleta)): ?>
                    <strong>La carpeta no existe.</strong><br>
                    <small>Verifica que <code>generar_respaldo_grupal.php</code> haya creado los archivos.</small>
                <?php else: ?>
                    Los archivos PDF generados aparecerán aquí automáticamente.
                <?php endif; ?>
            </p>
            <a href="boleta_alumnos_nueva.php?grado=<?= urlencode($grado) ?>&grupo=<?= urlencode($grupo) ?>&turno=<?= urlencode($turno) ?>"
               class="btn btn-primary mt-4"
               style="background: linear-gradient(135deg, #0f6fff, #14f1f8); border: none; padding: 12px 35px; border-radius: 50px;">
                <i class="fas fa-plus me-2"></i>Generar Primer Respaldo
            </a>
        </div>
        <?php endif; ?>
    </div>

</div>
</div>

<!-- ============================================================
     MODAL DE VISTA PREVIA DE PDF
     ============================================================ -->
<div class="modal fade" id="modalPreviewPDF" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="previewTitle">
                    <i class="fas fa-file-pdf me-2"></i>Vista Previa
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-0">
                <div class="pdf-preview-container">
                    <iframe id="pdfFrame" title="Vista previa del PDF" src=""></iframe>
                    <div id="pdfFallback" class="pdf-fallback d-none">
                        <i class="fas fa-exclamation-circle fs-1 mb-3"></i>
                        <p class="mb-3">Tu navegador no puede mostrar este PDF.</p>
                        <a id="pdfDownloadLink" href="#" target="_blank" class="btn btn-light">
                            <i class="fas fa-download me-2"></i>Descargar PDF
                        </a>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <a id="pdfDownloadBtn" href="#" download class="btn btn-success">
                    <i class="fas fa-download me-2"></i>Descargar
                </a>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================
     MODAL GRÁFICA DOUGHNUT - MATERIA MÁS APROBADA
     ============================================================ -->
<div class="modal fade" id="modalGrafica" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 20px;">
            <div class="modal-header" style="background: linear-gradient(135deg, #1a355e, #2b91ff); color: white;">
                <h5 class="modal-title">
                    <i class="fas fa-chart-pie me-2"></i><span id="graficaMateriaTitulo">Analítica</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-3">
                    <span class="badge bg-light text-dark px-3 py-2">
                        <?= htmlspecialchars("{$grado}º {$grupoRomano} {$turno}") ?>
                    </span>
                </div>
                <div class="chart-container">
                    <canvas id="graficaCircular"></canvas>
                </div>
                <div class="d-flex justify-content-center gap-4 mt-3 flex-wrap">
                    <div class="text-center">
                        <span class="badge bg-success px-3 py-2">
                            <i class="fas fa-check-circle me-1"></i>
                            <strong id="legendAprobados"><?= (int)($datosGraficaMateria['aprobados'] ?? 0) ?></strong> Aprobados
                        </span>
                    </div>
                    <div class="text-center">
                        <span class="badge bg-danger px-3 py-2">
                            <i class="fas fa-times-circle me-1"></i>
                            <strong id="legendReprobados"><?= (int)($datosGraficaMateria['reprobados'] ?? 0) ?></strong> Reprobados
                        </span>
                    </div>
                </div>
                <?php if (empty($datosGraficaMateria['total']) || (int)$datosGraficaMateria['total'] === 0): ?>
                <div class="alert alert-info mt-3 mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    No hay datos de calificaciones para mostrar la gráfica de esta materia.
                </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script>
// ============================================================
// BOOTSTRAP: si el header no lo cargó, lo cargamos aquí (sin duplicar)
// ============================================================
function ensureBootstrapReady(cb){
    if (window.bootstrap && window.bootstrap.Modal) return cb();
    const s = document.createElement('script');
    s.src = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js';
    s.onload = cb;
    document.head.appendChild(s);
}

// ============================================================
// FILTRADO Y BÚSQUEDA
// ============================================================
function aplicarFiltro(tipo, valor) {
    const url = new URL(window.location.href);
    if (valor) url.searchParams.set(tipo, valor);
    else url.searchParams.delete(tipo);
    window.location.href = url.toString();
}

function limpiarFiltros() {
    const url = new URL(window.location.href);
    url.searchParams.delete('anio');
    url.searchParams.delete('grupo_filtro');
    url.searchParams.delete('turno_filtro');
    window.location.href = url.toString();
}

function filtrarTabla() {
    const input = document.getElementById('searchInput');
    const filter = (input.value || '').toLowerCase().trim();
    const table = document.getElementById('backupTable');
    if (!table) return;

    const rows = table.getElementsByTagName('tr');
    for (let i = 1; i < rows.length; i++) {
        const nombre = rows[i].getAttribute('data-nombre') || '';
        const alumno = rows[i].getAttribute('data-alumno') || '';
        const id = rows[i].getAttribute('data-id') || '';
        const match = nombre.includes(filter) || alumno.includes(filter) || id.includes(filter);
        rows[i].style.display = match ? '' : 'none';
    }
}

// ============================================================
// VISTA PREVIA DE PDF
// ============================================================
function abrirPreviewPDF(rutaWeb, nombreArchivo) {
    ensureBootstrapReady(() => {
        const modal = new bootstrap.Modal(document.getElementById('modalPreviewPDF'));
        const iframe = document.getElementById('pdfFrame');
        const fallback = document.getElementById('pdfFallback');
        const downloadLink = document.getElementById('pdfDownloadLink');
        const downloadBtn = document.getElementById('pdfDownloadBtn');
        const title = document.getElementById('previewTitle');

        title.innerHTML = `<i class="fas fa-file-pdf me-2"></i>${nombreArchivo}`;

        downloadLink.href = rutaWeb;
        downloadLink.download = nombreArchivo;
        downloadBtn.href = rutaWeb;
        downloadBtn.setAttribute('download', nombreArchivo);

        iframe.src = rutaWeb;
        iframe.classList.remove('d-none');
        fallback.classList.add('d-none');

        iframe.onerror = function() {
            iframe.classList.add('d-none');
            fallback.classList.remove('d-none');
        };

        setTimeout(() => {
            try {
                // Si el iframe bloquea por CORS, caemos al fallback
                void iframe.contentDocument;
            } catch (e) {
                iframe.classList.add('d-none');
                fallback.classList.remove('d-none');
            }
        }, 1500);

        modal.show();

        document.getElementById('modalPreviewPDF').addEventListener('hidden.bs.modal', function handler() {
            iframe.src = '';
            this.removeEventListener('hidden.bs.modal', handler);
        }, { once: true });
    });
}

// ============================================================
// GRÁFICA DOUGHNUT - MATERIA MÁS APROBADA
// ============================================================
function abrirModalGrafica() {
    ensureBootstrapReady(() => {
        const datos = <?= json_encode($datosGraficaMateria, JSON_HEX_TAG | JSON_HEX_AMP) ?>;
        const modalEl = document.getElementById('modalGrafica');
        const tituloEl = document.getElementById('graficaMateriaTitulo');

        if (datos.materia) tituloEl.textContent = datos.materia;

        const ctx = document.getElementById('graficaCircular');
        if (!ctx) return;

        if (!datos.total || datos.total === 0) {
            ctx.parentNode.innerHTML =
                '<p class="text-center text-muted py-5"><i class="fas fa-chart-pie fs-1 mb-3 d-block"></i>No hay datos para la gráfica.</p>';
            new bootstrap.Modal(modalEl).show();
            return;
        }

        if (window.graficaCircularInstance) window.graficaCircularInstance.destroy();

        window.graficaCircularInstance = new Chart(ctx.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Aprobados', 'Reprobados'],
                datasets: [{
                    data: [
                        <?= (int)($datosGraficaMateria['aprobados'] ?? 0) ?>,
                        <?= (int)($datosGraficaMateria['reprobados'] ?? 0) ?>
                    ],
                    backgroundColor: ['#28a745', '#dc3545'],
                    borderColor: '#ffffff',
                    borderWidth: 3,
                    hoverOffset: 10
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { padding: 15, font: { size: 11, weight: '500' }, usePointStyle: true }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(26, 53, 94, 0.95)',
                        titleFont: { size: 13, weight: '600' },
                        bodyFont: { size: 12 },
                        padding: 12,
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw || 0;
                                const total = <?= (int)($datosGraficaMateria['total'] ?? 1) ?>;
                                const percentage = total > 0 ? Math.round((value/total)*100) : 0;
                                return `${label}: ${value} alumno(s) (${percentage}%)`;
                            }
                        }
                    }
                },
                animation: { animateScale: true, animateRotate: true, duration: 800 }
            }
        });

        new bootstrap.Modal(modalEl).show();

        modalEl.addEventListener('hidden.bs.modal', function handler() {
            if (window.graficaCircularInstance) {
                window.graficaCircularInstance.destroy();
                window.graficaCircularInstance = null;
            }
            this.removeEventListener('hidden.bs.modal', handler);
        }, { once: true });
    });
}

// ============================================================
// INIT
// ============================================================
document.addEventListener('DOMContentLoaded', function() {
    // Tooltips si Bootstrap está listo (si no, se activan al cargar)
    ensureBootstrapReady(() => {
        if (bootstrap && bootstrap.Tooltip) {
            document.querySelectorAll('[title]').forEach(el => {
                if (!el.getAttribute('data-bs-toggle')) new bootstrap.Tooltip(el, { trigger: 'hover' });
            });
        }
    });
});
</script>

<?php
// ✅ FOOTER AL FINAL (para no romper modales/scripts)
require_once __DIR__ . '/footer_orientador.php';