<?php
session_start();
// Asegurar horario local para que la hora guardada coincida con el momento del click
date_default_timezone_set('America/Argentina/Buenos_Aires');
require_once 'Conexion.php';

// Obtener columnas de una tabla
function getColumns(PDO $pdo, string $table): array {
    $stmt = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
    $stmt->execute([$table]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Saber si una columna permite NULL
function isColumnNullable(PDO $pdo, string $table, string $column): bool {
    $stmt = $pdo->prepare("SELECT IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1");
    $stmt->execute([$table, $column]);
    $val = $stmt->fetchColumn();
    return strtoupper((string)$val) === 'YES';
}

// Devuelve el nombre real de la columna si existe (comparación case-insensitive)
function findColumn(array $cols, array $candidates): ?string {
    foreach ($cols as $col) {
        foreach ($candidates as $cand) {
            if (strcasecmp($col, $cand) === 0) {
                return $col;
            }
        }
    }
    return null;
}

// Obtener horario de entrada para el usuario (busca por id_cargo del usuario y día actual)
// Flujo: 1) Obtener id_cargo del usuario 2) Buscar columna con cargo en tabla horario 3) Obtener Id_horario para hoy
function getHorarioForUser(PDO $pdo, $legajo) {
    // Obtener id_cargo del usuario
    $stmt = $pdo->prepare("SELECT id_cargo FROM usuario WHERE legajo = ? LIMIT 1");
    $stmt->execute([$legajo]);
    $usr = $stmt->fetch(PDO::FETCH_ASSOC);
    $userIdCargo = $usr['id_cargo'] ?? null;

    // Detectar columna que vincula cargo con horario
    $stmtCols = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'horario'");
    $stmtCols->execute();
    $colsHor = $stmtCols->fetchAll(PDO::FETCH_COLUMN);
    $possible = ['id_cargo','Id_cargo','Id_Cargo','IdCargo','cargo_id','idCargo','Cargo'];
    $col = null;
    foreach ($possible as $c) { if (in_array($c,$colsHor,true)) { $col = $c; break; } }

    // Buscar horario para el cargo del usuario en el día de hoy (o genérico 0000-00-00)
    if ($col && $userIdCargo) {
        $q = "SELECT Id_horario FROM horario WHERE `" . $col . "` = ? AND (Dia = CURDATE() OR Dia = '0000-00-00') ORDER BY (Dia = CURDATE()) DESC, Entrada ASC LIMIT 1";
        $s = $pdo->prepare($q);
        $s->execute([$userIdCargo]);
    } else {
        // Fallback: si no hay cargo o no se encuentra columna, obtener cualquier horario del día o genérico
        $s = $pdo->prepare("SELECT Id_horario FROM horario WHERE Dia = CURDATE() OR Dia = '0000-00-00' ORDER BY Dia DESC, Entrada ASC LIMIT 1");
        $s->execute();
    }
    $r = $s->fetch(PDO::FETCH_ASSOC);
    return $r['Id_horario'] ?? null;
}

// Verificar si el usuario registró hoy
function usuarioRegistroHoy(PDO $pdo, $legajo, array $cols): bool {
    $hasLegajo = in_array('legajo',$cols,true) || in_array('Legajo',$cols,true);
    $hasIdAsiste = in_array('Id_asiste',$cols,true) || in_array('id_asiste',$cols,true);
    if ($hasLegajo) {
        $st = $pdo->prepare("SELECT 1 FROM `asiste-c` WHERE `legajo` = ? AND `fecha` = CURDATE() LIMIT 1");
        $st->execute([$legajo]);
        return (bool)$st->fetchColumn();
    }
    if ($hasIdAsiste) {
        $st = $pdo->prepare("SELECT 1 FROM `asiste-c` WHERE `Id_asiste` = ? AND `fecha` = CURDATE() LIMIT 1");
        $st->execute([$legajo]);
        return (bool)$st->fetchColumn();
    }
    return false;
}

// Insertar asistencia dinámicamente
function insertarAsistencia(PDO $pdo, array $cols, $legajo, $idHorario, $fechaActual, $horaActual): bool {
    // Estructura fija de asiste-c. Salida se guarda como 00:00:00 para dejarla pendiente (columna es NOT NULL).
    // Si el Id_asiste ya existe (PK), actualizamos el registro para el día actual; si no, insertamos.
    $existing = $pdo->prepare("SELECT `fecha` FROM `asiste-c` WHERE `Id_asiste` = ? LIMIT 1");
    $existing->execute([$legajo]);
    $row = $existing->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        // Reusar el mismo Id_asiste: actualizamos fecha, entrada y salida (pendiente)
        $upd = $pdo->prepare("UPDATE `asiste-c` SET `fecha` = ?, `Entrada` = ?, `Salida` = '00:00:00' WHERE `Id_asiste` = ?");
        $upd->execute([$fechaActual, $horaActual, $legajo]);
    } else {
        $st = $pdo->prepare("INSERT INTO `asiste-c` (`Id_asiste`,`fecha`,`Entrada`,`Salida`) VALUES (?,?,?,?)");
        $st->execute([$legajo, $fechaActual, $horaActual, '00:00:00']);
    }

    // Verificamos que la fila esté para hoy
    $chk = $pdo->prepare("SELECT COUNT(*) FROM `asiste-c` WHERE `fecha` = ? AND `Id_asiste` = ?");
    $chk->execute([$fechaActual, $legajo]);
    return ((int)$chk->fetchColumn()) > 0;
}

// Registrar salida en la fila del día (actualiza la columna de Salida si existe)
function registrarSalida(PDO $pdo, array $cols, $legajo, string $fechaActual, string $horaSalida): bool {
    // Intentar actualizar la fila del día
    $st = $pdo->prepare("UPDATE `asiste-c` SET `Salida` = ? WHERE `Id_asiste` = ? AND `fecha` = ? LIMIT 1");
    $st->execute([$horaSalida, $legajo, $fechaActual]);
    if ($st->rowCount() > 0) {
        // Verificamos que quedó grabada
        $chk = $pdo->prepare("SELECT `Salida` FROM `asiste-c` WHERE `Id_asiste` = ? AND `fecha` = ? LIMIT 1");
        $chk->execute([$legajo, $fechaActual]);
        $val = $chk->fetchColumn();
        return !empty($val) && $val !== '00:00:00' && $val !== '00:00:00.000000';
    }

    // Buscar la última fila del legajo para reusarla
    $sel = $pdo->prepare("SELECT `Id_asiste`, `fecha`, `Entrada`, `Salida` FROM `asiste-c` WHERE `Id_asiste` = ? ORDER BY `fecha` DESC LIMIT 1");
    $sel->execute([$legajo]);
    $row = $sel->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        // Si existe, actualizamos la fecha a hoy y guardamos la salida; si la entrada está vacía, la seteamos a la salida
        $entradaGuardar = $row['Entrada'] ?? $horaSalida;
        if ($entradaGuardar === '00:00:00' || $entradaGuardar === '00:00:00.000000' || empty($entradaGuardar)) {
            $entradaGuardar = $horaSalida;
        }
        $upd = $pdo->prepare("UPDATE `asiste-c` SET `fecha` = ?, `Entrada` = ?, `Salida` = ? WHERE `Id_asiste` = ? LIMIT 1");
        $upd->execute([$fechaActual, $entradaGuardar, $horaSalida, $legajo]);
        if ($upd->rowCount() > 0) return true;
    }

    // Si no había fila previa, insertamos una nueva con entrada/salida iguales
    $ins = $pdo->prepare("INSERT INTO `asiste-c` (`Id_asiste`,`fecha`,`Entrada`,`Salida`) VALUES (?,?,?,?)");
    $ins->execute([$legajo, $fechaActual, $horaSalida, $horaSalida]);
    if ($ins->rowCount() > 0) return true;

    return false;
}

