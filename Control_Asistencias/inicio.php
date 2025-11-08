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
if (isset($_POST['enviar']) && $_POST['enviar'] == 'Asistencia' && $usuarioLogueado) {
    $cargo = $_POST['cargo'] ?? '';
    $fechaActual = date('Y-m-d H:i:s');
    
    if (!empty($cargo)) {
        try {
            $conexion = new Conexion();
            $pdo = $conexion->getConexion();
            // Verificar si ya registró asistencia hoy usando legajo
            $stmt = $pdo->prepare("SELECT `Id_asiste` FROM `asistencia_c` WHERE `Id_asiste` = ? AND `fecha` = CURDATE()");
            $stmt->execute([$legajo]);
            if ($stmt->rowCount() == 0) {
                // Registrar nueva asistencia usando legajo
                $horaActual = date('H:i:s');
                $stmt = $pdo->prepare("INSERT INTO `asistencia_c` (`Id_asiste`, `fecha`, `Entrada`, `Salida`) VALUES (?, ?, ?, ?)");
                $stmt->execute([$legajo, date('Y-m-d'), $horaActual, $horaActual]);
                $mensaje = 'Asistencia registrada correctamente.';
            } else {
                $mensaje = 'Ya registró su asistencia hoy.';
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
    
    // Obtener usuarios que NO registraron asistencia hoy
    $stmt = $pdo->prepare("
        SELECT u.`legajo`, u.`nombre`, u.`apellido`, c.`Denominacion` as `cargo`
        FROM `usuario` u
        LEFT JOIN `cargo` c ON u.`id_cargo` = c.`id_cargo`
        WHERE u.`legajo` NOT IN (
            SELECT DISTINCT a.`legajo` 
            FROM `asistencias` a 
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
    <?php if (!empty($mensaje)): ?>
        <div class="mensaje <?php echo (strpos($mensaje, 'Error') !== false) ? 'error' : 'exito'; ?>">
            <?php echo htmlspecialchars($mensaje); ?>
        </div>
    <?php endif; ?>

    <!-- Usuario logueado - Formulario de Asistencia -->
    <form method="post" action="inicio.php">
        <fieldset>
            <legend>Control de Asistencia Escolar</legend>
            <p>¡Bienvenido, <?php echo htmlspecialchars($nombreUsuario); ?>!</p>
            <p>E.P.E.T Nº 20</p>
        </fieldset>
        
        <fieldset>
            <legend>Registro de Asistencia</legend>
            <label for="cargo">Selecciona tu cargo:</label>
            <select name="cargo" id="cargo" required>
                <option value="">Seleccione un cargo...</option>
                <?php
                // Obtener los cargos del usuario desde la base de datos
                try {
                    $stmtCargos = $pdo->prepare("SELECT c.id_cargo, c.Denominacion FROM cargo c INNER JOIN usuario u ON c.id_cargo = u.id_cargo WHERE u.legajo = ?");
                    $stmtCargos->execute([$legajo]);
                    $cargosUsuario = $stmtCargos->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($cargosUsuario as $cargo) {
                        echo '<option value="' . htmlspecialchars($cargo['Denominacion']) . '">' . htmlspecialchars($cargo['Denominacion']) . '</option>';
                    }
                } catch (PDOException $e) {
                    echo '<option value="">Error al cargar cargos</option>';
                }
                ?>
            </select>
            <p>Marque el botón para confirmar su asistencia</p>
            <input type="submit" name="enviar" value="Asistencia">
        </fieldset>
    </form>

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
                    <td colspan="7" style="text-align: center;">No hay ausentes registrados para hoy.</td>
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

    <form method="post" action="inicioSesion.php" style="margin-top: 20px;">
        <input type="submit" name="logout" value="Cerrar Sesión" style="background-color: #f44336;">
    </form>
    
</body>
</html>
