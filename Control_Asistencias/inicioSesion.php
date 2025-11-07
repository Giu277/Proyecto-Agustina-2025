<?php
session_start();
require_once 'Conexion.php';

// Procesar logout
if (isset($_POST['logout'])) {
    session_destroy();
    header("Location: inicioSesion.php");
    exit();
}

// Si ya está logueado, redirigir a inicio.php
if (isset($_SESSION['legajo'])) {
    header("Location: inicio.php");
    exit();
}

$mensaje = '';

// Procesar login
if (isset($_POST['enviar']) && $_POST['enviar'] == 'Ingresar') {
    $legajoIngresado = trim($_POST['legajo'] ?? '');
    $contraseniaIngresada = $_POST['contrasenia'] ?? '';
    
    if (!empty($legajoIngresado) && !empty($contraseniaIngresada)) {
        try {
            $conexion = new Conexion();
            $pdo = $conexion->getConexion();
            
            // Primero verificamos si el legajo existe
            $stmt = $pdo->prepare("SELECT `legajo`, `nombre`, `apellido`, `contrasenia` FROM `usuario` WHERE `legajo` = ?");
            $stmt->execute([$legajoIngresado]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($usuario) {
                // Si el legajo existe, verificamos la contraseña usando password_verify
                if (password_verify($contraseniaIngresada, $usuario['contrasenia'])) {
                // Usuario encontrado, guardar datos en sesión
                    $_SESSION['legajo'] = $usuario['legajo'];
                    $_SESSION['nombre'] = $usuario['nombre'] . ' ' . $usuario['apellido'];
                    // Redirigir a la página principal
                    header("Location: inicio.php");
                    exit();
                } else {
                    $mensaje = 'Contraseña incorrecta';
                }
            } else {
                $mensaje = 'Usuario no encontrado';
            }
        } catch (PDOException $e) {
            $mensaje = 'Error al conectar con la base de datos: ' . $e->getMessage();
        }
    } else {
        $mensaje = 'Por favor complete todos los campos.';
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

    <!-- Formulario de Login -->
    <form method="post" action="inicioSesion.php">
        <fieldset>
            <legend>Control de Asistencia Escolar</legend>
            <p>Ingrese sus datos:</p>
            <label for="legajo">Legajo</label>
            <input type="text" name="legajo" id="legajo" required>
            
            <label for="contrasenia">Contraseña</label>
            <input type="password" name="contrasenia" id="contrasenia" required>
            
            <input type="submit" name="enviar" value="Ingresar">
            <p>E.P.E.T Nº 20</p>
        </fieldset>
    </form>
    <div class="link-registro">
        <p>¿No tienes cuenta? <a href="crearcuenta.php">Crear cuenta</a></p>
    </div>
</body>
</html>