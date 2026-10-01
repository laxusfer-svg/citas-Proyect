<?php

session_start();
include "conexion.php";

if (!isset($_SESSION["id"]) || !isset($_SESSION["rol"]) || !in_array((int)$_SESSION["rol"], [1, 2], true)) {
    header("Location: /");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    exit("Método no permitido.");
}

if (empty($_SESSION["csrf_token"]) || !hash_equals($_SESSION["csrf_token"], $_POST["csrf_token"] ?? "")) {
    http_response_code(403);
    die("Solicitud no válida.");
}

$rol = (int)$_SESSION["rol"];
$usuario_id = (int)$_SESSION["id"];
$origen = $_POST["origen"] ?? "";
$destinos = ["reporte" => "reporte_citas", "citas" => "citas_admin"];
$destino = $rol === 2 && isset($destinos[$origen]) ? $destinos[$origen] : "panel";

    $cita_id = (int)($_POST["cita_id"] ?? 0);
    $estado = $_POST["estado"] ?? "";

    $estadosPermitidos = ["pendiente", "confirmada", "atendida", "cancelada"];

    if ($cita_id <= 0 || !in_array($estado, $estadosPermitidos, true)) {
        http_response_code(400);
        die("La cita o el estado no son válidos.");
    }

    $doctor_id = null;

    if ($rol === 1) {
        $sqlDoctor = "SELECT id FROM doctores WHERE usuario_id = ? LIMIT 1";
        $stmtDoctor = $conexion->prepare($sqlDoctor);
        $stmtDoctor->bind_param("i", $usuario_id);
        $stmtDoctor->execute();
        $resultadoDoctor = $stmtDoctor->get_result();
        $doctor = $resultadoDoctor->fetch_assoc();
        $resultadoDoctor->free();
        $stmtDoctor->close();

        if (!$doctor) {
            http_response_code(403);
            die("Doctor no encontrado.");
        }

        $doctor_id = (int)$doctor["id"];
    }

    $conexion->begin_transaction();

    if ($rol === 2) {
        $sqlEstadoActual = "SELECT estado FROM citas WHERE id = ? FOR UPDATE";
        $stmtEstadoActual = $conexion->prepare($sqlEstadoActual);
        $stmtEstadoActual->bind_param("i", $cita_id);
    } else {
        $sqlEstadoActual = "SELECT estado FROM citas WHERE id = ? AND doctor_id = ? FOR UPDATE";
        $stmtEstadoActual = $conexion->prepare($sqlEstadoActual);
        $stmtEstadoActual->bind_param("ii", $cita_id, $doctor_id);
    }

    $stmtEstadoActual->execute();
    $resultadoEstadoActual = $stmtEstadoActual->get_result();

    if ($resultadoEstadoActual->num_rows === 0) {
        $resultadoEstadoActual->free();
        $stmtEstadoActual->close();
        $conexion->rollback();
        http_response_code(404);
        die("Cita no encontrada.");
    }

    $estadoActual = $resultadoEstadoActual->fetch_assoc()["estado"];
    $resultadoEstadoActual->free();
    $stmtEstadoActual->close();
    $transiciones = [
        "pendiente" => ["confirmada", "cancelada"],
        "confirmada" => ["atendida", "cancelada"],
        "atendida" => [],
        "cancelada" => []
    ];

    if ($estadoActual !== $estado && !in_array($estado, $transiciones[$estadoActual], true)) {
        $conexion->rollback();
        http_response_code(400);
        die("La transición de estado no está permitida.");
    }

    if ($estadoActual === $estado) {
        $conexion->commit();
        header("Location: " . $destino);
        exit;
    }


    if ($rol === 2) {
        $sql = "UPDATE citas SET estado = ? WHERE id = ?";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("si", $estado, $cita_id);
    } else {
        $sql = "UPDATE citas SET estado = ? WHERE id = ? AND doctor_id = ?";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("sii", $estado, $cita_id, $doctor_id);
    }

    if ($stmt->execute()) {

        $conexion->commit();
        header("Location: " . $destino);
        exit;

    } else {

        $conexion->rollback();
        echo "Error al actualizar la cita: " . $stmt->error;
    }
?>