<?php
/**
 * ══════════════════════════════════════════════════════════════════════
 * VISOR DE AUDITORÍA DE RESPALDOS
 * Muestra los registros de respaldos generados con filtros
 * ══════════════════════════════════════════════════════════════════════
 */

session_start();
if (!isset($_SESSION['id_credencial'])) {
    header("Location: login.php");
    exit();
}

include '../funciones/conexQRConejo.php';

// Parámetros de filtro
$filtro_tipo = $_GET['tipo'] ?? '';
$filtro_usuario = $_GET['usuario'] ?? '';
$filtro_fecha_inicio = $_GET['fecha_inicio'] ?? '';
$filtro_fecha_fin = $_GET['fecha_fin'] ?? '';
$limite = $_GET['limite'] ?? 50;

// Construir query con filtros - USAR TABLA DIRECTAMENTE EN LUGAR DE VISTA
$query = "SELECT 
    l.id,
    l.usuario_db,
    l.usuario_sistema,
    l.nombre_usuario,
    l.host_origen,
    l.fecha_hora,
    DATE_FORMAT(l.fecha_hora, '%d/%m/%Y %H:%i:%s') as fecha_formateada,
    l.nombre_archivo,
    l.ruta_archivo,
    l.tipo_respaldo,
    l.tipo_boleta,
    l.id_alumno,
    l.grado,
    l.grupo,
    l.turno,
    l.id_escuela,
    l.tamano_bytes,
    ROUND(l.tamano_bytes / 1024, 2) AS tamano_kb,
    l.ip_cliente,
    l.user_agent,
    CASE 
        WHEN l.tipo_respaldo = 'Individual' THEN CONCAT(COALESCE(c.nombre_credencial, ''), ' ', COALESCE(c.apellidos_credencial, ''))
        ELSE CONCAT('Grupo: ', COALESCE(l.grado, ''), ' ', COALESCE(l.grupo, ''), ' (', COALESCE(l.turno, ''), ')')
    END as detalle_respaldo,
    e.nombre_escuela
FROM respaldos_log l
LEFT JOIN credenciales c ON l.id_alumno = c.id_credencial
LEFT JOIN escuelas e ON l.id_escuela = e.id_escuela
WHERE 1=1";
$params = [];
$types = '';

if (!empty($filtro_tipo)) {
    $query .= " AND tipo_respaldo = ?";
    $params[] = $filtro_tipo;
    $types .= 's';
}

if (!empty($filtro_usuario)) {
    $query .= " AND usuario_db LIKE ?";
    $params[] = "%$filtro_usuario%";
    $types .= 's';
}

if (!empty($filtro_fecha_inicio)) {
    $query .= " AND DATE(fecha_hora) >= ?";
    $params[] = $filtro_fecha_inicio;
    $types .= 's';
}

if (!empty($filtro_fecha_fin)) {
    $query .= " AND DATE(fecha_hora) <= ?";
    $params[] = $filtro_fecha_fin;
    $types .= 's';
}

$query .= " ORDER BY fecha_hora DESC LIMIT ?";
$params[] = (int)$limite;
$types .= 'i';

$stmt = mysqli_prepare($conexion, $query);

if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$logs = mysqli_fetch_all($result, MYSQLI_ASSOC);

