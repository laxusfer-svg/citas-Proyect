<?php

session_start();

include "conexion.php";


/* =====================================================
   VERIFICAR SESIÓN
===================================================== */

if (!isset($_SESSION["id"]) || $_SESSION["rol"] != 0) {

    header("Content-Type: application/json");

    echo json_encode([
        "error" => "No autorizado."
    ]);

    exit;
}


/* =====================================================
   RECIBIR DATOS
===================================================== */

$doctor_id = intval($_GET["doctor_id"] ?? 0);

$fecha = $_GET["fecha"] ?? "";


/* =====================================================
   VALIDAR
===================================================== */

if ($doctor_id <= 0 || $fecha == "") {

    header("Content-Type: application/json");

    echo json_encode([
        "error" => "Seleccione un doctor y una fecha."
    ]);

    exit;
}

$fechaValidada = DateTime::createFromFormat("!Y-m-d", $fecha);
$erroresFecha = DateTime::getLastErrors();

if (
    $fechaValidada === false ||
    ($erroresFecha !== false && ($erroresFecha["warning_count"] > 0 || $erroresFecha["error_count"] > 0)) ||
    $fechaValidada->format("Y-m-d") !== $fecha ||
    $fecha < date("Y-m-d")
) {
    header("Content-Type: application/json");
    echo json_encode(["error" => "Seleccione una fecha válida, igual o posterior a hoy."]);
    exit;
}


/* =====================================================
   OBTENER HORARIO DEL DOCTOR
===================================================== */

$sqlDoctor = "
    SELECT
        hora_inicio,
        hora_fin,
        refrigerio_inicio,
        refrigerio_fin
    FROM doctores
    WHERE id = ?
";

$stmtDoctor = $conexion->prepare($sqlDoctor);

$stmtDoctor->bind_param(
    "i",
    $doctor_id
);

$stmtDoctor->execute();

$resultDoctor = $stmtDoctor->get_result();


if ($resultDoctor->num_rows == 0) {

    header("Content-Type: application/json");

    echo json_encode([
        "error" => "No se encontró el doctor."
    ]);

    exit;
}


$doctor = $resultDoctor->fetch_assoc();


/* =====================================================
   HORARIOS DEL DOCTOR
===================================================== */

$horaInicio = $doctor["hora_inicio"];

$horaFin = $doctor["hora_fin"];

$refrigerioInicio = $doctor["refrigerio_inicio"];

$refrigerioFin = $doctor["refrigerio_fin"];


/* =====================================================
   OBTENER CITAS OCUPADAS
===================================================== */

$sqlCitas = "
    SELECT hora
    FROM citas
    WHERE doctor_id = ?
    AND fecha = ?
    AND estado != 'cancelada'
";

$stmtCitas = $conexion->prepare($sqlCitas);

$stmtCitas->bind_param(
    "is",
    $doctor_id,
    $fecha
);

$stmtCitas->execute();

$resultCitas = $stmtCitas->get_result();


$horariosOcupados = [];


while ($fila = $resultCitas->fetch_assoc()) {

    $horariosOcupados[] = new DateTime($fila["hora"]);
}


/* =====================================================
   GENERAR HORARIOS
===================================================== */

$horariosDisponibles = [];


$inicio = new DateTime($horaInicio);

$fin = new DateTime($horaFin);


/*
 * Una cita dura 30 minutos.
 *
 * Por eso la hora de inicio debe permitir
 * que la cita termine antes de la hora de salida.
 */

$limiteUltimaCita = clone $fin;

$limiteUltimaCita->modify("-30 minutes");


while ($inicio <= $limiteUltimaCita) {

    $horaActual = $inicio->format("H:i");

    $horaActualCompleta = $inicio->format("H:i:s");
    $finCita = clone $inicio;
    $finCita->modify("+30 minutes");


    /* =================================================
       COMPROBAR REFRIGERIO
    ================================================= */

    $estaEnRefrigerio = false;


    if (
        !empty($refrigerioInicio) &&
        !empty($refrigerioFin)
    ) {

        $inicioRefrigerio =
            new DateTime($refrigerioInicio);

        $finRefrigerio =
            new DateTime($refrigerioFin);


        /*
         * Una cita de 30 minutos tampoco puede
         * cruzarse con el refrigerio.
         */

        if (
            $inicio < $finRefrigerio &&
            $finCita > $inicioRefrigerio
        ) {

            $estaEnRefrigerio = true;
        }
    }


    /* =================================================
       COMPROBAR SI ESTÁ OCUPADO
    ================================================= */

    $estaOcupado = false;

    foreach ($horariosOcupados as $horaOcupada) {
        $finCitaOcupada = clone $horaOcupada;
        $finCitaOcupada->modify("+30 minutes");

        if ($inicio < $finCitaOcupada && $finCita > $horaOcupada) {
            $estaOcupado = true;
            break;
        }
    }


    /* =================================================
       SI ES HOY, NO MOSTRAR HORAS PASADAS
    ================================================= */

    $horaPasada = false;


    if ($fecha == date("Y-m-d")) {

        if ($horaActual <= date("H:i")) {

            $horaPasada = true;
        }
    }


    /* =================================================
       AGREGAR HORARIO DISPONIBLE
    ================================================= */

    if (
        !$estaEnRefrigerio &&
        !$estaOcupado &&
        !$horaPasada
    ) {

        $horariosDisponibles[] =
            $horaActualCompleta;
    }


    /*
     * Siguiente cita:
     * +30 minutos
     */

    $inicio->modify("+30 minutes");
}


/* =====================================================
   RESPUESTA
===================================================== */

header("Content-Type: application/json");

echo json_encode([
    "horarios" => $horariosDisponibles,
    "hora_inicio" => $horaInicio,
    "hora_fin" => $horaFin,
    "refrigerio_inicio" => $refrigerioInicio,
    "refrigerio_fin" => $refrigerioFin
]);

?>