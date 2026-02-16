<?php
// historial_respaldos.php — GESTIÓN DE HISTORIAL DE RESPALDOS
session_start();
if (!isset($_SESSION['id_credencial'])) {
    header("Location: ../login.php");
    exit();
}

include '../funciones/conexQRConejo.php';

$secretKey = 'your-secret-key';
$grado = $_GET['grado'] ?? '';
$grupo = $_GET['grupo'] ?? '';
$turno = $_GET['turno'] ?? '';
$anioFiltro = $_GET['anio'] ?? ''; // ← NUEVO: Filtro por año

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
// FUNCIONES AUXILIARES (IDÉNTICAS A generar_respaldo_grupal.php)
// ============================================================

function decryptData($data, $key) {
    if (empty($data)) return '';
    $parts = explode('::', base64_decode($data), 2);
    if (count($parts) !== 2) return '—';
    [$cipher, $iv] = $parts;
    return openssl_decrypt($cipher, 'aes-256-cbc', $key, 0, base64_decode($iv));
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

// ── PROCESAR ELIMINACIÓN ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eliminar'])) {
    $archivo = basename($_POST['eliminar']);
    
    $rutaBase = __DIR__ . '/respaldos/boletas/';
    $gradoNormalizado = normalizarGrado($grado);
    $grupoRomano = convertirGrupoARomano($grupo);
    $nombreCarpetaGrupo = $gradoNormalizado . ' ' . $grupoRomano;
    $rutaCompleta = $rutaBase . $id_escuela . '/grupos/' . $nombreCarpetaGrupo . '/';
    $rutaArchivo = $rutaCompleta . $archivo;
    
    if (file_exists($rutaArchivo) && is_writable($rutaArchivo)) {
        unlink($rutaArchivo);
        $redirect = "historial_respaldos.php?grado=" . urlencode($grado) . 
               "&grupo=" . urlencode($grupo) . "&turno=" . urlencode($turno) . "&eliminado=1";
        if (!empty($anioFiltro)) {
            $redirect .= "&anio=" . urlencode($anioFiltro);
        }
        header("Location: $redirect");
        exit();
    }
}

$grupo_romano = convertirGrupoARomano($grupo);

// ============================================================
// CONSTRUIR RUTA DE RESPALDOS (IDÉNTICA A generar_respaldo_grupal.php)
// ============================================================

$rutaBase = __DIR__ . '/respaldos/boletas/';
$gradoNormalizado = normalizarGrado($grado);
$grupoRomano = convertirGrupoARomano($grupo);
$nombreCarpetaGrupo = $gradoNormalizado . ' ' . $grupoRomano;
$rutaCompleta = $rutaBase . $id_escuela . '/grupos/' . $nombreCarpetaGrupo . '/';

// ── OBTENER ARCHIVOS PDF ──
$archivos = [];
$aniosDisponibles = []; // ← Para el dropdown

if (is_dir($rutaCompleta)) {
    $scan = scandir($rutaCompleta);
    foreach ($scan as $file) {
        if ($file !== '.' && $file !== '..' && pathinfo($file, PATHINFO_EXTENSION) === 'pdf') {
            $path = $rutaCompleta . $file;
            
            // Extraer año del nombre del archivo (formato: Boleta_Tipo_ID_YYYY-MM-DD_HH-MM-SS.pdf)
            $anioArchivo = '';
            if (preg_match('/_(\d{4})-\d{2}-\d{2}_/', $file, $matches)) {
                $anioArchivo = $matches[1];
                if (!in_array($anioArchivo, $aniosDisponibles)) {
                    $aniosDisponibles[] = $anioArchivo;
                }
            }
            
            $archivos[] = [
                'nombre' => $file,
                'ruta' => $path,
                'tamano' => filesize($path),
                'fecha' => filemtime($path),
                'tipo' => (strpos($file, 'Boleta_Final_') !== false) ? 'Final' : 
                          ((strpos($file, 'Boleta_Parcial_') !== false) ? 'Parcial' : 'Otro'),
                'anio' => $anioArchivo
            ];
        }
    }
    
    // Ordenar años (descendente)
    rsort($aniosDisponibles);
    
    // Ordenar archivos por fecha (más reciente primero)
    usort($archivos, function($a, $b) {
        return $b['fecha'] - $a['fecha'];
    });
}

// ── APLICAR FILTRO POR AÑO ──
$archivosFiltrados = $archivos;
if (!empty($anioFiltro)) {
    $archivosFiltrados = array_filter($archivos, function($a) use ($anioFiltro) {
        return $a['anio'] === $anioFiltro;
    });
}

