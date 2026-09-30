<?php
session_start();
include "conexion.php";

/* =====================================================
   SEGURIDAD
===================================================== */

if (!isset($_SESSION["id"]) || $_SESSION["rol"] != 0) {
    header("Location: login.php");
    exit;
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

    $doctor_id = intval($_POST["doctor_id"] ?? 0);
    $fecha = $_POST["fecha"] ?? "";
    $hora = $_POST["hora"] ?? "";
    $motivo = trim($_POST["motivo"] ?? "");

    if ($doctor_id <= 0 || $fecha === "" || $hora === "") {

        $mensaje = "Complete todos los campos obligatorios.";
        $tipo_mensaje = "error";

    } else {

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
        ";

        $stmtDoctor = $conexion->prepare($sqlDoctor);
        $stmtDoctor->bind_param("i", $doctor_id);
        $stmtDoctor->execute();

        $resultDoctor = $stmtDoctor->get_result();

        if ($resultDoctor->num_rows == 0) {

            $mensaje = "El doctor seleccionado no existe.";
            $tipo_mensaje = "error";

        } else {

            $doctor = $resultDoctor->fetch_assoc();

            /* =========================================
               VALIDAR FECHA
            ========================================= */

            if ($fecha < date("Y-m-d")) {

                $mensaje = "No puede seleccionar una fecha anterior a hoy.";
                $tipo_mensaje = "error";

            } else {

                /* =====================================
                   VALIDAR HORARIO
                ===================================== */

                $inicio = new DateTime($doctor["hora_inicio"]);
                $fin = new DateTime($doctor["hora_fin"]);

                $horaSeleccionada = new DateTime($hora);

                /* Una cita dura 30 minutos */
                $finCita = clone $horaSeleccionada;
                $finCita->modify("+30 minutes");

                $horarioValido = true;

                if (
                    $horaSeleccionada < $inicio ||
                    $finCita > $fin
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
                    $horaSeleccionada <= new DateTime()
                ) {
                    $horarioValido = false;
                }

                if (!$horarioValido) {

                    $mensaje = "El horario seleccionado no está disponible para este doctor.";
                    $tipo_mensaje = "error";

                } else {

                    /* =================================
                       VERIFICAR CITA EXISTENTE (DOCTOR O PACIENTE)
                    ================================= */

                    $sqlExiste = "
                        SELECT id
                        FROM citas
                        WHERE (
                            (doctor_id = ? AND fecha = ? AND hora = ?)
                            OR
                            (paciente_id = ? AND fecha = ? AND hora = ?)
                        )
                        AND estado != 'cancelada'
                        LIMIT 1
                    ";

                    $stmtExiste = $conexion->prepare($sqlExiste);
                    $stmtExiste->bind_param(
                        "ississ",
                        $doctor_id,
                        $fecha,
                        $hora,
                        $paciente_id,
                        $fecha,
                        $hora
                    );

                    $stmtExiste->execute();
                    $resultExiste = $stmtExiste->get_result();

                    if ($resultExiste->num_rows > 0) {

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

                            $mensaje = "✅ Cita registrada correctamente.";
                            $tipo_mensaje = "success";

                        } else {

                            if ($conexion->errno == 1062) {
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

<style>

* {
    box-sizing: border-box;
}

body {
    font-family: Arial, sans-serif;
    background: #f4f6f9;
    margin: 0;
    padding: 30px;
}

.contenedor {
    max-width: 900px;
    margin: auto;
}

.cabecera {
    background: #1976d2;
    color: white;
    padding: 25px;
    border-radius: 12px 12px 0 0;
}

.cabecera h1 {
    margin: 0 0 8px;
}

.formulario {
    background: white;
    padding: 30px;
    border-radius: 0 0 12px 12px;
    box-shadow: 0 3px 12px rgba(0,0,0,.10);
}

.campo {
    margin-bottom: 25px;
}

.campo label {
    display: block;
    font-weight: bold;
    margin-bottom: 8px;
}

.campo select,
.campo textarea {
    width: 100%;
    padding: 12px;
    border: 1px solid #ccc;
    border-radius: 7px;
    font-size: 15px;
}

.campo textarea {
    resize: vertical;
    min-height: 100px;
}

.info-doctor {
    display: none;
    background: #eef6ff;
    border-left: 5px solid #1976d2;
    padding: 15px;
    margin-bottom: 20px;
    border-radius: 6px;
    line-height: 1.7;
}

/* Estilos para la integración del calendario */
.calendario-wrapper {
    display: flex;
    justify-content: center;
    background: #fafafa;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 15px;
}

.flatpickr-calendar.inline {
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}

.horarios {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 10px;
    margin-top: 10px;
}

.horario {
    padding: 13px;
    border: 2px solid #1976d2;
    background: white;
    color: #1976d2;
    border-radius: 7px;
    cursor: pointer;
    text-align: center;
    font-weight: bold;
    transition: all 0.2s ease;
}

.horario:hover {
    background: #1976d2;
    color: white;
}

.horario.seleccionado {
    background: #1976d2;
    color: white;
}

.mensaje {
    padding: 15px;
    border-radius: 7px;
    margin-bottom: 20px;
    font-weight: bold;
}

.mensaje.success {
    background: #dff5e4;
    color: #176b2c;
}

.mensaje.error {
    background: #fde2e2;
    color: #a32121;
}

.sin-horarios {
    background: #fff3cd;
    color: #856404;
    padding: 15px;
    border-radius: 7px;
}

.cargando {
    color: #1976d2;
    font-weight: bold;
    padding: 10px 0;
}

.botones {
    display: flex;
    gap: 10px;
    margin-top: 25px;
}

button,
.boton {
    border: none;
    padding: 12px 20px;
    border-radius: 7px;
    cursor: pointer;
    text-decoration: none;
    font-size: 15px;
    font-weight: bold;
}

.btn-guardar {
    background: #1976d2;
    color: white;
}

.btn-guardar:disabled {
    background: #aaa;
    cursor: not-allowed;
}

.btn-volver {
    background: #777;
    color: white;
}

@media (max-width: 700px) {
    .horarios {
        grid-template-columns: repeat(2, 1fr);
    }
}

</style>

</head>

<body>

<div class="contenedor">

<div class="cabecera">

<h1>📅 Sacar nueva cita</h1>

<p>
Paciente:
<strong>
<?php echo htmlspecialchars($paciente["nombres"] . " " . $paciente["apellidos"]); ?>
</strong>
</p>

</div>

<div class="formulario">

<?php if ($mensaje !== ""): ?>
<div class="mensaje <?php echo $tipo_mensaje; ?>">
    <?php echo htmlspecialchars($mensaje); ?>
</div>
<?php endif; ?>

<form method="POST" id="formCita">

<!-- DOCTOR -->
<div class="campo">
<label for="doctor_id">🩺 Seleccione el doctor</label>
<select name="doctor_id" id="doctor_id" required>
<option value="">-- Seleccione un doctor --</option>
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
🩺 Especialidad: <strong id="especialidadDoctor"></strong><br>
⏰ Horario: <strong id="horarioDoctor"></strong><br>
🍽️ Refrigerio: <strong id="refrigerioDoctor"></strong><br>
⌛ Duración de la cita: <strong>30 minutos</strong>
</div>

<!-- CALENDARIO INTERACTIVO -->
<div class="campo">
<label>📅 Seleccione la fecha en el calendario</label>
<div class="calendario-wrapper">
    <div id="calendarioVisor"></div>
</div>
<!-- Campo oculto para enviar la fecha seleccionada -->
<input type="hidden" name="fecha" id="fecha" required>
</div>

<!-- HORARIOS -->
<div class="campo">
<label>⏰ Horarios disponibles</label>
<div id="mensajeHorarios">Seleccione primero un doctor y una fecha.</div>
<div id="listaHorarios" class="horarios"></div>
</div>

<input type="hidden" name="hora" id="hora">

<!-- MOTIVO -->
<div class="campo">
<label for="motivo">📝 Motivo de la consulta</label>
<textarea name="motivo" id="motivo" placeholder="Describa brevemente el motivo de su consulta..."></textarea>
</div>

<!-- BOTONES -->
<div class="botones">
<button type="submit" class="btn-guardar" id="btnGuardar" disabled>
📅 Registrar cita
</button>
<a href="panel.php" class="boton btn-volver">⬅ Volver</a>
</div>

</form>

</div>

</div>

<!-- Scripts de Flatpickr (Calendario) -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>

<script>

const doctorSelect = document.getElementById("doctor_id");
const fechaInput = document.getElementById("fecha");
const listaHorarios = document.getElementById("listaHorarios");
const mensajeHorarios = document.getElementById("mensajeHorarios");
const horaInput = document.getElementById("hora");
const btnGuardar = document.getElementById("btnGuardar");

const infoDoctor = document.getElementById("infoDoctor");
const especialidadDoctor = document.getElementById("especialidadDoctor");
const horarioDoctor = document.getElementById("horarioDoctor");
const refrigerioDoctor = document.getElementById("refrigerioDoctor");

/* =====================================================
   INICIALIZAR CALENDARIO FLATPICKR
===================================================== */
const fp = flatpickr("#calendarioVisor", {
    inline: true,
    locale: "es",
    minDate: "today",
    dateFormat: "Y-m-d",
    onChange: function(selectedDates, dateStr) {
        fechaInput.value = dateStr;
        horaInput.value = "";
        btnGuardar.disabled = true;
        cargarHorarios();
    }
});

/* =====================================================
   CAMBIO DE DOCTOR
===================================================== */
doctorSelect.addEventListener("change", function() {

    const opcion = this.options[this.selectedIndex];

    horaInput.value = "";
    btnGuardar.disabled = true;
    listaHorarios.innerHTML = "";

    if (this.value !== "") {

        especialidadDoctor.textContent = opcion.dataset.especialidad;
        horarioDoctor.textContent =
            opcion.dataset.inicio.substring(0, 5) + " - " + opcion.dataset.fin.substring(0, 5);

        if (opcion.dataset.refrigerioInicio && opcion.dataset.refrigerioFin) {
            refrigerioDoctor.textContent =
                opcion.dataset.refrigerioInicio.substring(0, 5) + " - " + opcion.dataset.refrigerioFin.substring(0, 5);
        } else {
            refrigerioDoctor.textContent = "Sin refrigerio registrado";
        }

        infoDoctor.style.display = "block";

    } else {
        infoDoctor.style.display = "none";
    }

    cargarHorarios();
});

/* =====================================================
   CARGAR HORARIOS (AJAX)
===================================================== */
function cargarHorarios() {

    const doctorId = doctorSelect.value;
    const fecha = fechaInput.value;

    listaHorarios.innerHTML = "";
    horaInput.value = "";
    btnGuardar.disabled = true;

    if (doctorId === "" || fecha === "") {
        mensajeHorarios.innerHTML = "Seleccione primero un doctor y una fecha en el calendario.";
        return;
    }

    mensajeHorarios.innerHTML = '<div class="cargando">⏳ Consultando horarios...</div>';

    fetch("horarios_disponibles.php?doctor_id=" + encodeURIComponent(doctorId) + "&fecha=" + encodeURIComponent(fecha))
    .then(function(response) {
        return response.json();
    })
    .then(function(data) {

        listaHorarios.innerHTML = "";

        if (data.error) {
            mensajeHorarios.innerHTML = '<div class="sin-horarios">' + data.error + '</div>';
            return;
        }

        if (!data.horarios || data.horarios.length === 0) {
            mensajeHorarios.innerHTML = '<div class="sin-horarios">❌ No hay horarios disponibles para esta fecha.</div>';
            return;
        }

        mensajeHorarios.innerHTML = '<strong>🟢 Seleccione un horario libre:</strong>';

        data.horarios.forEach(function(horario) {

            const boton = document.createElement("div");
            boton.className = "horario";
            boton.textContent = horario.substring(0, 5);

            boton.addEventListener("click", function() {

                document.querySelectorAll(".horario").forEach(function(elemento) {
                    elemento.classList.remove("seleccionado");
                });

                boton.classList.add("seleccionado");
                horaInput.value = horario;
                btnGuardar.disabled = false;
            });

            listaHorarios.appendChild(boton);
        });

    })
    .catch(function(error) {
        console.error(error);
        mensajeHorarios.innerHTML = '<div class="sin-horarios">❌ Error al consultar los horarios.</div>';
    });
}

</script>

</body>

</html>