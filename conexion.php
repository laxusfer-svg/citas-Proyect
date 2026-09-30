<?php

$conexion = new mysqli("localhost", "root", "Prueba123$", "sistema");

if ($conexion->connect_error) {
    die("Error de conexión: " . $conexion->connect_error);
}

?>