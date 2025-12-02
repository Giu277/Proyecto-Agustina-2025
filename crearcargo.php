<?php
session_start();
require_once __DIR__ . '/Control_Asistencias/Conexion.php';

$mensaje = '';

// Conexión
try {
    $pdo = (new Conexion())->getConexion();
} catch (PDOException $e) {
    die('Error de conexión: ' . $e->getMessage());
}

// Validar sesión y rol Preceptor (simple)
$esPreceptor = false;
if (!isset($_SESSION['legajo'])) {
    header('Location: Control_Asistencias/inicioSesion.php');
    exit();
}
try {
    $st = $pdo->prepare("SELECT c.Denominacion FROM usuario u INNER JOIN cargo c ON c.id_cargo = u.id_cargo WHERE u.legajo = ? LIMIT 1");
    $st->execute([$_SESSION['legajo']]);
    $cargoActual = $st->fetch(PDO::FETCH_ASSOC);
    $esPreceptor = isset($cargoActual['Denominacion']) && strcasecmp($cargoActual['Denominacion'], 'Preceptor') === 0;
} catch (PDOException $e) {
    $esPreceptor = false;
}
if (!$esPreceptor) {
    $mensaje = 'Solo los preceptores pueden gestionar cargos.';
}

// Crear cargo (solo Denominación)
if ($esPreceptor && isset($_POST['accion']) && $_POST['accion'] === 'crear') {
    $nombre = trim($_POST['nombre_cargo'] ?? '');
    if ($nombre === '') {
        $mensaje = 'Ingrese un nombre de cargo.';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO cargo (Denominacion) VALUES (?)");
            $stmt->execute([$nombre]);
            $mensaje = 'Cargo creado correctamente.';
        } catch (PDOException $e) {
            $mensaje = 'Error al crear cargo: ' . $e->getMessage();
        }
    }
}

// Eliminar cargo
if ($esPreceptor && isset($_POST['accion']) && $_POST['accion'] === 'eliminar') {
    $idEliminar = (int)($_POST['id_cargo'] ?? 0);
    if ($idEliminar > 0) {
        try {
            $stmt = $pdo->prepare("DELETE FROM cargo WHERE id_cargo = ? LIMIT 1");
            $stmt->execute([$idEliminar]);
            $mensaje = 'Cargo eliminado.';
        } catch (PDOException $e) {
            $mensaje = 'Error al eliminar cargo: ' . $e->getMessage();
        }
    }
}

