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
$paginaActiva = "doctores";

$mensaje = "";
$tipoMensaje = "error";
$formulario = [
    "usuario" => "",
    "nombres" => "",
    "apellidos" => "",
    "cmp" => "",
    "especialidad" => "",
    "telefono" => "",
    "correo" => "",
    "hora_inicio" => "08:00",
    "hora_fin" => "17:00",
    "refrigerio_inicio" => "",
    "refrigerio_fin" => ""
];

if (isset($_SESSION["mensaje_doctor"])) {
    $mensaje = $_SESSION["mensaje_doctor"];
    $tipoMensaje = "success";
    unset($_SESSION["mensaje_doctor"]);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!hash_equals($_SESSION["csrf_token"], $_POST["csrf_token"] ?? "")) {
        http_response_code(403);
        exit("Solicitud no válida.");
    }

    foreach ($formulario as $campo => $valor) {
        $formulario[$campo] = trim($_POST[$campo] ?? $valor);
    }
    $password = $_POST["password"] ?? "";
    $emailValido = $formulario["correo"] === "" || filter_var($formulario["correo"], FILTER_VALIDATE_EMAIL);
    $horaValida = static function ($hora) {
        return preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D', $hora) === 1;
    };

    $datosValidos = $formulario["usuario"] !== "" && strlen($formulario["usuario"]) <= 50 &&
        $formulario["nombres"] !== "" && $formulario["apellidos"] !== "" &&
        $formulario["especialidad"] !== "" && strlen($password) >= 10 && $emailValido &&
        $horaValida($formulario["hora_inicio"]) && $horaValida($formulario["hora_fin"]) &&
        $formulario["hora_inicio"] < $formulario["hora_fin"];

    $tieneRefrigerio = $formulario["refrigerio_inicio"] !== "" || $formulario["refrigerio_fin"] !== "";
    if ($tieneRefrigerio) {
        $datosValidos = $datosValidos && $horaValida($formulario["refrigerio_inicio"]) &&
            $horaValida($formulario["refrigerio_fin"]) &&
            $formulario["refrigerio_inicio"] < $formulario["refrigerio_fin"] &&
            $formulario["refrigerio_inicio"] >= $formulario["hora_inicio"] &&
            $formulario["refrigerio_fin"] <= $formulario["hora_fin"];
    }

    if (!$datosValidos) {
        $mensaje = "Revisa los campos: usuario de hasta 50 caracteres, contraseña de 10 o más, correo válido y horarios coherentes.";
    } else {
        try {
            $conexion->begin_transaction();

            $usuario = $formulario["usuario"];
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $rolDoctor = 1;
            $stmtUsuario = $conexion->prepare("INSERT INTO usuarios (usuario, password, rol) VALUES (?, ?, ?)");
            $stmtUsuario->bind_param("ssi", $usuario, $passwordHash, $rolDoctor);
            $stmtUsuario->execute();
            $usuarioId = $conexion->insert_id;
            $stmtUsuario->close();

            $nombres = $formulario["nombres"];
            $apellidos = $formulario["apellidos"];
            $cmp = $formulario["cmp"] !== "" ? $formulario["cmp"] : null;
            $especialidad = $formulario["especialidad"];
            $telefono = $formulario["telefono"] !== "" ? $formulario["telefono"] : null;
            $correo = $formulario["correo"] !== "" ? $formulario["correo"] : null;
            $horaInicio = $formulario["hora_inicio"] . ":00";
            $horaFin = $formulario["hora_fin"] . ":00";
            $refrigerioInicio = $formulario["refrigerio_inicio"] !== "" ? $formulario["refrigerio_inicio"] . ":00" : null;
            $refrigerioFin = $formulario["refrigerio_fin"] !== "" ? $formulario["refrigerio_fin"] . ":00" : null;

            $stmtDoctor = $conexion->prepare("INSERT INTO doctores (usuario_id, nombres, apellidos, cmp, especialidad, telefono, correo, hora_inicio, hora_fin, refrigerio_inicio, refrigerio_fin) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmtDoctor->bind_param("issssssssss", $usuarioId, $nombres, $apellidos, $cmp, $especialidad, $telefono, $correo, $horaInicio, $horaFin, $refrigerioInicio, $refrigerioFin);
            $stmtDoctor->execute();
            $stmtDoctor->close();

            $conexion->commit();
            $_SESSION["mensaje_doctor"] = "Doctor registrado correctamente. Ya puede iniciar sesión.";
            header("Location: doctores");
            exit;
        } catch (mysqli_sql_exception $error) {
            $conexion->rollback();
            error_log("Error al registrar doctor: " . $error->getMessage());
            $mensaje = $error->getCode() === 1062
                ? "Ese nombre de usuario ya está registrado. Elige otro."
                : "No se pudo registrar el doctor. Revisa los datos e inténtalo de nuevo.";
        }
    }
}

$doctores = [];
$sqlDoctores = "
    SELECT d.nombres, d.apellidos, d.cmp, d.especialidad, d.telefono,
           d.hora_inicio, d.hora_fin, u.usuario
    FROM doctores d
    INNER JOIN usuarios u ON u.id = d.usuario_id
    ORDER BY d.apellidos, d.nombres
