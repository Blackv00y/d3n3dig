<?php
/**
 * ══════════════════════════════════════════════════════════════════════
 * FUNCIÓN DE AUDITORÍA DE RESPALDOS — v4 DEFINITIVA
 *
 * HISTORIAL DE CORRECCIONES:
 *
 *  BUG 1 — String de tipos incorrecto en bind_param
 *           Original : "ssssssssisssiiss"
 *           Correcto : "ssssssssiisssiis"
 *           id_alumno (pos 9) e id_escuela (pos 13) son INTEGER.
 *           Causaba fallo silencioso a partir del 2.° registro.
 *
 *  BUG 2 — filesize() puede retornar false → forzado a NULL.
 *
 *  BUG 3 — id_alumno/id_escuela llegaban como string vacío con tipo 'i'.
 *
 *  BUG 4 — Dependencia de la conexión principal ya cerrada.
 *           La función abre su propia conexión interna.
 *
 *  BUG 5 — define() con credenciales placeholder rompía todo si el
 *           archivo se incluía más de una vez (Constant already defined).
 *           SOLUCIÓN: se leen las variables globales de conexQRConejo.php
 *           ($servername, $username, $password, $dbname).
 * ══════════════════════════════════════════════════════════════════════
 */

/**
 * Abre una conexión dedicada para auditoría usando las mismas
 * credenciales que ya define conexQRConejo.php.
 *
 * Variables leídas: $servername, $username, $password, $dbname
 * (las mismas que usa mysqli_connect en conexQRConejo.php)
 */
function _auditConexion() {
    // Leer las variables que define conexQRConejo.php
    global $servername, $username, $password, $dbname;

    if (empty($dbname)) {
        error_log("[AUDITORIA] ERROR — \$dbname está vacío. " .
                  "Asegúrate de incluir conexQRConejo.php antes de llamar a registrarAuditoriaRespaldo().");
        return null;
    }

    $c = mysqli_connect($servername, $username, $password, $dbname);

    if (!$c) {
        error_log("[AUDITORIA] ERROR — mysqli_connect falló: " . mysqli_connect_error() .
                  " | host=$servername user=$username db=$dbname");
        return null;
    }

    mysqli_set_charset($c, 'utf8');
    return $c;
}

/**
 * Registra un respaldo en la tabla respaldos_log.
 *
 * @param mysqli|null $conexion  Ignorado — se mantiene por compatibilidad.
 *                               La función siempre usa su propia conexión.
 * @param array       $datos     Datos del respaldo.
 * @return bool
 */
