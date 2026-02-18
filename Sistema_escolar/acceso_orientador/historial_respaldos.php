<?php
// historial_respaldos.php — HISTORIAL DE RESPALDOS · VISTA DE CARPETAS
session_start();
if (!isset($_SESSION['id_credencial'])) {
    header("Location: ../login.php");
    exit();
}

include '../funciones/conexQRConejo.php';

// ── Obtener escuela del usuario ──
$stmt = mysqli_prepare($conexion, "SELECT id_escuela FROM credenciales WHERE id_credencial = ?");
mysqli_stmt_bind_param($stmt, "i", $_SESSION['id_credencial']);
mysqli_stmt_execute($stmt);
$id_escuela = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['id_escuela'] ?? 0;

$stmt = mysqli_prepare($conexion, "SELECT nombre_escuela FROM escuelas WHERE id_escuela = ?");
mysqli_stmt_bind_param($stmt, "i", $id_escuela);
mysqli_stmt_execute($stmt);
$nombre_escuela = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['nombre_escuela'] ?? '';

// Parámetros de origen (para el botón "Volver")
$grado = $_GET['grado'] ?? '';
$grupo = $_GET['grupo'] ?? '';
$turno = $_GET['turno'] ?? '';

// ── Funciones auxiliares ──
function convertirGrupoARomano($g) {
    $m = ['A'=>'I','B'=>'II','C'=>'III','D'=>'IV','E'=>'V','F'=>'VI',
          'G'=>'VII','H'=>'VIII','I'=>'IX','J'=>'X','K'=>'XI','L'=>'XII',
          'M'=>'XIII','N'=>'XIV','O'=>'XV','P'=>'XVI','Q'=>'XVII',
          'R'=>'XVIII','S'=>'XIX','T'=>'XX'];
    $g = strtoupper(trim($g));
    return $m[$g] ?? $g;
}

function normalizarGrado($grado) {
    $m = ['1'=>'Primero','2'=>'Segundo','3'=>'Tercero','4'=>'Cuarto','5'=>'Quinto','6'=>'Sexto',
          '1°'=>'Primero','2°'=>'Segundo','3°'=>'Tercero','4°'=>'Cuarto','5°'=>'Quinto','6°'=>'Sexto',
          'primero'=>'Primero','segundo'=>'Segundo','tercero'=>'Tercero',
          'cuarto'=>'Cuarto','quinto'=>'Quinto','sexto'=>'Sexto',
          'PRIMERO'=>'Primero','SEGUNDO'=>'Segundo','TERCERO'=>'Tercero',
          'CUARTO'=>'Cuarto','QUINTO'=>'Quinto','SEXTO'=>'Sexto'];
    $grado = trim($grado);
    return $m[$grado] ?? ucfirst(strtolower($grado));
}

function pesoGrado($nombre) {
    $pesos = ['Primero'=>1,'Segundo'=>2,'Tercero'=>3,'Cuarto'=>4,'Quinto'=>5,'Sexto'=>6];
    return $pesos[$nombre] ?? 99;
}

// ============================================================
// ESCANEAR TODAS LAS CARPETAS DE LA ESCUELA
// Nueva estructura: respaldos/boletas/{ID}/[GENERACIÓN]/[TURNO]/grupos/{GRADO GRUPO}/
// ============================================================
$rutaBaseEscuela = __DIR__ . '/respaldos/boletas/' . $id_escuela . '/';
$carpetas = [];

