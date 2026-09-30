<?php
session_start();
include "conexion.php";

/* =====================================================
   SEGURIDAD
===================================================== */

if (!isset($_SESSION["id"]) || $_SESSION["rol"] != 0) {
    header("Location: /");
    exit;
}

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$usuario_id = $_SESSION["id"];
$mensaje = "";
$tipo_mensaje = "";

/* =====================================================
   OBTENER PACIENTE
===================================================== */

$sqlPaciente = "
    SELECT id, nombres, apellidos, dni
    FROM pacientes
    WHERE usuario_id = ?
";

$stmtPaciente = $conexion->prepare($sqlPaciente);
$stmtPaciente->bind_param("i", $usuario_id);
$stmtPaciente->execute();

$resultPaciente = $stmtPaciente->get_result();

if ($resultPaciente->num_rows == 0) {
    die("No se encontraron los datos del paciente.");
}

$paciente = $resultPaciente->fetch_assoc();
$paciente_id = $paciente["id"];

/* =====================================================
   REGISTRAR CITA
===================================================== */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!hash_equals($_SESSION["csrf_token"], $_POST["csrf_token"] ?? "")) {
        http_response_code(403);
        die("Solicitud no válida.");
    }

    $doctor_id = intval($_POST["doctor_id"] ?? 0);
    $fecha = $_POST["fecha"] ?? "";
    $hora = $_POST["hora"] ?? "";
    $motivo = trim($_POST["motivo"] ?? "");
    $fechaValidada = DateTime::createFromFormat("!Y-m-d", $fecha);
    $erroresFecha = DateTime::getLastErrors();
    $horaValidada = DateTime::createFromFormat("!H:i:s", $hora);
    $erroresHora = DateTime::getLastErrors();

    $fechaValida = $fechaValidada !== false &&
        ($erroresFecha === false || ($erroresFecha["warning_count"] === 0 && $erroresFecha["error_count"] === 0)) &&
        $fechaValidada->format("Y-m-d") === $fecha;

    $horaValida = $horaValidada !== false &&
        ($erroresHora === false || ($erroresHora["warning_count"] === 0 && $erroresHora["error_count"] === 0)) &&
        $horaValidada->format("H:i:s") === $hora;

    if ($doctor_id <= 0 || !$fechaValida || !$horaValida) {

        $mensaje = "La fecha, la hora o el doctor seleccionados no son válidos.";
        $tipo_mensaje = "error";

    } else {

        $conexion->begin_transaction();

        /* =============================================
           OBTENER HORARIO DEL DOCTOR
        ============================================= */

        $sqlDoctor = "
            SELECT
                id,
                hora_inicio,
                hora_fin,
                refrigerio_inicio,
                refrigerio_fin
            FROM doctores
            WHERE id = ?
            FOR UPDATE
        ";

        $stmtDoctor = $conexion->prepare($sqlDoctor);
        $stmtDoctor->bind_param("i", $doctor_id);
        $stmtDoctor->execute();

        $resultDoctor = $stmtDoctor->get_result();

        if ($resultDoctor->num_rows == 0) {

            $conexion->rollback();

            $mensaje = "El doctor seleccionado no existe.";
            $tipo_mensaje = "error";

        } else {

            $doctor = $resultDoctor->fetch_assoc();

            $sqlBloqueoPaciente = "SELECT id FROM pacientes WHERE id = ? FOR UPDATE";
            $stmtBloqueoPaciente = $conexion->prepare($sqlBloqueoPaciente);
            $stmtBloqueoPaciente->bind_param("i", $paciente_id);
            $stmtBloqueoPaciente->execute();

            /* =========================================
               VALIDAR FECHA
            ========================================= */

            if ($fecha < date("Y-m-d")) {

                $conexion->rollback();

                $mensaje = "No puede seleccionar una fecha anterior a hoy.";
                $tipo_mensaje = "error";

            } else {

                /* =====================================
                   VALIDAR HORARIO
                ===================================== */

                $inicio = new DateTime($doctor["hora_inicio"]);
                $fin = new DateTime($doctor["hora_fin"]);

                $horaSeleccionada = new DateTime($hora);
                $fechaHoraSeleccionada = DateTime::createFromFormat("!Y-m-d H:i:s", $fecha . " " . $hora);

                /* Una cita dura 30 minutos */
                $finCita = clone $horaSeleccionada;
                $finCita->modify("+30 minutes");

                $segundosDesdeInicio = $horaSeleccionada->getTimestamp() - $inicio->getTimestamp();
                $horarioValido = true;

                if (
                    $horaSeleccionada < $inicio ||
                    $finCita > $fin ||
                    $segundosDesdeInicio % (30 * 60) !== 0
                ) {
                    $horarioValido = false;
                }

                /* =====================================
                   VALIDAR REFRIGERIO
                ===================================== */

                if (
                    $horarioValido &&
                    !empty($doctor["refrigerio_inicio"]) &&
                    !empty($doctor["refrigerio_fin"])
                ) {

                    $inicioRefrigerio = new DateTime($doctor["refrigerio_inicio"]);
                    $finRefrigerio = new DateTime($doctor["refrigerio_fin"]);

                    if (
                        $horaSeleccionada < $finRefrigerio &&
                        $finCita > $inicioRefrigerio
                    ) {
                        $horarioValido = false;
                    }
                }

                /* =====================================
                   SI ES HOY, NO PERMITIR HORAS PASADAS
                ===================================== */

                if (
                    $horarioValido &&
                    $fecha === date("Y-m-d") &&
                    $fechaHoraSeleccionada <= new DateTime()
                ) {
                    $horarioValido = false;
                }

                if (!$horarioValido) {

                    $conexion->rollback();

                    $mensaje = "El horario seleccionado no está disponible para este doctor.";
                    $tipo_mensaje = "error";

                } else {

                    /* =================================
                       VERIFICAR CITA EXISTENTE (DOCTOR O PACIENTE)
                    ================================= */

                    $sqlExiste = "
                        SELECT id
                        FROM citas
                        WHERE fecha = ?
                        AND estado != 'cancelada'
                        AND (
                            (doctor_id = ? AND hora < ADDTIME(?, '00:30:00') AND ADDTIME(hora, '00:30:00') > ?)
                            OR
                            (paciente_id = ? AND hora < ADDTIME(?, '00:30:00') AND ADDTIME(hora, '00:30:00') > ?)
                        )
                        LIMIT 1
                        FOR UPDATE
                    ";

                    $stmtExiste = $conexion->prepare($sqlExiste);
                    $horaFinTurno = $horaSeleccionada->format("H:i:s");
                    $stmtExiste->bind_param(
                        "sississ",
                        $fecha,
                        $doctor_id,
                        $horaFinTurno,
                        $hora,
                        $paciente_id,
                        $horaFinTurno,
                        $hora
                    );

                    $stmtExiste->execute();
                    $resultExiste = $stmtExiste->get_result();

                    if ($resultExiste->num_rows > 0) {

                        $conexion->rollback();

                        $mensaje = "⚠️ El horario ya está reservado o ya tienes otra cita en este mismo rango de fecha y hora.";
                        $tipo_mensaje = "error";

                    } else {

                        /* =============================
                           INSERTAR CITA
                        ============================= */

                        $sqlInsert = "
                            INSERT INTO citas
                            (
                                paciente_id,
                                doctor_id,
                                fecha,
                                hora,
                                motivo,
                                estado
                            )
                            VALUES
                            (?, ?, ?, ?, ?, 'pendiente')
                        ";

                        $stmtInsert = $conexion->prepare($sqlInsert);

                        $stmtInsert->bind_param(
                            "iisss",
                            $paciente_id,
                            $doctor_id,
                            $fecha,
                            $hora,
                            $motivo
                        );

                        if ($stmtInsert->execute()) {

                            $conexion->commit();
                            $mensaje = "✅ Cita registrada correctamente.";
                            $tipo_mensaje = "success";

                        } else {

                            $conexion->rollback();

                            if ($stmtInsert->errno == 1062) {
                                $mensaje = "⚠️ El horario acaba de ser ocupado por otro paciente. Seleccione otro horario.";
                            } else {
                                $mensaje = "Error al registrar la cita.";
                            }

                            $tipo_mensaje = "error";
                        }
                    }
                }
            }
        }
    }
}

