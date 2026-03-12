<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
//info_grupo.php



include '../funciones/conexQRConejo.php';
require_once '../QR/phpqrcode/qrlib.php';

session_start();
// Verificar que se haya almacenado el id_credencial y que el nivel_usuario sea 3
if (!isset($_SESSION['id_credencial']) || !isset($_SESSION['nivel_usuario']) || $_SESSION['nivel_usuario'] != 4) {
    echo "Acceso denegado. Detalles de la sesión:";
    var_dump($_SESSION);
    exit;
}
$id_usuario = $_SESSION['id_credencial'];
date_default_timezone_set('America/Mexico_City');

// Clave secreta para desencriptación
$secretKey = 'your-secret-key';

// Función para convertir grupo a número romano
function convertirGrupoARomano($grupo) {
    $grupo = strtoupper(trim($grupo));
    $correspondencia = [
        'A' => 'I',
        'B' => 'II',
        'C' => 'III',
        'D' => 'IV',
        'E' => 'V',
        'F' => 'VI',
        'G' => 'VII',
        'H' => 'VIII',
        'I' => 'IX',
        'J' => 'X'
    ];
    
    return $correspondencia[$grupo] ?? $grupo; // Si no está en el array, devuelve el grupo original
}

// Función para desencriptar
function decryptData($encryptedData, $secretKey) {
    if (empty($encryptedData)) {
        return ''; 
    }
    $parts = explode('::', base64_decode($encryptedData), 2);
    if (count($parts) !== 2) {
        return 'Datos encriptados inválidos';
    }
    list($ciphertext, $iv) = $parts;
    return openssl_decrypt($ciphertext, 'aes-256-cbc', $secretKey, 0, base64_decode($iv));
}

// Obtener el id_escuela del usuario
$query_escuela = "SELECT id_escuela FROM credenciales WHERE id_credencial = '$id_usuario'";
$result_escuela = mysqli_query($conexion, $query_escuela);

if ($result_escuela && mysqli_num_rows($result_escuela) > 0) {
    $row_escuela = mysqli_fetch_assoc($result_escuela);
    $id_escuela = $row_escuela['id_escuela'];
} else {
    die("Error al obtener el id_escuela: " . mysqli_error($conexion));
}

// Obtener el nombre de la escuela
$query_nombre_escuela = "SELECT nombre_escuela FROM escuelas WHERE id_escuela = '$id_escuela'";
$result_nombre_escuela = mysqli_query($conexion, $query_nombre_escuela);

if ($result_nombre_escuela && mysqli_num_rows($result_nombre_escuela) > 0) {
    $row_nombre_escuela = mysqli_fetch_assoc($result_nombre_escuela);
    $nombre_escuela = $row_nombre_escuela['nombre_escuela'];
} else {
    die("Error al obtener el nombre de la escuela: " . mysqli_error($conexion));
}

// Obtener el grado, grupo y turno desde la URL
$grado = isset($_GET['grado']) ? $_GET['grado'] : '';
$grupo = isset($_GET['grupo']) ? $_GET['grupo'] : '';
$turno = isset($_GET['turno']) ? $_GET['turno'] : '';

if (empty($grado) || empty($grupo)) {
    die("Grado y grupo no especificados.");
}

// Convertir grupo a romano para mostrarlo en la interfaz
$grupo_mostrar = convertirGrupoARomano($grupo);

// Consulta para obtener los alumnos del grupo sin ordenar
$query_alumnos = "
    SELECT 
    id_credencial,
    nombre_credencial,
    apellidos_credencial
    FROM credenciales
    WHERE grado_credencial = '$grado'
    AND grupo_credencial = '$grupo'
    AND turno_credencial = '$turno'
    AND id_escuela = '$id_escuela'
    AND nivel_usuario = 7
    
";

$result_alumnos = mysqli_query($conexion, $query_alumnos);

// Crear array de alumnos y desencriptar apellidos
$alumnos = array();
while($alumno = mysqli_fetch_assoc($result_alumnos)) {
    $apellidos = decryptData($alumno['apellidos_credencial'], $secretKey);
    $alumno['apellidos_desencriptados'] = $apellidos;
    $alumnos[] = $alumno;
}

