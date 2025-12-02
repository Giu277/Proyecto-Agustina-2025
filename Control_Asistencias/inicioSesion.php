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
            
            $stmt = $pdo->prepare("SELECT `legajo`, `nombre`, `apellido`, `contrasenia` FROM `usuario` WHERE `legajo` = ?");
            $stmt->execute([$legajoIngresado]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($usuario && password_verify($contraseniaIngresada, $usuario['contrasenia'])) {
                $_SESSION['legajo'] = $usuario['legajo'];
                $_SESSION['nombre'] = $usuario['nombre'] . ' ' . $usuario['apellido'];
                header("Location: inicio.php");
                exit();
            } else {
                $mensaje = $usuario ? 'Contraseña incorrecta' : 'Usuario no encontrado';
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
    <link rel="stylesheet" href="styles.css">
</head>
<body class="auth-page">
    <?php if (!empty($mensaje)): ?>
        <div class="mensaje <?php echo (strpos($mensaje, 'Error') !== false || strpos($mensaje, 'incorrecto') !== false || strpos($mensaje, 'no encontrado') !== false) ? 'error' : 'exito'; ?>">
            <?php echo htmlspecialchars($mensaje); ?>
        </div>
    <?php endif; ?>

    <div class="auth-card">
        <div class="auth-logo">
            <img src="Logo.epet" alt="Logo E.P.E.T" />
        </div>
        <h1>Control de Asistencia</h1>
        <p class="auth-subtitle">Ingrese sus datos</p>
        <form method="post" action="inicioSesion.php" class="auth-form">
            <label for="legajo">Legajo</label>
            <input type="text" name="legajo" id="legajo" required>
            
            <label for="contrasenia">Contraseña</label>
            <input type="password" name="contrasenia" id="contrasenia" required>
            
            <input type="submit" name="enviar" value="Ingresar" class="btn-primary">
        </form>
        <p class="auth-footer">E.P.E.T N° 20</p>
        <div class="link-registro">
            <p>¿No tienes cuenta? <a href="crearcuenta.php">Crear cuenta</a></p>
        </div>
    </div>
</body>
</html>