/* =====================================================
   OBTENER DOCTORES
===================================================== */

$sqlDoctores = "
    SELECT
        id,
        nombres,
        apellidos,
        especialidad,
        hora_inicio,
        hora_fin,
        refrigerio_inicio,
        refrigerio_fin
    FROM doctores
    ORDER BY apellidos ASC, nombres ASC
";

$resultDoctores = $conexion->query($sqlDoctores);

?>
<!DOCTYPE html>
<html lang="es">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sacar Cita</title>

<!-- Flatpickr CSS para el Calendario Interactivo -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">



    <link rel="stylesheet" href="css/style.css">
</head>

<body class="page-reserva">

<main class="reserva-overlay">
<section class="modal-cita" role="dialog" aria-modal="true" aria-labelledby="tituloReserva">
<header class="modal-cita-header">
    <div>
        <p class="modal-eyebrow">AGENDA MÉDICA / NUEVA CITA</p>
        <h1 id="tituloReserva">Agenda tu consulta</h1>
        <p class="modal-subtitulo">Elige al profesional, la fecha y un horario disponible.</p>
    </div>
    <a href="panel" class="modal-cerrar" aria-label="Cerrar y volver al panel" title="Volver al panel">&times;</a>
    <div class="modal-paciente">
        <span>Paciente</span>
        <strong><?php echo htmlspecialchars($paciente["nombres"] . " " . $paciente["apellidos"]); ?></strong>
    </div>
