<?php
session_start();
require_once 'Conexion.php';

$mensaje = '';

// Obtener cargos disponibles
$cargosDisponibles = [];
try {
    $conexion = new Conexion();
    $pdo = $conexion->getConexion();
    
    // Consulta para obtener todos los cargos ordenados alfabéticamente
    $stmt = $pdo->query("SELECT `id_cargo`, `Denominacion`, `Entrada`, `Salida` 
                        FROM `cargo` 
                        ORDER BY `Denominacion` ASC");
    $cargosDisponibles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($cargosDisponibles)) {
        $mensaje = 'No hay cargos disponibles en la base de datos.';
    }
} catch (PDOException $e) {
    $mensaje = 'Error al obtener los cargos: ' . $e->getMessage();
    // Log del error para debugging
    error_log("Error al obtener cargos: " . $e->getMessage());
}

// Procesar registro de nueva cuenta
if (isset($_POST['enviar']) && $_POST['enviar'] == 'Registrarse') {
    $nombreRegistro = trim($_POST['nombreRegistro'] ?? '');
    $apellidoRegistro = trim($_POST['apellidoRegistro'] ?? '');
    $legajoRegistro = trim($_POST['legajoRegistro'] ?? '');
    $contrasenia = trim($_POST['contrasenia'] ?? '');
    $tipoRegistro = $_POST['tipoRegistro'] ?? '';
    // Ahora soportamos múltiples cargos: cargoRegistro[] y cargoOtro[]
    $cargoRegistro = $_POST['cargoRegistro'] ?? [];
    if (!is_array($cargoRegistro)) $cargoRegistro = [$cargoRegistro];
    $cargoOtro = $_POST['cargoOtro'] ?? [];
    if (!is_array($cargoOtro)) $cargoOtro = [$cargoOtro];
    $materiaRegistro = trim($_POST['materiaRegistro'] ?? '');
    $horariosEntrada = $_POST['horario_entrada'] ?? [];
    $horariosSalida = $_POST['horario_salida'] ?? [];
    
    if (!empty($nombreRegistro) && !empty($apellidoRegistro) && !empty($legajoRegistro) && !empty($tipoRegistro) && !empty($contrasenia)) {
        if (strlen($contrasenia) < 6) {
            $mensaje = 'La contraseña debe tener al menos 6 caracteres.';
        } elseif ($tipoRegistro == 'cargo' && (empty($cargoRegistro) || count(array_filter($cargoRegistro)) == 0)) {
            $mensaje = 'Por favor seleccione al menos un cargo.';
        } elseif ($tipoRegistro == 'cargo' && in_array('otros', $cargoRegistro)) {
            // verificar que para cada 'otros' exista un cargoOtro correspondiente
            $okOtros = true;
            foreach ($cargoRegistro as $idx => $c) {
                if ($c === 'otros') {
                    $texto = trim($cargoOtro[$idx] ?? '');
                    if ($texto === '') { $okOtros = false; break; }
                }
            }
            if (!$okOtros) {
                $mensaje = 'Por favor ingrese el nombre del cargo para la opción "Otros".';
            }
        } elseif ($tipoRegistro == 'cargo' && (empty($horariosEntrada) || empty($horariosSalida))) {
            // horarios serán validados por cargo en el procesamiento posterior
            // aquí solo comprobamos que exista al menos alguna entrada
            // (no bloquearamos aún)
            ;
        } elseif ($tipoRegistro == 'materia' && empty($materiaRegistro)) {
            $mensaje = 'Por favor ingrese la materia.';
        } else {
            try {
                $conexion = new Conexion();
                $pdo = $conexion->getConexion();

                // Asegurar que las columnas necesarias existan en la tabla usuario
                try {
                    // Verificar y crear id_cargo si falta
                    $check = $pdo->prepare("SHOW COLUMNS FROM `usuario` LIKE 'id_cargo'");
                    $check->execute();
                    if ($check->rowCount() === 0) {
                        $pdo->exec("ALTER TABLE `usuario` ADD `id_cargo` INT NULL");
                    }

                    // Verificar y crear contrasenia si falta
                    $check = $pdo->prepare("SHOW COLUMNS FROM `usuario` LIKE 'contrasenia'");
                    $check->execute();
                    if ($check->rowCount() === 0) {
                        $pdo->exec("ALTER TABLE `usuario` ADD `contrasenia` VARCHAR(255) NULL");
                    }
                } catch (PDOException $e) {
                    // Si falla la alteración, no detener el flujo; las inserciones tienen fallback
                    error_log("Aviso: no se pudo asegurar columnas usuario: " . $e->getMessage());
                }
                
                // hashear la contraseña
                $hashContrasenia = password_hash($contrasenia, PASSWORD_DEFAULT);
                
                // Verificar si el legajo ya existe en la tabla usuario
                $stmt = $pdo->prepare("SELECT `legajo` FROM `usuario` WHERE `legajo` = ?");
                $stmt->execute([$legajoRegistro]);
                $legajoExiste = ($stmt->rowCount() > 0);
                
                if ($legajoExiste) {
                    $mensaje = 'El legajo ya está registrado. Por favor use otro legajo.';
                } else {
                    // Verificar que el cargo existe si se seleccionó uno
                    $idCargo = null;
                    if ($tipoRegistro == 'cargo' && !empty($cargoRegistro)) {
                        // Obtener el nombre del cargo seleccionado
                        $denominacionCargo = null;
                        
                        if ($cargoRegistro == 'otros') {
                            // Si seleccionó "Otros...", usar el cargo escrito
                            $denominacionCargo = $cargoOtro;
                        } else {
                            // Verificar y procesar múltiples cargos si corresponde
                            $idCargoParaUsuario = null;
                            $cargosProcesados = [];
                            if ($tipoRegistro == 'cargo' && !empty($cargoRegistro) && count(array_filter($cargoRegistro)) > 0) {
                                // iterar sobre cada cargo seleccionado
                                foreach ($cargoRegistro as $idx => $cargoSel) {
                                    $denominacionCargo = null;
                                    if ($cargoSel === 'otros') {
                                        $denominacionCargo = trim($cargoOtro[$idx] ?? '');
                                    } else {
                                        // buscar en lista de cargos disponibles por id
                                        foreach ($cargosDisponibles as $c) {
                                            if ((string)$c['id_cargo'] === (string)$cargoSel) {
                                                $denominacionCargo = $c['Denominacion'];
                                                break;
                                            }
                                        }
                                    }

                                    if (empty($denominacionCargo)) {
                                        // Omite cargos inválidos
                                        continue;
                                    }

                                    // Obtener o crear id_cargo en tabla cargo
                                    try {
                                        $stmt = $pdo->prepare("SELECT `id_cargo` FROM `cargo` WHERE `Denominacion` = ?");
                                        $stmt->execute([$denominacionCargo]);
                                        $cargoExiste = $stmt->fetch(PDO::FETCH_ASSOC);
                                    } catch (PDOException $e) {
                                        $cargoExiste = false;
                                    }

                                    if ($cargoExiste && !empty($cargoExiste['id_cargo'])) {
                                        $idCargoActual = (int)$cargoExiste['id_cargo'];
                                    } else {
                                        try {
                                            $stmt = $pdo->prepare("INSERT INTO `cargo` (`Denominacion`) VALUES (?)");
                                            $stmt->execute([$denominacionCargo]);
                                            $idCargoActual = (int)$pdo->lastInsertId();
                                        } catch (PDOException $e) {
                                            try {
                                                $pdo->exec("CREATE TABLE IF NOT EXISTS `cargo` (
                                                    `id_cargo` INT AUTO_INCREMENT PRIMARY KEY,
                                                    `Denominacion` VARCHAR(255) NOT NULL
                                                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
                                                $stmt = $pdo->prepare("INSERT INTO `cargo` (`Denominacion`) VALUES (?)");
                                                $stmt->execute([$denominacionCargo]);
                                                $idCargoActual = (int)$pdo->lastInsertId();
                                            } catch (PDOException $e2) {
                                                error_log("Error al crear/insertar en tabla cargo: " . $e2->getMessage());
                                                $idCargoActual = null;
                                            }
                                        }
                                    }

                                    $cargosProcesados[] = ['id' => $idCargoActual, 'denominacion' => $denominacionCargo];
                                    // Usar el primer cargo válido para el campo id_cargo del usuario
                                    if ($idCargoParaUsuario === null && $idCargoActual !== null) {
                                        $idCargoParaUsuario = $idCargoActual;
                                    }
                                }
                            }

                            // Insertar nuevo usuario en la tabla usuario (guardar contrasenia y primer id_cargo si existe)
                            try {
                                $stmt = $pdo->prepare("INSERT INTO `usuario` (`legajo`, `nombre`, `apellido`, `contrasenia`, `id_cargo`) VALUES (?, ?, ?, ?, ?)");
                                $stmt->execute([$legajoRegistro, $nombreRegistro, $apellidoRegistro, $hashContrasenia, $idCargoParaUsuario]);
                            } catch (PDOException $e) {
                                // Intentar sin id_cargo si falla
                                try {
                                    $stmt = $pdo->prepare("INSERT INTO `usuario` (`legajo`, `nombre`, `apellido`, `contrasenia`) VALUES (?, ?, ?, ?)");
                                    $stmt->execute([$legajoRegistro, $nombreRegistro, $apellidoRegistro, $hashContrasenia]);
                                } catch (PDOException $e2) {
                                    // Si falla la inserción con id_cargo, intentamos sin ese campo
                                    throw $e2;
                                }
                            }

                            // El legajo es el identificador principal
                            $legajoParaHorarios = $legajoRegistro;

                            // Guardar horarios por cada cargo (las entradas/salidas vienen como arreglos por cargo index)
                            if ($tipoRegistro == 'cargo' && !empty($cargosProcesados)) {
                                try {
                                    // Insertar horarios en la tabla horarios
                                    $stmtInsertHorario = $pdo->prepare("INSERT INTO `horarios` (`legajo`, `hora_entrada`, `hora_salida`) VALUES (?, ?, ?)");
                                    foreach ($cargosProcesados as $idx => $cproc) {
                                        $entradasPorCargo = $_POST['horario_entrada'][$idx] ?? [];
                                        $salidasPorCargo = $_POST['horario_salida'][$idx] ?? [];
                                        if (!is_array($entradasPorCargo) || !is_array($salidasPorCargo)) continue;
                                        for ($j = 0; $j < count($entradasPorCargo); $j++) {
                                            $entrada = trim($entradasPorCargo[$j]);
                                            $salida = trim($salidasPorCargo[$j] ?? '');
                                            if (!empty($entrada) && !empty($salida) && $entrada < $salida) {
                                                $stmtInsertHorario->execute([$legajoParaHorarios, $entrada, $salida]);
                                            }
                                        }
                                    }
                                } catch (PDOException $e) {
                                    error_log("Error al guardar horarios (tabla puede no existir): " . $e->getMessage());
                                }
                            }

                            $mensaje = 'Cuenta creada exitosamente. Ahora puede iniciar sesión.';
                            // Redirigir después de 2 segundos
                            header("refresh:2;url=inicioSesion.php");
                        }

                        // Si tiene materia, guardarla (asumiendo que existe una tabla usuario_materia o campo)
                        if ($tipoRegistro == 'materia' && !empty($materiaRegistro)) {
                            // Aquí se puede agregar la lógica para guardar la materia
                            // Ejemplo si existe tabla usuario_materia:
                            try {
                                $stmt = $pdo->prepare("INSERT INTO usuario_materia (legajo, materia) VALUES (?, ?)");
                                $stmt->execute([$legajoRegistro, $materiaRegistro]);
                            } catch (PDOException $e) {
                                // Si falla porque la tabla no existe, registrar error
                                error_log("No se pudo guardar materia (tabla puede no existir): " . $e->getMessage());
                            }
                        }
                        
                        $mensaje = 'Cuenta creada exitosamente. Ahora puede iniciar sesión.';
                        // Redirigir después de 2 segundos
                        header("refresh:2;url=inicioSesion.php");
                    }
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
        input[type="radio"] {
            margin-right: 5px;
            margin-top: 10px;
        }
        .radio-group {
            margin: 10px 0;
        }
        .radio-group label {
            display: inline-block;
            margin-right: 15px;
            margin-top: 0;
        }
        .campo-condicional {
            display: none;
            margin-top: 10px;
        }
        .campo-condicional.mostrar {
            display: block;
        }
        .horario-group {
            margin: 15px 0;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background-color: #f9f9f9;
        }
        .horario-row {
            display: flex;
            gap: 10px;
            align-items: flex-end;
            margin-bottom: 10px;
        }
        .horario-row label {
            flex: 1;
            margin-top: 0;
        }
        .horario-row input[type="time"] {
            flex: 1;
            margin-top: 0;
        }
        .btn-agregar-horario {
            background-color: #2196F3;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            margin-top: 10px;
        }
        .btn-agregar-horario:hover {
            background-color: #1976D2;
        }
        .btn-eliminar-horario {
            background-color: #f44336;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }
        .btn-eliminar-horario:hover {
            background-color: #d32f2f;
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
    </style>
</head>
<body>
    <?php if (!empty($mensaje)): ?>
        <div class="mensaje <?php echo (strpos($mensaje, 'Error') !== false || strpos($mensaje, 'incorrecto') !== false || strpos($mensaje, 'no encontrado') !== false || strpos($mensaje, 'ya está registrado') !== false) ? 'error' : 'exito'; ?>">
            <?php echo htmlspecialchars($mensaje); ?>
        </div>
    <?php endif; ?>

    <!-- Formulario de Registro -->
    <form method="post" action="crearcuenta.php" id="formRegistro">
        <fieldset>
            <legend>Crear Nueva Cuenta</legend>
            <p>Complete sus datos:</p>
            
            <label for="nomReg">Nombre</label>
            <input type="text" name="nombreRegistro" id="nomReg" required>
            
            <label for="apeReg">Apellido</label>
            <input type="text" name="apellidoRegistro" id="apeReg" required>
            
            <label for="legajoReg">Legajo</label>
            <input type="text" name="legajoRegistro" id="legajoReg" required>

            <!-- NUEVO: campo de contraseña -->
            <label for="passReg">Contraseña</label>
            <input type="password" name="contrasenia" id="passReg" required minlength="6" placeholder="Mínimo 6 caracteres">
            
            <label>¿Tiene cargo o materias?</label>
            <div class="radio-group">
                <label>
                    <input type="radio" name="tipoRegistro" value="cargo" required onchange="mostrarCampoCondicional()">
                    Cargo
                </label>
                <label>
                    <input type="radio" name="tipoRegistro" value="materia" required onchange="mostrarCampoCondicional()">
                    Materias
                </label>
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

            <input type="submit" name="enviar" value="Registrarse">
            <p>E.P.E.T Nº 20</p>
        </fieldset>
    </form>
    <div class="link-registro">
        <p><a href="inicioSesion.php">Volver al inicio de sesión</a></p>
    </div>
    
    <script>
        const cargosDisponibles = <?php echo json_encode($cargosDisponibles); ?>;
        let cargoCount = 0;

        function buildOptionsHTML() {
            let s = '<option value="">Seleccione un cargo...</option>';
            cargosDisponibles.forEach(c => {
                const entrada = c.Entrada ? c.Entrada : '';
                const salida = c.Salida ? c.Salida : '';
                s += `<option value="${c.id_cargo}" data-entrada="${entrada}" data-salida="${salida}">${c.Denominacion}</option>`;
            });
            s += '<option value="otros">Otros...</option>';
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
                <div class="campo-condicional" id="cargoOtro_${index}" style="display:none; margin-top:8px;">
                    <label>Ingrese el cargo:</label>
                    <input type="text" name="cargoOtro[]" placeholder="Escriba el nombre del cargo">
                </div>
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
            const otroDiv = document.getElementById('cargoOtro_' + index);
            const horariosCont = document.getElementById('horariosContainer_' + index);
            const horariosList = document.getElementById('horariosList_' + index);
            if (val === 'otros') {
                otroDiv.style.display = 'block';
                horariosCont.style.display = 'block';
                // agregar un horario inicial si no hay
                if (horariosList.children.length === 0) agregarHorarioEnCargo(index);
            } else if (val === '') {
                otroDiv.style.display = 'none';
                horariosCont.style.display = 'none';
                horariosList.innerHTML = '';
            } else {
                otroDiv.style.display = 'none';
                horariosCont.style.display = 'block';
                // auto-rellenar si cargo tiene entrada/salida
                const opt = selectElem.options[selectElem.selectedIndex];
                const ent = opt.getAttribute('data-entrada') || '';
                const sal = opt.getAttribute('data-salida') || '';
                horariosList.innerHTML = '';
                agregarHorarioEnCargo(index);
                const eInput = horariosList.querySelector('input[name="horario_entrada['+index+'][]"]');
                const sInput = horariosList.querySelector('input[name="horario_salida['+index+'][]"]');
                if (eInput && sInput && ent && sal) {
                    eInput.value = ent.substring(0,5);
                    sInput.value = sal.substring(0,5);
                }
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
                // si no hay cargos agregados, agregar uno
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
                    if (!sel) continue;
                    if (sel.value === '') { e.preventDefault(); alert('Seleccione un cargo en todos los bloques'); return false; }
                    if (sel.value === 'otros') {
                        const txt = b.querySelector('#cargoOtro_' + idx + ' input');
                        if (!txt || txt.value.trim() === '') { e.preventDefault(); alert('Complete el nombre del cargo para la opción Otros'); return false; }
                    }
                    const entradas = b.querySelectorAll('input[name^="horario_entrada['+idx+']"]');
                    const salidas = b.querySelectorAll('input[name^="horario_salida['+idx+']"]');
                    if (entradas.length === 0) { e.preventDefault(); alert('Agregue al menos un horario por cargo'); return false; }
                    for (let i=0;i<entradas.length;i++) {
                        if (!entradas[i].value || !salidas[i].value) { e.preventDefault(); alert('Complete todos los horarios'); return false; }
                        if (entradas[i].value >= salidas[i].value) { e.preventDefault(); alert('La hora de entrada debe ser menor que la de salida'); return false; }
                    }
                }
            }
        });

        // Inicializar
        document.addEventListener('DOMContentLoaded', function(){
            // Si ya se eligió tipo cargo en el formulario (caso de reload), respetar
            mostrarCampoCondicional();
        });
    </script>
</body>
</html>