// Listado de cargos
$cargos = [];
// Usuarios agrupados por cargo para mostrar al administrar
$usuariosPorCargo = [];
try {
    $stmt = $pdo->query("SELECT id_cargo, Denominacion FROM cargo ORDER BY Denominacion ASC");
    $cargos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Mapear usuarios por cargo
    if (!empty($cargos)) {
        $ids = array_column($cargos, 'id_cargo');
        if (!empty($ids)) {
            $in  = implode(',', array_fill(0, count($ids), '?'));
            $stU = $pdo->prepare("SELECT id_cargo, legajo, nombre, apellido FROM usuario WHERE id_cargo IN ($in) ORDER BY apellido, nombre");
            $stU->execute($ids);
            $rowsU = $stU->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rowsU as $u) {
                $cid = $u['id_cargo'] ?? null;
                if ($cid === null) continue;
                if (!isset($usuariosPorCargo[$cid])) $usuariosPorCargo[$cid] = [];
                $usuariosPorCargo[$cid][] = $u;
            }
        }
    }
} catch (PDOException $e) {
    $mensaje = $mensaje ?: ('Error al listar cargos: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar cargos</title>
    <link rel="stylesheet" href="Control_Asistencias/styles.css">
</head>
<body>
    <nav class="navbar" aria-label="Barra de navegación principal">
        <div class="nav-container">
            <div class="nav-inner">
                <div class="nav-logo">
                    <img src="Control_Asistencias/Logo.epet" alt="Logo E.P.E.T" />
                </div>
                <a href="Control_Asistencias/inicio.php" class="nav-item">Inicio</a>
                <a href="Control_Asistencias/Asistencia.php" class="nav-item">Asistencias Registradas</a>
                <a href="crearcargo.php" class="nav-item nav-active">Cargo</a>
            </div>
            <div class="nav-right">
                <div class="nav-burger">
                    <button type="button" aria-haspopup="true" aria-expanded="false" aria-label="Abrir menú" onclick="toggleBurgerMenu()">☰</button>
                    <div class="burger-menu" id="burger-menu">
                        <?php if ($esPreceptor): ?>
                        <a href="crearcargo.php">Cargo</a>
                        <a href="Control_Asistencias/Administrador.php">Administración</a>
                        <?php endif; ?>
                        <form method="post" action="Control_Asistencias/inicioSesion.php" style="margin:0;">
                            <button type="submit" name="logout">Cerrar Sesión</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="cards-wrapper" style="max-width: 1100px; margin-top: 20px;">
        <?php if (!empty($mensaje)): ?>
            <div class="mensaje <?php echo (strpos($mensaje, 'Error') !== false || strpos($mensaje, 'Solo') !== false) ? 'error' : 'exito'; ?>">
                <?php echo htmlspecialchars($mensaje); ?>
            </div>
        <?php endif; ?>

        <?php if ($esPreceptor): ?>
        <div class="card">
            <h2>Gestión de cargos</h2>
            <form method="post" action="crearcargo.php" class="auth-form">
                <input type="hidden" name="accion" value="crear">
                <label>Nombre del cargo</label>
                <input type="text" name="nombre_cargo" required>
                <div class="btn-row" style="margin-top:12px;">
                    <button type="submit" class="btn-primary" style="max-width: 140px;">Crear</button>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <div class="card">
            <h3>Cargos existentes</h3>
            <?php if (!empty($cargos)): ?>
                <table class="tabla-asistencias">
                    <tr>
                        <th>ID</th>
                        <th>Denominación</th>
                        <?php if ($esPreceptor): ?><th>Acciones</th><?php endif; ?>
                    </tr>
                    <?php foreach ($cargos as $c): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($c['id_cargo']); ?></td>
                        <td><?php echo htmlspecialchars($c['Denominacion']); ?></td>
                        <?php if ($esPreceptor): ?>
                        <td class="actions">
                            <button type="button" class="btn-secondary" onclick="toggleUsuarios(<?php echo (int)$c['id_cargo']; ?>)">Administrar</button>
                            <form method="post" action="crearcargo.php" onsubmit="return confirm('¿Eliminar este cargo?');">
                                <input type="hidden" name="accion" value="eliminar">
                                <input type="hidden" name="id_cargo" value="<?php echo htmlspecialchars($c['id_cargo']); ?>">
                                <button type="submit" class="btn-danger">Eliminar</button>
                            </form>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php if (isset($usuariosPorCargo[$c['id_cargo']])): ?>
                    <tr id="usuarios-<?php echo (int)$c['id_cargo']; ?>" class="usuarios-cargo" style="display:none;background:rgba(255,255,255,0.85);">
                        <td colspan="3">
                            <strong>Usuarios con este cargo:</strong>
                            <ul style="margin:6px 0 0 16px;padding:0;list-style:disc;color:#1f3a56;">
                                <?php foreach ($usuariosPorCargo[$c['id_cargo']] as $u): ?>
                                    <li><?php echo htmlspecialchars($u['legajo'] . ' - ' . $u['apellido'] . ', ' . $u['nombre']); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </table>
            <?php else: ?>
                <p>No hay cargos registrados.</p>
            <?php endif; ?>
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

        function toggleUsuarios(id) {
            const row = document.getElementById('usuarios-' + id);
            if (!row) return;
            row.style.display = row.style.display === 'none' ? 'table-row' : 'none';
        }
    </script>
</body>
</html>
