<?php
// vista_general_grupos_beta.php
include '../funciones/conexQRConejo.php';

// Iniciar la sesión
session_start();

// Verificar que se haya almacenado el id_credencial y que el nivel_usuario sea 3
if (!isset($_SESSION['id_credencial']) || !isset($_SESSION['nivel_usuario']) || $_SESSION['nivel_usuario'] != 4) {
    echo "Acceso denegado. Detalles de la sesión:";
    var_dump($_SESSION);
    exit;
}

// Función para convertir letra de grupo a número romano
function grupoToRomano($letra) {
    $letra = strtoupper($letra);
    $grupos = [
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
    
    return $grupos[$letra] ?? $letra; // Si no está en el rango A-J, devuelve la letra original
}

// Obtener el id_credencial del maestro logueado
$id_credencial_maestro = $_SESSION['id_credencial'];

// Obtener el id_escuela del maestro desde la tabla credenciales
$query_escuela = "SELECT id_escuela FROM credenciales WHERE id_credencial = ?";
$stmt_escuela = mysqli_prepare($conexion, $query_escuela);
mysqli_stmt_bind_param($stmt_escuela, "i", $id_credencial_maestro);
mysqli_stmt_execute($stmt_escuela);
$resultado_escuela = mysqli_stmt_get_result($stmt_escuela);

$id_escuela_maestro = null;
if ($fila_escuela = mysqli_fetch_assoc($resultado_escuela)) {
    $id_escuela_maestro = $fila_escuela['id_escuela'];
}

// Verificar que se obtuvo un id_escuela
if ($id_escuela_maestro === null) {
    echo "No se pudo obtener el ID de la escuela para el maestro.";
    exit;
}

// Obtener los grupos y grados asignados al orientador
$query_grupos_asignados = "SELECT grados_grupos_orientador FROM credenciales WHERE id_credencial = ?";
$stmt = mysqli_prepare($conexion, $query_grupos_asignados);
mysqli_stmt_bind_param($stmt, "i", $id_credencial_maestro);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$grupos_permitidos = [];

if ($fila = mysqli_fetch_assoc($res)) {
    $cadena = $fila['grados_grupos_orientador'];
    echo "<br>Grados y grupos permitidos (grados_grupos_orientador): " . htmlspecialchars($cadena) . "<br>";  // Depuración: Ver los datos obtenidos
    $pares = explode(',', $cadena);
    foreach ($pares as $par) {
        $partes = explode(' ', trim($par));
        if (count($partes) == 2) {
            $grupos_permitidos[] = [
                'grado' => $partes[0],  // Deja el grado tal cual
                'grupo' => $partes[1]   // Deja el grupo tal cual
            ];
        }
    }
}


// Crear filtro dinámico para la consulta de los grupos
$filtros_where = [];
foreach ($grupos_permitidos as $g) {
    $grado = mysqli_real_escape_string($conexion, $g['grado']);
    $grupo = mysqli_real_escape_string($conexion, $g['grupo']);
    $filtros_where[] = "(c.grado_credencial = '$grado' AND c.grupo_credencial = '$grupo')";
}
$condicion_grupos = implode(' OR ', $filtros_where);

// Consulta principal para obtener los grupos asignados con el filtro de grados y grupos
$query_grupos = "
    SELECT 
        c.grado_credencial, 
        c.grupo_credencial, 
        c.turno_credencial,
        COUNT(DISTINCT c.id_credencial) AS total_alumnos
    FROM credenciales c
    WHERE c.id_escuela = ?
    AND nivel_usuario = 7
    AND status = 'activo'
    AND ($condicion_grupos)
    GROUP BY c.grado_credencial, c.grupo_credencial, c.turno_credencial
    ORDER BY 
        CASE WHEN c.turno_credencial = 'Matutino' THEN 1 ELSE 2 END,
        CASE 
            WHEN c.grado_credencial LIKE 'Primero%' THEN 1
            WHEN c.grado_credencial LIKE 'Segundo%' THEN 2
            WHEN c.grado_credencial LIKE 'Tercero%' THEN 3
            WHEN c.grado_credencial LIKE 'Cuarto%' THEN 4
            WHEN c.grado_credencial LIKE 'Quinto%' THEN 5
            WHEN c.grado_credencial LIKE 'Sexto%' THEN 6
            ELSE 99
        END,
        c.grupo_credencial
";



// Ejecutar la consulta
$stmt = mysqli_prepare($conexion, $query_grupos);
mysqli_stmt_bind_param($stmt, "i", $id_escuela_maestro);
mysqli_stmt_execute($stmt);
$resultado = mysqli_stmt_get_result($stmt);



$grupos = [];
while ($fila = mysqli_fetch_assoc($resultado)) {
    

    // Usa la conversión a romano solo para la visualización
    $fila['grupo_romano'] = grupoToRomano($fila['grupo_credencial']);
    $grupos[] = $fila;
}


?>

<?php include 'header_orientador.php'; ?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grupos Escolares</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            margin: 0;
            padding: 0;
            min-height: 100vh;
            background: #333;
            justify-content: center;
            align-items: center;
            font-family: consolas;
        }

        .container-grupos {
            width: 90%;
            max-width: 1200px;
            position: relative;
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 30px;
            margin: 5% auto;
            padding-bottom: 50px;
        }

        .container-grupos .card {
            position: relative;
            cursor: pointer;
            background-color: transparent !important;
            border: none;
            flex: 0 0 calc(33.333% - 30px);
        }

        .container-grupos .card .face {
            width: 100%;
            height: 200px;
            transition: 0.5s;
        }

        .container-grupos .card .face.face1 {
            position: relative;
            background: #333;
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1;
            transform: translateY(100px);
        }

        .container-grupos .card:hover .face.face1 {
            background: #007bff;
            transform: translateY(0);
        }

        .container-grupos .card .face.face1 .content {
            opacity: 0.8;
            transition: 0.5s;
            text-align: center;
            color: white;
            width: 100%;
        }

        .container-grupos .card:hover .face.face1 .content {
            opacity: 1;
        }

        .container-grupos .card .face.face1 .content i {
            font-size: 3em;
            margin-bottom: 10px;
        }

        .container-grupos .card .face.face1 .content h3 {
            margin: 10px 0 0;
            padding: 0;
            color: #fff;
            text-align: center;
            font-size: 1.5em;
        }

        .container-grupos .card .face.face2 {
            position: relative;
            background: #fff;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            box-sizing: border-box;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.8);
            transform: translateY(-100px);
        }

        .container-grupos .card:hover .face.face2 {
            transform: translateY(0);
        }

        .container-grupos .card .face.face2 .content p {
            margin: 0;
            padding: 0;
            text-align: center;
        }

        .container-grupos .card .face.face2 .content a {
            margin: 15px auto 0;
            display: block;
            text-decoration: none;
            font-weight: 900;
            color: #333;
            padding: 5px;
            border: 1px solid #333;
            text-align: center;
            width: 80%;
        }

        .container-grupos .card .face.face2 .content a:hover {
            background: #007bff;
            color: #fff;
            border-color: #007bff;
        }

        .titulo-seccion {
            color: black;
            text-align: center;
            margin-top: 50px;
            font-size: 3em;
        }

        .badge-alumnos {
            position: absolute;
            top: 10px;
            right: 10px;
            font-size: 0.8em;
            padding: 5px 10px;
            border-radius: 20px;
        }

        .badge-con-alumnos {
            background-color: #28a745;
        }

        .badge-sin-alumnos {
            background-color: #dc3545;
        }

        .seccion-turno {
            width: 100%;
            margin-bottom: 40px;
        }

        .titulo-turno {
            color: black;
            text-align: center;
            margin: 30px 0 20px;
            font-size: 1.8em;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
        }

        @media (max-width: 992px) {
            .container-grupos .card {
                flex: 0 0 calc(50% - 30px);
            }
        }

        @media (max-width: 576px) {
            .container-grupos .card {
                flex: 0 0 100%;
            }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body>
<br>
    <h1 class="titulo-seccion">Grupos Escolares</h1>

    <div class="container-grupos">
        <?php 
        // Separar grupos por turno
        $grupos_matutinos = array_filter($grupos, function($g) { return $g['turno_credencial'] == 'Matutino'; });
        $grupos_vespertinos = array_filter($grupos, function($g) { return $g['turno_credencial'] == 'Vespertino'; });
        
        // Mostrar primero los matutinos
        if (!empty($grupos_matutinos)): ?>
            <div class="seccion-turno">
                <h2 class="titulo-turno">Turno Matutino</h2>
                <div class="grupos-container" style="display: flex; flex-wrap: wrap; justify-content: center; gap: 30px; width: 100%;">
                    <?php foreach ($grupos_matutinos as $grupo): ?>
                        <div class="card">
                            <div class="face face1">
                                <div class="content">
                                    <span class="badge-alumnos <?= $grupo['total_alumnos'] > 0 ? 'badge-con-alumnos' : 'badge-sin-alumnos' ?>">
                                        <?= $grupo['total_alumnos'] > 0 ? 
                                            '<i class="fas fa-check-circle me-1"></i> ' . $grupo['total_alumnos'] . ' alumnos' : 
                                            '<i class="fas fa-exclamation-circle me-1"></i> Sin alumnos' ?>
                                    </span>
                                    <i class="fas fa-users"></i>
                                    <h3><?= htmlspecialchars($grupo['grado_credencial']) ?>° <?= htmlspecialchars($grupo['grupo_romano']) ?></h3>
                                </div>
                            </div>
                            <div class="face face2">
                                <div class="content">
                                    <p>Turno: <?= htmlspecialchars($grupo['turno_credencial']) ?></p>
                                    <p>Alumnos: <?= $grupo['total_alumnos'] > 0 ? $grupo['total_alumnos'] : 'Ninguno registrado' ?></p>
                                    <a href="info_grupo.php?grado=<?= $grupo['grado_credencial'] ?>&grupo=<?= $grupo['grupo_credencial'] ?>&turno=<?= $grupo['turno_credencial'] ?>" class="btn-ver-grupo">
                                        Ver Detalles del Grupo
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($grupos_vespertinos)): ?>
            <div class="seccion-turno">
                <h2 class="titulo-turno">Turno Vespertino</h2>
                <div class="grupos-container" style="display: flex; flex-wrap: wrap; justify-content: center; gap: 30px; width: 100%;">
                    <?php foreach ($grupos_vespertinos as $grupo): ?>
                        <div class="card">
                            <div class="face face1">
                                <div class="content">
                                    <span class="badge-alumnos <?= $grupo['total_alumnos'] > 0 ? 'badge-con-alumnos' : 'badge-sin-alumnos' ?>">
                                        <?= $grupo['total_alumnos'] > 0 ? 
                                            '<i class="fas fa-check-circle me-1"></i> ' . $grupo['total_alumnos'] . ' alumnos' : 
                                            '<i class="fas fa-exclamation-circle me-1"></i> Sin alumnos' ?>
                                    </span>
                                    <i class="fas fa-users"></i>
                                    <h3><?= htmlspecialchars($grupo['grado_credencial']) ?>° <?= htmlspecialchars($grupo['grupo_romano']) ?></h3>
                                </div>
                            </div>
                            <div class="face face2">
                                <div class="content">
                                    <p>Turno: <?= htmlspecialchars($grupo['turno_credencial']) ?></p>
                                    <p>Alumnos: <?= $grupo['total_alumnos'] > 0 ? $grupo['total_alumnos'] : 'Ninguno registrado' ?></p>
                                    <a href="info_grupo.php?grado=<?= $grupo['grado_credencial'] ?>&grupo=<?= $grupo['grupo_credencial'] ?>&turno=<?= $grupo['turno_credencial'] ?>" class="btn-ver-grupo">
                                        Ver Detalles del Grupo
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php include 'footer_orientador.php'; ?>

</body>
</html>