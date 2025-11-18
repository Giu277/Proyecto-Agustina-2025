<?php
session_start();
require_once 'Conexion.php';

// Obtener columnas de una tabla
function getColumns(PDO $pdo, string $table): array {
    $stmt = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
    $stmt->execute([$table]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Obtener horario de entrada para el usuario (busca por id_cargo del usuario y día actual)
// Flujo: 1) Obtener id_cargo del usuario 2) Buscar columna con cargo en tabla horario 3) Obtener Id_horario para hoy
function getHorarioForUser(PDO $pdo, $legajo) {
    // Obtener id_cargo del usuario
    $stmt = $pdo->prepare("SELECT id_cargo FROM usuario WHERE legajo = ? LIMIT 1");
    $stmt->execute([$legajo]);
    $usr = $stmt->fetch(PDO::FETCH_ASSOC);
    $userIdCargo = $usr['id_cargo'] ?? null;

    // Detectar columna que vincula cargo con horario
    $stmtCols = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'horario'");
    $stmtCols->execute();
    $colsHor = $stmtCols->fetchAll(PDO::FETCH_COLUMN);
    $possible = ['id_cargo','Id_cargo','Id_Cargo','IdCargo','cargo_id','idCargo','Cargo'];
    $col = null;
    foreach ($possible as $c) { if (in_array($c,$colsHor,true)) { $col = $c; break; } }

    // Buscar horario para el cargo del usuario en el día de hoy
    if ($col && $userIdCargo) {
        $q = "SELECT Id_horario FROM horario WHERE Dia = CURDATE() AND `" . $col . "` = ? LIMIT 1";
        $s = $pdo->prepare($q);
        $s->execute([$userIdCargo]);
    } else {
        // Fallback: si no hay cargo o no se encuentra columna, obtener cualquier horario del día
        $s = $pdo->prepare("SELECT Id_horario FROM horario WHERE Dia = CURDATE() LIMIT 1");
        $s->execute();
    }
    $r = $s->fetch(PDO::FETCH_ASSOC);
    return $r['Id_horario'] ?? null;
}

// Verificar si el usuario registró hoy
function usuarioRegistroHoy(PDO $pdo, $legajo, array $cols): bool {
    $hasLegajo = in_array('legajo',$cols,true) || in_array('Legajo',$cols,true);
    $hasIdAsiste = in_array('Id_asiste',$cols,true) || in_array('id_asiste',$cols,true);
    if ($hasLegajo) {
        $st = $pdo->prepare("SELECT 1 FROM `asiste-c` WHERE `legajo` = ? AND `fecha` = CURDATE() LIMIT 1");
        $st->execute([$legajo]);
        return (bool)$st->fetchColumn();
    }
    if ($hasIdAsiste) {
        $st = $pdo->prepare("SELECT 1 FROM `asiste-c` WHERE `Id_asiste` = ? AND `fecha` = CURDATE() LIMIT 1");
        $st->execute([$legajo]);
        return (bool)$st->fetchColumn();
    }
    return false;
}

// Insertar asistencia dinámicamente
function insertarAsistencia(PDO $pdo, array $cols, $legajo, $idHorario, $fechaActual, $horaActual): bool {
    $insertCols = [];
    $values = [];
    if (in_array('legajo',$cols,true) || in_array('Legajo',$cols,true)) {
        $insertCols[] = in_array('legajo',$cols,true) ? 'legajo' : 'Legajo';
        $values[] = $legajo;
    } elseif (in_array('Id_asiste',$cols,true) || in_array('id_asiste',$cols,true)) {
        $insertCols[] = in_array('Id_asiste',$cols,true) ? 'Id_asiste' : 'id_asiste';
        $values[] = $legajo;
    }
    if (in_array('Id_horario',$cols,true) || in_array('id_horario',$cols,true)) { $insertCols[] = in_array('Id_horario',$cols,true) ? 'Id_horario' : 'id_horario'; $values[] = $idHorario; }
    if (in_array('fecha',$cols,true) || in_array('Fecha',$cols,true)) { $insertCols[] = in_array('fecha',$cols,true) ? 'fecha' : 'Fecha'; $values[] = $fechaActual; }
    if (in_array('Entrada',$cols,true) || in_array('entrada',$cols,true)) { $insertCols[] = in_array('Entrada',$cols,true) ? 'Entrada' : 'entrada'; $values[] = $horaActual; }
    if (in_array('Salida',$cols,true) || in_array('salida',$cols,true)) { $insertCols[] = in_array('Salida',$cols,true) ? 'Salida' : 'salida'; $values[] = $horaActual; }
    if (empty($insertCols)) return false;
    $placeholders = array_fill(0, count($insertCols), '?');
    $sql = "INSERT INTO `asiste-c` (`" . implode('`,`',$insertCols) . "`) VALUES (" . implode(',',$placeholders) . ")";
    $st = $pdo->prepare($sql);
    $st->execute($values);
    // comprobación mínima: existe registro del día con el identificador
    if (in_array('legajo',$cols,true) || in_array('Legajo',$cols,true)) {
        $chk = $pdo->prepare("SELECT COUNT(*) FROM `asiste-c` WHERE `fecha` = ? AND `legajo` = ?");
        $chk->execute([$fechaActual, $legajo]);
    } elseif (in_array('Id_asiste',$cols,true) || in_array('id_asiste',$cols,true)) {
        $chk = $pdo->prepare("SELECT COUNT(*) FROM `asiste-c` WHERE `fecha` = ? AND `Id_asiste` = ?");
        $chk->execute([$fechaActual, $legajo]);
    } else {
        $chk = $pdo->prepare("SELECT COUNT(*) FROM `asiste-c` WHERE `fecha` = ? AND `Entrada` = ?");
        $chk->execute([$fechaActual, $horaActual]);
    }
    return ((int)$chk->fetchColumn()) > 0;
}

// Obtener asistencias para una fecha (construcción segura según columnas)
function obtenerAsistencias(PDO $pdo, string $fecha): array {
    $colsA = getColumns($pdo,'asiste-c');
    $hasA_legajo = in_array('legajo',$colsA,true) || in_array('Legajo',$colsA,true);
    $hasA_Id_asiste = in_array('Id_asiste',$colsA,true) || in_array('id_asiste',$colsA,true);
    $hasA_fecha = in_array('fecha',$colsA,true) || in_array('Fecha',$colsA,true);
    $hasA_Entrada = in_array('Entrada',$colsA,true) || in_array('entrada',$colsA,true);
    $hasA_Salida = in_array('Salida',$colsA,true) || in_array('salida',$colsA,true);
    $joinConds = [];
    if ($hasA_legajo) $joinConds[] = 'a.legajo = u.legajo';
    if ($hasA_Id_asiste) $joinConds[] = 'a.Id_asiste = u.legajo';
    $select = [];
    if (!empty($joinConds)) {
        $select[] = 'COALESCE(u.legajo, ' . ($hasA_legajo ? 'a.legajo' : 'a.Id_asiste') . ') AS legajo';
        $select[] = "COALESCE(u.nombre,'') AS nombre";
        $select[] = "COALESCE(u.apellido,'') AS apellido";
        $select[] = "c.Denominacion AS cargo";
    } else {
        $select[] = $hasA_legajo ? 'a.legajo AS legajo' : ($hasA_Id_asiste ? 'a.Id_asiste AS legajo' : "NULL AS legajo");
        $select[] = "'' AS nombre";
        $select[] = "'' AS apellido";
        $select[] = "'' AS cargo";
    }
    $select[] = $hasA_fecha ? 'a.fecha' : "NULL AS fecha";
    $select[] = $hasA_Entrada ? 'a.Entrada' : "'' AS Entrada";
    $select[] = $hasA_Salida ? "COALESCE(a.Salida,'') AS Salida" : "'' AS Salida";
    $from = "FROM `asiste-c` a";
    $join = !empty($joinConds) ? ' LEFT JOIN usuario u ON (' . implode(' OR ', $joinConds) . ') LEFT JOIN cargo c ON c.id_cargo = u.id_cargo' : '';
    $where = $hasA_fecha ? 'WHERE a.fecha = ?' : '';
    $order = !empty($joinConds) ? 'ORDER BY u.apellido, u.nombre ASC' : '';
    $sql = 'SELECT ' . implode(",\n    ", $select) . "\n    " . $from . $join . "\n    " . $where . "\n    " . $order;
    $st = $pdo->prepare($sql);
    if ($hasA_fecha) $st->execute([$fecha]); else $st->execute();
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

// Obtener ausentes del día
function obtenerAusentes(PDO $pdo): array {
    $stmt = $pdo->prepare("SELECT u.`legajo`, u.`nombre`, u.`apellido`, c.`Denominacion` AS `cargo` FROM `usuario` u LEFT JOIN `cargo` c ON u.`id_cargo` = c.`id_cargo` WHERE u.`legajo` NOT IN (SELECT DISTINCT COALESCE(a.`legajo`, a.`Id_asiste`) FROM `asiste-c` a WHERE DATE(a.`fecha`) = CURDATE()) ORDER BY u.`apellido`, u.`nombre`");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Obtener registro del usuario hoy
function getUserRegistroHoy(PDO $pdo, $legajo) {
    $st = $pdo->prepare("SELECT `Entrada`,`Salida`,`fecha` FROM `asiste-c` WHERE (`legajo` = ? OR `Id_asiste` = ?) AND `fecha` = CURDATE() LIMIT 1");
    $st->execute([$legajo,$legajo]);
    return $st->fetch(PDO::FETCH_ASSOC);
}

// Verificar si el usuario está logueado
if (!isset($_SESSION['legajo'])) {
    header("Location: inicioSesion.php");
    exit();
}

$mensaje = '';
$nombreUsuario = $_SESSION['nombre'] ?? '';
$legajo = $_SESSION['legajo'];
$usuarioLogueado = true;

// Procesar registro de asistencia 
if (isset($_POST['enviar']) && $_POST['enviar'] == 'Registrar Asistencia' && $usuarioLogueado) {
    $cargo = $_POST['cargo'] ?? '';
    $fechaActual = date('Y-m-d');
    $horaActual = date('H:i:s');

    if (!empty($cargo)) {
        try {
            $conexion = new Conexion();
            $pdo = $conexion->getConexion();
            $idHorario = getHorarioForUser($pdo, $legajo);
            $cols = getColumns($pdo, 'asiste-c');
            if (usuarioRegistroHoy($pdo, $legajo, $cols)) {
                $mensaje = 'Ya registró su asistencia hoy.';
            } else {
                $ok = insertarAsistencia($pdo, $cols, $legajo, $idHorario, $fechaActual, $horaActual);
                $mensaje = $ok ? 'Asistencia registrada correctamente.' : 'No se encontró la fila insertada en `asiste-c`.';
            }
        } catch (PDOException $e) {
            $mensaje = 'Error al registrar asistencia: ' . $e->getMessage();
        }
    } else {
        $mensaje = 'Por favor seleccione un cargo.';
    }
}

// Obtener ausentes del día
$ausentes = [];
try {
    $conexion = new Conexion();
    $pdo = $conexion->getConexion();
    $ausentes = obtenerAusentes($pdo);
} catch (PDOException $e) {
    if (empty($ausentes)) $mensajeError = 'Error al obtener ausentes: ' . $e->getMessage();
}

// Comprobar si el usuario ya registró asistencia hoy (para mostrar estado)
$yaRegistro = false;
$horaRegistrada = null;
try {
    $conexionEstado = new Conexion();
    $pdoEstado = $conexionEstado->getConexion();
    $filaEst = getUserRegistroHoy($pdoEstado, $legajo);
    if ($filaEst) { $yaRegistro = true; $horaRegistrada = $filaEst['Entrada']; }
} catch (PDOException $e) {
    // no crítico; dejamos $yaRegistro = false
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inicio - Control de Asistencia Escolar</title>
    <style>
        fieldset {
            margin: 20px 0;
            padding: 15px;
            border: 2px solid #ccc;
            border-radius: 5px;
        }
        legend {
            font-weight: bold;
            padding: 0 10px;
        }
        label {
            display: block;
            margin-top: 10px;
        }
        select {
            width: 100%;
            padding: 8px;
            margin-top: 5px;
            box-sizing: border-box;
        }
        input[type="submit"] {
            margin-top: 15px;
            padding: 10px 20px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        input[type="submit"]:hover {
            background-color: #45a049;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #4CAF50;
            color: white;
        }
        .mensaje {
            padding: 10px;
            margin: 10px 0;
            border-radius: 4px;
        }
        /* Barra de navegación (vacía por ahora, con item activo marcado en azul) */
        :root { --nav-height: 56px; }
        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: var(--nav-height);
            /* Azul similar a la imagen */
            background-color: #1f6d7a;
            border-bottom: 1px solid rgba(0,0,0,0.12);
            z-index: 1000;
            box-shadow: 0 2px 4px rgba(0,0,0,0.08);
        }
        /* Barra interior centrada y logout pegado a la derecha del viewport */
        .nav-container {
            position: relative;
            width: 100%;
            height: 100%;
        }
        .nav-inner {
            max-width: 800px;
            height: 100%;
            margin: 0 auto;
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 0 16px;
            /* dejar espacio a la izquierda para el logo absoluto */
            padding-left: 96px;
            justify-content: flex-start;
        }
        .nav-logo {
            position: absolute;
            left: 12px; /* pegado al borde izquierdo con un pequeño margen */
            top: 50%;
            transform: translateY(-50%);
            display: flex;
            align-items: center;
        }
        .nav-logo img {
            height: calc(var(--nav-height) - 14px); /* pequeño margen dentro de la barra */
            width: auto;
            display: block;
            border-radius: 4px;
            object-fit: contain;
        }
        .nav-right {
            position: absolute;
            right: 16px; /* pegado al borde derecho con pequeño margen */
            top: 50%;
            transform: translateY(-50%); /* centrar verticalmente */
            display: flex;
            align-items: center;
        }
        .nav-item {
            color: #ffffff; /* texto blanco sobre barra azul */
            padding: 8px 12px;
            border-radius: 4px;
            text-decoration: none;
            font-weight: 500;
            cursor: default; /* aún no hay enlaces */
            display: inline-block;
            line-height: 1;
            opacity: 0.95;
        }
        .nav-item.nav-active {
            background-color: rgba(255,255,255,0.12); /* ligera marca */
            color: #ffffff;
        }

        /* Ajuste del contenido para no quedar oculto bajo la barra fija */
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
            padding-top: calc(var(--nav-height) + 20px);
        }
        .error {
            background-color: #f44336;
            color: white;
        }
        .exito {
            background-color: #4CAF50;
            color: white;
        }
    </style>
</head>
<body>
    <!-- Barra de navegación con botón de Cerrar Sesión a la derecha -->
    <nav class="navbar" aria-label="Barra de navegación principal">
        <div class="nav-container">
            <div class="nav-inner">
                <div class="nav-logo">
                    <!-- El logo se encuentra en la raíz del directorio del proyecto -->
                    <img src="Logo.epet" alt="Logo E.P.E.T" />
                </div>
                <!-- Placeholder de items: aún no tienen enlaces funcionales -->
                <a href="inicio.php" class="nav-item nav-active">Inicio</a>
                <a href="Asistencia.php" class="nav-item">Asistencias Registradas</a>
            </div>
            <div class="nav-right">
                <!-- Formulario de logout (envía POST a inicioSesion.php) -->
                <form method="post" action="inicioSesion.php" style="margin:0;">
                    <input type="submit" name="logout" value="Cerrar Sesión" style="background-color:#f44336;color:#fff;border:none;padding:6px 10px;border-radius:4px;cursor:pointer;">
                </form>
            </div>
        </div>
    </nav>

    <?php if (!empty($mensaje)): ?>
        <div class="mensaje <?php echo (strpos($mensaje, 'Error') !== false) ? 'error' : 'exito'; ?>">
            <?php echo htmlspecialchars($mensaje); ?>
        </div>
        <?php if ($mensaje === 'Asistencia registrada correctamente.'): ?>
            <div style="margin:18px 0 0 0;padding:16px;border:2px solid #4CAF50;border-radius:8px;background:#e8f5e9;">
                <h3 style="color:#2e7d32;margin:0 0 8px 0;">¡Asistencia registrada!</h3>
                <ul style="list-style:none;padding:0;margin:0 0 0 0;">
                    <li><strong>Legajo:</strong> <?php echo htmlspecialchars($legajo); ?></li>
                    <li><strong>Nombre:</strong> <?php echo htmlspecialchars($nombreUsuario); ?></li>
                    <li><strong>Fecha:</strong> <?php echo date('d/m/Y'); ?></li>
                    <li><strong>Hora de Entrada:</strong> <?php echo date('H:i:s'); ?></li>
                    <li><strong>Estado:</strong> <span style="color:#2e7d32;font-weight:bold;">Presente</span></li>
                </ul>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Tarjetas superiores: Bienvenida y Registro de Asistencia -->
    <div class="cards-wrapper" style="display:flex;flex-direction:column;gap:16px;">
        <div class="card" style="border:1px solid #e6e6e6;border-radius:8px;padding:14px;display:flex;gap:12px;align-items:center;">
            <!-- Icono usuario (inline SVG) -->
            <div style="flex:0 0 48px;">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="12" cy="8" r="4" fill="#1976D2" />
                    <path d="M4 20c0-4 4-6 8-6s8 2 8 6" fill="#1976D2" />
                </svg>
            </div>
            <div style="flex:1;">
                <p style="margin:0;font-weight:700;font-size:1.25rem;">Bienvenido, <?php echo htmlspecialchars($nombreUsuario); ?>!</p>
                <p style="margin:6px 0 0 0;color:#666;">E.P.E.T Nº 20</p>
            </div>
        </div>

        <div class="card" style="border:1px solid #e6e6e6;border-radius:8px;padding:14px;">
            <form method="post" action="inicio.php">
                <h4 style="margin:0 0 10px 0;">Registro de Asistencia</h4>
                <label for="cargo">Seleccione su cargo:</label>
                <div style="margin:8px 0 12px 0;">
                    <select name="cargo" id="cargo" required style="padding:8px;width:100%;max-width:320px;">
                        <option value="">Seleccione un cargo...</option>
                        <?php
                        // Obtener los cargos del usuario desde la base de datos
                        try {
                            $stmtCargos = $pdo->prepare("SELECT c.id_cargo, c.Denominacion FROM cargo c INNER JOIN usuario u ON c.id_cargo = u.id_cargo WHERE u.legajo = ?");
                            $stmtCargos->execute([$legajo]);
                            $cargosUsuario = $stmtCargos->fetchAll(PDO::FETCH_ASSOC);
                            foreach ($cargosUsuario as $cargoItem) {
                                echo '<option value="' . htmlspecialchars($cargoItem['Denominacion']) . '">' . htmlspecialchars($cargoItem['Denominacion']) . '</option>';
                            }
                        } catch (PDOException $e) {
                            echo '<option value="">Error al cargar cargos</option>';
                        }
                        ?>
                    </select>
                </div>

                <div style="margin-bottom:12px;">
                    <strong>Estado actual:</strong>
                    <?php if ($yaRegistro): ?>
                        <span style="color:#2e7d32;display:inline-flex;align-items:center;gap:8px;"> 
                            <!-- Check icon -->
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M20 6L9 17l-5-5" stroke="#2e7d32" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            Registrado hoy a las <?php echo htmlspecialchars($horaRegistrada); ?>
                        </span>
                    <?php else: ?>
                        <span style="color:#666;">Ninguna asistencia registrada hoy.</span>
                    <?php endif; ?>
                </div>

                <div>
                    <input type="submit" name="enviar" value="Registrar Asistencia" <?php echo $yaRegistro ? 'disabled' : ''; ?> style="background-color: #4CAF50; color: white; padding:10px 14px; border:none; border-radius:6px; cursor:pointer; <?php echo $yaRegistro ? 'opacity:0.6;cursor:not-allowed;' : ''; ?>">
                </div>
            </form>
        </div>
    </div>   
    <!-- Tabla de Asistencias Registradas (usa la fecha guardada en la tabla `asiste-c`) -->
    <?php
    $asistencias = [];
    try {
        $conexionA = new Conexion();
        $pdoA = $conexionA->getConexion();
        $fechaFiltro = date('Y-m-d');
        $asistencias = obtenerAsistencias($pdoA, $fechaFiltro);
    } catch (PDOException $e) {
        $mensaje = 'Error al obtener asistencias registradas: ' . $e->getMessage();
    }
    ?>

    
    <table>
        <caption>Asistencias Registradas - <?php echo date('d/m/Y'); ?></caption>
        <thead>
            <tr>
                <th>Nº</th>
                <th>Legajo</th>
                <th>Nombre y Apellido</th>
                <th>Cargo</th>
                <th>Fecha registrada</th>
                <th>Hora entrada</th>
                <th>Hora salida</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($asistencias)): ?>
                <tr>
                    <td colspan="7" style="text-align: center;">No hay asistencias registradas para la fecha seleccionada.</td>
                </tr>
            <?php else: ?>
                <?php $contadorA = 1; ?>
                <?php foreach ($asistencias as $fila): ?>
                    <tr>
                        <td><?php echo $contadorA++; ?></td>
                        <td><?php echo htmlspecialchars($fila['legajo']); ?></td>
                        <td><?php echo htmlspecialchars(trim($fila['nombre'] . ' ' . $fila['apellido'])); ?></td>
                        <td><?php echo htmlspecialchars($fila['cargo'] ?? 'N/A'); ?></td>
                        <td><?php echo !empty($fila['fecha']) ? date('d/m/Y', strtotime($fila['fecha'])) : '-'; ?></td>
                        <td><?php echo !empty($fila['Entrada']) ? htmlspecialchars($fila['Entrada']) : '-'; ?></td>
                        <td><?php echo isset($fila['Salida']) && $fila['Salida'] !== '' ? htmlspecialchars($fila['Salida']) : '-'; ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

</body>
</html>
