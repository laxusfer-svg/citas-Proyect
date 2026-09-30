<?php

$host = getenv("DB_HOST") ?: "localhost";
$usuario = getenv("DB_USER") ?: "root";
$password = getenv("DB_PASSWORD")?: "Prueba123$";
$baseDatos = getenv("DB_NAME") ?: "sistema";

if ($password === false) {
    $password = "";
}

$conexion = new mysqli($host, $usuario, $password, $baseDatos);

if ($conexion->connect_error) {
    error_log("Error de conexión a la base de datos: " . $conexion->connect_error);
    die("No se pudo conectar con la base de datos.");
}

?>