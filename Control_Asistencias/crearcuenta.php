<?php
session_start();
require_once 'Conexion.php';

$mensaje = '';

// Obtener cargos disponibles
$cargosDisponibles = [];
try {
    $conexion = new Conexion();
    $pdo = $conexion->getConexion();
    $stmt = $pdo->query("SELECT `id_cargo`, `Denominacion` FROM `cargo` ORDER BY `Denominacion`");
    $cargosDisponibles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Si no existe la tabla cargo, usar valores por defecto
    $cargosDisponibles = [
        ['id_cargo' => 1, 'Denominacion' => 'Preceptor'],
        ['id_cargo' => 2, 'Denominacion' => 'Secretario'],
        ['id_cargo' => 3, 'Denominacion' => 'Subsecretario'],
        ['id_cargo' => 4, 'Denominacion' => 'Jefe de preceptores']
    ];
}

// Procesar registro de nueva cuenta
if (isset($_POST['enviar']) && $_POST['enviar'] == 'Registrarse') {
    $nombreRegistro = trim($_POST['nombreRegistro'] ?? '');
    $apellidoRegistro = trim($_POST['apellidoRegistro'] ?? '');
    $legajoRegistro = trim($_POST['legajoRegistro'] ?? '');
    $contrasenia = trim($_POST['contrasenia'] ?? '');
    $tipoRegistro = $_POST['tipoRegistro'] ?? '';
    $cargoRegistro = $_POST['cargoRegistro'] ?? '';
    $cargoOtro = trim($_POST['cargoOtro'] ?? '');
    $materiaRegistro = trim($_POST['materiaRegistro'] ?? '');
    $horariosEntrada = $_POST['horario_entrada'] ?? [];
    $horariosSalida = $_POST['horario_salida'] ?? [];
    
    if (!empty($nombreRegistro) && !empty($apellidoRegistro) && !empty($legajoRegistro) && !empty($tipoRegistro) && !empty($contrasenia)) {
        if (strlen($contrasenia) < 6) {
            $mensaje = 'La contraseña debe tener al menos 6 caracteres.';
        } elseif ($tipoRegistro == 'cargo' && empty($cargoRegistro)) {
            $mensaje = 'Por favor seleccione un cargo.';
        } elseif ($tipoRegistro == 'cargo' && $cargoRegistro == 'otros' && empty($cargoOtro)) {
            $mensaje = 'Por favor ingrese el nombre del cargo.';
        } elseif ($tipoRegistro == 'cargo' && (empty($horariosEntrada) || empty($horariosSalida))) {
            $mensaje = 'Por favor ingrese al menos un horario de entrada y salida.';
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
                            // Buscar el cargo en la lista de cargos disponibles
                            foreach ($cargosDisponibles as $c) {
                                if ($c['id_cargo'] == $cargoRegistro) {
                                    $denominacionCargo = $c['Denominacion'];
                                    break;
                                }
                            }
                        }
                        
                        if (empty($denominacionCargo)) {
                            $mensaje = 'Por favor seleccione o ingrese un cargo válido.';
                        } else {
                            // Intentar obtener id_cargo desde la tabla cargo; si la tabla no existe, manejarlo
                            try {
                                $stmt = $pdo->prepare("SELECT `id_cargo` FROM `cargo` WHERE `Denominacion` = ?");
                                $stmt->execute([$denominacionCargo]);
                                $cargoExiste = $stmt->fetch(PDO::FETCH_ASSOC);
                            } catch (PDOException $e) {
                                // La tabla cargo puede no existir; marcar como no existente para crearla luego
                                $cargoExiste = false;
                            }
                            
                            if ($cargoExiste && !empty($cargoExiste['id_cargo'])) {
                                // Si existe, usar el id_cargo existente
                                $idCargo = (int)$cargoExiste['id_cargo'];
                            } else {
                                // Si no existe, crear el cargo en la tabla cargo.
                                // Si la tabla no existe, intentar crearla y reintentar la inserción.
                                try {
                                    $stmt = $pdo->prepare("INSERT INTO `cargo` (`Denominacion`) VALUES (?)");
                                    $stmt->execute([$denominacionCargo]);
                                    $idCargo = (int)$pdo->lastInsertId();
                                } catch (PDOException $e) {
                                    // Si falla porque la tabla no existe, crear la tabla y reintentar
                                    try {
                                        $pdo->exec("CREATE TABLE IF NOT EXISTS `cargo` (
                                            `id_cargo` INT AUTO_INCREMENT PRIMARY KEY,
                                            `Denominacion` VARCHAR(255) NOT NULL
                                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
                                        
                                        $stmt = $pdo->prepare("INSERT INTO `cargo` (`Denominacion`) VALUES (?)");
                                        $stmt->execute([$denominacionCargo]);
                                        $idCargo = (int)$pdo->lastInsertId();
                                    } catch (PDOException $e2) {
                                        // Si aún falla, registrar error y continuar sin id_cargo (se puede optar por abortar)
                                        error_log("Error al crear/insertar en tabla cargo: " . $e2->getMessage());
                                        $idCargo = null;
                                    }
                                }
                            }
                            
                            // Asegurar tipo correcto: entero o null
                            $idCargo = !empty($idCargo) ? (int)$idCargo : null;
                            
                            // Insertar nuevo usuario en la tabla usuario (guardando contrasenia y id_cargo puede ser NULL)
                            $stmt = $pdo->prepare("INSERT INTO `usuario` (`legajo`, `nombre`, `apellido`, `contrasenia`, `id_cargo`) VALUES (?, ?, ?, ?, ?)");
                            $stmt->execute([$legajoRegistro, $nombreRegistro, $apellidoRegistro, $hashContrasenia, $idCargo]);
                            
                            // El legajo es el identificador principal
                            $legajoParaHorarios = $legajoRegistro;
                            
                            // Guardar horarios si tiene cargo
                            if (!empty($horariosEntrada) && !empty($horariosSalida)) {
                                // Validar que los horarios de entrada y salida tengan la misma cantidad
                                if (count($horariosEntrada) === count($horariosSalida)) {
                                    try {
                                        // Insertar horarios en la tabla horarios
                                        $stmt = $pdo->prepare("INSERT INTO `horarios` (`legajo`, `hora_entrada`, `hora_salida`) VALUES (?, ?, ?)");
                                        
                                        for ($i = 0; $i < count($horariosEntrada); $i++) {
                                            $entrada = trim($horariosEntrada[$i]);
                                            $salida = trim($horariosSalida[$i]);
                                            
                                            if (!empty($entrada) && !empty($salida)) {
                                                // Verificar que la hora de entrada sea menor que la de salida
                                                if ($entrada < $salida) {
                                                    $stmt->execute([$legajoParaHorarios, $entrada, $salida]);
                                                }
                                            }
                                        }
                                    } catch (PDOException $e) {
                                        // Si la tabla horarios no existe, intentar crearla o registrar error
                                        error_log("Error al guardar horarios (tabla puede no existir): " . $e->getMessage());
                                    }
                                }
                            }
                            
                            $mensaje = 'Cuenta creada exitosamente. Ahora puede iniciar sesión.';
                            // Redirigir después de 2 segundos
                            header("refresh:2;url=index.php");
                        }
                    } else {
                        // Si no tiene cargo, insertar usuario sin id_cargo (NULL) pero guardando contrasenia
                        try {
                            $stmt = $pdo->prepare("INSERT INTO `usuario` (`legajo`, `nombre`, `apellido`, `contrasenia`, `id_cargo`) VALUES (?, ?, ?, ?, ?)");
                            $stmt->execute([$legajoRegistro, $nombreRegistro, $apellidoRegistro, $hashContrasenia, null]);
                        } catch (PDOException $e) {
                            // Si la tabla usuario no tiene la columna id_cargo (esquema antiguo), intentar insertar sin ese campo
                            try {
                                $stmt = $pdo->prepare("INSERT INTO `usuario` (`legajo`, `nombre`, `apellido`, `contrasenia`) VALUES (?, ?, ?, ?)");
                                $stmt->execute([$legajoRegistro, $nombreRegistro, $apellidoRegistro, $hashContrasenia]);
                            } catch (PDOException $e2) {
                                // Error al insertar usuario
                                throw $e2;
                            }
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
                        header("refresh:2;url=index.php");
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
                <label for="cargoReg">Seleccione su cargo:</label>
                <select name="cargoRegistro" id="cargoReg" onchange="mostrarHorarios()">
                    <option value="">Seleccione un cargo...</option>
                    <?php if (!empty($cargosDisponibles)): ?>
                        <?php foreach ($cargosDisponibles as $cargo): ?>
                            <option value="<?php echo htmlspecialchars($cargo['id_cargo']); ?>">
                                <?php echo htmlspecialchars($cargo['Denominacion']); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <option value="otros">Otros...</option>
                </select>
                
                <div id="campoCargoOtro" style="display: none; margin-top: 10px;">
                    <label for="cargoOtro">Ingrese el cargo:</label>
                    <input type="text" name="cargoOtro" id="cargoOtro" placeholder="Escriba el nombre del cargo">
                </div>
                
                <div id="horariosContainer" style="display: none; margin-top: 15px;">
                    <label>Horarios de trabajo:</label>
                    <div id="horariosList">
                        <!-- Los horarios se agregarán dinámicamente aquí -->
                    </div>
                    <button type="button" class="btn-agregar-horario" onclick="agregarHorario()">+ Agregar otro horario</button>
                </div>
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
        <p><a href="index.php">Volver al inicio de sesión</a></p>
    </div>
    
    <script>
        let contadorHorarios = 0;
        
        function mostrarCampoCondicional() {
            const tipoCargo = document.querySelector('input[name="tipoRegistro"][value="cargo"]').checked;
            const tipoMateria = document.querySelector('input[name="tipoRegistro"][value="materia"]').checked;
            
            const campoCargo = document.getElementById('campoCargo');
            const campoMateria = document.getElementById('campoMateria');
            const cargoSelect = document.getElementById('cargoReg');
            const materiaInput = document.getElementById('materiaReg');
            const horariosContainer = document.getElementById('horariosContainer');
            
            if (tipoCargo) {
                campoCargo.classList.add('mostrar');
                campoMateria.classList.remove('mostrar');
                cargoSelect.setAttribute('required', 'required');
                materiaInput.removeAttribute('required');
                materiaInput.value = '';
                // Mostrar horarios si ya hay un cargo seleccionado
                mostrarHorarios();
            } else if (tipoMateria) {
                campoMateria.classList.add('mostrar');
                campoCargo.classList.remove('mostrar');
                materiaInput.setAttribute('required', 'required');
                cargoSelect.removeAttribute('required');
                cargoSelect.value = '';
                horariosContainer.style.display = 'none';
                // Limpiar horarios
                document.getElementById('horariosList').innerHTML = '';
                contadorHorarios = 0;
            } else {
                campoCargo.classList.remove('mostrar');
                campoMateria.classList.remove('mostrar');
                cargoSelect.removeAttribute('required');
                materiaInput.removeAttribute('required');
                horariosContainer.style.display = 'none';
                document.getElementById('horariosList').innerHTML = '';
                contadorHorarios = 0;
            }
        }
        
        function mostrarHorarios() {
            const cargoSelect = document.getElementById('cargoReg');
            const horariosContainer = document.getElementById('horariosContainer');
            const campoCargoOtro = document.getElementById('campoCargoOtro');
            const cargoOtroInput = document.getElementById('cargoOtro');
            const tipoCargo = document.querySelector('input[name="tipoRegistro"][value="cargo"]').checked;
            
            // Mostrar/ocultar campo para escribir cargo personalizado
            if (cargoSelect.value === 'otros') {
                campoCargoOtro.style.display = 'block';
                cargoOtroInput.setAttribute('required', 'required');
            } else {
                campoCargoOtro.style.display = 'none';
                cargoOtroInput.removeAttribute('required');
                cargoOtroInput.value = '';
            }
            
            if (tipoCargo && cargoSelect.value !== '' && cargoSelect.value !== 'otros') {
                horariosContainer.style.display = 'block';
                // Si no hay horarios, agregar uno inicial
                if (contadorHorarios === 0) {
                    agregarHorario();
                }
            } else if (tipoCargo && cargoSelect.value === 'otros' && cargoOtroInput.value !== '') {
                horariosContainer.style.display = 'block';
                // Si no hay horarios, agregar uno inicial
                if (contadorHorarios === 0) {
                    agregarHorario();
                }
            } else {
                horariosContainer.style.display = 'none';
            }
        }
        
        // Mostrar campo de cargo personalizado cuando se escribe
        document.getElementById('cargoOtro').addEventListener('input', function() {
            mostrarHorarios();
        });
        
        function agregarHorario() {
            contadorHorarios++;
            const horariosList = document.getElementById('horariosList');
            
            const horarioDiv = document.createElement('div');
            horarioDiv.className = 'horario-group';
            horarioDiv.id = 'horario_' + contadorHorarios;
            
            horarioDiv.innerHTML = `
                <div class="horario-row">
                    <label>
                        Horario de entrada ${contadorHorarios > 1 ? contadorHorarios : ''}:
                        <input type="time" name="horario_entrada[]" required>
                    </label>
                    <label>
                        Horario de salida ${contadorHorarios > 1 ? contadorHorarios : ''}:
                        <input type="time" name="horario_salida[]" required>
                    </label>
                    ${contadorHorarios > 1 ? '<button type="button" class="btn-eliminar-horario" onclick="eliminarHorario(' + contadorHorarios + ')">Eliminar</button>' : ''}
                </div>
            `;
            
            horariosList.appendChild(horarioDiv);
        }
        
        function eliminarHorario(id) {
            const horariosList = document.getElementById('horariosList');
            const horarioDiv = document.getElementById('horario_' + id);
            
            // No permitir eliminar si solo queda un horario
            if (horariosList.children.length <= 1) {
                alert('Debe tener al menos un horario de entrada y salida.');
                return;
            }
            
            if (horarioDiv) {
                horarioDiv.remove();
            }
        }
        
        // Validar formulario antes de enviar
        document.getElementById('formRegistro').addEventListener('submit', function(e) {
            const tipoCargo = document.querySelector('input[name="tipoRegistro"][value="cargo"]').checked;
            const cargoSelect = document.getElementById('cargoReg');
            const cargoOtroInput = document.getElementById('cargoOtro');
            const passInput = document.getElementById('passReg');
            
            if (!passInput.value || passInput.value.length < 6) {
                e.preventDefault();
                alert('La contraseña es obligatoria y debe tener al menos 6 caracteres.');
                return false;
            }
            
            if (tipoCargo) {
                // Validar que se haya seleccionado un cargo o escrito uno personalizado
                if (cargoSelect.value === '') {
                    e.preventDefault();
                    alert('Por favor seleccione un cargo.');
                    return false;
                }
                
                if (cargoSelect.value === 'otros' && cargoOtroInput.value.trim() === '') {
                    e.preventDefault();
                    alert('Por favor ingrese el nombre del cargo.');
                    return false;
                }
            }
            
            if (tipoCargo && cargoSelect.value !== '' && cargoSelect.value !== 'otros') {
                const horariosEntrada = document.querySelectorAll('input[name="horario_entrada[]"]');
                const horariosSalida = document.querySelectorAll('input[name="horario_salida[]"]');
                let horariosCompletos = true;
                
                // Verificar que todos los horarios estén completos
                horariosEntrada.forEach(function(input, index) {
                    if (!input.value || !horariosSalida[index] || !horariosSalida[index].value) {
                        horariosCompletos = false;
                    } else if (input.value >= horariosSalida[index].value) {
                        alert('La hora de entrada debe ser menor que la hora de salida en el horario ' + (index + 1));
                        horariosCompletos = false;
                    }
                });
                
                if (!horariosCompletos) {
                    e.preventDefault();
                    alert('Por favor complete todos los horarios correctamente.');
                    return false;
                }
                
                if (horariosEntrada.length === 0) {
                    e.preventDefault();
                    alert('Debe ingresar al menos un horario de entrada y salida.');
                    return false;
                }
            }
        });
    </script>
</body>
</html>