mysqli_close($conexion);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auditoría de Respaldos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f4f6f9;
            padding: 20px;
        }
        .container { max-width: 1400px; }
        .page-title {
            color: #1a355e;
            font-weight: 700;
            margin-bottom: 25px;
        }
        .filter-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,.08);
        }
        .table-responsive {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,.08);
        }
        .badge-individual { background: #2b91ff; }
        .badge-grupal { background: #6f42c1; }
        .badge-final { background: #28a745; }
        .badge-parcial { background: #ffc107; color: #333; }
        .badge-manual { background: #17a2b8; }
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,.08);
            border-top: 4px solid #2b91ff;
        }
        .stat-number { font-size: 2rem; font-weight: 700; color: #1a355e; }
        .stat-label { color: #6c757d; font-size: 0.9rem; margin-top: 8px; }
    </style>
</head>
<body>

<div class="container">
    <h1 class="page-title">
        <i class="fas fa-clipboard-list me-2"></i>
        Auditoría de Respaldos
    </h1>

    <!-- Botón de regreso al historial -->
    <div class="mb-4">
        <a href="javascript:history.back()" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i>Volver al Historial
        </a>
        <a href="historial_respaldos_beta.php" class="btn btn-outline-secondary ms-2">
            <i class="fas fa-home me-2"></i>Ir a Historial Principal
        </a>
    </div>

    <!-- Stats -->
    <?php
    $total_logs = count($logs);
    $individuales = count(array_filter($logs, fn($l) => $l['tipo_respaldo'] == 'Individual'));
    $grupales = count(array_filter($logs, fn($l) => $l['tipo_respaldo'] == 'Grupal'));
    $total_kb = array_sum(array_column($logs, 'tamano_kb'));
    ?>
    
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-number"><?= $total_logs ?></div>
            <div class="stat-label"><i class="fas fa-file-pdf me-1"></i>Total Respaldos</div>
        </div>
        <div class="stat-card" style="border-top-color: #2b91ff;">
            <div class="stat-number"><?= $individuales ?></div>
            <div class="stat-label"><i class="fas fa-user me-1"></i>Individuales</div>
        </div>
        <div class="stat-card" style="border-top-color: #6f42c1;">
            <div class="stat-number"><?= $grupales ?></div>
            <div class="stat-label"><i class="fas fa-users me-1"></i>Grupales</div>
        </div>
        <div class="stat-card" style="border-top-color: #28a745;">
            <div class="stat-number"><?= number_format($total_kb / 1024, 2) ?> MB</div>
            <div class="stat-label"><i class="fas fa-hdd me-1"></i>Espacio Total</div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="filter-card">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Tipo de Respaldo</label>
                <select name="tipo" class="form-select">
                    <option value="">Todos</option>
                    <option value="Individual" <?= $filtro_tipo == 'Individual' ? 'selected' : '' ?>>Individual</option>
                    <option value="Grupal" <?= $filtro_tipo == 'Grupal' ? 'selected' : '' ?>>Grupal</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Usuario</label>
                <input type="text" name="usuario" class="form-control" 
                       placeholder="Buscar usuario..." value="<?= htmlspecialchars($filtro_usuario) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Fecha Inicio</label>
                <input type="date" name="fecha_inicio" class="form-control" value="<?= $filtro_fecha_inicio ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Fecha Fin</label>
                <input type="date" name="fecha_fin" class="form-control" value="<?= $filtro_fecha_fin ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Límite</label>
                <select name="limite" class="form-select">
                    <option value="25" <?= $limite == 25 ? 'selected' : '' ?>>25</option>
                    <option value="50" <?= $limite == 50 ? 'selected' : '' ?>>50</option>
                    <option value="100" <?= $limite == 100 ? 'selected' : '' ?>>100</option>
                    <option value="500" <?= $limite == 500 ? 'selected' : '' ?>>500</option>
                </select>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-filter me-2"></i>Filtrar
                </button>
                <a href="?" class="btn btn-secondary ms-2">
                    <i class="fas fa-times me-2"></i>Limpiar
                </a>
            </div>
        </form>
    </div>

    <!-- Tabla -->
    <div class="table-responsive">
        <table class="table table-hover">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Fecha/Hora</th>
                    <th>Usuario DB</th>
                    <th>Usuario Sistema</th>
                    <th>Tipo</th>
                    <th>Boleta</th>
                    <th>Detalle</th>
                    <th>Archivo</th>
                    <th>Tamaño</th>
                    <th>IP</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                <tr>
                    <td colspan="10" class="text-center text-muted py-5">
                        <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                        No hay registros de auditoría
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($logs as $log): ?>
                <tr>
                    <td><strong>#<?= $log['id'] ?></strong></td>
                    <td>
                        <small><?= $log['fecha_formateada'] ?></small>
                    </td>
                    <td>
                        <code class="small"><?= htmlspecialchars($log['usuario_db']) ?>@<?= htmlspecialchars($log['host_origen']) ?></code>
                    </td>
                    <td>
                        <small><?= htmlspecialchars($log['nombre_usuario'] ?? 'N/A') ?></small>
                    </td>
                    <td>
                        <span class="badge badge-<?= strtolower($log['tipo_respaldo']) ?>">
                            <?= $log['tipo_respaldo'] ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($log['tipo_boleta']): ?>
                        <span class="badge badge-<?= strtolower($log['tipo_boleta']) ?>">
                            <?= $log['tipo_boleta'] ?>
                        </span>
                        <?php endif; ?>
                    </td>
                    <td><small><?= htmlspecialchars($log['detalle_respaldo']) ?></small></td>
                    <td>
                        <small class="text-truncate d-inline-block" style="max-width: 200px;" 
                               title="<?= htmlspecialchars($log['nombre_archivo']) ?>">
                            <?= htmlspecialchars($log['nombre_archivo']) ?>
                        </small>
                    </td>
                    <td><small><?= number_format($log['tamano_kb'], 2) ?> KB</small></td>
                    <td><small><?= htmlspecialchars($log['ip_cliente']) ?></small></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>