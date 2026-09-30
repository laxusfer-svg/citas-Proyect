<?php

session_start();
include "conexion.php";

// Solo puede acceder un doctor
if (!isset($_SESSION["id"]) || $_SESSION["rol"] != 1) {
    header("Location: index.php");
    exit;
}

$usuario_id = $_SESSION["id"];

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $cita_id = (int)$_POST["cita_id"];
    $estado = $_POST["estado"];

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

        header("Location: panel.php");
        exit;

    } else {

        echo "Error al actualizar la cita: " . $stmt->error;
    }
}
?>