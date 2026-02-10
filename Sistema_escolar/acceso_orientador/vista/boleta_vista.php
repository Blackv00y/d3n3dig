<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Boleta Moderna</title>
<!--boleta_vista.php - DISEÑO ORIGINAL + BOTÓN RESPALDO MANUAL-->
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
            background: white;
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
            background:white;
            border: none;
            text-decoration: none;
            color: black;
            font-weight: bold;
            padding: 5px 15px;
            border-radius: 10px 10px;
            margin-top: auto;
        }
        /* Botón de respaldo manual - estilo discreto que combina con el diseño */
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
        /* Badge para boleta completa */
        .badge-complete {
            background: rgba(40, 167, 69, 0.9);
            color: white;
            padding: 5px 12px;
            border-radius: 8px;
            font-size: 0.85rem;
            margin-left: 8px;
            font-weight: 600;
        }
        .grades-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            font-size: 0.9rem;
        }
        .grades-table th {
            background-color: #f1f1f1;
            padding: 8px;
            text-align: left;
            font-weight: bold;
            border: 1px solid #ddd;
        }
        .student-card{
            background: linear-gradient(to right, #0f6fff, #14f1f8);
        }
        .grades-table td {
            padding: 8px;
            border: 1px solid #dddddd;
            text-align: center;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header-title">Boleta de Calificaciones</div>
    <div class="text-center mb-4">
        Escuela: <strong><?= htmlspecialchars($nombre_escuela) ?></strong><br>
        Grado: <strong><?= htmlspecialchars($grado) ?></strong> - Grupo: <strong><?= htmlspecialchars($grupo_romano) ?></strong> - Turno: <strong><?= htmlspecialchars($turno) ?></strong>
        Total de alumnos: <strong><?= htmlspecialchars($total_alumnos) ?></strong> 
    </div>

    <!-- Tarjetas por alumno -->
    <?php foreach ($alumnos as $alum): ?>
        <?php
        $nombre_completo = htmlspecialchars($alum['nombre_credencial'] . ' ' . $alum['apellidos_decrypted']);
        $foto = !empty($alum['ruta_foto']) ? htmlspecialchars($alum['ruta_foto']) : 'https://tse3.mm.bing.net/th/id/OIP.2L4bAjBAkwILmakMvHA8AgHaFY?rs=1&pid=ImgDetMain&o=7&rm=3';
        $boleta_completa = $alum['boleta_completa'] ?? false;
        ?>
        <div class="student-card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <div style="display: flex; align-items: center; gap: 15px;">
                    <img src="<?= $foto ?>" alt="Foto" class="student-avatar">
                    <div>
                        <div class="student-name"><?= $nombre_completo ?></div>
                        <div style="font-size: 0.9rem; color: #f9f9f9;">Estudiante / <?= htmlspecialchars($grado) ?> <?= htmlspecialchars($grupo_romano)?> <?= htmlspecialchars(" / Turno:".$turno) ?></div>
                    </div>
                </div>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <!-- Botón principal: Imprimir PDF (siempre visible) -->
                    <a href="generar_pdf_individual.php?id=<?= $alum['id_credencial'] ?>" target="_blank" class="download-btn">
                        📄 Imprimir PDF
                    </a>
                    
                    <?php if ($boleta_completa): ?>
                        <!-- Si está completa: mostrar badge verde -->
                        <span class="badge-complete">✓ Respaldada</span>
                    <?php else: ?>
                        <!-- Si NO está completa: mostrar botón de respaldo manual -->
                        <a href="generar_pdf_individual.php?id=<?= $alum['id_credencial'] ?>&forzar_respaldo=1" 
                           target="_blank" 
                           class="backup-btn"
                           onclick="return confirm('¿Generar respaldo manual?\n\nSe guardará como Boleta_Manual_<?= $alum['id_credencial'] ?>.pdf');">
                            💾 Respaldar
                        ></a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Botón ZIP -->
<a href="generar_zip_boletas.php?grado=<?= urlencode($grado) ?>&grupo=<?= urlencode($grupo) ?>&turno=<?= urlencode($turno) ?>" 
   class="btn btn-download-all">
    Descargar Todas las Boletas en ZIP (<?= count($alumnos) ?> estudiantes)
</a>

<!-- Nota informativa discreta -->
<div class="container mt-3 mb-4">
    <div class="alert alert-info" style="font-size: 0.9rem;">
        <strong>ℹ️ Información sobre Respaldos:</strong>
        <ul class="mb-0" style="font-size: 0.85rem;">
            <li><strong>Respaldo Automático:</strong> Las boletas con todas las calificaciones capturadas se guardan automáticamente como <code>Boleta_Final_[ID].pdf</code></li>
            <li><strong>Botón "Respaldar":</strong> Permite guardar manualmente boletas incompletas como <code>Boleta_Manual_[ID].pdf</code></li>
            <li><strong>Ubicación:</strong> <code>respaldos/boletas/<?= $id_escuela ?>/grupos/[Grado] [Grupo]/</code></li>
        </ul>
    </div>
</div>

<?php include 'footer_orientador.php'; ?>

</body>
</html>