</header>

<div class="formulario">

<?php if ($mensaje !== ""): ?>
<div class="mensaje <?php echo $tipo_mensaje; ?>">
    <?php echo htmlspecialchars($mensaje); ?>
</div>
<?php endif; ?>

<form method="POST" id="formCita">

<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION["csrf_token"], ENT_QUOTES, "UTF-8"); ?>">

<div class="reserva-grid">
<div class="reserva-columna">

<!-- DOCTOR -->
<div class="campo">
<label for="doctor_id">Profesional médico</label>
<select name="doctor_id" id="doctor_id" required>
<option value="">Selecciona un doctor</option>
<?php
if ($resultDoctores->num_rows > 0) {
    while ($doctor = $resultDoctores->fetch_assoc()) {
        ?>
        <option
            value="<?php echo $doctor["id"]; ?>"
            data-especialidad="<?php echo htmlspecialchars($doctor["especialidad"]); ?>"
            data-inicio="<?php echo $doctor["hora_inicio"]; ?>"
            data-fin="<?php echo $doctor["hora_fin"]; ?>"
            data-refrigerio-inicio="<?php echo $doctor["refrigerio_inicio"] ?? ""; ?>"
            data-refrigerio-fin="<?php echo $doctor["refrigerio_fin"] ?? ""; ?>"
        >
            Dr(a). <?php echo htmlspecialchars($doctor["nombres"] . " " . $doctor["apellidos"]); ?>
            - <?php echo htmlspecialchars($doctor["especialidad"]); ?>
        </option>
        <?php
    }
} else {
    ?>
    <option value="">No hay doctores registrados</option>
    <?php
}
?>
</select>
</div>

<!-- INFORMACIÓN DEL DOCTOR -->
<div class="info-doctor" id="infoDoctor">
<div><span>Especialidad</span><strong id="especialidadDoctor"></strong></div>
<div><span>Atención</span><strong id="horarioDoctor"></strong></div>
<div><span>Refrigerio</span><strong id="refrigerioDoctor"></strong></div>
<div><span>Duración</span><strong>30 minutos</strong></div>
</div>

<!-- CALENDARIO INTERACTIVO -->
<div class="campo">
<label id="etiquetaFecha">Fecha de la consulta</label>
<div class="calendario-wrapper">
    <div id="calendarioVisor"></div>
</div>
<!-- Campo oculto para enviar la fecha seleccionada -->
<input type="hidden" name="fecha" id="fecha" required>
</div>

</div>
<div class="reserva-columna reserva-columna-horarios">

<!-- HORARIOS -->
<div class="campo">
<label>Horario disponible</label>
<div id="mensajeHorarios" aria-live="polite">Selecciona un doctor y una fecha para consultar los turnos.</div>
<div id="listaHorarios" class="horarios"></div>
</div>

<input type="hidden" name="hora" id="hora">

<!-- MOTIVO -->
<div class="campo">
<label for="motivo">Motivo de consulta <span class="opcional">Opcional</span></label>
<textarea name="motivo" id="motivo" placeholder="Cuéntanos brevemente el motivo de tu consulta"></textarea>
</div>

</div>
</div>

<!-- BOTONES -->
<div class="botones reserva-acciones">
<button type="submit" class="btn-guardar" id="btnGuardar" disabled>
Confirmar cita
</button>
<a href="panel" class="boton btn-volver">Cancelar</a>
</div>

</form>

</div>
</section>
</main>

<!-- Scripts de Flatpickr (Calendario) -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>
<script src="js/javascript.js" defer></script>

</body>

</html>