// Ordenar por apellidos desencriptados (A-Z)
usort($alumnos, function($a, $b) {
    return strcmp($a['apellidos_desencriptados'], $b['apellidos_desencriptados']);
});

// Consulta para obtener materias y maestros
$query_materias_maestros = "
    SELECT DISTINCT
        m.id_materia, 
        m.nombre_materia,
        cr.nombre_credencial,
        cr.apellidos_credencial
    FROM asignacion_materias am
    JOIN materias m ON am.id_materia = m.id_materia
    JOIN credenciales cr ON am.id_credencial = cr.id_credencial
    WHERE am.grado_credencial = '$grado'
    AND am.grupo_credencial = '$grupo'
    AND am.turno_credencial = '$turno'
    AND am.id_escuela = '$id_escuela'
    ORDER BY N_orden_materia ASC
";

$result_materias = mysqli_query($conexion, $query_materias_maestros);
?>
<?php include 'header_orientador.php'; ?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Información del Grupo <?= htmlspecialchars($grado) ?>-<?= htmlspecialchars($grupo_mostrar) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: 'League Spartan', sans-serif;
            background-color: #f8f9fa;
            padding-top: 20px;
        }
        .main-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        .header-group {
            text-align: center;
            padding-bottom: 20px;
            margin-bottom: 30px;
            border-bottom: 2px solid #007bff;
        }
        .header-group h2 {
            color: #007bff;
            font-weight: bold;
        }
        .content-wrapper {
            display: flex;
            gap: 30px;
        }
        .alumnos-section {
            flex: 1;
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            padding: 25px;
        }
        .materias-section {
            flex: 1;
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            padding: 25px;
        }
        .section-title {
            font-size: 1.5rem;
            color: #007bff;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            align-items: center;
        }
        .alumnos-list {
            max-height: 600px;
            overflow-y: auto;
            padding-right: 10px;
        }
        .alumno-item {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
            transition: background-color 0.2s;
        }
        .alumno-item:hover {
            background-color: #f8f9fa;
        }
        .alumno-icon {
            margin-right: 10px;
            color: #6c757d;
            width: 25px;
            text-align: center;
        }
        .materias-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 15px;
        }
        .materia-card {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
            transition: all 0.3s ease;
            position: relative;
        }
        .materia-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border-color: #007bff;
        }
        .materia-title {
            font-size: 1.2rem;
            color: #007bff;
            font-weight: bold;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            width: 70%;
        }
        .maestro-info {
            display: flex;
            align-items: center;
            margin-top: 10px;
            padding-left: 5px;
        }
        .maestro-icon {
            color: #6c757d;
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        .btn-boletas {
            display: block;
            width: 100%;
            margin-top: 30px;
            padding: 10px;
            font-weight: bold;
        }
        .empty-message {
            text-align: center;
            padding: 30px;
            color: #6c757d;
            background-color: #f8f9fa;
            border-radius: 8px;
        }
        .empty-icon {
            font-size: 2rem;
            margin-bottom: 15px;
            color: #adb5bd;
        }
        .btn-f1 {
            position: absolute;
            width: 20%;
            right: 15px;
            top: 20%;
            padding: 5px 10px;
            font-size: 0.8rem;
            background-color: #28a745;
            border-color: #28a745;
        }
        .btn-f1:hover {
            background-color: #218838;
            border-color: #1e7e34;
        }
        @media (max-width: 992px) {
            .content-wrapper {
                flex-direction: column;
            }
            .alumnos-list {
                max-height: 300px;
            }
        }
        /* Estilo para la barra de scroll */
        .alumnos-list::-webkit-scrollbar {
            width: 8px;
        }
        .alumnos-list::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        .alumnos-list::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 10px;
        }
        .alumnos-list::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }
    </style>
