<?php
session_start();
include "conexion.php";

if (!isset($_SESSION["id"]) || !isset($_SESSION["rol"]) || (int)$_SESSION["rol"] !== 2) {
    header("Location: /");
    exit;
}

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}
$rol = 2;
$paginaActiva = "pacientes";

$mensaje = "";
$tipoMensaje = "error";
$pacienteId = (int)($_GET["editar"] ?? 0);
$formulario = [
    "nombres" => "",
    "apellidos" => "",
    "dni" => "",
    "fecha_nacimiento" => "",
    "sexo" => "",
    "telefono" => "",
    "direccion" => "",
    "correo" => ""
];

if (isset($_SESSION["mensaje_paciente"])) {
    $mensaje = $_SESSION["mensaje_paciente"];
    $tipoMensaje = "success";
    unset($_SESSION["mensaje_paciente"]);
}

if ($pacienteId > 0) {
    $stmtEditar = $conexion->prepare("SELECT nombres, apellidos, dni, fecha_nacimiento, sexo, telefono, direccion, correo FROM pacientes WHERE id = ?");
    $stmtEditar->bind_param("i", $pacienteId);
    $stmtEditar->execute();
    $pacienteEditar = $stmtEditar->get_result()->fetch_assoc();
    $stmtEditar->close();

    if (!$pacienteEditar) {
        http_response_code(404);
        exit("Paciente no encontrado.");
    }
    $formulario = array_merge($formulario, $pacienteEditar);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!hash_equals($_SESSION["csrf_token"], $_POST["csrf_token"] ?? "")) {
        http_response_code(403);
        exit("Solicitud no válida.");
    }

    $pacienteId = (int)($_POST["paciente_id"] ?? 0);
    foreach ($formulario as $campo => $valor) {
        $formulario[$campo] = trim($_POST[$campo] ?? "");
    }

    $fechaNacimiento = $formulario["fecha_nacimiento"];
    $fechaValida = true;
    if ($fechaNacimiento !== "") {
        $fecha = DateTime::createFromFormat("!Y-m-d", $fechaNacimiento);
        $erroresFecha = DateTime::getLastErrors();
        $fechaValida = $fecha !== false &&
            ($erroresFecha === false || ($erroresFecha["warning_count"] === 0 && $erroresFecha["error_count"] === 0)) &&
            $fecha->format("Y-m-d") === $fechaNacimiento &&
            $fechaNacimiento <= date("Y-m-d");
    }

    $sexoValido = in_array($formulario["sexo"], ["", "M", "F", "X"], true);
    $correoValido = $formulario["correo"] === "" || filter_var($formulario["correo"], FILTER_VALIDATE_EMAIL);
    $datosValidos = $pacienteId > 0 && $formulario["nombres"] !== "" &&
        $formulario["apellidos"] !== "" && $formulario["dni"] !== "" &&
        strlen($formulario["dni"]) <= 20 && strlen($formulario["direccion"]) <= 200 &&
        strlen($formulario["telefono"]) <= 20 && strlen($formulario["correo"]) <= 100 &&
        $fechaValida && $sexoValido && $correoValido;

    if (!$datosValidos) {
        $mensaje = "Revisa los campos obligatorios, el formato de correo y la fecha de nacimiento.";
    } else {
        try {
            $nombres = $formulario["nombres"];
            $apellidos = $formulario["apellidos"];
            $dni = $formulario["dni"];
            $fechaNacimientoDb = $fechaNacimiento !== "" ? $fechaNacimiento : null;
            $sexo = $formulario["sexo"] !== "" ? $formulario["sexo"] : null;
            $telefono = $formulario["telefono"] !== "" ? $formulario["telefono"] : null;
            $direccion = $formulario["direccion"] !== "" ? $formulario["direccion"] : null;
            $correo = $formulario["correo"] !== "" ? $formulario["correo"] : null;

            $stmtActualizar = $conexion->prepare("UPDATE pacientes SET nombres = ?, apellidos = ?, dni = ?, fecha_nacimiento = ?, sexo = ?, telefono = ?, direccion = ?, correo = ? WHERE id = ?");
            $stmtActualizar->bind_param("ssssssssi", $nombres, $apellidos, $dni, $fechaNacimientoDb, $sexo, $telefono, $direccion, $correo, $pacienteId);
            $stmtActualizar->execute();
            $stmtActualizar->close();

            $_SESSION["mensaje_paciente"] = "Datos del paciente actualizados correctamente.";
            header("Location: pacientes");
            exit;
        } catch (mysqli_sql_exception $error) {
            error_log("Error al actualizar paciente: " . $error->getMessage());
            $mensaje = $error->getCode() === 1062
                ? "Ese DNI ya está asociado a otro paciente."
                : "No se pudieron actualizar los datos. Inténtalo de nuevo.";
        }
    }
}

$pacientes = [];
$sqlPacientes = "
    SELECT p.id, p.nombres, p.apellidos, p.dni, p.telefono, p.correo, p.fecha_registro
    FROM pacientes p
    ORDER BY p.apellidos, p.nombres