function registrarAuditoriaRespaldo($conexion = null, $datos = []) {

    error_log("[AUDITORIA] Iniciando registro — archivo: " . ($datos['nombre_archivo'] ?? 'N/A'));

    $db = _auditConexion();
    if (!$db) {
        error_log("[AUDITORIA] FALLO — No se pudo abrir conexión propia.");
        return false;
    }

    try {

        // ── 1. Usuario MySQL actual ───────────────────────────────────────
        $r = mysqli_query($db, "SELECT USER() AS u");
        if (!$r) {
            error_log("[AUDITORIA] ERROR — USER(): " . mysqli_error($db));
            mysqli_close($db);
            return false;
        }
        $row             = mysqli_fetch_assoc($r);
        $usuarioCompleto = $row['u'] ?? 'unknown@unknown';
        [$usuario_db, $host_origen] = array_pad(explode('@', $usuarioCompleto, 2), 2, 'unknown');

        // ── 2. Datos del cliente HTTP ─────────────────────────────────────
        $ip_cliente = $_SERVER['REMOTE_ADDR']     ?? 'unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

        // ── 3. Normalizar datos de entrada ────────────────────────────────
        $s = fn($k) => (isset($datos[$k]) && $datos[$k] !== '') ? (string)$datos[$k] : null;
        $i = fn($k) => (isset($datos[$k]) && is_numeric($datos[$k])) ? (int)$datos[$k] : null;

        $nombre_archivo  = (string)($datos['nombre_archivo'] ?? '');
        $ruta_archivo    = $s('ruta_archivo');
        $tipo_respaldo   = (string)($datos['tipo_respaldo']  ?? 'Individual');
        $tipo_boleta     = $s('tipo_boleta');
        $grado           = $s('grado');
        $grupo           = $s('grupo');
        $turno           = $s('turno');
        $nombre_usuario  = $s('nombre_usuario');
        $usuario_sistema = $s('usuario_sistema');
        $id_alumno       = $i('id_alumno');
        $id_escuela      = $i('id_escuela');

        // BUG FIX 2: filesize() puede retornar false
        $tamano_bytes = null;
        if ($ruta_archivo !== null && file_exists($ruta_archivo)) {
            $fs = filesize($ruta_archivo);
            $tamano_bytes = ($fs !== false) ? (int)$fs : null;
        }

        // ── Log diagnóstico antes del INSERT ─────────────────────────────
        error_log("[AUDITORIA] tipo=$tipo_respaldo | boleta=$tipo_boleta | " .
                  "id_alumno=$id_alumno | id_escuela=$id_escuela | " .
                  "usuario=$usuario_sistema | archivo=$nombre_archivo");

        // ── 4. INSERT ─────────────────────────────────────────────────────
        //
        //  Pos  Columna           Tipo
        //   1   usuario_db        s
        //   2   usuario_sistema   s
        //   3   nombre_usuario    s
        //   4   host_origen       s
        //       fecha_hora        NOW()  ← sin marcador
        //   5   nombre_archivo    s
        //   6   ruta_archivo      s
        //   7   tipo_respaldo     s
        //   8   tipo_boleta       s
        //   9   id_alumno         i  ← INTEGER
        //  10   grado             s
        //  11   grupo             s
        //  12   turno             s
        //  13   id_escuela        i  ← INTEGER (BUG FIX 1: era 's')
        //  14   tamano_bytes      i  ← INTEGER
        //  15   ip_cliente        s
        //  16   user_agent        s
        //
        //  String correcto: "ssssssssiisssiis"
        // ─────────────────────────────────────────────────────────────────

        $sql = "INSERT INTO respaldos_log (
                    usuario_db, usuario_sistema, nombre_usuario, host_origen,
                    fecha_hora,
                    nombre_archivo, ruta_archivo, tipo_respaldo, tipo_boleta,
                    id_alumno, grado, grupo, turno,
                    id_escuela, tamano_bytes, ip_cliente, user_agent
                ) VALUES (
                    ?, ?, ?, ?, NOW(),
                    ?, ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?, ?, ?
                )";

        $stmt = mysqli_prepare($db, $sql);

        if (!$stmt) {
            error_log("[AUDITORIA] ERROR — prepare(): " . mysqli_error($db));
            mysqli_close($db);
            return false;
        }

        mysqli_stmt_bind_param(
            $stmt,
            "ssssssssiisssiis",  // ← CORRECTO (BUG FIX 1)
            $usuario_db,         //  1  s
            $usuario_sistema,    //  2  s
            $nombre_usuario,     //  3  s
            $host_origen,        //  4  s
            $nombre_archivo,     //  5  s
            $ruta_archivo,       //  6  s
            $tipo_respaldo,      //  7  s
            $tipo_boleta,        //  8  s
            $id_alumno,          //  9  i
            $grado,              // 10  s
            $grupo,              // 11  s
            $turno,              // 12  s
            $id_escuela,         // 13  i  (BUG FIX 1)
            $tamano_bytes,       // 14  i
            $ip_cliente,         // 15  s
            $user_agent          // 16  s
        );

        $ok = mysqli_stmt_execute($stmt);

        if (!$ok) {
            error_log("[AUDITORIA] ERROR — execute(): " . mysqli_stmt_error($stmt));
            mysqli_stmt_close($stmt);
            mysqli_close($db);
            return false;
        }

        $id_log = mysqli_insert_id($db);
        mysqli_stmt_close($stmt);
        mysqli_close($db);

        error_log("[AUDITORIA] ✓ Registrado [ID:$id_log] — $nombre_archivo");
        return true;

    } catch (Exception $e) {
        error_log("[AUDITORIA] EXCEPCIÓN: " . $e->getMessage());
        if (isset($db) && $db) mysqli_close($db);
        return false;
    }
}

/**
 * ══════════════════════════════════════════════════════════════════════
 * FUNCIÓN AUXILIAR — nombre completo del usuario de sesión
 * ══════════════════════════════════════════════════════════════════════
 */
function obtenerNombreUsuarioSesion($conexion, $id_credencial) {
    if (!$conexion) return null;

    $stmt = mysqli_prepare(
        $conexion,
        "SELECT CONCAT(nombre_credencial, ' ', apellidos_credencial) AS nombre_completo
         FROM credenciales WHERE id_credencial = ?"
    );
    if (!$stmt) return null;

    mysqli_stmt_bind_param($stmt, "i", $id_credencial);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $nombre = ($row = mysqli_fetch_assoc($result)) ? $row['nombre_completo'] : null;
    mysqli_stmt_close($stmt);
    return $nombre;
}