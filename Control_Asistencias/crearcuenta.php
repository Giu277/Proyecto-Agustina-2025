<?php
session_start();
require_once 'Conexion.php';

$mensaje = '';
$horarioTieneIdCargo = false;

// Obtener cargos disponibles (solo existentes)
$cargosDisponibles = [];
try {
    $conexion = new Conexion();
    $pdo = $conexion->getConexion();
    $stmt = $pdo->query("SELECT `id_cargo`, `Denominacion` FROM `cargo` ORDER BY `Denominacion` ASC");
    $cargosDisponibles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($cargosDisponibles)) {
        $mensaje = 'No hay cargos disponibles en la base de datos.';
    }
    // Asegurar columna id_cargo en horario para vincular el horario elegido al cargo
    try {
        $check = $pdo->prepare("SHOW COLUMNS FROM `horario` LIKE 'id_cargo'");
        $check->execute();
        if ($check->rowCount() === 0) {
            $pdo->exec("ALTER TABLE `horario` ADD `id_cargo` INT NULL");
        }
        $horarioTieneIdCargo = true;
    } catch (PDOException $e) {
        error_log("Aviso: no se pudo asegurar columna id_cargo en horario: " . $e->getMessage());
    }
} catch (PDOException $e) {
    $mensaje = 'Error al obtener los cargos: ' . $e->getMessage();
    error_log("Error al obtener cargos: " . $e->getMessage());
}

