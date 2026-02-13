<?php
echo "<h2>PHP Info</h2>";
echo "Versión de PHP: " . phpversion() . "<br>";
echo "Archivo php.ini cargado: " . php_ini_loaded_file() . "<br><br>";

$extensions = get_loaded_extensions();
if (in_array('zip', $extensions)) {
    echo "<span style='color:green;'>✅ Extensión ZIP está ACTIVADA.</span>";
} else {
    echo "<span style='color:red;'>❌ Extensión ZIP NO está activada.</span><br>";
    echo "<strong>Extensiones cargadas:</strong><br>";
    echo implode(', ', $extensions);
}
?>
