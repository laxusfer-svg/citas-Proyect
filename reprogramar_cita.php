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

$citaId = (int)($_GET["id"] ?? $_POST["cita_id"] ?? 0);
$origen = ($_GET["origen"] ?? $_POST["origen"] ?? "") === "reporte" ? "reporte" : "panel";
$mensaje = "";
$tipoMensaje = "error";
$reprogramada = false;
$fechaConsulta = "";
$horaSeleccionada = $_POST["hora"] ?? "";
$horariosDisponibles = [];

if ($citaId <= 0) {
    http_response_code(400);
    exit("La cita seleccionada no es válida.");
}

function obtenerHorariosReprogramacion($conexion, $citaId, $doctorId, $pacienteId, $fecha, $doctor)
{
    $horariosOcupados = [];
    $sqlOcupadas = "
        SELECT hora
        FROM citas
        WHERE id <> ?
          AND fecha = ?
          AND estado != 'cancelada'
          AND (doctor_id = ? OR paciente_id = ?)
    ";
    $stmtOcupadas = $conexion->prepare($sqlOcupadas);
    $stmtOcupadas->bind_param("isii", $citaId, $fecha, $doctorId, $pacienteId);
    $stmtOcupadas->execute();
    $resultadoOcupadas = $stmtOcupadas->get_result();
    while ($filaOcupada = $resultadoOcupadas->fetch_assoc()) {
        $horariosOcupados[] = new DateTime($filaOcupada["hora"]);
    }
    $resultadoOcupadas->free();
    $stmtOcupadas->close();

    $inicio = new DateTime($doctor["hora_inicio"]);
    $fin = new DateTime($doctor["hora_fin"]);
    $ultimoInicio = clone $fin;
    $ultimoInicio->modify("-30 minutes");
    $horarios = [];

    while ($inicio <= $ultimoInicio) {
        $finCita = clone $inicio;
        $finCita->modify("+30 minutes");
        $noDisponible = false;

        if (!empty($doctor["refrigerio_inicio"]) && !empty($doctor["refrigerio_fin"])) {
            $inicioRefrigerio = new DateTime($doctor["refrigerio_inicio"]);
            $finRefrigerio = new DateTime($doctor["refrigerio_fin"]);
            $noDisponible = $inicio < $finRefrigerio && $finCita > $inicioRefrigerio;
        }

        foreach ($horariosOcupados as $horaOcupada) {
            $finOcupada = clone $horaOcupada;
            $finOcupada->modify("+30 minutes");
            if ($inicio < $finOcupada && $finCita > $horaOcupada) {
                $noDisponible = true;
                break;
            }
        }

        $hora = $inicio->format("H:i:s");
        if ($fecha === date("Y-m-d") && $hora <= date("H:i:s")) {
            $noDisponible = true;
        }

        if (!$noDisponible) {
            $horarios[] = $hora;
        }

        $inicio->modify("+30 minutes");
    }

    return $horarios;
}

$sqlCita = "
    SELECT c.id, c.paciente_id, c.doctor_id, c.fecha, c.hora, c.modalidad, c.estado,
           p.nombres AS paciente_nombres, p.apellidos AS paciente_apellidos,
           d.nombres AS doctor_nombres, d.apellidos AS doctor_apellidos,
           d.especialidad, d.hora_inicio, d.hora_fin, d.refrigerio_inicio, d.refrigerio_fin
    FROM citas c
    INNER JOIN pacientes p ON p.id = c.paciente_id
    INNER JOIN doctores d ON d.id = c.doctor_id
    WHERE c.id = ?
";
$stmtCita = $conexion->prepare($sqlCita);
$stmtCita->bind_param("i", $citaId);
$stmtCita->execute();
$cita = $stmtCita->get_result()->fetch_assoc();
$stmtCita->close();

if (!$cita) {
    http_response_code(404);
    exit("Cita no encontrada.");
}

if (!in_array($cita["estado"], ["pendiente", "confirmada"], true)) {
    http_response_code(409);
    exit("Solo se pueden reprogramar citas pendientes o confirmadas.");
}

$fechaConsulta = max(date("Y-m-d"), $cita["fecha"]);
$fechaSolicitada = $_SERVER["REQUEST_METHOD"] === "POST"
    ? ($_POST["fecha"] ?? "")
    : ($_GET["fecha"] ?? $fechaConsulta);
$fechaValidada = DateTime::createFromFormat("!Y-m-d", $fechaSolicitada);
$erroresFecha = DateTime::getLastErrors();
$fechaValida = $fechaValidada !== false &&
    ($erroresFecha === false || ($erroresFecha["warning_count"] === 0 && $erroresFecha["error_count"] === 0)) &&
    $fechaValidada->format("Y-m-d") === $fechaSolicitada &&
    $fechaSolicitada >= date("Y-m-d");