</head>
<body>
    <br><br><br>
    <div class="main-container">
        <div class="header-group">
            <h2><i class="fas fa-users-class me-2"></i> Grupo <?= htmlspecialchars($grado) ?>-<?= htmlspecialchars($grupo_mostrar) ?></h2>
            <h4><?= htmlspecialchars($nombre_escuela) ?></h4>
            <h5 class="text-muted">Turno <?= htmlspecialchars($turno) ?></h5>
        </div>

        <div class="content-wrapper">
            <!-- Sección de Alumnos (Izquierda) -->
            <div class="alumnos-section">
                <h3 class="section-title">
                    <i class="fas fa-user-graduate me-2"></i>
                    Alumnos (<?= count($alumnos) ?>)
                </h3>
                
                <div class="alumnos-list">
                    <?php if(count($alumnos) > 0): ?>
                        <?php foreach($alumnos as $alumno): ?>
                            <div class="alumno-item">
                                <i class="fas fa-user alumno-icon"></i>
                                <span><?= htmlspecialchars($alumno['apellidos_desencriptados']) . ' ' . htmlspecialchars($alumno['nombre_credencial']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-message">
                            <div class="empty-icon">
                                <i class="fas fa-user-slash"></i>
                            </div>
                            <p>No hay alumnos registrados en este grupo</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <a href="F_tabla_asistencia.php?grado=<?= urlencode($grado) ?>&grupo=<?= urlencode($grupo) ?>&turno=<?= urlencode($turno) ?>" 
                   class="btn btn-primary btn-boletas"
                   target="_blank">
                   <i class="fas fa-file-alt me-2"></i>Ver Listas De Asistencias
                </a>
                <a href="Concentrado_Calificaciones.php?grado=<?= urlencode($grado) ?>&grupo=<?= urlencode($grupo) ?>&turno=<?= urlencode($turno) ?>" 
   target="_blank"
 
   <i 
</a>
                
                <a href="listado_extraordinarios.php?grado=<?= urlencode($grado) ?>&grupo=<?= urlencode($grupo) ?>&turno=<?= urlencode($turno) ?>" 
                   class="btn btn-primary btn-boletas"
                   target="_blank">
                   <i class="fas fa-file-alt me-2"></i>Ver listados de extraordinarios
                </a>
            </div>

            <!-- Sección de Materias y Maestros (Derecha) -->
            <div class="materias-section">
                <h3 class="section-title">
                    <i class="fas fa-book me-2"></i>
                    Materias y Maestros (<?= mysqli_num_rows($result_materias) ?>)
                </h3>
                
                <div class="materias-grid">
                    <?php if(mysqli_num_rows($result_materias) > 0): ?>
                        <?php while($materia = mysqli_fetch_assoc($result_materias)): ?>
                            <?php 
                            $apellidos = decryptData($materia['apellidos_credencial'], $secretKey);
                            $nombre_maestro = htmlspecialchars($materia['nombre_credencial']) . ' ' . htmlspecialchars($apellidos);
                            ?>
                            <div class="materia-card">
                                <a href="example.php?id_escuela=<?= $id_escuela ?>&grado=<?= urlencode($grado) ?>&grupo=<?= urlencode($grupo) ?>&turno=<?= urlencode($turno) ?>&id_materia=<?= $materia['id_materia'] ?>" 
                                   
                                   target="_blank">
                                   <i class="fas fa-file-pdf me-1"></i>F1
                                </a>
                                <div class="materia-title">
                                    <i class="fas fa-book-open me-2"></i>
                                    <?= htmlspecialchars($materia['nombre_materia']) ?>
                                </div>
                                <div class="maestro-info">
                                    <i class="fas fa-chalkboard-teacher maestro-icon"></i>
                                    <span><?= $nombre_maestro ?></span>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="empty-message">
                            <div class="empty-icon">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <p>No hay materias asignadas para este grupo</p>
                        </div>
                    <?php endif; ?>
                </div>

                <a href="boleta_alumnos_nueva_beta.php?grado=<?= urlencode($grado) ?>&grupo=<?= urlencode($grupo) ?>&turno=<?= urlencode($turno) ?>" 
                   class="btn btn-primary btn-boletas">
                   <i class="fas fa-file-alt me-2"></i>Ver Boleta Grupal
                </a>
            </div>
        </div>
    </div>

</body>
</html>

<?php
mysqli_close($conexion);
?>
<?php include 'footer_orientador.php'; ?>