<?php
session_start();
// Alinear la zona horaria para mostrar las horas tal como se registran
date_default_timezone_set('America/Argentina/Buenos_Aires');
require_once 'Conexion.php';

// Clase original (se deja por compatibilidad con usos existentes)
class Asistencia {
    private $pdo;

    public function __construct() {
        $conexion = new Conexion();
        $this->pdo = $conexion->getConexion();
    }

    public function registrarAsistencia($legajo, $cargo) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 1 FROM asistencia_c 
                WHERE Id_asiste = ? AND Cargo = ? AND fecha = CURDATE() LIMIT 1
            ");
            $stmt->execute([$legajo, $cargo]);

            if ($stmt->rowCount() > 0) {
                return 'Ya registró su asistencia hoy.';
            }

            $horaActual = date('H:i:s');
            $stmt = $this->pdo->prepare("
                INSERT INTO asistencia_c (Id_asiste, fecha, Entrada, Salida, Cargo) 
                VALUES (?, CURDATE(), ?, ?, ?)
            ");
            $salidaNull = isColumnNullable($this->pdo, 'asistencia_c', 'Salida');
            $salidaValor = $salidaNull ? null : $horaActual;
            $stmt->execute([$legajo, $horaActual, $salidaValor, $cargo]);

            return 'Asistencia registrada correctamente.';
        } catch (PDOException $e) {
            return 'Error al registrar asistencia: ' . $e->getMessage();
        }
    }

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

// Helpers para obtener la tabla de asistencias (mismo criterio que en inicio.php)
function getColumns(PDO $pdo, string $table): array {
    $stmt = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
    $stmt->execute([$table]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function isColumnNullable(PDO $pdo, string $table, string $column): bool {
    $stmt = $pdo->prepare("SELECT IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1");
    $stmt->execute([$table, $column]);
    $val = $stmt->fetchColumn();
    return strtoupper((string)$val) === 'YES';
}

function obtenerAsistencias(PDO $pdo, string $fecha): array {
    $colsA = getColumns($pdo, 'asiste-c');
    $hasA_legajo = in_array('legajo', $colsA, true) || in_array('Legajo', $colsA, true);
    $hasA_Id_asiste = in_array('Id_asiste', $colsA, true) || in_array('id_asiste', $colsA, true);
    $hasA_fecha = in_array('fecha', $colsA, true) || in_array('Fecha', $colsA, true);
    $hasA_Entrada = in_array('Entrada', $colsA, true) || in_array('entrada', $colsA, true);
    $hasA_Salida = in_array('Salida', $colsA, true) || in_array('salida', $colsA, true);

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
    if ($hasA_fecha) {
        $st->execute([$fecha]);
    } else {
        $st->execute();
    }
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

// Validar sesión
if (!isset($_SESSION['legajo'])) {
    header("Location: inicioSesion.php");
    exit();
}

$mensaje = '';
$nombreUsuario = $_SESSION['nombre'] ?? '';
$legajo = $_SESSION['legajo'];
$asistencias = [];

try {
    $conexion = new Conexion();
    $pdo = $conexion->getConexion();
    $asistencias = obtenerAsistencias($pdo, date('Y-m-d'));
} catch (PDOException $e) {
    $mensaje = 'Error al obtener asistencias registradas: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asistencias Registradas</title>
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
        :root { --nav-height: 56px; }
        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: var(--nav-height);
            background-color: #1f6d7a;
            border-bottom: 1px solid rgba(0,0,0,0.12);
            z-index: 1000;
            box-shadow: 0 2px 4px rgba(0,0,0,0.08);
        }
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
            padding-left: 96px;
            justify-content: flex-start;
        }
        .nav-logo {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            display: flex;
            align-items: center;
        }
        .nav-logo img {
            height: calc(var(--nav-height) - 14px);
            width: auto;
            display: block;
            border-radius: 4px;
            object-fit: contain;
        }
        .nav-right {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            display: flex;
            align-items: center;
        }
        .nav-item {
            color: #ffffff;
            padding: 8px 12px;
            border-radius: 4px;
            text-decoration: none;
            font-weight: 500;
            cursor: default;
            display: inline-block;
            line-height: 1;
            opacity: 0.95;
        }
        .nav-item.nav-active {
            background-color: rgba(255,255,255,0.12);
            color: #ffffff;
        }
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
            padding-top: calc(var(--nav-height) + 20px);
        }
        .error { background-color: #f44336; color: white; }
        .exito { background-color: #4CAF50; color: white; }
    </style>
</head>
<body>
    <nav class="navbar" aria-label="Barra de navegación principal">
        <div class="nav-container">
            <div class="nav-inner">
                <div class="nav-logo">
                    <img src="Logo.epet" alt="Logo E.P.E.T" />
                </div>
                <a href="inicio.php" class="nav-item">Inicio</a>
                <a href="Asistencia.php" class="nav-item nav-active">Asistencias Registradas</a>
            </div>
            <div class="nav-right">
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
    <?php endif; ?>

    <div class="card" style="border:1px solid #e6e6e6;border-radius:8px;padding:14px;margin-top:10px;">
        <h3 style="margin:0 0 6px 0;">Asistencias registradas</h3>
        <p style="margin:0;color:#666;">Hola <?php echo htmlspecialchars($nombreUsuario); ?>, estas son las asistencias de hoy <?php echo date('d/m/Y'); ?>.</p>
    </div>

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