if (!$fechaValida) {
    $mensaje = "Selecciona una fecha válida, igual o posterior a hoy.";
    $fechaConsulta = date("Y-m-d");
} else {
    $fechaConsulta = $fechaSolicitada;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!hash_equals($_SESSION["csrf_token"], $_POST["csrf_token"] ?? "")) {
        http_response_code(403);
        exit("Solicitud no válida.");
    }

    $hora = $_POST["hora"] ?? "";
    $horaValidada = DateTime::createFromFormat("!H:i:s", $hora);
    $erroresHora = DateTime::getLastErrors();
    $horaValida = $horaValidada !== false &&
        ($erroresHora === false || ($erroresHora["warning_count"] === 0 && $erroresHora["error_count"] === 0)) &&
        $horaValidada->format("H:i:s") === $hora;

    if (!$fechaValida || !$horaValida) {
        $mensaje = "Selecciona una fecha y un horario válidos.";
    } else {
        try {
            $conexion->begin_transaction();

            $doctorId = (int)$cita["doctor_id"];
            $pacienteId = (int)$cita["paciente_id"];

            $stmtDoctor = $conexion->prepare("SELECT hora_inicio, hora_fin, refrigerio_inicio, refrigerio_fin FROM doctores WHERE id = ? FOR UPDATE");
            $stmtDoctor->bind_param("i", $doctorId);
            $stmtDoctor->execute();
            $resultadoDoctor = $stmtDoctor->get_result();
            $doctorBloqueado = $resultadoDoctor->fetch_assoc();
            $resultadoDoctor->free();
            $stmtDoctor->close();

            $stmtPaciente = $conexion->prepare("SELECT id FROM pacientes WHERE id = ? FOR UPDATE");
            $stmtPaciente->bind_param("i", $pacienteId);
            $stmtPaciente->execute();
            $resultadoPaciente = $stmtPaciente->get_result();
            $pacienteBloqueado = $resultadoPaciente->fetch_assoc();
            $resultadoPaciente->free();
            $stmtPaciente->close();

            $stmtBloqueoCita = $conexion->prepare("SELECT paciente_id, doctor_id, estado FROM citas WHERE id = ? FOR UPDATE");
            $stmtBloqueoCita->bind_param("i", $citaId);
            $stmtBloqueoCita->execute();
            $resultadoBloqueoCita = $stmtBloqueoCita->get_result();
            $citaBloqueada = $resultadoBloqueoCita->fetch_assoc();
            $resultadoBloqueoCita->free();
            $stmtBloqueoCita->close();

            if (
                !$doctorBloqueado ||
                !$pacienteBloqueado ||
                !$citaBloqueada ||
                (int)$citaBloqueada["doctor_id"] !== $doctorId ||
                (int)$citaBloqueada["paciente_id"] !== $pacienteId ||
                !in_array($citaBloqueada["estado"], ["pendiente", "confirmada"], true)
            ) {
                throw new RuntimeException("No se pudo validar el doctor o el paciente de la cita.");
            }

            $horariosDisponibles = obtenerHorariosReprogramacion($conexion, $citaId, $doctorId, $pacienteId, $fechaConsulta, $doctorBloqueado);
            if ($fechaConsulta === $cita["fecha"] && $hora === $cita["hora"]) {
                throw new RuntimeException("Selecciona una fecha u horario distinto al actual.");
            }
            if (!in_array($hora, $horariosDisponibles, true)) {
                throw new RuntimeException("Ese horario ya no está disponible. Selecciona otro.");
            }

            $stmtActualizar = $conexion->prepare("UPDATE citas SET fecha = ?, hora = ? WHERE id = ? AND estado IN ('pendiente', 'confirmada')");
            $stmtActualizar->bind_param("ssi", $fechaConsulta, $hora, $citaId);
            $stmtActualizar->execute();
            $stmtActualizar->close();
            $conexion->commit();

            $cita["fecha"] = $fechaConsulta;
            $cita["hora"] = $hora;
            $horaSeleccionada = $hora;
                $mensaje = "Cita reprogramada para el " . date("d/m/Y", strtotime($fechaConsulta)) . " a las " . substr($hora, 0, 5) . ".";
            $tipoMensaje = "success";
            $reprogramada = true;
        } catch (Throwable $error) {
            $conexion->rollback();
            $mensaje = $error instanceof RuntimeException
                ? $error->getMessage()
                : "No se pudo reprogramar la cita. Inténtalo de nuevo.";
            if (!$error instanceof RuntimeException) {
                error_log("Error al reprogramar cita: " . $error->getMessage());
            }
        }
    }
}