if (is_dir($rutaBaseEscuela)) {
    // Escanear todas las generaciones
    $generaciones = array_diff(scandir($rutaBaseEscuela), ['.', '..']);
    
    foreach ($generaciones as $generacion) {
        $rutaGeneracion = $rutaBaseEscuela . $generacion . '/';
        if (!is_dir($rutaGeneracion)) continue;
        
        // Escanear todos los turnos dentro de la generación
        $turnos = array_diff(scandir($rutaGeneracion), ['.', '..']);
        
        foreach ($turnos as $turno) {
            $rutaTurno = $rutaGeneracion . $turno . '/';
            if (!is_dir($rutaTurno)) continue;
            
            // Verificar que exista la carpeta 'grupos'
            $rutaGrupos = $rutaTurno . 'grupos/';
            if (!is_dir($rutaGrupos)) continue;
            
            // Escanear carpetas de grupos
            $gruposEnTurno = array_diff(scandir($rutaGrupos), ['.', '..']);
            
            foreach ($gruposEnTurno as $nombreCarpetaGrupo) {
                $rutaCarpeta = $rutaGrupos . $nombreCarpetaGrupo . '/';
                if (!is_dir($rutaCarpeta)) continue;

                $archivosEnCarpeta = [];
                $aniosEnCarpeta = [];

                foreach (array_diff(scandir($rutaCarpeta), ['.','..']) as $file) {
                    if (pathinfo($file, PATHINFO_EXTENSION) !== 'pdf') continue;

                    $rutaArchivo = $rutaCarpeta . $file;
                    $anio = '';
                    if (preg_match('/_(\d{4})-\d{2}-\d{2}_/', $file, $m)) {
                        $anio = $m[1];
                        if (!in_array($anio, $aniosEnCarpeta)) $aniosEnCarpeta[] = $anio;
                    }

                    $archivosEnCarpeta[] = [
                        'nombre'     => $file,
                        'tamano'     => filesize($rutaArchivo),
                        'fecha'      => filemtime($rutaArchivo),
                        'tipo'       => str_contains($file, 'Boleta_Final_')   ? 'Final'
                                      : (str_contains($file, 'Boleta_Parcial_') ? 'Parcial' : 'Otro'),
                        'anio'       => $anio,
                        'carpeta'    => $nombreCarpetaGrupo,
                        'generacion' => $generacion,
                        'turno'      => $turno,
                    ];
                }

                // Más reciente primero
                usort($archivosEnCarpeta, fn($a,$b) => $b['fecha'] - $a['fecha']);
                rsort($aniosEnCarpeta);

                // Extraer grado del primer token (ej. "Primero I" → "Primero")
                $partes = explode(' ', $nombreCarpetaGrupo, 2);
                $gradoCarpeta = normalizarGrado($partes[0] ?? $nombreCarpetaGrupo);
                $grupoCarpeta = $partes[1] ?? '';

                // Clave única combinando generación + turno + grupo
                $claveUnica = $generacion . ' · ' . $turno . ' · ' . $nombreCarpetaGrupo;

                $carpetas[$claveUnica] = [
                    'label'       => $nombreCarpetaGrupo . ' (' . $turno . ' - ' . $generacion . ')',
                    'grado_texto' => $gradoCarpeta,
                    'grupo_texto' => $grupoCarpeta,
                    'grado_peso'  => pesoGrado($gradoCarpeta),
                    'generacion'  => $generacion,
                    'turno'       => $turno,
                    'archivos'    => $archivosEnCarpeta,
                    'anios'       => $aniosEnCarpeta,
                    'total'       => count($archivosEnCarpeta),
                    'finales'     => count(array_filter($archivosEnCarpeta, fn($a) => $a['tipo'] === 'Final')),
                    'parciales'   => count(array_filter($archivosEnCarpeta, fn($a) => $a['tipo'] === 'Parcial')),
                ];
            }
        }
    }
}

// Ordenar: primero por grado (numérico), luego por grupo (alfabético)
uasort($carpetas, function($a, $b) {
    if ($a['grado_peso'] !== $b['grado_peso']) return $a['grado_peso'] - $b['grado_peso'];
    return strcmp($a['grupo_texto'], $b['grupo_texto']);
});

$totalCarpetas        = count($carpetas);
$totalArchivosGlobal  = array_sum(array_column($carpetas, 'total'));
$totalFinalesGlobal   = array_sum(array_column($carpetas, 'finales'));
$totalParcialesGlobal = array_sum(array_column($carpetas, 'parciales'));

