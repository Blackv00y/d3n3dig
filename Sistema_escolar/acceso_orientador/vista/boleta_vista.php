<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Boleta Moderna</title>
    <!-- boleta_vista.php — CON MODAL DE RESPALDO GRUPAL Y BARRA DE PROGRESO -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'League Spartan', sans-serif; background: #f8f9fa; padding: 20px; }
        .container { max-width: 1200px; }

        .header-title {
            text-align: center;
            margin-bottom: 30px;
            color: #1a355e;
            font-size: 2rem;
            font-weight: bold;
            margin-top: 3em;
        }

        /* CONTENEDOR DE INFO + BOTÓN RESPALDO GRUPAL */
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
        .info-text {
            flex: 1;
            line-height: 1.6;
        }

        /* Botón de respaldo grupal — verde, ahora dispara modal */
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
        }
        .student-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #dddddd;
        }
        .student-name {
            font-size: 1.2rem;
            font-weight: bold;
            color: #ffffff;
        }
        .download-btn {
            background: white;
            border: none;
            text-decoration: none;
            color: black;
            font-weight: bold;
            padding: 5px 15px;
            border-radius: 10px;
            margin-top: auto;
        }
        /* Botón de respaldo individual — discreto sobre fondo degradado */
        .backup-btn {
            background: rgba(255, 255, 255, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.6);
            text-decoration: none;
            color: white;
            font-weight: 600;
            padding: 5px 12px;
            border-radius: 8px;
            font-size: 0.85rem;
            margin-left: 8px;
            display: inline-block;
        }
        .backup-btn:hover {
            background: rgba(255, 255, 255, 0.5);
            color: white;
        }

        /* Mensaje de éxito/resultado de respaldo */
        .alert-success-custom {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            border: 2px solid #28a745;
            color: #155724;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 600;
            box-shadow: 0 2px 8px rgba(40, 167, 69, 0.2);
        }
        
        /* Estilos para la barra de progreso personalizada */
        .progreso-container {
            position: relative;
            width: 100px;
            height: 100px;
            margin: 0 auto;
        }
        .progreso-circular {
            position: absolute;
            top: 50%;
            left: 50%;
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
    </style>
</head>
<body>
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

    <!-- ── INFO ESCOLAR + BOTÓN RESPALDO (ahora abre el modal) ── -->
    <div class="info-header-wrapper">
        <div class="info-text">
            <strong>Escuela:</strong> <?= htmlspecialchars($nombre_escuela) ?><br>
            <strong>Grado:</strong> <?= htmlspecialchars($grado) ?> |
            <strong>Grupo:</strong> <?= htmlspecialchars($grupo_romano) ?> |
            <strong>Turno:</strong> <?= htmlspecialchars($turno) ?> |
            <strong>Total de alumnos:</strong> <?= htmlspecialchars($total_alumnos) ?>
        </div>
        <div>
            <!-- onclick llama a la función JS que abre el modal y dispara el AJAX -->
            <button
                class="btn-backup-group"
                onclick="abrirModalRespaldo(
                    '<?= htmlspecialchars($grado,  ENT_QUOTES) ?>',
                    '<?= htmlspecialchars($grupo,  ENT_QUOTES) ?>',
                    '<?= htmlspecialchars($turno,  ENT_QUOTES) ?>'
                )">
                💾 Generar Respaldo General
            </button>
        </div>
    </div>

    <!-- ── TARJETAS POR ALUMNO ── -->
    <?php foreach ($alumnos as $alum): ?>
        <?php
        $nombre_completo = htmlspecialchars($alum['nombre_credencial'] . ' ' . $alum['apellidos_decrypted']);
        $foto = !empty($alum['ruta_foto'])
            ? htmlspecialchars($alum['ruta_foto'])
            : 'https://tse3.mm.bing.net/th/id/OIP.2L4bAjBAkwILmakMvHA8AgHaFY?rs=1&pid=ImgDetMain&o=7&rm=3';
        ?>
        <div class="student-card">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                <div style="display:flex; align-items:center; gap:15px;">
                    <img src="<?= $foto ?>" alt="Foto" class="student-avatar">
                    <div>
                        <div class="student-name"><?= $nombre_completo ?></div>
                        <div style="font-size:0.9rem; color:#f9f9f9;">
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