";
$resultadoDoctores = $conexion->query($sqlDoctores);
while ($doctor = $resultadoDoctores->fetch_assoc()) {
    $doctores[] = $doctor;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Directorio de doctores</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="page-doctores has-sidebar">
<?php include "sidebar.php"; ?>
<main class="doctores-page">
    <header class="doctores-header">
        <div>
            <p class="doctores-eyebrow">ADMINISTRACIÓN / PERSONAL MÉDICO</p>
            <h1>Directorio de doctores</h1>
            <p>Administra perfiles profesionales y sus cuentas de acceso.</p>
        </div>
        <a class="doctores-back" href="panel">Volver al panel</a>
    </header>

    <?php if ($mensaje !== ""): ?>
        <div class="doctores-alerta <?php echo $tipoMensaje; ?>" role="status">
            <?php echo htmlspecialchars($mensaje, ENT_QUOTES, "UTF-8"); ?>
        </div>
    <?php endif; ?>

    <section class="doctores-layout">
        <section class="doctores-form-section" aria-labelledby="tituloNuevoDoctor">
            <div class="doctores-section-heading">
                <div>
                    <p class="doctores-eyebrow">NUEVO PERFIL</p>
                    <h2 id="tituloNuevoDoctor">Agregar doctor</h2>
                </div>
                <span class="doctores-count"><?php echo count($doctores); ?> registrados</span>
            </div>

            <form method="POST" action="doctores" class="doctor-form">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION["csrf_token"], ENT_QUOTES, "UTF-8"); ?>">

                <div class="doctor-form-grid">
                    <label>Nombre de usuario
                        <input name="usuario" maxlength="50" autocomplete="off" required value="<?php echo htmlspecialchars($formulario["usuario"], ENT_QUOTES, "UTF-8"); ?>">
                    </label>
                    <label>Contraseña inicial
                        <input type="password" name="password" minlength="10" autocomplete="new-password" required>
                    </label>
                    <label>Nombres
                        <input name="nombres" maxlength="100" autocomplete="given-name" required value="<?php echo htmlspecialchars($formulario["nombres"], ENT_QUOTES, "UTF-8"); ?>">
                    </label>
                    <label>Apellidos
                        <input name="apellidos" maxlength="100" autocomplete="family-name" required value="<?php echo htmlspecialchars($formulario["apellidos"], ENT_QUOTES, "UTF-8"); ?>">
                    </label>
                    <label>Especialidad
                        <input name="especialidad" maxlength="100" required value="<?php echo htmlspecialchars($formulario["especialidad"], ENT_QUOTES, "UTF-8"); ?>">
                    </label>
                    <label>Registro CMP <span>Opcional</span>
                        <input name="cmp" maxlength="30" value="<?php echo htmlspecialchars($formulario["cmp"], ENT_QUOTES, "UTF-8"); ?>">
                    </label>
                    <label>Teléfono <span>Opcional</span>
                        <input name="telefono" maxlength="20" autocomplete="tel" value="<?php echo htmlspecialchars($formulario["telefono"], ENT_QUOTES, "UTF-8"); ?>">
                    </label>
                    <label>Correo <span>Opcional</span>
                        <input type="email" name="correo" maxlength="100" autocomplete="email" value="<?php echo htmlspecialchars($formulario["correo"], ENT_QUOTES, "UTF-8"); ?>">
                    </label>
                    <label>Inicio de atención
                        <input type="time" name="hora_inicio" required value="<?php echo htmlspecialchars($formulario["hora_inicio"], ENT_QUOTES, "UTF-8"); ?>">
                    </label>
                    <label>Fin de atención
                        <input type="time" name="hora_fin" required value="<?php echo htmlspecialchars($formulario["hora_fin"], ENT_QUOTES, "UTF-8"); ?>">
                    </label>
                    <label>Inicio de refrigerio <span>Opcional</span>
                        <input type="time" name="refrigerio_inicio" value="<?php echo htmlspecialchars($formulario["refrigerio_inicio"], ENT_QUOTES, "UTF-8"); ?>">
                    </label>
                    <label>Fin de refrigerio <span>Opcional</span>
                        <input type="time" name="refrigerio_fin" value="<?php echo htmlspecialchars($formulario["refrigerio_fin"], ENT_QUOTES, "UTF-8"); ?>">
                    </label>
                </div>

                <div class="doctor-form-footer">
                    <p>El horario se usará para generar los turnos disponibles.</p>
                    <button type="submit">Registrar doctor</button>
                </div>
            </form>
        </section>

        <section class="doctores-list-section" aria-labelledby="tituloListaDoctores">
            <div class="doctores-section-heading">
                <div>
                    <p class="doctores-eyebrow">PERSONAL ACTIVO</p>
                    <h2 id="tituloListaDoctores">Doctores registrados</h2>
                </div>
            </div>

            <div class="doctores-table-wrap">
                <table class="doctores-table">
                    <thead>
                        <tr><th>Profesional</th><th>Especialidad</th><th>Horario</th><th>Acceso</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($doctores as $doctor): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($doctor["nombres"] . " " . $doctor["apellidos"], ENT_QUOTES, "UTF-8"); ?></strong>
                                    <small><?php echo htmlspecialchars($doctor["cmp"] ?: "CMP no registrado", ENT_QUOTES, "UTF-8"); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($doctor["especialidad"], ENT_QUOTES, "UTF-8"); ?></td>
                                <td><?php echo htmlspecialchars(substr($doctor["hora_inicio"], 0, 5) . " - " . substr($doctor["hora_fin"], 0, 5), ENT_QUOTES, "UTF-8"); ?></td>
                                <td><?php echo htmlspecialchars($doctor["usuario"], ENT_QUOTES, "UTF-8"); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$doctores): ?>
                            <tr><td colspan="4" class="doctores-empty">Todavía no hay doctores registrados.</td></tr>
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