// Procesar registro de nueva cuenta
if (isset($_POST['enviar']) && $_POST['enviar'] === 'Registrarse') {
    $nombreRegistro   = trim($_POST['nombreRegistro'] ?? '');
    $apellidoRegistro = trim($_POST['apellidoRegistro'] ?? '');
    $legajoRegistro   = trim($_POST['legajoRegistro'] ?? '');
    $contrasenia      = trim($_POST['contrasenia'] ?? '');
    $tipoRegistro     = $_POST['tipoRegistro'] ?? '';

    // Campos de cargos/materia
    $cargoRegistro = $_POST['cargoRegistro'] ?? [];
    if (!is_array($cargoRegistro)) $cargoRegistro = [$cargoRegistro];
    $materiaRegistro = trim($_POST['materiaRegistro'] ?? '');

    $horariosEntrada = $_POST['horario_entrada'] ?? [];
    $horariosSalida  = $_POST['horario_salida'] ?? [];

    if ($nombreRegistro && $apellidoRegistro && $legajoRegistro && $tipoRegistro && $contrasenia) {
        if (strlen($contrasenia) < 6) {
            $mensaje = 'La contraseña debe tener al menos 6 caracteres.';
        } elseif ($tipoRegistro === 'cargo' && count(array_filter($cargoRegistro)) === 0) {
            $mensaje = 'Por favor seleccione al menos un cargo.';
        } elseif ($tipoRegistro === 'materia' && $materiaRegistro === '') {
            $mensaje = 'Por favor ingrese la materia.';
        }

        if ($mensaje === '') {
            try {
                $conexion = new Conexion();
                $pdo = $conexion->getConexion();
                // Revalidar columna id_cargo en horario en esta conexion
                try {
                    $check = $pdo->prepare("SHOW COLUMNS FROM `horario` LIKE 'id_cargo'");
                    $check->execute();
                    if ($check->rowCount() === 0) {
                        $pdo->exec("ALTER TABLE `horario` ADD `id_cargo` INT NULL");
                    }
                    $horarioTieneIdCargo = true;
                } catch (PDOException $e) {
                    error_log("Aviso: no se pudo asegurar columna id_cargo en horario (registro): " . $e->getMessage());
                    $horarioTieneIdCargo = false;
                }

                // Asegurar columnas id_cargo y contrasenia en usuario
                try {
                    $check = $pdo->prepare("SHOW COLUMNS FROM `usuario` LIKE 'id_cargo'");
                    $check->execute();
                    if ($check->rowCount() === 0) {
                        $pdo->exec("ALTER TABLE `usuario` ADD `id_cargo` INT NULL");
                    }
                    $check = $pdo->prepare("SHOW COLUMNS FROM `usuario` LIKE 'contrasenia'");
                    $check->execute();
                    if ($check->rowCount() === 0) {
                        $pdo->exec("ALTER TABLE `usuario` ADD `contrasenia` VARCHAR(255) NULL");
                    }
                } catch (PDOException $e) {
                    error_log("Aviso: no se pudo asegurar columnas usuario: " . $e->getMessage());
                }

                $hashContrasenia = password_hash($contrasenia, PASSWORD_DEFAULT);

                // Validar legajo duplicado
                $stmt = $pdo->prepare("SELECT 1 FROM `usuario` WHERE `legajo` = ?");
                $stmt->execute([$legajoRegistro]);
                if ($stmt->fetchColumn()) {
                    $mensaje = 'El legajo ya está registrado. Por favor use otro legajo.';
                } else {
                    // Procesar cargos seleccionados (solo existentes)
                    $idCargoParaUsuario = null;
                    $cargosProcesados = [];
                    if ($tipoRegistro === 'cargo' && count(array_filter($cargoRegistro)) > 0) {
                        foreach ($cargoRegistro as $idx => $cargoSel) {
                            foreach ($cargosDisponibles as $c) {
                                if ((string)$c['id_cargo'] === (string)$cargoSel) {
                                    $idCargoActual = (int)$c['id_cargo'];
                                    $denominacionCargo = $c['Denominacion'];
                                    $cargosProcesados[] = ['id' => $idCargoActual, 'denominacion' => $denominacionCargo];
                                    if ($idCargoParaUsuario === null) {
                                        $idCargoParaUsuario = $idCargoActual;
                                    }
                                    break;
                                }
                            }
                        }
                    }

                    // Insertar usuario (con id_cargo si existe alguno seleccionado)
                    try {
                        $stmtUser = $pdo->prepare("INSERT INTO `usuario` (`legajo`, `nombre`, `apellido`, `contrasenia`, `id_cargo`) VALUES (?, ?, ?, ?, ?)");
                        $stmtUser->execute([$legajoRegistro, $nombreRegistro, $apellidoRegistro, $hashContrasenia, $idCargoParaUsuario]);
                    } catch (PDOException $e) {
                        $stmtUser = $pdo->prepare("INSERT INTO `usuario` (`legajo`, `nombre`, `apellido`, `contrasenia`) VALUES (?, ?, ?, ?)");
                        $stmtUser->execute([$legajoRegistro, $nombreRegistro, $apellidoRegistro, $hashContrasenia]);
                    }

                    // Guardar horarios para cada cargo (opcional)
                    if ($tipoRegistro === 'cargo' && !empty($cargosProcesados)) {
                        try {
                            if ($horarioTieneIdCargo) {
                                $stmtInsertHorario = $pdo->prepare("INSERT INTO `horario` (`Dia`, `Entrada`, `Salida`, `id_cargo`) VALUES (?, ?, ?, ?)");
                            } else {
                                $stmtInsertHorario = $pdo->prepare("INSERT INTO `horario` (`Dia`, `Entrada`, `Salida`) VALUES (?, ?, ?)");
                            }
                            foreach ($cargosProcesados as $idx => $cproc) {
                                $entradas = $_POST['horario_entrada'][$idx] ?? [];
                                $salidas  = $_POST['horario_salida'][$idx] ?? [];
                                if (!is_array($entradas) || !is_array($salidas)) continue;
                                for ($i = 0; $i < count($entradas); $i++) {
                                    $entrada = trim($entradas[$i] ?? '');
                                    $salida  = trim($salidas[$i] ?? '');
                                    if ($entrada !== '' && $salida !== '' && $entrada < $salida) {
                                        if ($horarioTieneIdCargo) {
                                            $stmtInsertHorario->execute(['0000-00-00', $entrada, $salida, $cproc['id']]);
                                        } else {
                                            $stmtInsertHorario->execute(['0000-00-00', $entrada, $salida]);
                                        }
                                    }
                                }
                            }
                        } catch (PDOException $e) {
                            error_log("Error al guardar horarios: " . $e->getMessage());
                        }
                    }

                    // Guardar materia si aplica
                    if ($tipoRegistro === 'materia' && $materiaRegistro !== '') {
                        try {
                            $stmt = $pdo->prepare("INSERT INTO usuario_materia (legajo, materia) VALUES (?, ?)");
                            $stmt->execute([$legajoRegistro, $materiaRegistro]);
                        } catch (PDOException $e) {
                            error_log("No se pudo guardar materia (tabla puede no existir): " . $e->getMessage());
                        }
                    }

                    $mensaje = 'Cuenta creada exitosamente. Ahora puede iniciar sesión.';
                    header('refresh:2;url=inicioSesion.php');
                }
            } catch (PDOException $e) {
                $mensaje = 'Error al crear la cuenta: ' . $e->getMessage();
                error_log("Error en registro: " . $e->getMessage());
            }
        }
    } else {
        $mensaje = 'Por favor complete todos los campos obligatorios.';
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Cuenta - Control de Asistencia Escolar</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="auth-page">
    <?php if (!empty($mensaje)): ?>
        <div class="mensaje <?php echo (strpos($mensaje, 'Error') !== false || strpos($mensaje, 'incorrecto') !== false || strpos($mensaje, 'no encontrado') !== false || strpos($mensaje, 'ya está registrado') !== false) ? 'error' : 'exito'; ?>">
            <?php echo htmlspecialchars($mensaje); ?>
        </div>
    <?php endif; ?>

    <div class="auth-card wide">
        <div class="auth-logo">
            <img src="Logo.epet" alt="Logo E.P.E.T" />
        </div>
        <h1>Crear cuenta</h1>
        <p class="auth-subtitle">Complete sus datos</p>

        <form method="post" action="crearcuenta.php" id="formRegistro" class="auth-form">
            <label for="nomReg">Nombre</label>
            <input type="text" name="nombreRegistro" id="nomReg" required>
            
            <label for="apeReg">Apellido</label>
            <input type="text" name="apellidoRegistro" id="apeReg" required>
            
            <label for="legajoReg">Legajo</label>
            <input type="text" name="legajoRegistro" id="legajoReg" required>

            <label for="passReg">Contraseña</label>
            <input type="password" name="contrasenia" id="passReg" required minlength="6" placeholder="Mínimo 6 caracteres">
            
            <label>¿Tiene cargo o materias?</label>
            <div class="radio-group">
                <label><input type="radio" name="tipoRegistro" value="cargo" required onchange="mostrarCampoCondicional()"> Cargo</label>
                <label><input type="radio" name="tipoRegistro" value="materia" required onchange="mostrarCampoCondicional()"> Materias</label>
            </div>
            
            <div id="campoCargo" class="campo-condicional">
                <label>Seleccione sus cargos (puede agregar más de uno):</label>
                <div id="cargosContainer"></div>
                <button type="button" class="btn-agregar-horario" onclick="agregarCargo()">+ Agregar otro cargo</button>
            </div>

            <div id="campoMateria" class="campo-condicional">
                <label for="materiaReg">Ingrese la materia:</label>
                <input type="text" name="materiaRegistro" id="materiaReg" placeholder="Ej: Matemática, Lengua, etc.">
            </div>

            <input type="submit" name="enviar" value="Registrarse" class="btn-primary">
            <p class="auth-footer">E.P.E.T Nº 20</p>
        </form>
        <div class="link-registro">
            <p><a href="inicioSesion.php">Volver al inicio de sesión</a></p>
        </div>
    </div>
    
    <script>
        const cargosDisponibles = <?php echo json_encode($cargosDisponibles); ?>;
        let cargoCount = 0;

        function buildOptionsHTML() {
            let s = '<option value="">Seleccione un cargo...</option>';
            cargosDisponibles.forEach(c => {
                s += `<option value="${c.id_cargo}">${c.Denominacion}</option>`;
            });
            return s;
        }

        function crearBloqueCargo(index) {
            const div = document.createElement('div');
            div.className = 'cargo-block horario-group';
            div.dataset.index = index;
            div.innerHTML = `
                <label>Cargo:</label>
                <select name="cargoRegistro[]" onchange="onCargoChange(${index}, this)">
                    ${buildOptionsHTML()}
                </select>
                <div id="horariosContainer_${index}" style="display:none; margin-top:10px;">
                    <label>Horarios:</label>
                    <div id="horariosList_${index}"></div>
                    <button type="button" class="btn-agregar-horario" onclick="agregarHorarioEnCargo(${index})">+ Agregar horario</button>
                    <button type="button" class="btn-eliminar-horario" style="background:#777;margin-left:10px;" onclick="eliminarCargo(${index})">Eliminar cargo</button>
                </div>
            `;
            return div;
        }

        function agregarCargo() {
            const container = document.getElementById('cargosContainer');
            const index = cargoCount++;
            const bloque = crearBloqueCargo(index);
            container.appendChild(bloque);
        }

        function eliminarCargo(index) {
            const container = document.getElementById('cargosContainer');
            const bloque = container.querySelector(`div.cargo-block[data-index="${index}"]`);
            if (bloque) bloque.remove();
        }

        function onCargoChange(index, selectElem) {
            const val = selectElem.value;
            const horariosCont = document.getElementById('horariosContainer_' + index);
            const horariosList = document.getElementById('horariosList_' + index);
            if (val === '') {
                horariosCont.style.display = 'none';
                horariosList.innerHTML = '';
            } else {
                horariosCont.style.display = 'block';
                horariosList.innerHTML = '';
                agregarHorarioEnCargo(index);
            }
        }

        function agregarHorarioEnCargo(index) {
            const list = document.getElementById('horariosList_' + index);
            if (!list) return;
            const row = document.createElement('div');
            row.className = 'horario-row';
            row.innerHTML = `
                <label>Entrada: <input type="time" name="horario_entrada[${index}][]" required></label>
                <label>Salida: <input type="time" name="horario_salida[${index}][]" required></label>
                <button type="button" class="btn-eliminar-horario" onclick="this.parentNode.remove()">Eliminar</button>
            `;
            list.appendChild(row);
        }

        function mostrarCampoCondicional() {
            const tipoCargo = document.querySelector('input[name="tipoRegistro"][value="cargo"]').checked;
            const campoCargo = document.getElementById('campoCargo');
            const campoMateria = document.getElementById('campoMateria');
            if (tipoCargo) {
                campoCargo.classList.add('mostrar');
                campoMateria.classList.remove('mostrar');
                if (document.getElementById('cargosContainer').children.length === 0) agregarCargo();
            } else {
                campoCargo.classList.remove('mostrar');
                campoMateria.classList.add('mostrar');
            }
        }

        document.getElementById('formRegistro').addEventListener('submit', function(e) {
            const passInput = document.getElementById('passReg');
            if (!passInput.value || passInput.value.length < 6) {
                e.preventDefault(); alert('La contraseña es obligatoria y debe tener al menos 6 caracteres.'); return false;
            }
            const esCargo = document.querySelector('input[name="tipoRegistro"][value="cargo"]').checked;
            if (esCargo) {
                const bloques = document.querySelectorAll('#cargosContainer .cargo-block');
                if (!bloques || bloques.length === 0) { e.preventDefault(); alert('Agregue al menos un cargo'); return false; }
                for (let b of bloques) {
                    const idx = b.dataset.index;
                    const sel = b.querySelector('select');
                    if (!sel || sel.value === '') { e.preventDefault(); alert('Seleccione un cargo en todos los bloques'); return false; }
                    const entradas = b.querySelectorAll(`input[name^="horario_entrada[${idx}]"]`);
                    const salidas  = b.querySelectorAll(`input[name^="horario_salida[${idx}]"]`);
                    if (entradas.length === 0) { e.preventDefault(); alert('Agregue al menos un horario por cargo'); return false; }
                    for (let i = 0; i < entradas.length; i++) {
                        if (!entradas[i].value || !salidas[i].value) { e.preventDefault(); alert('Complete todos los horarios'); return false; }
                        if (entradas[i].value >= salidas[i].value) { e.preventDefault(); alert('La hora de entrada debe ser menor que la de salida'); return false; }
                    }
                }
            }
        });

        document.addEventListener('DOMContentLoaded', mostrarCampoCondicional);
    </script>
</body>
</html>
