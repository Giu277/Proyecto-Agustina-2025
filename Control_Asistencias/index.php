<?php
session_start();
require_once 'Conexion.php';

$mensaje = '';
$usuarioLogueado = false;
$nombreUsuario = '';
$legajo = null;

// Procesar login
if (isset($_POST['enviar']) && $_POST['enviar'] == 'Ingresar') {
    $nombreIngresado = trim($_POST['nombreUsuario'] ?? '');
    $apellidoIngresado = trim($_POST['apellidoUsuario'] ?? '');
    $legajoIngresado = $_POST['pass'] ?? '';
    
    if (!empty($nombreIngresado) && !empty($apellidoIngresado) && !empty($legajoIngresado)) {
        try {
            $conexion = new Conexion();
            $pdo = $conexion->getConexion();
            
            // Buscar usuario por nombre, apellido y legajo en la tabla usuario
            // El legajo es el ID principal, se busca directamente
            $stmt = $pdo->prepare("SELECT `legajo`, `nombre`, `apellido` FROM `usuario` WHERE `nombre` LIKE ? AND `apellido` LIKE ? AND `legajo` = ?");
            $nombreBuscar = "%{$nombreIngresado}%";
            $apellidoBuscar = "%{$apellidoIngresado}%";
            $stmt->execute([$nombreBuscar, $apellidoBuscar, $legajoIngresado]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($usuario) {
                // Usuario encontrado, legajo es el ID principal
                $_SESSION['legajo'] = $usuario['legajo'];
                $_SESSION['nombre'] = $usuario['nombre'] . ' ' . $usuario['apellido'];
                $usuarioLogueado = true;
                $nombreUsuario = $_SESSION['nombre'];
                $legajo = $_SESSION['legajo'];
                $mensaje = 'Login exitoso. ¡Bienvenido!';
            } else {
                $mensaje = 'Usuario no encontrado. Verifique sus datos.';
            }
        } catch (PDOException $e) {
            $mensaje = 'Error al conectar con la base de datos: ' . $e->getMessage();
            // Para debugging - remover en producción
            error_log("Error en login: " . $e->getMessage());
        }
    } else {
        $mensaje = 'Por favor complete todos los campos.';
    }
}

// Verificar si ya está logueado
if (isset($_SESSION['legajo'])) {
    $usuarioLogueado = true;
    $nombreUsuario = $_SESSION['nombre'] ?? '';
    $legajo = $_SESSION['legajo'];
}

// Procesar registro de asistencia
if (isset($_POST['enviar']) && $_POST['enviar'] == 'Asistencia' && $usuarioLogueado) {
    $cargo = $_POST['cargo'] ?? '';
    $fechaActual = date('Y-m-d H:i:s');
    
    if (!empty($cargo)) {
        try {
            $conexion = new Conexion();
            $pdo = $conexion->getConexion();
            
            // Verificar si ya registró asistencia hoy usando legajo
            $stmt = $pdo->prepare("SELECT `Id_asistencia` FROM `asistencias` WHERE `legajo` = ? AND DATE(`fecha`) = CURDATE()");
            $stmt->execute([$legajo]);
            
            if ($stmt->rowCount() == 0) {
                // Registrar nueva asistencia usando legajo
                $stmt = $pdo->prepare("INSERT INTO `asistencias` (`legajo`, `fecha`, `cargo`) VALUES (?, ?, ?)");
                $stmt->execute([$legajo, $fechaActual, $cargo]);
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
    // El identificador es legajo, no Id_usuario
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
    // Si hay error, mostrar mensaje pero continuar
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
    <title>Control de Asistencia Escolar</title>
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
        input[type="text"], input[type="password"], select {
            width: 100%;
            padding: 8px;
            margin-top: 5px;
            box-sizing: border-box;
        }
        .link-registro {
            margin-top: 15px;
            text-align: center;
        }
        .link-registro a {
            color: #2196F3;
            text-decoration: none;
        }
        .link-registro a:hover {
            text-decoration: underline;
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
        .info {
            background-color: #2196F3;
            color: white;
        }
    </style>
</head>
<body>
    <?php if (!empty($mensaje)): ?>
        <div class="mensaje <?php echo (strpos($mensaje, 'Error') !== false || strpos($mensaje, 'incorrecto') !== false || strpos($mensaje, 'no encontrado') !== false) ? 'error' : 'exito'; ?>">
            <?php echo htmlspecialchars($mensaje); ?>
        </div>
    <?php endif; ?>

    <?php if (!$usuarioLogueado): ?>
        <!-- Formulario de Login -->
        <form method="post" action="index.php">
            <fieldset>
                <legend>Control de Asistencia Escolar</legend>
                <p>Ingrese sus datos:</p>
                <label for="nom">Nombre</label>
                <input type="text" name="nombreUsuario" id="nom" required>
                
                <label for="ape">Apellido</label>
                <input type="text" name="apellidoUsuario" id="ape" required>
                
                <label for="contra">Legajo</label>
                <input type="password" name="pass" id="contra" required>
                
                <input type="submit" name="enviar" value="Ingresar">
                <p>E.P.E.T Nº 20</p>
            </fieldset>
        </form>
        <div class="link-registro">
            <p>¿No tienes cuenta? <a href="crearcuenta.php">Crear cuenta</a></p>
        </div>
    <?php else: ?>
        <!-- Usuario logueado - Formulario de Asistencia -->
        <form method="post" action="index.php">
            <fieldset>
                <legend>Control de Asistencia Escolar</legend>
                <p>¡Bienvenido, <?php echo htmlspecialchars($nombreUsuario); ?>!</p>
                <p>E.P.E.T Nº 20</p>
            </fieldset>
            
            <fieldset>
                <p>¡Bienvenido!</p>
                <label for="cargo">Selecciona tu cargo:</label>
                <select name="cargo" id="cargo" required>
                    <option value="">Seleccione un cargo...</option>
                    <option value="Preceptor">Preceptor</option>
                    <option value="Secretario">Secretario</option>
                    <option value="Subsecretario">Subsecretario</option>
                    <option value="Jefe de preceptores">Jefe de preceptores</option>
                </select>
                <p>Marque el botón para confirmar su asistencia</p>
                <input type="submit" name="enviar" value="Asistencia">
            </fieldset>
        </form>

        <!-- Tabla de Ausentes del Día -->
        <table border="1">
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

        <p><a href="index.php?logout=1">Cerrar Sesión</a></p>
    <?php endif; ?>

    <?php
    // Procesar logout
    if (isset($_GET['logout'])) {
        session_destroy();
        header("Location: index.php");
        exit();
    }
    ?>
</body>
</html>

