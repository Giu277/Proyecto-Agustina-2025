<?php
session_start();
date_default_timezone_set('America/Argentina/Buenos_Aires');
require_once 'Conexion.php';

// Helpers
function getColumns(PDO $pdo, string $table): array {
    $stmt = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
    $stmt->execute([$table]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function getCargoUsuario(PDO $pdo, $legajo): string {
    try {
        $st = $pdo->prepare("SELECT c.Denominacion FROM usuario u INNER JOIN cargo c ON c.id_cargo = u.id_cargo WHERE u.legajo = ? LIMIT 1");
        $st->execute([$legajo]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r['Denominacion'] ?? '';
    } catch (PDOException $e) {
        return '';
    }
}

function obtenerAsistencias(PDO $pdo): array {
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
        $select[] = "c.`Denominacion` AS cargo"; 
    } else {
        $select[] = $hasA_legajo ? 'a.legajo AS legajo' : ($hasA_Id_asiste ? 'a.Id_asiste AS legajo' : "NULL AS legajo");
        $select[] = "'' AS nombre";
        $select[] = "'' AS apellido";
        $select[] = "'' AS cargo";
    }

    $select[] = $hasA_fecha ? 'a.fecha' : "NULL AS fecha";
    $select[] = $hasA_Entrada ? 'a.Entrada' : "'' AS Entrada";
    $select[] = $hasA_Salida ? "COALESCE(a.Salida,'') AS Salida" : "'' AS Salida";
    $select[] = "CASE
                    WHEN a.Entrada IS NULL THEN 'Ausente'
                    WHEN a.Entrada <= c.Entrada THEN 'A horario'
                    WHEN a.Entrada > c.Entrada THEN 'Tarde'
                    ELSE 'Sin dato'
                 END AS estado";

    $from = "FROM `asiste-c` a";
    $join = !empty($joinConds) ? ' LEFT JOIN usuario u ON (' . implode(' OR ', $joinConds) . ') LEFT JOIN cargo c ON c.id_cargo = u.id_cargo' : '';
    $order = 'ORDER BY a.fecha DESC, u.apellido, u.nombre ASC';

    $sql = 'SELECT ' . implode(",\n    ", $select) . "\n    " . $from . $join . "\n    " . $order;
    $st = $pdo->prepare($sql);
    $st->execute();
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

// Validación sesión
if (!isset($_SESSION['legajo'])) {
    header("Location: inicioSesion.php");
    exit();
}

$mensaje = '';
$nombreUsuario = $_SESSION['nombre'] ?? '';
$legajo = $_SESSION['legajo'];
$asistencias = [];
$cargoUsuario = '';

try {
    $conexion = new Conexion();
    $pdo = $conexion->getConexion();
    $asistencias = obtenerAsistencias($pdo);
    $cargoUsuario = getCargoUsuario($pdo, $legajo);
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
    <link rel="stylesheet" href="styles.css">
    
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
                <div class="nav-burger">
                    <button type="button" aria-haspopup="true" aria-expanded="false" aria-label="Abrir menú" onclick="toggleBurgerMenu()">☰</button>
                    <div class="burger-menu" id="burger-menu">
                        <?php if (isset($cargoUsuario) && strcasecmp($cargoUsuario, 'Preceptor') === 0): ?>
                        <a href="Administrador.php">Administración</a>
                        <?php endif; ?>
                        <form method="post" action="inicioSesion.php" style="margin:0;">
                            <button type="submit" name="logout">Cerrar Sesión</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <?php if (!empty($mensaje)): ?>
        <div class="mensaje <?php echo (strpos($mensaje, 'Error') !== false) ? 'error' : 'exito'; ?>">
            <?php echo htmlspecialchars($mensaje); ?>
        </div>
    <?php endif; ?>

<?php if (!empty($asistencias)): ?>
    <table class="tabla-asistencias">
        <tr>
            <th>Legajo</th>
            <th>Nombre</th>
            <th>Apellido</th>
            <th>Cargo</th>
            <th>Fecha</th>
            <th>Entrada</th>
            <th>Salida</th>
            <th>Estado</th>
        </tr>
        <?php foreach ($asistencias as $row): ?>
            <tr>
                <td><?php echo htmlspecialchars($row['legajo']); ?></td>
                <td><?php echo htmlspecialchars($row['nombre']); ?></td>
                <td><?php echo htmlspecialchars($row['apellido']); ?></td>
                <td><?php echo htmlspecialchars($row['cargo']); ?></td>
                <td><?php echo htmlspecialchars($row['fecha']); ?></td>
                <td><?php echo htmlspecialchars($row['Entrada']); ?></td>
                <td><?php echo htmlspecialchars($row['Salida']); ?></td>
                <td><?php echo htmlspecialchars($row['estado']); ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
<?php else: ?>
    <p>No hay asistencias registradas para mostrar.</p>
<?php endif; ?>

    <script>
        function toggleBurgerMenu() {
            const menu = document.getElementById('burger-menu');
            if (!menu) return;
            menu.classList.toggle('open');
        }
        document.addEventListener('click', function(ev) {
            const menu = document.getElementById('burger-menu');
            const btn = document.querySelector('.nav-burger button');
            if (!menu || !btn) return;
            if (menu.contains(ev.target) || btn.contains(ev.target)) return;
            menu.classList.remove('open');
        });
    </script>
</body>
</html>


