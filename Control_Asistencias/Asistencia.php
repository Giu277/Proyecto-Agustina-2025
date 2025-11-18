<?php
require_once 'Conexion.php';

class Asistencia {
    private $pdo;

    public function __construct() {
        $conexion = new Conexion();
        $this->pdo = $conexion->getConexion();
    }

    // Registrar asistencia (verifica si ya existe en el día)
    public function registrarAsistencia($legajo, $cargo) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM asistencia_c 
                WHERE Id_asiste = ? AND Cargo = ? AND fecha = CURDATE()
            ");
            $stmt->execute([$legajo]);

            if ($stmt->rowCount() > 0) {
                return 'Ya registró su asistencia hoy.';
            }

            $horaActual = date('H:i:s');
            $stmt = $this->pdo->prepare("
                INSERT INTO asistencia_c (Id_asiste, fecha, Entrada, Salida, Cargo) 
                VALUES (?, CURDATE(), ?, ?, ?)
            ");
            $stmt->execute([$legajo, $horaActual, $horaActual, $cargo]);

            return 'Asistencia registrada correctamente.';
        } catch (PDOException $e) {
            return 'Error al registrar asistencia: ' . $e->getMessage();
        }
    }

    // Obtener ausentes del día
    public function obtenerAusentes() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT u.legajo, u.nombre, u.apellido, c.Denominacion AS cargo
                FROM usuario u
                LEFT JOIN cargo c ON u.id_cargo = c.id_cargo
                WHERE u.legajo NOT IN (
                    SELECT DISTINCT a.Id_asiste 
                    FROM asistencia_c a 
                    WHERE DATE(a.fecha) = CURDATE()
                )
                ORDER BY u.apellido, u.nombre
            ");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
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
    <table>
        <caption>Asistencias Registradas - <?php echo date('d/m/Y'); ?></caption>
        <thead>
            <tr>
                <th>Nº</th>
                <th>Legajo</th>
                <th>Nombre y Apellido</th>
                <th>Cargo</th>
                <th>Fecha registrada</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($asistencias)): ?>
                <tr>
                    <td colspan="5" style="text-align: center;">No hay asistencias registradas para la fecha seleccionada.</td>
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
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
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
</body>
</html>