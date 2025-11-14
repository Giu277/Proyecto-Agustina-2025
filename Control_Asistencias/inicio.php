<?php
session_start();
require_once 'Conexion.php';

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

            // Intentar obtener un Id_horario para hoy (si existe)
            $stmtHor = $pdo->prepare("SELECT Id_horario FROM horario WHERE Dia = CURDATE() LIMIT 1");
            $stmtHor->execute();
            $hor = $stmtHor->fetch(PDO::FETCH_ASSOC);
            $idHorario = $hor['Id_horario'] ?? null;

            // Comprobar qué columnas existen en la tabla asiste-c
            $stmtCols = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'asiste-c'");
            $stmtCols->execute();
            $cols = $stmtCols->fetchAll(PDO::FETCH_COLUMN);
            $hasLegajo = in_array('legajo', $cols, true) || in_array('Legajo', $cols, true);
            $hasIdHorario = in_array('Id_horario', $cols, true) || in_array('id_horario', $cols, true);
            $hasIdAsiste = in_array('Id_asiste', $cols, true);

            // Verificar si ya registró asistencia hoy (usar la columna disponible)
            if ($hasLegajo) {
                $stmtCheck = $pdo->prepare("SELECT * FROM `asiste-c` WHERE `legajo` = ? AND `fecha` = CURDATE()");
                $stmtCheck->execute([$legajo]);
            } elseif ($hasIdAsiste) {
                $stmtCheck = $pdo->prepare("SELECT * FROM `asiste-c` WHERE `Id_asiste` = ? AND `fecha` = CURDATE()");
                $stmtCheck->execute([$legajo]);
            } else {
                $stmtCheck = null;
            }

            if ($stmtCheck && $stmtCheck->rowCount() > 0) {
                $mensaje = 'Ya registró su asistencia hoy.';
            } else {
                // Preparar e insertar según columnas disponibles
                if ($hasLegajo && $hasIdHorario) {
                    $idHorarioCol = in_array('Id_horario', $cols, true) ? 'Id_horario' : 'id_horario';
                    $sql = "INSERT INTO `asiste-c` (`legajo`, `" . $idHorarioCol . "`, `fecha`, `Entrada`, `Salida`) VALUES (?, ?, ?, ?, ?)";
                    $stmtIns = $pdo->prepare($sql);
                    $stmtIns->execute([$legajo, $idHorario, $fechaActual, $horaActual, $horaActual]);
                } elseif ($hasLegajo) {
                    $stmtIns = $pdo->prepare("INSERT INTO `asiste-c` (`legajo`, `fecha`, `Entrada`, `Salida`) VALUES (?, ?, ?, ?)");
                    $stmtIns->execute([$legajo, $fechaActual, $horaActual, $horaActual]);
                } elseif ($hasIdAsiste && $hasIdHorario) {
                    $idHorarioCol = in_array('Id_horario', $cols, true) ? 'Id_horario' : 'id_horario';
                    $sql = "INSERT INTO `asiste-c` (`Id_asiste`, `" . $idHorarioCol . "`, `fecha`, `Entrada`, `Salida`) VALUES (?, ?, ?, ?, ?)";
                    $stmtIns = $pdo->prepare($sql);
                    $stmtIns->execute([$legajo, $idHorario, $fechaActual, $horaActual, $horaActual]);
                } elseif ($hasIdAsiste) {
                    $stmtIns = $pdo->prepare("INSERT INTO `asiste-c` (`Id_asiste`, `fecha`, `Entrada`, `Salida`) VALUES (?, ?, ?, ?)");
                    $stmtIns->execute([$legajo, $fechaActual, $horaActual, $horaActual]);
                } else {
                    // Intento genérico si la estructura es inesperada
                    $stmtIns = $pdo->prepare("INSERT INTO `asiste-c` VALUES (?, ?, ?, ?)");
                    $stmtIns->execute([$legajo, $fechaActual, $horaActual, $horaActual]);
                }

                $mensaje = 'Asistencia registrada correctamente.';
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
    
    // Obtener usuarios que NO registraron asistencia hoy (ausentes)
    $stmt = $pdo->prepare("
        SELECT u.`legajo`, u.`nombre`, u.`apellido`, c.`Denominacion` as `cargo`
        FROM `usuario` u
        LEFT JOIN `cargo` c ON u.`id_cargo` = c.`id_cargo`
        WHERE u.`legajo` NOT IN (
            SELECT DISTINCT a.`Id_asiste` 
            FROM `asiste-c` a 
            WHERE DATE(a.`fecha`) = CURDATE()
        )
        ORDER BY u.`apellido`, u.`nombre`
    ");
    $stmt->execute();
    $ausentes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    if (empty($ausentes)) {
        $mensajeError = 'Error al obtener ausentes: ' . $e->getMessage();
    }
}

// Comprobar si el usuario ya registró asistencia hoy (para mostrar estado)
$yaRegistro = false;
$horaRegistrada = null;
try {
    $conexionEstado = new Conexion();
    $pdoEstado = $conexionEstado->getConexion();
    $stmtEst = $pdoEstado->prepare("SELECT `Entrada`, `Salida`, `fecha` FROM `asiste-c` WHERE (`legajo` = ? OR `Id_asiste` = ?) AND `fecha` = CURDATE()");
    $stmtEst->execute([$legajo, $legajo]);
    $filaEst = $stmtEst->fetch(PDO::FETCH_ASSOC);
    if ($filaEst) {
        $yaRegistro = true;
        $horaRegistrada = $filaEst['Entrada'];
    }
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
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
        }
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
    <!-- Barra de navegación (vacía por ahora) con botón de Cerrar Sesión a la derecha -->
    <nav class="navbar" aria-label="Barra de navegación principal">
        <div class="nav-container">
            <div class="nav-inner">
                <div class="nav-logo">
                    <!-- El logo se encuentra en la raíz del directorio del proyecto -->
                    <img src="Logo.epet" alt="Logo E.P.E.T" />
                </div>
                <!-- Placeholder de items: aún no tienen enlaces funcionales -->
                <span class="nav-item nav-active">Inicio</span>
                <span class="nav-item">Registro</span>
                <span class="nav-item">Usuarios</span>
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
    <!-- Tabla de Asistencias del Día (Presentes y Ausentes) -->
    <?php
$allRegistros = [];
try {
    $conexion = new Conexion();
    $pdo = $conexion->getConexion();

    // Obtener TODOS los usuarios con su estado de asistencia de hoy
    $stmt = $pdo->query("
        SELECT 
            u.legajo,
            u.nombre,
            u.apellido,
            c.Denominacion AS cargo,
            a.fecha,
            a.Entrada,
            CASE 
                WHEN a.Id_asiste IS NOT NULL THEN 'Presente'
                ELSE 'Ausente'
            END AS estado
        FROM usuario u
        LEFT JOIN cargo c ON c.id_cargo = u.id_cargo
        LEFT JOIN `asiste-c` a ON (a.Id_asiste = u.legajo OR a.legajo = u.legajo) AND DATE(a.fecha) = CURDATE()
        ORDER BY 
            CASE WHEN a.Id_asiste IS NOTcu NULL THEN 0 ELSE 1 END,
            u.apellido, u.nombre ASC
    ");
    $allRegistros = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $mensaje = 'Error al obtener asistencias: ' . $e->getMessage();
}
?>

<table>
    <caption>Asistencias del Día - <?php echo date('d/m/Y'); ?></caption>
    <thead>
        <tr>
            <th>Nº</th>
            <th>Legajo</th>
            <th>Nombre y Apellido</th>
            <th>Cargo</th>
            <th>Fecha</th>
            <th>Hora de Entrada</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($allRegistros)): ?>
            <tr>
                <td colspan="6" style="text-align: center;">No hay registros disponibles.</td>
            </tr>
        <?php else: ?>
            <?php $contador = 1; ?>
            <?php foreach ($allRegistros as $registro): ?>
                <tr style="<?php echo ($registro['estado'] === 'Presente') ? 'background-color: #e8f5e9;' : 'background-color: #ffebee;'; ?>">
                    <td><?php echo $contador++; ?></td>
                    <td><?php echo htmlspecialchars($registro['legajo']); ?></td>
                    <td><?php echo htmlspecialchars($registro['nombre'] . ' ' . $registro['apellido']); ?></td>
                    <td><?php echo htmlspecialchars($registro['cargo'] ?? 'N/A'); ?></td>
                    <td><?php echo date('d/m/Y'); ?></td>
                    <td><?php echo !empty($registro['Entrada']) ? htmlspecialchars($registro['Entrada']) : '-'; ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

    <!-- Tabla de Ausentes del Día -->
    <table>
        <caption>Ausentes del Día - <?php echo date('d/m/Y'); ?></caption>
        <thead>
            <tr>
                <th>Nº</th>
                <th>Ausentes</th>
                <th>Cargo</th>
                <th>Materia</th>
                <th>Curso</th>
                <th>Fecha</th>
                <th>Detalles</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($ausentes)): ?>
                <tr>
                    <td colspan="7" style="text-align: center; color:#2e7d32;">
                        <!-- Icono check y mensaje -->
                        <span style="display:inline-flex;align-items:center;gap:8px;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M20 6L9 17l-5-5" stroke="#2e7d32" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            No hay ausentes registrados para hoy.
                        </span>
                    </td>
                </tr>
            <?php else: ?>
                <?php $contador = 1; ?>
                <?php foreach ($ausentes as $ausente): ?>
                    <tr>
                        <td><?php echo $contador++; ?></td>
                        <td><?php echo htmlspecialchars($ausente['nombre'] . ' ' . $ausente['apellido']); ?></td>
                        <td><?php echo htmlspecialchars($ausente['cargo'] ?? 'N/A'); ?></td>
                        <td>-</td>
                        <td>-</td>
                        <td><?php echo date('d/m/Y'); ?></td>
                        <td>-</td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

</body>
</html>