mysqli_close($conexion);
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
        /* ──────────────── BASE ──────────────── */
        body {
            font-family: 'League Spartan', sans-serif;
            background: #f0f4ff;
            padding: 24px 20px;
        }
        .container { max-width: 1200px; }

        /* ──────────────── ENCABEZADO ──────────────── */
        .page-title {
            text-align: center;
            color: #1a355e;
            font-size: 1.9rem;
            font-weight: 700;
            margin: 10px 0 24px;
        }
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: white;
            border-radius: 16px;
            padding: 18px 24px;
            margin-bottom: 24px;
            box-shadow: 0 2px 12px rgba(26,53,94,.08);
            flex-wrap: wrap;
            gap: 12px;
        }
        .school-info { line-height: 1.7; color: #444; }
        .school-info strong { color: #1a355e; font-size: 1.05rem; }

        .btn-back {
            background: linear-gradient(135deg, #6c757d, #495057);
            border: none;
            color: white;
            font-weight: 600;
            padding: 9px 22px;
            border-radius: 50px;
            text-decoration: none;
            font-size: .88rem;
            transition: all .25s;
            white-space: nowrap;
        }
        .btn-back:hover {
            background: linear-gradient(135deg,#5a6268,#343a40);
            color: white;
            transform: translateY(-1px);
        }

        /* ──────────────── STATS ──────────────── */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(170px,1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .stat-card {
            background: white;
            border-radius: 14px;
            padding: 18px 16px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,.07);
            border-top: 4px solid #2b91ff;
        }
        .stat-card.st-grupos  { border-top-color: #6f42c1; }
        .stat-card.st-final   { border-top-color: #28a745; }
        .stat-card.st-parcial { border-top-color: #ffc107; }
        .stat-number { font-size: 2.2rem; font-weight: 700; color: #1a355e; line-height: 1; }
        .stat-label  { color: #6c757d; font-size: .82rem; margin-top: 6px; }

        /* ──────────────── BUSCADOR ──────────────── */
        .search-wrap { position: relative; margin-bottom: 22px; }
        .search-wrap .form-control {
            border: 2px solid #dee2e6;
            border-radius: 50px;
            padding: 10px 44px 10px 20px;
            font-size: .9rem;
            font-family: 'League Spartan', sans-serif;
            transition: border-color .25s, box-shadow .25s;
        }
        .search-wrap .form-control:focus {
            border-color: #2b91ff;
            box-shadow: 0 0 0 3px rgba(43,145,255,.15);
            outline: none;
        }
        .search-wrap .search-ico {
            position: absolute;
            right: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: #adb5bd;
            pointer-events: none;
        }

        /* ──────────────── ACORDEÓN DE CARPETAS ──────────────── */
        .folders-list { display: flex; flex-direction: column; gap: 12px; }

        .folder-card {
            background: white;
            border-radius: 14px;
            box-shadow: 0 2px 10px rgba(0,0,0,.07);
            overflow: hidden;
            transition: box-shadow .25s;
        }
        .folder-card:hover { box-shadow: 0 4px 22px rgba(43,145,255,.15); }

        /* Botón/cabecera de carpeta */
        .folder-hdr {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 15px 20px;
            cursor: pointer;
            user-select: none;
            border: none;
            background: transparent;
            width: 100%;
            text-align: left;
            transition: background .2s;
        }
        .folder-hdr:hover         { background: #f8f9ff; }
        .folder-hdr.is-open       { background: #f0f4ff; border-bottom: 1px solid #dce8ff; }

        /* Ícono de carpeta */
        .folder-ico {
            width: 46px; height: 46px;
            border-radius: 12px;
            background: linear-gradient(135deg, #2b91ff, #0f6fff);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.25rem; color: white; flex-shrink: 0;
            box-shadow: 0 3px 10px rgba(43,145,255,.35);
            transition: transform .2s;
        }
        .folder-hdr.is-open .folder-ico { transform: scale(1.07); }

        .folder-label { flex: 1; }
        .folder-name {
            font-size: 1rem; font-weight: 700; color: #1a355e; margin-bottom: 3px;
        }
        .folder-years { font-size: .78rem; color: #6c757d; }

        /* Badges del header de carpeta */
        .folder-badges { display: flex; gap: 7px; align-items: center; flex-shrink: 0; }
        .fbadge {
            font-size: .74rem; font-weight: 600;
            padding: 4px 10px; border-radius: 20px;
        }
        .fbadge-total   { background: #e8f0ff; color: #2b91ff; }
        .fbadge-final   { background: linear-gradient(135deg,#28a745,#20c997); color: white; }
        .fbadge-parcial { background: linear-gradient(135deg,#ffc107,#ff9800); color: #333; }

        /* Chevron */
        .folder-chevron {
            color: #adb5bd; font-size: .85rem; flex-shrink: 0;
            transition: transform .3s;
        }
        .folder-hdr.is-open .folder-chevron {
            transform: rotate(180deg);
            color: #2b91ff;
        }

        /* Cuerpo colapsable */
        .folder-body { display: none; }
        .folder-body.is-open { display: block; }

        /* Tabla interna */
        .inner-table { width: 100%; border-collapse: collapse; }
        .inner-table thead { background: linear-gradient(135deg,#1a355e,#2b91ff); }
        .inner-table thead th {
            color: white; font-size: .78rem; font-weight: 600;
            padding: 9px 14px; letter-spacing: .04em; text-transform: uppercase;
        }
        .inner-table tbody tr {
            border-bottom: 1px solid #f0f4ff;
            transition: background .15s;
        }
        .inner-table tbody tr:last-child { border-bottom: none; }
        .inner-table tbody tr:hover { background: #f8faff; }
        .inner-table tbody td {
            padding: 10px 14px; font-size: .87rem;
            color: #333; vertical-align: middle;
        }

        /* Celda de nombre de archivo */
        .fname-cell { display: flex; align-items: center; gap: 10px; }
        .pdf-dot {
            width: 32px; height: 32px; border-radius: 8px;
            background: #fff0f0; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center;
            color: #dc3545; font-size: .95rem;
        }
        .fname-text { font-weight: 600; color: #1a355e; font-size: .84rem; word-break: break-all; }

        /* Badges tipo dentro de la tabla */
        .tbadge {
            font-size: .73rem; font-weight: 600;
            padding: 3px 10px; border-radius: 20px;
        }
        .tbadge-final   { background: linear-gradient(135deg,#28a745,#20c997); color: white; }
        .tbadge-parcial { background: linear-gradient(135deg,#ffc107,#ff9800); color: #333; }
        .tbadge-otro    { background: #e9ecef; color: #555; }

        .fecha-pill {
            background: #f0f4ff; padding: 3px 10px;
            border-radius: 20px; font-size: .76rem; color: #495057; white-space: nowrap;
        }

        /* Botones de acción */
        .act-btn {
            padding: 5px 12px; border-radius: 8px; border: none;
            font-size: .82rem; cursor: pointer; transition: all .2s;
        }
        .act-btn-view { background: #2b91ff; color: white; }
        .act-btn-view:hover { background: #1a78e6; transform: translateY(-1px); }
        .act-btn-dl   { background: #28a745; color: white; }
        .act-btn-dl:hover { background: #218838; transform: translateY(-1px); }

        /* Estado vacío */
        .empty-global {
            text-align: center; padding: 70px 20px; color: #6c757d;
            background: white; border-radius: 16px;
            box-shadow: 0 2px 10px rgba(0,0,0,.07);
        }
        .empty-global .ei { font-size: 4rem; color: #dee2e6; margin-bottom: 18px; }

        .folder-empty {
            padding: 26px; text-align: center;
            color: #adb5bd; font-size: .88rem;
        }

        @media (max-width: 576px) {
            .col-kb, .folder-badges .fbadge-parcial { display: none; }
        }
    </style>
</head>
<body>
<div class="container">

    <!-- TÍTULO -->
    <div class="page-title">
        <i class="fas fa-history me-2"></i>Historial de Respaldos
    </div>

    <!-- ENCABEZADO -->
    <div class="page-header">
        <div class="school-info">
            <strong><?= htmlspecialchars($nombre_escuela) ?></strong><br>
            <span style="font-size:.88rem; color:#666;">
                <?php if ($grado && $grupo && $turno): ?>
                    Grado <?= htmlspecialchars($grado) ?> &nbsp;·&nbsp;
                    Grupo <?= htmlspecialchars(convertirGrupoARomano($grupo)) ?> &nbsp;·&nbsp;
                    Turno <?= htmlspecialchars($turno) ?>
                <?php else: ?>
                    Todos los grupos
                <?php endif; ?>
            </span>
        </div>
        <?php if ($grado && $grupo && $turno): ?>
        <a href="boleta_alumnos_nueva.php?grado=<?= urlencode($grado) ?>&grupo=<?= urlencode($grupo) ?>&turno=<?= urlencode($turno) ?>"
           class="btn-back">
            <i class="fas fa-arrow-left me-2"></i>Volver a Boletas
        </a>
        <?php endif; ?>
    </div>

    <!-- STATS (sin MB Totales) -->
    <div class="stats-row">
        <div class="stat-card st-grupos">
            <div class="stat-number"><?= $totalCarpetas ?></div>
            <div class="stat-label"><i class="fas fa-folder me-1"></i>Grupos con respaldo</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= $totalArchivosGlobal ?></div>
            <div class="stat-label"><i class="fas fa-file-pdf me-1"></i>Total archivos</div>
        </div>
        <div class="stat-card st-final">
            <div class="stat-number"><?= $totalFinalesGlobal ?></div>
            <div class="stat-label"><i class="fas fa-check-circle me-1"></i>Boletas finales</div>
        </div>
        <div class="stat-card st-parcial">
            <div class="stat-number"><?= $totalParcialesGlobal ?></div>
            <div class="stat-label"><i class="fas fa-clock me-1"></i>Boletas parciales</div>
        </div>
    </div>

    <!-- BUSCADOR -->
    <div class="search-wrap">
        <input type="text"
               id="searchInput"
               class="form-control"
               placeholder="Buscar por nombre de archivo o ID de alumno..."
               oninput="filtrarArchivos(this.value)">
        <i class="fas fa-search search-ico"></i>
    </div>

    <!-- CARPETAS -->
    <?php if ($totalCarpetas > 0): ?>
    <div class="folders-list" id="foldersContainer">

        <?php foreach ($carpetas as $clave => $carpeta):
            $uid = 'fc-' . md5($clave);
        ?>
        <div class="folder-card" data-folder="<?= htmlspecialchars(strtolower($clave)) ?>">

            <!-- Cabecera -->
            <button class="folder-hdr" onclick="toggleFolder(this, '<?= $uid ?>')">

                <div class="folder-ico">
                    <i class="fas fa-folder-open"></i>
                </div>

                <div class="folder-label">
                    <div class="folder-name"><?= htmlspecialchars($carpeta['label']) ?></div>
                    <?php if (!empty($carpeta['anios'])): ?>
                    <div class="folder-years">
                        <i class="far fa-calendar-alt me-1"></i><?= implode(' · ', $carpeta['anios']) ?>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="folder-badges">
                    <span class="fbadge fbadge-total"><?= $carpeta['total'] ?> archivos</span>
                    <?php if ($carpeta['finales'] > 0): ?>
                    <span class="fbadge fbadge-final"><?= $carpeta['finales'] ?> finales</span>
                    <?php endif; ?>
                    <?php if ($carpeta['parciales'] > 0): ?>
                    <span class="fbadge fbadge-parcial"><?= $carpeta['parciales'] ?> parciales</span>
                    <?php endif; ?>
                </div>

                <i class="fas fa-chevron-down folder-chevron"></i>
            </button>

            <!-- Cuerpo -->
            <div class="folder-body" id="<?= $uid ?>">
                <?php if (empty($carpeta['archivos'])): ?>
                <div class="folder-empty">
                    <i class="fas fa-file-excel me-2"></i>No hay archivos en esta carpeta.
                </div>
                <?php else: ?>
                <table class="inner-table">
                    <thead>
                        <tr>
                            <th style="width:47%">Archivo</th>
                            <th style="width:11%">Tipo</th>
                            <th class="col-kb" style="width:9%">Tamaño</th>
                            <th style="width:18%">Fecha</th>
                            <th style="width:15%; text-align:center;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($carpeta['archivos'] as $arch): ?>
                        <tr data-nombre="<?= strtolower(htmlspecialchars($arch['nombre'])) ?>">
                            <td>
                                <div class="fname-cell">
                                    <div class="pdf-dot"><i class="fas fa-file-pdf"></i></div>
                                    <span class="fname-text"><?= htmlspecialchars($arch['nombre']) ?></span>
                                </div>
                            </td>
                            <td>
                                <?php
                                $tc = $arch['tipo'] === 'Final'   ? 'tbadge-final'
                                    : ($arch['tipo'] === 'Parcial' ? 'tbadge-parcial' : 'tbadge-otro');
                                ?>
                                <span class="tbadge <?= $tc ?>"><?= $arch['tipo'] ?></span>
                            </td>
                            <td class="col-kb" style="color:#6c757d; font-size:.8rem;">
                                <?= round($arch['tamano'] / 1024, 1) ?> KB
                            </td>
                            <td>
                                <span class="fecha-pill">
                                    <i class="far fa-calendar-alt me-1"></i>
                                    <?= date('d/m/Y H:i', $arch['fecha']) ?>
                                </span>
                            </td>
                            <td style="text-align:center;">
                                <button class="act-btn act-btn-view me-1"
                                        data-action="preview"
                                        data-archivo="<?= htmlspecialchars($arch['nombre']) ?>"
                                        data-carpeta="<?= htmlspecialchars($arch['carpeta']) ?>"
                                        data-generacion="<?= htmlspecialchars($arch['generacion']) ?>"
                                        data-turno="<?= htmlspecialchars($arch['turno']) ?>"
                                        title="Vista previa">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="act-btn act-btn-dl"
                                        data-action="download"
                                        data-archivo="<?= htmlspecialchars($arch['nombre']) ?>"
                                        data-carpeta="<?= htmlspecialchars($arch['carpeta']) ?>"
                                        data-generacion="<?= htmlspecialchars($arch['generacion']) ?>"
                                        data-turno="<?= htmlspecialchars($arch['turno']) ?>"
                                        title="Descargar">
                                    <i class="fas fa-download"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div><!-- /.folder-body -->

        </div><!-- /.folder-card -->
        <?php endforeach; ?>

    </div><!-- /.folders-list -->

    <?php else: ?>
    <div class="empty-global">
        <div class="ei"><i class="fas fa-folder-open"></i></div>
        <h5 style="color:#1a355e; font-weight:700;">No hay respaldos generados</h5>
        <p class="mb-4 text-muted">
            Los archivos PDF aparecerán aquí organizados por grupo, una vez que generes el primer respaldo.
        </p>
        <?php if ($grado && $grupo && $turno): ?>
        <a href="boleta_alumnos_nueva.php?grado=<?= urlencode($grado) ?>&grupo=<?= urlencode($grupo) ?>&turno=<?= urlencode($turno) ?>"
           class="btn text-white fw-semibold px-4 py-2"
           style="background:linear-gradient(135deg,#0f6fff,#14f1f8); border:none; border-radius:50px; text-decoration:none;">
            <i class="fas fa-plus me-2"></i>Generar primer respaldo
        </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div><!-- /.container -->

<br>
<?php include 'footer_orientador.php'; ?>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.min.js"></script>

<!-- ═══════════════════════════════════════════════════════
     MODAL DE PREVISUALIZACIÓN DE PDF
═══════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalPreviewPDF" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"
         style="max-width:90%; height:92vh; margin:4vh auto;">
        <div class="modal-content" style="height:100%; border-radius:16px; overflow:hidden;">
            <div class="modal-header border-0"
                 style="background:linear-gradient(135deg,#1a355e,#2b91ff); padding:13px 20px; flex-shrink:0;">
                <h6 class="modal-title fw-bold text-white mb-0">
                    <i class="fas fa-file-pdf me-2"></i><span id="preview-filename"></span>
                </h6>
                <button type="button" class="btn-close btn-close-white"
                        data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-0" style="flex:1; overflow:hidden;">
                <iframe id="pdf-iframe" src=""
                        style="width:100%; height:100%; border:none; display:block;"></iframe>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════
     JAVASCRIPT
═══════════════════════════════════════════════════════ -->
<script>
// ── Abrir / cerrar carpeta ──────────────────────────────────────────
function toggleFolder(btn, id) {
    const body   = document.getElementById(id);
    const isOpen = body.classList.contains('is-open');
    body.classList.toggle('is-open', !isOpen);
    btn.classList.toggle('is-open',  !isOpen);
}

// ── Previsualizar PDF en modal ──────────────────────────────────────
function previsualizarPDF(archivo, carpeta, generacion, turno) {
    const url = `descargar_pdf.php?archivo=${encodeURIComponent(archivo)}&carpeta=${encodeURIComponent(carpeta)}&generacion=${encodeURIComponent(generacion)}&turno=${encodeURIComponent(turno)}&accion=visualizar`;
    document.getElementById('preview-filename').textContent = archivo;
    document.getElementById('pdf-iframe').src = url;
    new bootstrap.Modal(document.getElementById('modalPreviewPDF')).show();
}

// ── Descargar PDF ───────────────────────────────────────────────────
function descargarPDF(archivo, carpeta, generacion, turno) {
    window.location.href = `descargar_pdf.php?archivo=${encodeURIComponent(archivo)}&carpeta=${encodeURIComponent(carpeta)}&generacion=${encodeURIComponent(generacion)}&turno=${encodeURIComponent(turno)}&accion=descargar`;
}

// ── Limpiar iframe al cerrar el modal ──────────────────────────────
document.getElementById('modalPreviewPDF')
    .addEventListener('hidden.bs.modal', () => {
        document.getElementById('pdf-iframe').src = '';
    });

// ── Event delegation para ver y descargar ──────────────────────────
document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-action]');
    if (!btn) return;
    const { action, archivo, carpeta, generacion, turno } = btn.dataset;
    if (action === 'preview')  previsualizarPDF(archivo, carpeta, generacion, turno);
    if (action === 'download') descargarPDF(archivo, carpeta, generacion, turno);
});

// ── Buscador global ─────────────────────────────────────────────────
// Filtra filas dentro de todas las carpetas; abre las que tienen coincidencias
function filtrarArchivos(q) {
    q = q.trim().toLowerCase();

    document.querySelectorAll('.folder-card').forEach(card => {
        const rows = card.querySelectorAll('tbody tr');
        let vis = 0;

        rows.forEach(row => {
            const nombre = row.dataset.nombre || '';
            const ok = !q || nombre.includes(q);
            row.style.display = ok ? '' : 'none';
            if (ok) vis++;
        });

        // Con búsqueda activa: abrir carpeta si tiene coincidencias, ocultar si no
        if (q) {
            const body = card.querySelector('.folder-body');
            const hdr  = card.querySelector('.folder-hdr');
            if (vis > 0) {
                body.classList.add('is-open');
                hdr.classList.add('is-open');
                card.style.display = '';
            } else {
                card.style.display = 'none';
            }
        } else {
            // Sin búsqueda: restaurar visibilidad, respetar estado de apertura actual
            card.style.display = '';
        }
    });
}
</script>

</body>
</html>