</div><!-- /.container -->

<!-- ── BOTÓN ZIP ── -->
<a href="generar_zip_boletas.php?grado=<?= urlencode($grado) ?>&grupo=<?= urlencode($grupo) ?>&turno=<?= urlencode($turno) ?>"
   class="btn btn-download-all">
    Descargar Todas las Boletas en ZIP (<?= count($alumnos) ?> estudiantes)
</a>

<!-- ── NOTA INFORMATIVA ── -->
<div class="container mt-3 mb-4">
    <div class="alert alert-info" style="font-size:0.9rem;">
        <strong>ℹ️ Información sobre Respaldos:</strong>
        <ul class="mb-0" style="font-size:0.85rem;">
            <li>
                <strong>Respaldo General del Grupo:</strong>
                Al hacer clic en "Generar Respaldo General" se analizará el grupo y podrás elegir
                respaldar <em>solo las boletas completas</em> o <em>todo el grupo</em>.
                Los parciales faltantes aparecerán como <code>--</code>.
            </li>
            <li>
                <strong>Botón "💾 Respaldar" individual:</strong>
                Guarda la boleta del alumno en el servidor como <code>Boleta_Final_[ID].pdf</code>.
            </li>
            <li>
                <strong>Ubicación:</strong>
                <code>respaldos/boletas/<?= $id_escuela ?>/grupos/[Grado] [Grupo]/</code>
            </li>
        </ul>
    </div>
</div>

<?php include 'footer_orientador.php'; ?>

<!-- ================================================================
     MODAL DE RESPALDO GRUPAL CON BARRA DE PROGRESO
     ================================================================ -->
