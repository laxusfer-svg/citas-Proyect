<?php

session_start();
include "conexion.php";

// Solo puede acceder un doctor
if (!isset($_SESSION["id"]) || $_SESSION["rol"] != 1) {
    header("Location: /");
    exit;
}

if (empty($_SESSION["csrf_token"]) || !hash_equals($_SESSION["csrf_token"], $_POST["csrf_token"] ?? "")) {
    http_response_code(403);
    die("Solicitud no válida.");
}

$usuario_id = $_SESSION["id"];

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $cita_id = (int)($_POST["cita_id"] ?? 0);
    $estado = $_POST["estado"] ?? "";

    // Estados permitidos
    $estados_permitidos = [
        "pendiente",
        "confirmada",
        "atendida",
        "cancelada"
    ];

    if (!in_array($estado, $estados_permitidos)) {
        die("Estado no válido.");
    }

    /*
     * Obtener el doctor correspondiente
     * al usuario que inició sesión
     */

    $sql = "SELECT id
            FROM doctores
            WHERE usuario_id = ?";

    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();

    $resultado = $stmt->get_result();

    if ($resultado->num_rows == 0) {
        die("Doctor no encontrado.");
    }

    $doctor = $resultado->fetch_assoc();

    $doctor_id = $doctor["id"];

    $conexion->begin_transaction();

    $sqlEstadoActual = "SELECT estado FROM citas WHERE id = ? AND doctor_id = ? FOR UPDATE";
    $stmtEstadoActual = $conexion->prepare($sqlEstadoActual);
    $stmtEstadoActual->bind_param("ii", $cita_id, $doctor_id);
    $stmtEstadoActual->execute();
    $resultadoEstadoActual = $stmtEstadoActual->get_result();

    if ($resultadoEstadoActual->num_rows === 0) {
        $conexion->rollback();
        http_response_code(404);
        die("Cita no encontrada.");
    }

    $estadoActual = $resultadoEstadoActual->fetch_assoc()["estado"];
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
        header("Location: panel");
        exit;
    }


    /*
     * Actualizar SOLO una cita
     * que pertenezca a este doctor
     */

    $sql = "UPDATE citas
            SET estado = ?
            WHERE id = ?
            AND doctor_id = ?";

    $stmt = $conexion->prepare($sql);

    $stmt->bind_param(
        "sii",
        $estado,
        $cita_id,
        $doctor_id
    );

    if ($stmt->execute()) {

        $conexion->commit();
        header("Location: panel");
        exit;

    } else {

        $conexion->rollback();
        echo "Error al actualizar la cita: " . $stmt->error;
    }
}
?>