// Obtener ausentes del día
function obtenerAusentes(PDO $pdo): array {
    $stmt = $pdo->prepare("SELECT u.`legajo`, u.`nombre`, u.`apellido`, c.`Denominacion` AS `cargo` FROM `usuario` u LEFT JOIN `cargo` c ON u.`id_cargo` = c.`id_cargo` WHERE u.`legajo` NOT IN (SELECT DISTINCT COALESCE(a.`legajo`, a.`Id_asiste`) FROM `asiste-c` a WHERE DATE(a.`fecha`) = CURDATE()) ORDER BY u.`apellido`, u.`nombre`");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Obtener registro del usuario hoy
function getUserRegistroHoy(PDO $pdo, $legajo) {
    // Estructura conocida de asiste-c: Id_asiste, fecha, Entrada, Salida
    $st = $pdo->prepare("SELECT `Entrada`,`Salida`,`fecha` FROM `asiste-c` WHERE `Id_asiste` = ? AND `fecha` = CURDATE() LIMIT 1");
    $st->execute([$legajo]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if ($row) return $row;
    // Fallback: última fila, por si la fecha quedó mal cargada
    $st2 = $pdo->prepare("SELECT `Entrada`,`Salida`,`fecha` FROM `asiste-c` WHERE `Id_asiste` = ? ORDER BY `fecha` DESC LIMIT 1");
    $st2->execute([$legajo]);
    return $st2->fetch(PDO::FETCH_ASSOC);
}

// Determinar si la salida sigue pendiente
function isSalidaPendiente(?string $salida, ?string $entrada, ?string $fecha): bool {
    $hoy = date('Y-m-d');
    if (empty($fecha) || $fecha !== $hoy) return true;
    $esCero = ($salida === null || $salida === '' || $salida === '00:00:00' || $salida === '00:00:00.000000');
    $igualEntrada = ($entrada !== null && $salida === $entrada);
    return $esCero || $igualEntrada;
}

// Obtener denominación del cargo del usuario (para menú)
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

// Verificar si el usuario está logueado
if (!isset($_SESSION['legajo'])) {
    header("Location: inicioSesion.php");
    exit();
}

$mensaje = '';
$nombreUsuario = $_SESSION['nombre'] ?? '';
$legajo = $_SESSION['legajo'];
$usuarioLogueado = true;

// Cargo del usuario (para menú)
$cargoUsuario = '';
try {
    $pdoMenu = (new Conexion())->getConexion();
    $cargoUsuario = getCargoUsuario($pdoMenu, $legajo);
} catch (PDOException $e) {
    $cargoUsuario = '';
}

// Procesar registro de asistencia 
if (isset($_POST['enviar']) && $_POST['enviar'] == 'Registrar Asistencia' && $usuarioLogueado) {
    $cargo = $_POST['cargo'] ?? '';
    $fechaActual = date('Y-m-d');
    $horaActual = date('H:i:s');

    if (!empty($cargo)) {
        try {
            $conexion = new Conexion();
            $pdo = $conexion->getConexion();
            $idHorario = getHorarioForUser($pdo, $legajo);
            $cols = getColumns($pdo, 'asiste-c');
            if (usuarioRegistroHoy($pdo, $legajo, $cols)) {
                $mensaje = 'Ya registró su asistencia hoy.';
            } else {
                $ok = insertarAsistencia($pdo, $cols, $legajo, $idHorario, $fechaActual, $horaActual);
                $mensaje = $ok ? 'Asistencia registrada correctamente.' : 'No se encontró la fila insertada en `asiste-c`.';
            }
        } catch (PDOException $e) {
            $mensaje = 'Error al registrar asistencia: ' . $e->getMessage();
        }
    } else {
        $mensaje = 'Por favor seleccione un cargo.';
    }
}

// Procesar registro de salida (botón rojo "Salida")
if (isset($_POST['enviar_salida']) && $usuarioLogueado) {
    $fechaActual = date('Y-m-d');
    $horaActual = date('H:i:s');
    try {
        $conexion = new Conexion();
        $pdo = $conexion->getConexion();
        $cols = getColumns($pdo, 'asiste-c');
        $estadoHoy = getUserRegistroHoy($pdo, $legajo);
        if (!$estadoHoy) {
            $mensaje = 'Primero registre su entrada.';
        } else {
            $salidaActual = $estadoHoy['Salida'] ?? null;
            $entradaActual = $estadoHoy['Entrada'] ?? null;
            $salidaPendienteHoy = isSalidaPendiente($salidaActual, $entradaActual, $estadoHoy['fecha'] ?? null);
            if (!$salidaPendienteHoy) {
                $mensaje = 'Ya registró su salida hoy.';
            } else {
                $ok = registrarSalida($pdo, $cols, $legajo, $fechaActual, $horaActual);
                $mensaje = $ok ? 'Salida registrada correctamente.' : 'No se pudo registrar la salida.';
            }
        }
    } catch (PDOException $e) {
        $mensaje = 'Error al registrar salida: ' . $e->getMessage();
    }
}

// Obtener ausentes del día
$ausentes = [];
try {
    $conexion = new Conexion();
    $pdo = $conexion->getConexion();
    $ausentes = obtenerAusentes($pdo);
} catch (PDOException $e) {
    if (empty($ausentes)) $mensajeError = 'Error al obtener ausentes: ' . $e->getMessage();
}

// Comprobar si el usuario ya registró asistencia hoy (para mostrar estado)
$yaRegistro = false;
$horaRegistrada = null;
$salidaRegistrada = null;
$salidaPendiente = true;
try {
    $conexionEstado = new Conexion();
    $pdoEstado = $conexionEstado->getConexion();
    $filaEst = getUserRegistroHoy($pdoEstado, $legajo);
    if ($filaEst) { 
        $yaRegistro = true; 
        $horaRegistrada = $filaEst['Entrada']; 
        $salidaRegistrada = $filaEst['Salida']; 
        $salidaPendiente = isSalidaPendiente($salidaRegistrada, $horaRegistrada, $filaEst['fecha'] ?? null);
    }
} catch (PDOException $e) {
    // no crítico; dejamos $yaRegistro = false
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inicio - Control de Asistencia Escolar</title>
    <link rel="stylesheet" href="styles.css">
    
</head>
<body>
    <!-- Barra de navegación -->
    <nav class="navbar" aria-label="Barra de navegación principal">
        <div class="nav-container">
            <div class="nav-inner">
                <div class="nav-logo">
                    <!-- El logo se encuentra en la raíz del directorio del proyecto -->
                    <img src="Logo.epet" alt="Logo E.P.E.T" />
                </div>
                <a href="inicio.php" class="nav-item nav-active">Inicio</a>
                <a href="Asistencia.php" class="nav-item">Asistencias Registradas</a>
            </div>
            <div class="nav-right">
                <div class="nav-burger">
                    <button type="button" aria-haspopup="true" aria-expanded="false" aria-label="Abrir menu" onclick="toggleBurgerMenu()">&#9776;</button>
                    <div class="burger-menu" id="burger-menu">
                        <?php if (isset($cargoUsuario) && strcasecmp($cargoUsuario, 'Preceptor') === 0): ?>
                        <a href="../crearcargo.php">Cargo</a>
                        <a href="Administrador.php">Administracion</a>
                        <?php endif; ?>
                        <form method="post" action="inicioSesion.php" style="margin:0;">
                            <button type="submit" name="logout" style="width:100%;text-align:left;border:none;background:none;padding:10px 12px;cursor:pointer;">Cerrar Sesion</button>
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
        <?php if ($mensaje === 'Asistencia registrada correctamente.'): ?>
            <div style="margin:18px 0 0 0;padding:16px;border:2px solid #4CAF50;border-radius:8px;background:#e8f5e9;">
                <h3 style="color:#2e7d32;margin:0 0 8px 0;">¡Asistencia registrada!</h3>
                <ul style="list-style:none;padding:0;margin:0 0 0 0;">
                    <li><strong>Legajo:</strong> <?php echo htmlspecialchars($legajo); ?></li>
                    <li><strong>Nombre:</strong> <?php echo htmlspecialchars($nombreUsuario); ?></li>
                    <li><strong>Fecha:</strong> <?php echo date('d/m/Y'); ?></li>
                    <li><strong>Hora de Entrada:</strong> <?php echo date('H:i:s'); ?></li>
                    <li><strong>Estado:</strong> <span style="color:#2e7d32;font-weight:bold;">Presente</span></li>
                </ul>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Tarjetas superiores: Bienvenida y Registro de Asistencia -->
    <div class="cards-wrapper" style="display:flex;flex-direction:column;gap:16px;">
        <div class="card" style="border:1px solid #e6e6e6;border-radius:8px;padding:14px;display:flex;gap:12px;align-items:center;">
            <div style="flex:0 0 48px;">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="12" cy="8" r="4" fill="#1976D2" />
                    <path d="M4 20c0-4 4-6 8-6s8 2 8 6" fill="#1976D2" />
                </svg>
            </div>
            <div style="flex:1;">
                <p style="margin:0;font-weight:700;font-size:1.25rem;">Bienvenido, <?php echo htmlspecialchars($nombreUsuario); ?>!</p>
                <p style="margin:6px 0 0 0;color:#666;">E.P.E.T N&ordm; 20</p>
            </div>
        </div>

        <div class="card" style="border:1px solid #e6e6e6;border-radius:8px;padding:14px;">
            <form method="post" action="inicio.php">
                <h4 style="margin:0 0 10px 0;">Registro de Asistencia</h4>
                <label for="cargo">Seleccione su cargo:</label>
                <div style="margin:8px 0 12px 0;">
                    <select name="cargo" id="cargo" required style="padding:8px;width:100%;max-width:320px;">
                        <option value="">Seleccione un cargo...</option>
                        <?php
                        // Obtener los cargos del usuario desde la base de datos
                        try {
                            $stmtCargos = $pdo->prepare("SELECT c.id_cargo, c.Denominacion FROM cargo c INNER JOIN usuario u ON c.id_cargo = u.id_cargo WHERE u.legajo = ?");
                            $stmtCargos->execute([$legajo]);
                            $cargosUsuario = $stmtCargos->fetchAll(PDO::FETCH_ASSOC);
                            foreach ($cargosUsuario as $cargoItem) {
                                echo '<option value="' . htmlspecialchars($cargoItem['Denominacion']) . '">' . htmlspecialchars($cargoItem['Denominacion']) . '</option>';
                            }
                        } catch (PDOException $e) {
                            echo '<option value="">Error al cargar cargos</option>';
                        }
                        ?>
                    </select>
                </div>

                <div style="margin-bottom:12px;">
                    <strong>Estado actual:</strong>
                    <?php if ($yaRegistro): ?>
                        <span style="color:#2e7d32;display:inline-flex;align-items:center;gap:8px;"> 
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M20 6L9 17l-5-5" stroke="#2e7d32" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            Registrado hoy a las <?php echo htmlspecialchars($horaRegistrada); ?>
                            <?php if ($salidaPendiente): ?>
                                <span style="color:#c57f17;font-weight:600;">| Salida pendiente</span>
                            <?php else: ?>
                                <span style="color:#1f6d7a;font-weight:600;">| Salida: <?php echo htmlspecialchars($salidaRegistrada); ?></span>
                            <?php endif; ?>
                        </span>
                    <?php else: ?>
                        <span style="color:#666;">Ninguna asistencia registrada hoy.</span>
                    <?php endif; ?>
                </div>

                <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
                    <input type="submit" name="enviar" value="Registrar Asistencia" <?php echo $yaRegistro ? 'disabled' : ''; ?> style="background-color: #4CAF50; color: white; padding:10px 14px; border:none; border-radius:6px; cursor:pointer; <?php echo $yaRegistro ? 'opacity:0.6;cursor:not-allowed;' : ''; ?>">
                    <input type="submit" name="enviar_salida" value="Salida" <?php echo $salidaPendiente ? '' : 'disabled'; ?> style="background-color:#e53935;color:#fff;padding:10px 14px;border:none;border-radius:6px;cursor:pointer;<?php echo $salidaPendiente ? '' : 'opacity:0.6;cursor:not-allowed;'; ?>">
                </div>
            </form>
            <?php if ($yaRegistro): ?>
                <div style="margin-top:10px;">
                    <?php if ($salidaPendiente): ?>
                        <span style="color:#c57f17;font-weight:600;">Salida pendiente</span>
                    <?php else: ?>
                        <span style="color:#1f6d7a;font-weight:600;">Salida registrada a las <?php echo htmlspecialchars($salidaRegistrada); ?></span>
                    <?php endif; ?>
                </div>
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
    </script>
</body>
</html>