<div class="modal fade" id="modalRespaldo" tabindex="-1"
     aria-labelledby="modalRespaldoLabel" aria-hidden="true"
     data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:16px; overflow:hidden;">

            <!-- Cabecera -->
            <div class="modal-header"
                 style="background:linear-gradient(135deg,#1a355e,#2b91ff); color:white; border:none;">
                <h5 class="modal-title fw-bold" id="modalRespaldoLabel">
                    💾 Generar Respaldo del Grupo
                </h5>
                <button type="button" class="btn-close btn-close-white"
                        data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <!-- Cuerpo -->
            <div class="modal-body p-4">

                <!-- ========== ESTADO: CARGANDO CON PORCENTAJE ========== -->
                <div id="respaldo-loading" class="text-center py-4">
                    <!-- Círculo con porcentaje integrado -->
                    <div class="progreso-container">
                        <div class="spinner-border text-primary" style="width: 100px; height: 100px;" role="status"></div>
                        <div id="porcentaje-texto" class="progreso-circular">0%</div>
                    </div>
                    
                    <!-- Barra de progreso animada -->
                    <div class="progress-custom progress mt-4">
                        <div id="barra-progreso" class="progress-bar progress-bar-striped progress-bar-animated progress-bar-custom" 
                             style="width: 0%;">
                        </div>
                    </div>
                    
                    <!-- Mensajes de estado dinámicos -->
                    <p class="mt-4 fw-semibold fs-5" id="mensaje-progreso" style="color: #1a355e;">
                        ⏳ Iniciando respaldo grupal...
                    </p>
                    
                    <p class="text-muted small mb-0" id="detalle-progreso">
                        Preparando análisis del grupo...
                    </p>
                </div>

                <!-- Estado: error -->
                <div id="respaldo-error" class="alert alert-danger d-none" role="alert">
                    <strong>⚠️ Error:</strong>
                    <span id="respaldo-error-msg"></span>
                </div>

                <!-- Estado: resultados -->
                <div id="respaldo-resultados" class="d-none">

                    <!-- Tarjetas de conteo -->
                    <div class="row g-3 mb-4">
                        <div class="col-4 text-center">
                            <div class="p-3 rounded-3"
                                 style="background:#f0f4ff; border:1px solid #c8d8ff;">
                                <div class="fs-2 fw-bold text-primary" id="cnt-total">—</div>
                                <div class="small text-muted">Total de alumnos</div>
                            </div>
                        </div>
                        <div class="col-4 text-center">
                            <div class="p-3 rounded-3"
                                 style="background:#f0fff4; border:1px solid #a8e6c0;">
                                <div class="fs-2 fw-bold text-success" id="cnt-listos">—</div>
                                <div class="small text-muted">Boletas completas</div>
                            </div>
                        </div>
                        <div class="col-4 text-center">
                            <div class="p-3 rounded-3"
                                 style="background:#fff8f0; border:1px solid #ffd8a8;">
                                <div class="fs-2 fw-bold text-warning" id="cnt-pendientes">—</div>
                                <div class="small text-muted">Con pendientes</div>
                            </div>
                        </div>
                    </div>

                    <!-- Nota contextual dinámica -->
                    <div id="respaldo-nota" class="alert mb-0" role="alert"></div>

                </div>
            </div><!-- /.modal-body -->

            <!-- Pie con botones de acción -->
            <div id="respaldo-acciones"
                 class="modal-footer d-none justify-content-between"
                 style="border-top:1px solid #dee2e6; padding:16px 24px;">

                <button type="button" class="btn btn-outline-secondary"
                        data-bs-dismiss="modal">
                    Cancelar
                </button>

                <div class="d-flex gap-2">
                    <!-- Solo alumnos con boleta completa (todo=0) -->
                    <button type="button" id="btn-solo-listos"
                            class="btn btn-outline-success fw-semibold"
                            onclick="ejecutarRespaldo(0)">
                        ✅ Solo boletas completas
                    </button>

                    <!-- Todos los alumnos (todo=1) -->
                    <button type="button" id="btn-todos"
                            class="btn fw-bold text-white"
                            style="background:linear-gradient(135deg,#28a745,#20c997);"
                            onclick="ejecutarRespaldo(1)">
                        💾 Respaldar todo el grupo
                    </button>
                </div>
            </div>

        </div>
    </div>
</div><!-- /#modalRespaldo -->

<!-- ================================================================
     JAVASCRIPT DEL MODAL CON BARRA DE PROGRESO
     ================================================================ -->
<script>
// Almacena los parámetros del grupo activo mientras el modal está abierto
const _respaldo = { grado: '', grupo: '', turno: '' };
let intervaloProgreso = null;

/**
 * Abre el modal y lanza la verificación AJAX con barra de progreso.
 * Se llama desde el botón "Generar Respaldo General".
 */
function abrirModalRespaldo(grado, grupo, turno) {
    _respaldo.grado = grado;
    _respaldo.grupo = grupo;
    _respaldo.turno = turno;

    // Resetear estado visual
    resetearModal();

    // Abrir modal Bootstrap 5
    new bootstrap.Modal(document.getElementById('modalRespaldo')).show();

    // Iniciar animación de progreso simulado
    iniciarProgresoSimulado();

    // Disparar la consulta AJAX
    verificarGrupo(grado, grupo, turno);
}

/**
 * Resetea el modal a su estado inicial
 */
function resetearModal() {
    // Mostrar pantalla de carga
    document.getElementById('respaldo-loading').classList.remove('d-none');
    document.getElementById('respaldo-error').classList.add('d-none');
    document.getElementById('respaldo-resultados').classList.add('d-none');
    document.getElementById('respaldo-acciones').classList.add('d-none');
    
    // Restaurar botones
    document.getElementById('btn-solo-listos')?.classList.remove('d-none');
    document.getElementById('btn-todos')?.classList.remove('d-none');
    
    // Resetear progreso a 0%
    actualizarProgreso(0, '⏳ Iniciando respaldo grupal...', 'Preparando análisis del grupo...');
    
    // Limpiar intervalo anterior si existe
    if (intervaloProgreso) {
        clearInterval(intervaloProgreso);
        intervaloProgreso = null;
    }
}