if ($fechaValida && !$reprogramada && !$horariosDisponibles) {
    $horariosDisponibles = obtenerHorariosReprogramacion(
        $conexion,
        $citaId,
        (int)$cita["doctor_id"],
        (int)$cita["paciente_id"],
        $fechaConsulta,
        $cita
    );
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reprogramar cita</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="page-reserva">
<main class="reserva-overlay">
    <section class="modal-cita" role="dialog" aria-modal="true" aria-labelledby="tituloReprogramar">
        <header class="modal-cita-header">
            <div>
                <p class="modal-eyebrow">ADMINISTRACIÓN / AGENDA</p>
                <h1 id="tituloReprogramar">Reprogramar cita</h1>
                <p class="modal-subtitulo">Selecciona una nueva fecha y un horario libre para el mismo doctor.</p>
            </div>
            <a class="modal-cerrar" href="<?php echo $origen === "reporte" ? "reporte_citas" : "panel"; ?>" aria-label="Cerrar y volver">&times;</a>
            <div class="modal-paciente">
                <span>Paciente</span>
                <strong><?php echo htmlspecialchars($cita["paciente_nombres"] . " " . $cita["paciente_apellidos"], ENT_QUOTES, "UTF-8"); ?></strong>
            </div>
        </header>

        <div class="formulario">
            <div class="reprogramar-resumen">
                <div><span>Doctor</span><strong><?php echo htmlspecialchars("Dr. " . $cita["doctor_nombres"] . " " . $cita["doctor_apellidos"], ENT_QUOTES, "UTF-8"); ?></strong></div>
                <div><span>Especialidad</span><strong><?php echo htmlspecialchars($cita["especialidad"], ENT_QUOTES, "UTF-8"); ?></strong></div>
                <div><span>Modalidad</span><strong><?php echo $cita["modalidad"] === "virtual" ? "Virtual" : "Presencial"; ?></strong></div>
                <div><span>Estado actual</span><strong><?php echo htmlspecialchars(ucfirst($cita["estado"]), ENT_QUOTES, "UTF-8"); ?></strong></div>
                    <div><span>Turno actual</span><strong><?php echo htmlspecialchars(substr($cita["hora"], 0, 5), ENT_QUOTES, "UTF-8"); ?></strong></div>
            </div>

            <?php if ($mensaje !== ""): ?>
                <div class="mensaje <?php echo $tipoMensaje; ?>" role="status">
                    <?php echo htmlspecialchars($mensaje, ENT_QUOTES, "UTF-8"); ?>
                </div>
            <?php endif; ?>

            <?php if (!$reprogramada): ?>
                <form method="GET" action="reprogramar_cita" class="campo reprogramar-fecha-form">
                    <input type="hidden" name="id" value="<?php echo $citaId; ?>">
                    <input type="hidden" name="origen" value="<?php echo htmlspecialchars($origen, ENT_QUOTES, "UTF-8"); ?>">
                    <label for="fechaReprogramacion">Nueva fecha</label>
                    <div class="reprogramar-fecha-controles">
                        <input type="date" id="fechaReprogramacion" name="fecha" min="<?php echo date("Y-m-d"); ?>" value="<?php echo htmlspecialchars($fechaConsulta, ENT_QUOTES, "UTF-8"); ?>" required>
                        <button type="submit" class="btn-guardar">Consultar horarios</button>
                    </div>
                </form>

                <form method="POST" action="reprogramar_cita?id=<?php echo $citaId; ?>&origen=<?php echo htmlspecialchars($origen, ENT_QUOTES, "UTF-8"); ?>" class="reprogramar-confirmar-form">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION["csrf_token"], ENT_QUOTES, "UTF-8"); ?>">
                    <input type="hidden" name="cita_id" value="<?php echo $citaId; ?>">
                    <input type="hidden" name="origen" value="<?php echo htmlspecialchars($origen, ENT_QUOTES, "UTF-8"); ?>">
                    <input type="hidden" name="fecha" value="<?php echo htmlspecialchars($fechaConsulta, ENT_QUOTES, "UTF-8"); ?>">
                    <div class="campo">
                        <label for="horaReprogramacion">Horarios disponibles</label>
                        <select id="horaReprogramacion" name="hora" required <?php echo !$horariosDisponibles ? "disabled" : ""; ?>>
                            <option value="">Selecciona un horario</option>
                            <?php foreach ($horariosDisponibles as $horario): ?>
                                <option value="<?php echo htmlspecialchars($horario, ENT_QUOTES, "UTF-8"); ?>" <?php echo $horaSeleccionada === $horario ? "selected" : ""; ?>><?php echo htmlspecialchars(substr($horario, 0, 5), ENT_QUOTES, "UTF-8"); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (!$horariosDisponibles): ?>
                            <p class="reprogramar-sin-horarios">No hay horarios disponibles para esa fecha.</p>
                        <?php endif; ?>
                    </div>
                    <div class="botones reserva-acciones">
                        <button type="submit" class="btn-guardar" <?php echo !$horariosDisponibles ? "disabled" : ""; ?>>Confirmar reprogramación</button>
                        <a class="boton btn-volver" href="<?php echo $origen === "reporte" ? "reporte_citas" : "panel"; ?>">Cancelar</a>
                    </div>
                </form>
            <?php else: ?>
                <div class="botones reserva-acciones">
                    <a class="boton btn-volver" href="<?php echo $origen === "reporte" ? "reporte_citas" : "panel"; ?>">Volver a las citas</a>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>
</body>
</html>
