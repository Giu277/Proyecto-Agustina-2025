<?php
// Página de selección inicial
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bienvenido</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body style="max-width: 480px; margin: 40px auto; text-align: center;">
    <h1>Bienvenido</h1>
    <p style="margin: 12px 0 24px 0;">Seleccione una opción para continuar.</p>
    <div style="display:flex; flex-direction:column; gap:12px; align-items:center;">
        <a href="inicioSesion.php" style="display:inline-block;background:#1f6d7a;color:#fff;padding:12px 18px;border-radius:6px;text-decoration:none;">Iniciar sesión</a>
        <a href="crearcuenta.php" style="display:inline-block;background:#4CAF50;color:#fff;padding:12px 18px;border-radius:6px;text-decoration:none;">Crear cuenta</a>
    </div>
</body>
</html>