/**
 * Inicia la simulación de progreso mientras se espera la respuesta del servidor
 */
function iniciarProgresoSimulado() {
    let progreso = 0;
    let pasoActual = 0;
    
    const pasos = [
        { limite: 15, mensaje: '🔍 Analizando grupo...', detalle: 'Verificando alumnos registrados...' },
        { limite: 30, mensaje: '📄 Revisando boletas...', detalle: 'Comprobando calificaciones por parcial...' },
        { limite: 50, mensaje: '⚙️ Procesando información...', detalle: 'Calculando estadísticas del grupo...' },
        { limite: 70, mensaje: '📊 Generando reporte...', detalle: 'Preparando resumen de estado...' },
        { limite: 90, mensaje: '💾 Finalizando análisis...', detalle: 'Cargando resultados...' }
    ];
    
    intervaloProgreso = setInterval(() => {
        if (progreso < 95) {
            // Incremento aleatorio para simular procesamiento real
            progreso += Math.floor(Math.random() * 4) + 1;
            if (progreso > 95) progreso = 95;
            
            // Actualizar mensaje según el progreso
            while (pasoActual < pasos.length && progreso >= pasos[pasoActual].limite) {
                actualizarProgreso(progreso, pasos[pasoActual].mensaje, pasos[pasoActual].detalle);
                pasoActual++;
            }
            
            actualizarProgreso(progreso);
        }
    }, 350);
}

/**
 * Actualiza visualmente los elementos de progreso
 */
function actualizarProgreso(porcentaje, mensaje = null, detalle = null) {
    // Actualizar texto del porcentaje
    const porcentajeEl = document.getElementById('porcentaje-texto');
    if (porcentajeEl) porcentajeEl.textContent = Math.round(porcentaje) + '%';
    
    // Actualizar barra de progreso
    const barraEl = document.getElementById('barra-progreso');
    if (barraEl) barraEl.style.width = porcentaje + '%';
    
    // Actualizar mensaje principal
    if (mensaje) {
        const mensajeEl = document.getElementById('mensaje-progreso');
        if (mensajeEl) mensajeEl.textContent = mensaje;
    }
    
    // Actualizar detalle
    if (detalle) {
        const detalleEl = document.getElementById('detalle-progreso');
        if (detalleEl) detalleEl.textContent = detalle;
    }
}

/**
 * Llama a verificar_estado_grupo.php y recibe el JSON con los conteos.
 */
function verificarGrupo(grado, grupo, turno) {
    const url = `verificar_estado_grupo.php`
              + `?grado=${encodeURIComponent(grado)}`
              + `&grupo=${encodeURIComponent(grupo)}`
              + `&turno=${encodeURIComponent(turno)}`;

    fetch(url, { credentials: 'same-origin' })
        .then(res => {
            if (!res.ok) throw new Error(`Error HTTP ${res.status}`);
            return res.json();
        })
        .then(data => {
            // Detener el progreso simulado
            if (intervaloProgreso) {
                clearInterval(intervaloProgreso);
                intervaloProgreso = null;
            }
            
            // Mostrar progreso completado
            actualizarProgreso(100, '✅ Análisis completado exitosamente', 'Listo para generar respaldo');
            
            // Pequeño delay para mostrar el 100%
            setTimeout(() => {
                renderizarResultados(data);
            }, 600);
        })
        .catch(err => {
            // Limpiar intervalo en caso de error
            if (intervaloProgreso) {
                clearInterval(intervaloProgreso);
                intervaloProgreso = null;
            }
            
            // Ocultar pantalla de carga y mostrar error
            document.getElementById('respaldo-loading').classList.add('d-none');
            document.getElementById('respaldo-error-msg').textContent = err.message;
            document.getElementById('respaldo-error').classList.remove('d-none');
        });
}

