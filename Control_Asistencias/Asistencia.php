<?php
session_start();
date_default_timezone_set('America/Argentina/Buenos_Aires');
require_once 'Conexion.php';

// Clase original (compatibilidad)
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
            if ($stmt->rowCount() > 0) return 'Ya registró su asistencia hoy.';
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

// Helpers
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

function formatearHora(?string $valor): string {
    if (empty($valor)) return '-';
    $t = strtotime($valor);
    return $t ? date('H:i', $t) : htmlspecialchars($valor);
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
$cargoUsuario = '';

try {
    $conexion = new Conexion();
    $pdo = $conexion->getConexion();
    $asistencias = obtenerAsistencias($pdo, date('Y-m-d'));
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
                    <button type="button" aria-haspopup="true" aria-expanded="false" aria-label="Abrir menú" onclick="toggleBurgerMenu()">&#9776;</button>
                    <div class="burger-menu" id="burger-menu">
                        <?php if (isset($cargoUsuario) && strcasecmp($cargoUsuario, 'Preceptor') === 0): ?>
                        <a href="../crearcargo.php">Cargo</a>
                        <a href="Administrador.php">Administracion</a>
                        <?php endif; ?>
                            <button type="submit" name="logout">Cerrar sesion</button>
                            <button type="submit" name="logout">Cerrar sesión</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="cards-wrapper" style="max-width: 1100px; margin-top: 20px;">
        <?php if (!empty($mensaje)): ?>
            <div class="mensaje <?php echo (strpos($mensaje, 'Error') !== false) ? 'error' : 'exito'; ?>">
                <?php echo htmlspecialchars($mensaje); ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <h3 style="margin:0 0 6px 0;">Asistencias registradas</h3>
            <p style="margin:0;color:#eaf6ff;">Hola <?php echo htmlspecialchars($nombreUsuario); ?>, estas son las asistencias de hoy <?php echo date('d/m/Y'); ?>.</p>
        </div>

        <div class="card table-card">
            <h3>Asistencias registradas - <?php echo date('d/m/Y'); ?></h3>
            <table class="tabla-asistencias">
                <tr>
                    <th>Nro</th>
                    <th>Legajo</th>
                    <th>Nombre y Apellido</th>
                    <th>Cargo</th>
                    <th>Fecha</th>
                    <th>Entrada</th>
                    <th>Salida</th>
                </tr>
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
                            <td><?php echo htmlspecialchars(trim(($fila['nombre'] ?? '') . ' ' . ($fila['apellido'] ?? ''))); ?></td>
                            <td><?php echo htmlspecialchars($fila['cargo'] ?? 'N/A'); ?></td>
                            <td><?php echo !empty($fila['fecha']) ? date('d/m/Y', strtotime($fila['fecha'])) : '-'; ?></td>
                            <td><?php echo formatearHora($fila['Entrada'] ?? ''); ?></td>
                            <td><?php echo formatearHora($fila['Salida'] ?? ''); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </table>
        </div>
    </div>

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