$totalArchivos = count($archivosFiltrados);
$totalArchivosSinFiltro = count($archivos);
$boletasFinales = count(array_filter($archivosFiltrados, fn($a) => $a['tipo'] === 'Final'));
$boletasParciales = count(array_filter($archivosFiltrados, fn($a) => $a['tipo'] === 'Parcial'));
$mbTotales = $totalArchivos > 0 ? round(array_sum(array_column($archivosFiltrados, 'tamano')) / 1024 / 1024, 2) : 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Respaldos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'League Spartan', sans-serif;
            background: #f8f9fa;
            padding: 20px;
        }
        .container { max-width: 1400px; }
        
        .header-title {
            text-align: center;
            margin-bottom: 30px;
            color: #1a355e;
            font-size: 2rem;
            font-weight: bold;
            margin-top: 2em;
        }
        
        .info-header-wrapper {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding: 15px 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }
        
        .btn-back {
            background: linear-gradient(135deg, #6c757d, #495057);
            border: none;
            color: white;
            font-weight: bold;
            padding: 10px 20px;
            border-radius: 25px;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
        }
        .btn-back:hover {
            background: linear-gradient(135deg, #5a6268, #343a40);
            color: white;
            transform: translateY(-2px);
        }
        
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            border-left: 4px solid #2b91ff;
        }
        .stat-card.final { border-left-color: #28a745; }
        .stat-card.parcial { border-left-color: #ffc107; }
        .stat-card.deleted { border-left-color: #dc3545; }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: #1a355e;
        }
        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
            margin-top: 5px;
        }
        
        .backup-table {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }
        
        .table thead {
            background: linear-gradient(135deg, #1a355e, #2b91ff);
            color: white;
        }
        
        .table tbody tr:hover {
            background: #f8f9fa;
        }
        
        .badge-tipo {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .badge-final {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
        }
        .badge-parcial {
            background: linear-gradient(135deg, #ffc107, #ff9800);
            color: #333;
        }
        
        .btn-action {
            padding: 5px 10px;
            border-radius: 8px;
            margin: 0 2px;
            transition: all 0.2s;
        }
        .btn-view {
            background: #2b91ff;
            color: white;
            border: none;
        }
        .btn-view:hover { background: #1a78e6; color: white; }
        
        .btn-download {
            background: #28a745;
            color: white;
            border: none;
        }
        .btn-download:hover { background: #218838; color: white; }
        
        .btn-delete {
            background: #dc3545;
            color: white;
            border: none;
        }
        .btn-delete:hover { background: #c82333; color: white; }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            color: #dee2e6;
        }
        
        .search-box {
            max-width: 400px;
            margin-bottom: 20px;
        }
        
        .alert-custom {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            border: 2px solid #28a745;
            color: #155724;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 600;
        }
        
        .file-size {
            color: #6c757d;
            font-size: 0.85rem;
        }
        
        .fecha-badge {
            background: #e9ecef;
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 0.85rem;
            color: #495057;
        }
        
        .filter-wrapper {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 20px;
            align-items: center;
        }
        
        .filter-select {
            min-width: 150px;
            border-radius: 10px;
            border: 2px solid #dee2e6;
            padding: 8px 15px;
            font-weight: 500;
            background: white;
            cursor: pointer;
            transition: all 0.3s;
        }
        .filter-select:focus {
            border-color: #2b91ff;
            box-shadow: 0 0 0 3px rgba(43, 145, 255, 0.15);
            outline: none;
        }
        .filter-select:hover {
            border-color: #2b91ff;
        }
        
        .badge-anio {
            background: linear-gradient(135deg, #6f42c1, #a66efa);
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .debug-info {
            background: #fff3cd;
            border: 1px solid #ffc107;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-family: monospace;
            font-size: 0.85rem;
        }
    </style>
</head>
<body>
<div class="container">

    <?php if (isset($_GET['eliminado'])): ?>
    <div class="alert-custom">
        ✅ Archivo eliminado correctamente
    </div>
    <?php endif; ?>

    <div class="header-title">
        <i class="fas fa-history me-2"></i>Historial de Respaldos
    </div>

    <!-- INFO + BOTÓN VOLVER -->
    <div class="info-header-wrapper">
        <div>
            <strong>Escuela:</strong> <?= htmlspecialchars($nombre_escuela) ?><br>
            <strong>Grado:</strong> <?= htmlspecialchars($grado) ?> |
            <strong>Grupo:</strong> <?= htmlspecialchars($grupo_romano) ?> |
            <strong>Turno:</strong> <?= htmlspecialchars($turno) ?>
        </div>
        <div>
            <a href="boleta_alumnos_nueva.php?grado=<?= urlencode($grado) ?>&grupo=<?= urlencode($grupo) ?>&turno=<?= urlencode($turno) ?>"
               class="btn-back">
                <i class="fas fa-arrow-left me-2"></i>Volver a Boletas
            </a>
        </div>
    </div>

    <!-- FILTROS: AÑO + BÚSQUEDA -->
    <div class="filter-wrapper">
        <!-- Filtro por Año -->
        <div>
            <label for="filtroAnio" class="form-label fw-semibold mb-1">
                <i class="fas fa-calendar-alt me-1"></i>Filtrar por Año:
            </label>
            <select id="filtroAnio" class="form-select filter-select" onchange="cambiarFiltroAnio()">
                <option value="">Todos los años</option>
                <?php foreach ($aniosDisponibles as $anio): ?>
                <option value="<?= htmlspecialchars($anio) ?>" 
                        <?= $anioFiltro === $anio ? 'selected' : '' ?>>
                    <?= htmlspecialchars($anio) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <!-- Contador de archivos filtrados -->
        <?php if (!empty($anioFiltro)): ?>
        <div class="d-flex align-items-end mb-2">
            <span class="badge-anio">
                <i class="fas fa-filter me-1"></i>
                Filtrado: <?= $totalArchivos ?> de <?= $totalArchivosSinFiltro ?> archivos
            </span>
        </div>
        <?php endif; ?>
        
        <!-- Búsqueda -->
        <div class="ms-auto">
            <label for="searchInput" class="form-label fw-semibold mb-1">
                <i class="fas fa-search me-1"></i>Buscar:
            </label>
            <input type="text" 
                   id="searchInput" 
                   class="form-control filter-select" 
                   style="min-width: 300px;"
                   placeholder="Nombre de alumno o ID..."
                   onkeyup="filtrarTabla()">
        </div>
    </div>

    <!-- DEBUG INFO (para verificar ruta) -->
    <?php if (isset($_GET['debug'])): ?>
    <div class="debug-info">
        <strong>🔍 Ruta de búsqueda:</strong><br>
        <?= htmlspecialchars($rutaCompleta) ?><br>
        <strong>¿Existe?</strong> <?= is_dir($rutaCompleta) ? '✅ Sí' : '❌ No' ?><br>
        <strong>Años disponibles:</strong> <?= implode(', ', $aniosDisponibles) ?: 'Ninguno' ?><br>
        <strong>Archivos encontrados:</strong> <?= $totalArchivosSinFiltro ?><br>
        <strong>Archivos filtrados:</strong> <?= $totalArchivos ?>
    </div>
    <?php endif; ?>

    <!-- TARJETAS DE ESTADÍSTICAS -->
    <div class="stats-cards">
        <div class="stat-card">
            <div class="stat-number"><?= $totalArchivos ?></div>
            <div class="stat-label"><i class="fas fa-file-pdf me-1"></i>Total Archivos</div>
        </div>
        <div class="stat-card final">
            <div class="stat-number"><?= $boletasFinales ?></div>
            <div class="stat-label"><i class="fas fa-check-circle me-1"></i>Boletas Finales</div>
        </div>
        <div class="stat-card parcial">
            <div class="stat-number"><?= $boletasParciales ?></div>
            <div class="stat-label"><i class="fas fa-clock me-1"></i>Boletas Parciales</div>
        </div>
        <div class="stat-card deleted">
            <div class="stat-number"><?= $mbTotales ?></div>
            <div class="stat-label"><i class="fas fa-database me-1"></i>MB Totales</div>
        </div>
    </div>

    <!-- TABLA DE ARCHIVOS -->
    <div class="backup-table">
        <?php if ($totalArchivos > 0): ?>
        <table class="table table-hover mb-0" id="backupTable">
            <thead>
                <tr>
                    <th style="width: 5%;">#</th>
                    <th style="width: 35%;">Nombre del Archivo</th>
                    <th style="width: 12%;">Año</th>
                    <th style="width: 13%;">Tipo</th>
                    <th style="width: 10%;">Tamaño</th>
                    <th style="width: 12%;">Fecha</th>
                    <th style="width: 15%;" class="text-center">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php $i = 1; foreach ($archivosFiltrados as $archivo): ?>
                <tr data-nombre="<?= strtolower(htmlspecialchars($archivo['nombre'])) ?>" 
                    data-anio="<?= htmlspecialchars($archivo['anio']) ?>">
                    <td><?= $i++ ?></td>
                    <td>
                        <i class="fas fa-file-pdf text-danger me-2"></i>
                        <strong><?= htmlspecialchars($archivo['nombre']) ?></strong>
                    </td>
                    <td>
                        <span class="badge bg-secondary"><?= htmlspecialchars($archivo['anio']) ?></span>
                    </td>
                    <td>
                        <span class="badge-tipo badge-<?= strtolower($archivo['tipo']) ?>">
                            <?= $archivo['tipo'] ?>
                        </span>
                    </td>
                    <td class="file-size">
                        <?= round($archivo['tamano'] / 1024, 2) ?> KB
                    </td>
                    <td>
                        <span class="fecha-badge">
                            <i class="far fa-calendar-alt me-1"></i>
                            <?= date('d/m/Y H:i', $archivo['fecha']) ?>
                        </span>
                    </td>
                    <td class="text-center">
                        <a href="<?= htmlspecialchars($archivo['ruta']) ?>" 
                           target="_blank" 
                           class="btn btn-action btn-view" 
                           title="Ver PDF">
                            <i class="fas fa-eye"></i>
                        </a>
                        <a href="<?= htmlspecialchars($archivo['ruta']) ?>" 
                           download 
                           class="btn btn-action btn-download" 
                           title="Descargar">
                            <i class="fas fa-download"></i>
                        </a>
                        <form method="POST" style="display:inline;" 
                              onsubmit="return confirm('¿Eliminar este archivo?\n\n<?= addslashes($archivo['nombre']) ?>\n\nEsta acción no se puede deshacer.');">
                            <input type="hidden" name="eliminar" value="<?= htmlspecialchars($archivo['nombre']) ?>">
                            <button type="submit" class="btn btn-action btn-delete" title="Eliminar">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-folder-open"></i>
            <h4>No hay respaldos disponibles</h4>
            <p class="mb-0">
                <?php if (!empty($anioFiltro)): ?>
                    No hay archivos para el año <strong><?= htmlspecialchars($anioFiltro) ?></strong>.
                    <br>
                    <a href="?grado=<?= urlencode($grado) ?>&grupo=<?= urlencode($grupo) ?>&turno=<?= urlencode($turno) ?>"
                       class="btn btn-outline-primary btn-sm mt-2">
                        <i class="fas fa-times me-1"></i>Quitar filtro de año
                    </a>
                <?php else: ?>
                    Los archivos PDF generados aparecerán aquí automáticamente.
                <?php endif; ?>
            </p>
            <a href="boleta_alumnos_nueva.php?grado=<?= urlencode($grado) ?>&grupo=<?= urlencode($grupo) ?>&turno=<?= urlencode($turno) ?>"
               class="btn btn-primary mt-3"
               style="background: linear-gradient(135deg, #0f6fff, #14f1f8); border: none; padding: 12px 30px; border-radius: 50px;">
                <i class="fas fa-plus me-2"></i>Generar Primer Respaldo
            </a>
            <!-- Botón de debug -->
            <br>
            <a href="?grado=<?= urlencode($grado) ?>&grupo=<?= urlencode($grupo) ?>&turno=<?= urlencode($turno) ?>&debug=1"
               class="btn btn-outline-warning mt-2 btn-sm">
                🔍 Verificar Ruta (Debug)
            </a>
        </div>
        <?php endif; ?>
    </div>

</div><!-- /.container -->
<br>
<?php include 'footer_orientador.php'; ?>

<!-- SCRIPT DE FILTROS -->
<script>
// Cambiar filtro de año (recarga la página con el parámetro)
function cambiarFiltroAnio() {
    const anio = document.getElementById('filtroAnio').value;
    const url = new URL(window.location.href);
    
    if (anio) {
        url.searchParams.set('anio', anio);
    } else {
        url.searchParams.delete('anio');
    }
    
    window.location.href = url.toString();
}

// Filtrar tabla por búsqueda de texto
function filtrarTabla() {
    const input = document.getElementById('searchInput');
    const filter = input.value.toLowerCase();
    const table = document.getElementById('backupTable');
    if (!table) return;
    const tr = table.getElementsByTagName('tr');
    
    for (let i = 1; i < tr.length; i++) {
        const nombre = tr[i].getAttribute('data-nombre');
        tr[i].style.display = nombre && nombre.includes(filter) ? '' : 'none';
    }
}
</script>

</body>
</html>
<?php mysqli_close($conexion); ?>