/**
 * Rellena el modal con los datos recibidos y ajusta los botones según el escenario.
 */
function renderizarResultados(data) {
    // Ocultar pantalla de carga
    document.getElementById('respaldo-loading').classList.add('d-none');

    // Actualizar contadores
    document.getElementById('cnt-total').textContent      = data.total;
    document.getElementById('cnt-listos').textContent     = data.listos;
    document.getElementById('cnt-pendientes').textContent = data.pendientes;

    const notaEl        = document.getElementById('respaldo-nota');
    const btnSoloListos = document.getElementById('btn-solo-listos');
    const btnTodos      = document.getElementById('btn-todos');

    if (data.total === 0) {
        // Sin alumnos — no hay nada que respaldar
        notaEl.className = 'alert alert-warning mb-0';
        notaEl.innerHTML = '⚠️ No se encontraron alumnos en este grupo.';
        btnSoloListos.classList.add('d-none');
        btnTodos.classList.add('d-none');

    } else if (data.pendientes === 0) {
        // Todos completos — no tiene sentido el botón "solo listos"
        notaEl.className = 'alert alert-success mb-0';
        notaEl.innerHTML = `✅ <strong>¡Grupo listo!</strong> Los ${data.total} alumnos tienen sus 3 parciales capturados.`;
        btnSoloListos.classList.add('d-none');

    } else if (data.listos === 0) {
        // Ninguno completo — solo tiene sentido respaldar todo
        notaEl.className = 'alert alert-warning mb-0';
        notaEl.innerHTML = `⚠️ <strong>Ningún alumno</strong> tiene los 3 parciales completos. `
                         + `Se generarán boletas con calificaciones pendientes (<code>--</code>).`;
        btnSoloListos.classList.add('d-none');

    } else {
        // Mezcla: hay listos y pendientes — mostrar ambas opciones
        notaEl.className = 'alert alert-info mb-0';
        notaEl.innerHTML = `ℹ️ Hay <strong>${data.listos} alumno(s) con boleta completa</strong> `
                         + `y <strong>${data.pendientes} con calificaciones pendientes</strong>. `
                         + `Elige cómo proceder:`;
    }

    // Mostrar resultados y acciones
    document.getElementById('respaldo-resultados').classList.remove('d-none');
    document.getElementById('respaldo-acciones').classList.remove('d-none');
}

/**
 * Cierra el modal y redirige a generar_respaldo_grupal.php.
 *   todo=0 → solo alumnos con boleta completa
 *   todo=1 → todos los alumnos del grupo
 */
function ejecutarRespaldo(todo) {
    const url = `generar_respaldo_grupal.php`
              + `?grado=${encodeURIComponent(_respaldo.grado)}`
              + `&grupo=${encodeURIComponent(_respaldo.grupo)}`
              + `&turno=${encodeURIComponent(_respaldo.turno)}`
              + `&todo=${todo}`;

    // Cerrar modal
    bootstrap.Modal.getInstance(document.getElementById('modalRespaldo'))?.hide();
    
    // Limpiar intervalo si existe
    if (intervaloProgreso) {
        clearInterval(intervaloProgreso);
        intervaloProgreso = null;
    }
    
    // Redirigir
    window.location.href = url;
}

// Limpiar intervalo cuando se cierra el modal manualmente
document.addEventListener('DOMContentLoaded', function() {
    const modalElement = document.getElementById('modalRespaldo');
    modalElement.addEventListener('hidden.bs.modal', function() {
        if (intervaloProgreso) {
            clearInterval(intervaloProgreso);
            intervaloProgreso = null;
        }
    });
});
</script>

<!-- Cargar Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>