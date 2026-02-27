<!-- vista/boleta_vista.php - SIN ESTRUCTURA HTML DUPLICADA -->
<!-- El header, head y body ya vienen de header_orientador.php -->

<style>
    body { font-family: 'League Spartan', sans-serif; background: #f8f9fa; padding: 20px; }
    .container { max-width: 1200px; }

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
    .info-text { flex: 1; line-height: 1.6; }

    .btn-backup-group {
        background: linear-gradient(135deg, #28a745, #20c997);
        border: none;
        color: white;
        font-weight: bold;
        padding: 10px 20px;
        border-radius: 25px;
        text-decoration: none;
        display: inline-block;
        font-size: 0.9rem;
        box-shadow: 0 3px 10px rgba(40, 167, 69, 0.3);
        white-space: nowrap;
        transition: all 0.3s;
        cursor: pointer;
    }
    .btn-backup-group:hover {
        background: linear-gradient(135deg, #218838, #1ba87a);
        color: white;
        box-shadow: 0 5px 15px rgba(40, 167, 69, 0.5);
        transform: translateY(-2px);
    }

    .btn-analytics {
        background: linear-gradient(135deg, #6f42c1, #a66efa);
        border: none;
        color: white;
        font-weight: 600;
        padding: 10px 20px;
        border-radius: 25px;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin-left: 10px;
        transition: all 0.3s;
        box-shadow: 0 3px 10px rgba(111,66,193,0.3);
    }
    .btn-analytics:hover {
        background: linear-gradient(135deg, #5a32a3, #8b5cf6);
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(111,66,193,0.4);
    }

    .btn-download-all {
        display: block;
        width: 100%;
        max-width: 300px;
        margin: 0 auto 30px;
        background: linear-gradient(135deg, #2b91ff, #0056b3);
        border: none;
        color: white;
        font-weight: bold;
        padding: 12px 20px;
        border-radius: 50px;
    }

    .student-card {
        background: linear-gradient(to right, #0f6fff, #14f1f8);
        border-radius: 15px;
        padding: 20px;
        margin-bottom: 20px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        position: relative;
        overflow: hidden;
    }
    .student-card::before {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0; bottom: 0;
        background: linear-gradient(135deg, transparent 60%, rgba(255,255,255,0.1));
        pointer-events: none;
    }

    .student-avatar {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        object-fit: cover;
        border: 3px solid rgba(255,255,255,0.9);
        box-shadow: 0 2px 8px rgba(0,0,0,0.15);
    }
    .student-name {
        font-size: 1.2rem;
        font-weight: bold;
        color: #ffffff;
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
    }

    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 5px 12px;
        border-radius: 30px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        box-shadow: 0 2px 6px rgba(0,0,0,0.15);
        animation: badgeSlideIn 0.3s ease forwards;
        opacity: 0;
        transform: translateX(-10px);
    }
    .status-badge.visible {
        opacity: 1;
        transform: translateX(0);
    }
    
    .status-incomplete {
        background: linear-gradient(135deg, #ffc107, #ff9800);
        color: #1a1a1a;
        border: 2px solid rgba(255,255,255,0.3);
    }
    .status-incomplete:hover {
        transform: scale(1.05);
        box-shadow: 0 4px 12px rgba(255,152,0,0.4);
    }
    
    .status-failed {
        background: linear-gradient(135deg, #dc3545, #c82333);
        color: white;
        border: 2px solid rgba(255,255,255,0.3);
    }
    .status-failed:hover {
        transform: scale(1.05);
        box-shadow: 0 4px 12px rgba(220,53,69,0.4);
    }
    
    .status-approved {
        background: linear-gradient(135deg, #28a745, #20c997);
        color: white;
        border: 2px solid rgba(255,255,255,0.3);
    }
    .status-approved:hover {
        transform: scale(1.05);
        box-shadow: 0 4px 12px rgba(40,167,69,0.4);
    }

    .download-btn {
        background: white;
        border: none;
        text-decoration: none;
        color: #1a355e;
        font-weight: bold;
        padding: 6px 16px;
        border-radius: 10px;
        margin-top: auto;
        transition: all 0.2s;
        box-shadow: 0 2px 6px rgba(0,0,0,0.1);
    }
    .download-btn:hover {
        background: #f8f9fa;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        color: #0f6fff;
    }

    .backup-btn {
        background: rgba(255, 255, 255, 0.25);
        border: 1px solid rgba(255, 255, 255, 0.5);
        text-decoration: none;
        color: white;
        font-weight: 600;
        padding: 5px 12px;
        border-radius: 8px;
        font-size: 0.85rem;
        margin-left: 8px;
        display: inline-block;
        transition: all 0.2s;
    }
    .backup-btn:hover {
        background: rgba(255, 255, 255, 0.4);
        color: white;
        transform: translateY(-1px);
    }

    .alert-success-custom {
        background: linear-gradient(135deg, #d4edda, #c3e6cb);
        border: 2px solid #28a745;
        color: #155724;
        padding: 15px;
        border-radius: 10px;
        margin-bottom: 20px;
        font-weight: 600;
        box-shadow: 0 2px 8px rgba(40, 167, 69, 0.2);
        animation: slideDown 0.4s ease;
    }
    
    @keyframes badgeSlideIn {
        to { opacity: 1; transform: translateX(0); }
    }
    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .progreso-container {
        position: relative;
        width: 100px;
        height: 100px;
        margin: 0 auto;
    }
    .progreso-circular {
        position: absolute;
        top: 50%; left: 50%;
        transform: translate(-50%, -50%);
        font-size: 1.8rem;
        font-weight: bold;
        color: #1a355e;
    }
    .progress-custom {
        height: 12px;
        max-width: 350px;
        margin: 0 auto;
        border-radius: 50px;
        background-color: #e9ecef;
    }
    .progress-bar-custom {
        background: linear-gradient(135deg, #28a745, #20c997);
        border-radius: 50px;
        transition: width 0.3s ease;
    }

    .modal-chart .modal-content {
        border-radius: 20px;
        border: none;
        box-shadow: 0 15px 50px rgba(0,0,0,0.25);
    }
    .modal-chart .modal-header {
        background: linear-gradient(135deg, #1a355e, #2b91ff);
        color: white;
        border-radius: 20px 20px 0 0;
        border: none;
        padding: 18px 24px;
    }
    .modal-chart .chart-wrapper {
        background: white;
        border-radius: 16px;
        padding: 20px;
        margin: 10px 0;
        box-shadow: 0 4px 16px rgba(0,0,0,0.06);
    }
    .modal-chart .stats-legend {
        display: flex;
        justify-content: center;
        gap: 20px;
        flex-wrap: wrap;
        margin-top: 15px;
    }
    .legend-item {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 0.9rem;
        font-weight: 500;
    }
    .legend-dot {
        width: 14px;
        height: 14px;
        border-radius: 50%;
        display: inline-block;
    }
    .legend-approved { background: #28a745; }
    .legend-failed { background: #dc3545; }
    .legend-incomplete { background: #ffc107; }
</style>

<div class="container">

    <?php
    // ── Mensaje de resultado del respaldo grupal ──
    if (isset($_GET['total'])) {
        $t  = (int)$_GET['total'];
        $fi = (int)($_GET['finales']   ?? 0);
        $pa = (int)($_GET['parciales'] ?? 0);
        $mo = htmlspecialchars($_GET['modo'] ?? 'completo');
        $modoTexto = ($mo === 'solo_listos') ? 'Solo boletas completas' : 'Todo el grupo';
        echo "<div class='alert alert-success-custom' role='alert'>";
        echo "✅ Respaldo completado (<strong>{$modoTexto}</strong>): "
           . "<strong>{$t}</strong> PDF(s) guardados "
           . "— {$fi} finales, {$pa} parciales.";
        echo "</div>";
    } elseif (isset($_GET['mensaje'])) {
        $mensaje = htmlspecialchars($_GET['mensaje']);
        echo "<div class='alert alert-success-custom' role='alert'>✅ {$mensaje}</div>";
    }
    ?>

    <div class="header-title">Boleta de Calificaciones</div>

    <!-- INFO ESCOLAR + BOTONES DE ACCIÓN -->
    <div class="info-header-wrapper">
        <div class="info-text">
            <strong>Escuela:</strong> <?= htmlspecialchars($nombre_escuela) ?><br>
            <strong>Grado:</strong> <?= htmlspecialchars($grado) ?> |
            <strong>Grupo:</strong> <?= htmlspecialchars($grupo_romano) ?> |
            <strong>Turno:</strong> <?= htmlspecialchars($turno) ?> |
            <strong>Total de alumnos:</strong> <?= htmlspecialchars($total_alumnos) ?>
        </div>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <!-- Historial -->
            <a href="historial_respaldos.php?grado=<?= urlencode($grado) ?>&grupo=<?= urlencode($grupo) ?>&turno=<?= urlencode($turno) ?>"
               class="btn-backup-group"
               style="background: linear-gradient(135deg, #105881, #34c0e6);">
                📋 Historial
            </a>
            
            <!-- Analítica (Modal) -->
            <button type="button" class="btn-analytics" data-bs-toggle="modal" data-bs-target="#modalAnalytics">
                <i class="fas fa-chart-pie"></i> Analítica
            </button>
            
            <!-- ✅ BOTÓN DE RESPALDO GENERAL - FUNCIONAL -->
            <button type="button" class="btn-backup-group"
                onclick="abrirModalRespaldo(
                    '<?= htmlspecialchars($grado, ENT_QUOTES) ?>',
                    '<?= htmlspecialchars($grupo, ENT_QUOTES) ?>',
                    '<?= htmlspecialchars($turno, ENT_QUOTES) ?>'
                )">
                💾 Respaldo General
            </button>
        </div>
    </div>

    <!-- 🔹 MODAL DE GRÁFICA ANALÍTICA -->
    <div class="modal fade modal-chart" id="modalAnalytics" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-chart-pie me-2"></i>Analítica del Grupo
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-3">
                        <span class="badge bg-light text-dark px-3 py-2">
                            <?= htmlspecialchars("{$grado}º {$grupo_romano} {$turno}") ?>
                        </span>
                    </div>
                    <div class="chart-wrapper">
                        <canvas id="boletaChartModal" style="max-height: 350px;"></canvas>
                    </div>
                    <div class="stats-legend">
                        <div class="legend-item">
                            <span class="legend-dot legend-approved"></span>
                            <span>Aprobados: <strong id="legendAprobados"><?= $count_aprobados ?? 0 ?></strong></span>
                        </div>
                        <div class="legend-item">
                            <span class="legend-dot legend-failed"></span>
                            <span>Reprobados: <strong id="legendReprobados"><?= $count_reprobados ?? 0 ?></strong></span>
                        </div>
                        <div class="legend-item">
                            <span class="legend-dot legend-incomplete"></span>
                            <span>Incompletos: <strong id="legendIncompletos"><?= $count_incompletos ?? 0 ?></strong></span>
                        </div>
                    </div>
                    <?php if (($count_aprobados ?? 0) + ($count_reprobados ?? 0) + ($count_incompletos ?? 0) === 0): ?>
                    <div class="alert alert-info mt-3 mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        Aún no hay datos de calificaciones para mostrar la analítica.
                    </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- TARJETAS POR ALUMNO -->
    <?php foreach ($alumnos as $alum): ?>
        <?php
        $nombre_completo = htmlspecialchars($alum['nombre_credencial'] . ' ' . $alum['apellidos_decrypted']);
        $foto = !empty($alum['ruta_foto'])
            ? htmlspecialchars($alum['ruta_foto'])
            : 'https://tse3.mm.bing.net/th/id/OIP.2L4bAjBAkwILmakMvHA8AgHaFY?rs=1&pid=ImgDetMain&o=7&rm=3';
        
        $estado = $alum['estado_final'] ?? ($alum['boleta_completa'] ? 'aprobado' : 'incompleto');
        $promedio = $alum['promedio_final'] ?? null;
        $claseBadge = $estado === 'incompleto' ? 'status-incomplete' : 
                      ($estado === 'reprobado' ? 'status-failed' : 'status-approved');
        $iconoBadge = $estado === 'incompleto' ? '⚠️' : 
                      ($estado === 'reprobado' ? '❌' : '✅');
        $textoBadge = $estado === 'incompleto' ? 'Incompleta' : 
                      ($estado === 'reprobado' ? 'Reprobado' : 'Aprobado');
        $titleBadge = $estado === 'incompleto' ? 'Boleta incompleta: faltan calificaciones' : 
                     "Promedio: " . number_format($promedio ?? 0, 2) . " (" . ucfirst($estado) . ")";
        ?>
        <div class="student-card">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                <div style="display:flex; align-items:center; gap:15px;">
                    <img src="<?= $foto ?>" alt="Foto" class="student-avatar">
                    <div>
                        <div class="student-name">
                            <?= $nombre_completo ?>
                            <span class="status-badge <?= $claseBadge ?>" title="<?= $titleBadge ?>">
                                <?= $iconoBadge ?> <?= $textoBadge ?>
                            </span>
                        </div>
                        <div style="font-size:0.9rem; color:#f9f9f9; margin-top: 4px;">
                            Estudiante / <?= htmlspecialchars($grado) ?>
                            <?= htmlspecialchars($grupo_romano) ?>
                            <?= htmlspecialchars(' / Turno: ' . $turno) ?>
                        </div>
                    </div>
                </div>
                <div style="display:flex; align-items:center; gap:8px;">
                    <a href="generar_pdf_individual.php?id=<?= $alum['id_credencial'] ?>"
                       target="_blank" class="download-btn">
                        📄 Imprimir PDF
                    </a>
                    <a href="generar_pdf_individual.php?id=<?= $alum['id_credencial'] ?>&forzar_respaldo=1"
                       target="_blank"
                       class="backup-btn"
                       onclick="return confirm('¿Generar respaldo?\n\nSe guardará como Boleta_Final_<?= $alum['id_credencial'] ?>.pdf');">
                        💾 Respaldar
                    </a>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

</div>

<!-- BOTÓN ZIP -->
<a href="generar_zip_boletas.php?grado=<?= urlencode($grado) ?>&grupo=<?= urlencode($grupo) ?>&turno=<?= urlencode($turno) ?>"
   class="btn btn-download-all">
    Descargar Todas las Boletas en ZIP (<?= count($alumnos) ?> estudiantes)
</a>

<!-- NOTA INFORMATIVA -->
<div class="container mt-3 mb-4">
    <div class="alert alert-info" style="font-size:0.9rem;">
        <strong>ℹ️ Información sobre Respaldos:</strong>
        <ul class="mb-0" style="font-size:0.85rem;">
            <li><strong>Respaldo General:</strong> Analiza el grupo y permite elegir respaldar solo boletas completas o todo el grupo.</li>
            <li><strong>Botón individual:</strong> Guarda la boleta del alumno como <code>Boleta_Final_[ID].pdf</code>.</li>
            <li><strong>Ubicación:</strong> <code>respaldos/boletas/<?= $id_escuela ?>/grupos/[Grado] [Grupo]/</code></li>
        </ul>
    </div>
</div>

<!-- ================================================================
     MODAL DE RESPALDO GRUPAL - FUNCIONAL
     ================================================================ -->
<div class="modal fade" id="modalRespaldo" tabindex="-1"
     aria-labelledby="modalRespaldoLabel" aria-hidden="true"
     data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:16px; overflow:hidden;">
            <div class="modal-header"
                 style="background:linear-gradient(135deg,#1a355e,#2b91ff); color:white; border:none;">
                <h5 class="modal-title fw-bold" id="modalRespaldoLabel">
                    💾 Generar Respaldo del Grupo
                </h5>
                <button type="button" class="btn-close btn-close-white"
                        data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-4">
                <!-- ANALIZANDO -->
                <div id="respaldo-loading" class="text-center py-3">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="mt-2 text-muted">Analizando el grupo...</p>
                </div>
                
                <!-- RESPALDANDO (barra de progreso) -->
                <div id="respaldo-ejecutando" class="text-center py-4 d-none">
                    <div class="progreso-container">
                        <div class="spinner-border text-primary" style="width:100px; height:100px;" role="status"></div>
                        <div id="porcentaje-texto" class="progreso-circular">0%</div>
                    </div>
                    <div class="progress-custom progress mt-4">
                        <div id="barra-progreso"
                             class="progress-bar progress-bar-striped progress-bar-animated progress-bar-custom"
                             style="width:0%;">
                        </div>
                    </div>
                    <p class="mt-4 fw-semibold fs-5" id="mensaje-progreso" style="color:#1a355e;">
                        ⏳ Iniciando respaldo grupal...
                    </p>
                    <p class="text-muted small mb-0" id="detalle-progreso">
                        Preparando archivos...
                    </p>
                </div>
                
                <!-- ERROR -->
                <div id="respaldo-error" class="alert alert-danger d-none" role="alert">
                    <strong>⚠️ Error:</strong>
                    <span id="respaldo-error-msg"></span>
                </div>
                
                <!-- RESULTADOS -->
                <div id="respaldo-resultados" class="d-none">
                    <div class="row g-3 mb-4">
                        <div class="col-4 text-center">
                            <div class="p-3 rounded-3" style="background:#f0f4ff; border:1px solid #c8d8ff;">
                                <div class="fs-2 fw-bold text-primary" id="cnt-total">—</div>
                                <div class="small text-muted">Total</div>
                            </div>
                        </div>
                        <div class="col-4 text-center">
                            <div class="p-3 rounded-3" style="background:#f0fff4; border:1px solid #a8e6c0;">
                                <div class="fs-2 fw-bold text-success" id="cnt-listos">—</div>
                                <div class="small text-muted">Completas</div>
                            </div>
                        </div>
                        <div class="col-4 text-center">
                            <div class="p-3 rounded-3" style="background:#fff8f0; border:1px solid #ffd8a8;">
                                <div class="fs-2 fw-bold text-warning" id="cnt-pendientes">—</div>
                                <div class="small text-muted">Pendientes</div>
                            </div>
                        </div>
                    </div>
                    <div id="respaldo-nota" class="alert mb-0" role="alert"></div>
                </div>
            </div>
            
            <!-- ACCIONES -->
            <div id="respaldo-acciones" class="modal-footer d-none" style="padding: 16px 24px; border-top: 1px solid #dee2e6;">
                <button type="button" id="btn-todos" class="btn btn-secondary w-100 mb-2"
                        onclick="pedirConfirmacion(1)">💾 Todo el grupo</button>
                <div class="d-flex gap-2 w-100">
                    <button type="button" id="btn-solo-listos"
                            class="btn btn-outline-success fw-semibold"
                            onclick="pedirConfirmacion(0)">
                        ✅ Solo boletas completas
                    </button>
                    <button type="button" class="btn btn-danger w-50 fw-bold py-2"
                            data-bs-dismiss="modal">
                        Cancelar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL DE CONFIRMACIÓN -->
<div class="modal fade" id="modalConfirmacion" tabindex="-1"
     aria-labelledby="modalConfirmacionLabel" aria-hidden="true"
     data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content" style="border-radius:16px; overflow:hidden; box-shadow:0 20px 60px rgba(0,0,0,0.2);">
            <div class="modal-header border-0 pb-0"
                 style="background:linear-gradient(135deg, #0f6fff, #14f1f8); padding:20px 20px 0;">
                <div class="w-100 text-center pb-3">
                    <div id="conf-icono"
                         style="width:56px; height:56px; border-radius:50%; background:rgba(255,255,255,0.25);
                                display:flex; align-items:center; justify-content:center;
                                margin:0 auto 10px; font-size:1.6rem; backdrop-filter:blur(4px);">
                        💾
                    </div>
                    <h5 class="modal-title fw-bold text-white mb-0" id="modalConfirmacionLabel">
                        Confirmar respaldo
                    </h5>
                </div>
            </div>
            <div class="modal-body text-center px-4 pt-4 pb-3">
                <p id="conf-pregunta" class="fw-semibold mb-1" style="color:#1a355e; font-size:0.95rem;"></p>
                <p id="conf-detalle" class="text-muted small mb-0"></p>
            </div>
            <div class="modal-footer border-0 d-flex gap-2 px-4 pb-4 pt-1 justify-content-center">
                <button type="button" id="conf-btn-cancelar"
                        class="btn btn-outline-secondary px-4" style="border-radius:50px;">
                    Cancelar
                </button>
                <button type="button" id="conf-btn-confirmar"
                        class="btn fw-bold text-white px-4"
                        style="background:linear-gradient(135deg,#28a745,#20c997); border:none; border-radius:50px;
                               box-shadow:0 4px 12px rgba(40,167,69,0.35);">
                    ✔ Confirmar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ================================================================
     JAVASCRIPT - BOTÓN DE RESPALDO FUNCIONAL
     ================================================================ -->
<script>
// Animar badges al cargar
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.status-badge').forEach((badge, index) => {
        setTimeout(() => {
            badge.classList.add('visible');
        }, 150 + (index * 50));
    });
    
    // Inicializar gráfica del modal de analítica
    const modalAnalytics = document.getElementById('modalAnalytics');
    let chartInstance = null;
    
    modalAnalytics.addEventListener('shown.bs.modal', function() {
        const ctx = document.getElementById('boletaChartModal');
        if (!ctx) return;
        
        if (chartInstance) chartInstance.destroy();
        
        const total = <?php echo ($count_aprobados ?? 0) + ($count_reprobados ?? 0) + ($count_incompletos ?? 0); ?>;
        
        chartInstance = new Chart(ctx.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Aprobados', 'Reprobados', 'Incompletos'],
                datasets: [{
                    data: [
                        <?php echo (int)($count_aprobados ?? 0); ?>,
                        <?php echo (int)($count_reprobados ?? 0); ?>,
                        <?php echo (int)($count_incompletos ?? 0); ?>
                    ],
                    backgroundColor: ['#28a745', '#dc3545', '#ffc107'],
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
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(26, 53, 94, 0.95)',
                        titleFont: { size: 13, weight: '600' },
                        bodyFont: { size: 12 },
                        padding: 12,
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw || 0;
                                const percentage = total > 0 ? Math.round((value/total)*100) : 0;
                                return `${label}: ${value} alumno(s) (${percentage}%)`;
                            }
                        }
                    }
                },
                animation: {
                    animateScale: true,
                    animateRotate: true,
                    duration: 800
                }
            }
        });
    });
    
    modalAnalytics.addEventListener('hidden.bs.modal', function() {
        if (chartInstance) {
            chartInstance.destroy();
            chartInstance = null;
        }
    });
});

// ============================================================
// FUNCIONES DEL MODAL DE RESPALDO - FUNCIONALES
// ============================================================
const _respaldo = { grado: '', grupo: '', turno: '' };
let intervaloProgreso = null;

const PASOS_RESPALDO = [
    { limite: 10, mensaje: '📂 Preparando carpetas...', detalle: 'Verificando estructura...' },
    { limite: 25, mensaje: '🔍 Leyendo datos...', detalle: 'Obteniendo alumnos...' },
    { limite: 45, mensaje: '📄 Generando PDFs...', detalle: 'Construyendo boletas...' },
    { limite: 65, mensaje: '🖨️ Procesando...', detalle: 'Aplicando diseño...' },
    { limite: 80, mensaje: '💾 Guardando...', detalle: 'Escribiendo archivos...' },
    { limite: 93, mensaje: '✔️ Finalizando...', detalle: 'Verificando integridad...' },
];

// ✅ FUNCIÓN PRINCIPAL: Abre el modal de respaldo
function abrirModalRespaldo(grado, grupo, turno) {
    _respaldo.grado = grado;
    _respaldo.grupo = grupo;
    _respaldo.turno = turno;
    
    // Resetear estados del modal
    document.getElementById('respaldo-loading').classList.remove('d-none');
    document.getElementById('respaldo-ejecutando').classList.add('d-none');
    document.getElementById('respaldo-error').classList.add('d-none');
    document.getElementById('respaldo-resultados').classList.add('d-none');
    document.getElementById('respaldo-acciones').classList.add('d-none');
    document.getElementById('btn-solo-listos').classList.remove('d-none');
    document.getElementById('btn-todos').classList.remove('d-none');
    
    // Habilitar botón de cierre durante análisis
    document.querySelector('#modalRespaldo .btn-close').disabled = false;
    
    // Abrir modal
    new bootstrap.Modal(document.getElementById('modalRespaldo')).show();
    
    // Iniciar verificación del grupo
    verificarGrupo(grado, grupo, turno);
}

// ✅ VERIFICAR ESTADO DEL GRUPO (AJAX)
function verificarGrupo(grado, grupo, turno) {
    const url = `verificar_estado_grupo.php?grado=${encodeURIComponent(grado)}&grupo=${encodeURIComponent(grupo)}&turno=${encodeURIComponent(turno)}`;
    
    fetch(url, { credentials: 'same-origin' })
        .then(res => {
            if (!res.ok) throw new Error(`Error HTTP ${res.status}`);
            return res.json();
        })
        .then(data => {
            if (data.error) throw new Error(data.error);
            document.getElementById('respaldo-loading').classList.add('d-none');
            renderizarResultados(data);
        })
        .catch(err => {
            document.getElementById('respaldo-loading').classList.add('d-none');
            document.getElementById('respaldo-error-msg').textContent = err.message;
            document.getElementById('respaldo-error').classList.remove('d-none');
        });
}

// ✅ RENDERIZAR RESULTADOS DEL ANÁLISIS
function renderizarResultados(data) {
    document.getElementById('cnt-total').textContent = data.total;
    document.getElementById('cnt-listos').textContent = data.listos;
    document.getElementById('cnt-pendientes').textContent = data.pendientes;
    
    const notaEl = document.getElementById('respaldo-nota');
    const btnSoloListos = document.getElementById('btn-solo-listos');
    const btnTodos = document.getElementById('btn-todos');
    
    if (data.total === 0) {
        notaEl.className = 'alert alert-warning mb-0';
        notaEl.innerHTML = '⚠️ No se encontraron alumnos en este grupo.';
        btnSoloListos.classList.add('d-none');
        btnTodos.classList.add('d-none');
    } else if (data.pendientes === 0) {
        notaEl.className = 'alert alert-success mb-0';
        notaEl.innerHTML = `✅ <strong>¡Grupo listo!</strong> Los ${data.total} alumnos tienen sus 3 parciales.`;
        btnSoloListos.classList.add('d-none');
    } else if (data.listos === 0) {
        notaEl.className = 'alert alert-warning mb-0';
        notaEl.innerHTML = `⚠️ <strong>Ningún alumno</strong> tiene los 3 parciales completos.`;
        btnSoloListos.classList.add('d-none');
    } else {
        notaEl.className = 'alert alert-info mb-0';
        notaEl.innerHTML = `ℹ️ Hay <strong>${data.listos} alumno(s) completo(s)</strong> y <strong>${data.pendientes} con pendientes</strong>. Elige cómo proceder:`;
    }
    
    document.getElementById('respaldo-resultados').classList.remove('d-none');
    document.getElementById('respaldo-acciones').classList.remove('d-none');
}

// ✅ PEDIR CONFIRMACIÓN ANTES DE RESPALDAR
function pedirConfirmacion(todo) {
    const config = (todo === 0)
        ? { icono: '✅', pregunta: '¿Respaldar solo boletas completas?', detalle: 'Solo alumnos con 3 parciales.' }
        : { icono: '💾', pregunta: '¿Respaldar todo el grupo?', detalle: 'Se generarán PDFs para todos (faltantes = --).' };
    
    document.getElementById('conf-icono').textContent = config.icono;
    document.getElementById('conf-pregunta').textContent = config.pregunta;
    document.getElementById('conf-detalle').textContent = config.detalle;
    
    const btnConfirmar = document.getElementById('conf-btn-confirmar');
    const nuevoBtn = btnConfirmar.cloneNode(true);
    btnConfirmar.parentNode.replaceChild(nuevoBtn, btnConfirmar);
    nuevoBtn.addEventListener('click', () => {
        bootstrap.Modal.getInstance(document.getElementById('modalConfirmacion'))?.hide();
        ejecutarRespaldo(todo);
    });
    
    const btnCancelar = document.getElementById('conf-btn-cancelar');
    const nuevoBtnC = btnCancelar.cloneNode(true);
    btnCancelar.parentNode.replaceChild(nuevoBtnC, btnCancelar);
    nuevoBtnC.addEventListener('click', () => {
        bootstrap.Modal.getInstance(document.getElementById('modalConfirmacion'))?.hide();
    });
    
    new bootstrap.Modal(document.getElementById('modalConfirmacion')).show();
}

// ✅ EJECUTAR RESPALDO (AJAX CON BARRA DE PROGRESO)
function ejecutarRespaldo(todo) {
    document.getElementById('respaldo-resultados').classList.add('d-none');
    document.getElementById('respaldo-acciones').classList.add('d-none');
    document.getElementById('respaldo-error').classList.add('d-none');
    document.querySelector('#modalRespaldo .btn-close').disabled = true;
    
    actualizarProgreso(0, '⏳ Iniciando respaldo...', 'Preparando archivos...');
    document.getElementById('respaldo-ejecutando').classList.remove('d-none');
    iniciarProgresoSimulado();
    
    const url = `generar_respaldo_grupal.php?grado=${encodeURIComponent(_respaldo.grado)}&grupo=${encodeURIComponent(_respaldo.grupo)}&turno=${encodeURIComponent(_respaldo.turno)}&todo=${todo}&ajax=1`;
    
    fetch(url, { credentials: 'same-origin' })
        .then(res => {
            if (!res.ok) throw new Error(`Error HTTP ${res.status}`);
            return res.json();
        })
        .then(data => {
            detenerProgreso();
            actualizarProgreso(100, '✅ Respaldo completado', 'Todos los PDFs guardados.');
            setTimeout(() => {
                bootstrap.Modal.getInstance(document.getElementById('modalRespaldo'))?.hide();
                const params = new URLSearchParams({
                    grado: _respaldo.grado,
                    grupo: _respaldo.grupo,
                    turno: _respaldo.turno,
                    total: data.total ?? 0,
                    finales: data.finales ?? 0,
                    parciales: data.parciales ?? 0,
                    modo: data.modo ?? 'completo'
                });
                window.location.href = `boleta_alumnos_nueva.php?${params.toString()}`;
            }, 800);
        })
        .catch(err => {
            detenerProgreso();
            document.getElementById('respaldo-ejecutando').classList.add('d-none');
            document.getElementById('respaldo-error-msg').textContent = err.message;
            document.getElementById('respaldo-error').classList.remove('d-none');
            document.querySelector('#modalRespaldo .btn-close').disabled = false;
        });
}

// ✅ SIMULAR BARRA DE PROGRESO
function iniciarProgresoSimulado() {
    let progreso = 0, pasoActual = 0;
    intervaloProgreso = setInterval(() => {
        if (progreso < 93) {
            progreso += Math.floor(Math.random() * 3) + 1;
            if (progreso > 93) progreso = 93;
            while (pasoActual < PASOS_RESPALDO.length && progreso >= PASOS_RESPALDO[pasoActual].limite) {
                actualizarProgreso(progreso, PASOS_RESPALDO[pasoActual].mensaje, PASOS_RESPALDO[pasoActual].detalle);
                pasoActual++;
            }
            if (pasoActual === 0 || progreso < PASOS_RESPALDO[pasoActual - 1]?.limite) {
                actualizarProgreso(progreso);
            }
        }
    }, 400);
}

function detenerProgreso() {
    if (intervaloProgreso) {
        clearInterval(intervaloProgreso);
        intervaloProgreso = null;
    }
}

function actualizarProgreso(porcentaje, mensaje = null, detalle = null) {
    const pct = Math.min(Math.round(porcentaje), 100);
    const elPct = document.getElementById('porcentaje-texto');
    const elBarra = document.getElementById('barra-progreso');
    if (elPct) elPct.textContent = pct + '%';
    if (elBarra) elBarra.style.width = pct + '%';
    if (mensaje) {
        const el = document.getElementById('mensaje-progreso');
        if (el) el.textContent = mensaje;
    }
    if (detalle) {
        const el = document.getElementById('detalle-progreso');
        if (el) el.textContent = detalle;
    }
}

// Limpiar intervalo si el modal se cierra manualmente
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('modalRespaldo')?.addEventListener('hidden.bs.modal', detenerProgreso);
});
</script>