";
$resultadoPacientes = $conexion->query($sqlPacientes);
while ($paciente = $resultadoPacientes->fetch_assoc()) {
    $pacientes[] = $paciente;
}
$estaEditando = $pacienteId > 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrar pacientes</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="page-pacientes has-sidebar">
<?php include "sidebar.php"; ?>
<main class="pacientes-page">
    <header class="pacientes-header">
        <div>
            <p class="pacientes-eyebrow">ADMINISTRACIÓN / PACIENTES</p>
            <h1>Directorio de pacientes</h1>
            <p>Consulta los registros y actualiza sus datos personales o de contacto.</p>
        </div>
        <a class="pacientes-back" href="panel">Volver al panel</a>
    </header>

    <?php if ($mensaje !== ""): ?>
        <div class="pacientes-alerta <?php echo $tipoMensaje; ?>" role="status">
            <?php echo htmlspecialchars($mensaje, ENT_QUOTES, "UTF-8"); ?>
        </div>
    <?php endif; ?>

    <section class="pacientes-layout <?php echo $estaEditando || $_SERVER["REQUEST_METHOD"] === "POST" ? "is-editing" : ""; ?>">
        <?php if ($estaEditando || $_SERVER["REQUEST_METHOD"] === "POST"): ?>
            <section class="pacientes-form-section" aria-labelledby="tituloEditarPaciente">
                <div class="pacientes-section-heading">
                    <div>
                        <p class="pacientes-eyebrow">FICHA DEL PACIENTE</p>
                        <h2 id="tituloEditarPaciente">Editar datos</h2>
                    </div>
                    <a class="pacientes-cancel" href="pacientes">Cancelar</a>
                </div>

                <form method="POST" action="pacientes" class="paciente-form">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION["csrf_token"], ENT_QUOTES, "UTF-8"); ?>">
                    <input type="hidden" name="paciente_id" value="<?php echo $pacienteId; ?>">

                    <div class="paciente-form-grid">
                        <label>Nombres
                            <input name="nombres" maxlength="100" required value="<?php echo htmlspecialchars($formulario["nombres"], ENT_QUOTES, "UTF-8"); ?>">
                        </label>
                        <label>Apellidos
                            <input name="apellidos" maxlength="100" required value="<?php echo htmlspecialchars($formulario["apellidos"], ENT_QUOTES, "UTF-8"); ?>">
                        </label>
                        <label>DNI
                            <input name="dni" maxlength="20" required value="<?php echo htmlspecialchars($formulario["dni"], ENT_QUOTES, "UTF-8"); ?>">
                        </label>
                        <label>Fecha de nacimiento <span>Opcional</span>
                            <input type="date" name="fecha_nacimiento" max="<?php echo date("Y-m-d"); ?>" value="<?php echo htmlspecialchars($formulario["fecha_nacimiento"] ?? "", ENT_QUOTES, "UTF-8"); ?>">
                        </label>
                        <label>Sexo <span>Opcional</span>
                            <select name="sexo">
                                <option value="">Sin especificar</option>
                                <option value="M" <?php echo ($formulario["sexo"] ?? "") === "M" ? "selected" : ""; ?>>Masculino</option>
                                <option value="F" <?php echo ($formulario["sexo"] ?? "") === "F" ? "selected" : ""; ?>>Femenino</option>
                                <option value="X" <?php echo ($formulario["sexo"] ?? "") === "X" ? "selected" : ""; ?>>Otro</option>
                            </select>
                        </label>
                        <label>Teléfono <span>Opcional</span>
                            <input name="telefono" maxlength="20" autocomplete="tel" value="<?php echo htmlspecialchars($formulario["telefono"] ?? "", ENT_QUOTES, "UTF-8"); ?>">
                        </label>
                        <label>Correo <span>Opcional</span>
                            <input type="email" name="correo" maxlength="100" autocomplete="email" value="<?php echo htmlspecialchars($formulario["correo"] ?? "", ENT_QUOTES, "UTF-8"); ?>">
                        </label>
                        <label class="paciente-direccion">Dirección <span>Opcional</span>
                            <input name="direccion" maxlength="200" autocomplete="street-address" value="<?php echo htmlspecialchars($formulario["direccion"] ?? "", ENT_QUOTES, "UTF-8"); ?>">
                        </label>
                    </div>

                    <div class="paciente-form-footer">
                        <p>El nombre de usuario y la contraseña no se modifican desde esta ficha.</p>
                        <button type="submit">Guardar cambios</button>
                    </div>
                </form>
            </section>
        <?php endif; ?>

        <section class="pacientes-list-section" aria-labelledby="tituloListaPacientes">
            <div class="pacientes-section-heading">
                <div>
                    <p class="pacientes-eyebrow">REGISTROS</p>
                    <h2 id="tituloListaPacientes">Pacientes registrados</h2>
                </div>
                <span class="pacientes-count"><?php echo count($pacientes); ?> pacientes</span>
            </div>

            <div class="pacientes-table-wrap">
                <table class="pacientes-table">
                    <thead>
                        <tr><th>Paciente</th><th>DNI</th><th>Contacto</th><th>Registro</th><th>Acción</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pacientes as $paciente): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($paciente["nombres"] . " " . $paciente["apellidos"], ENT_QUOTES, "UTF-8"); ?></strong></td>
                                <td><?php echo htmlspecialchars($paciente["dni"], ENT_QUOTES, "UTF-8"); ?></td>
                                <td>
                                    <span><?php echo htmlspecialchars($paciente["telefono"] ?: "Sin teléfono", ENT_QUOTES, "UTF-8"); ?></span>
                                    <small><?php echo htmlspecialchars($paciente["correo"] ?: "Sin correo", ENT_QUOTES, "UTF-8"); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars(date("d/m/Y", strtotime($paciente["fecha_registro"])), ENT_QUOTES, "UTF-8"); ?></td>
                                <td><a class="pacientes-edit" href="pacientes?editar=<?php echo (int)$paciente["id"]; ?>">Editar</a></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$pacientes): ?>
                            <tr><td colspan="5" class="pacientes-empty">Todavía no hay pacientes registrados.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </section>
</main>
<script src="js/javascript.js" defer></script>
</body>
</html>
