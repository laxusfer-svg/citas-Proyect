<?php
session_start();

// Solo administradores
if (!isset($_SESSION["id"]) || ($_SESSION["rol"] ?? null) != 2) {
    http_response_code(403);
    exit("Acceso denegado");
}

// Leer configuración privada
$config = parse_ini_file(
    "C:/xampp/private/config/reportes.ini",
    false,
    INI_SCANNER_RAW
);

if ($config === false ||
    empty($config["DB_USER"]) ||
    !isset($config["DB_PASSWORD"]) ||
    $config["DB_PASSWORD"] === "") {
    http_response_code(500);
    exit("Error de configuración del reporte.");
}

$java = "C:/Program Files/Java/jdk-27.0.1.0.3/bin/java.exe";
$jar = "C:/xampp/private/java/modulo-reportes.jar";
$carpeta = "C:/xampp/private/reportes";

// Nombre único para cada descarga
$archivo = $carpeta . "/reporte_" .
    bin2hex(random_bytes(12)) . ".xlsx";

if (!is_file($java) || !is_file($jar) || !is_dir($carpeta)) {
    http_response_code(500);
    exit("No se encontraron los archivos necesarios.");
}

// Ejecutar Java sin colocar la contraseña en el comando
// Recibir los filtros desde el formulario
$fechaInicio = $_GET["fecha_inicio"] ?? "";
$fechaFin = $_GET["fecha_fin"] ?? "";
$dni = trim($_GET["dni"] ?? "");
$estado = $_GET["estado"] ?? "";

// Validar las fechas
foreach ([$fechaInicio, $fechaFin] as $fecha) {
    if ($fecha !== "") {
        $f = DateTimeImmutable::createFromFormat("!Y-m-d", $fecha);

        if (!$f || $f->format("Y-m-d") !== $fecha) {
            http_response_code(400);
            exit("Fecha no válida.");
        }
    }
}

// Validar el DNI
if (!preg_match('/^[0-9]{0,8}$/', $dni)) {
    http_response_code(400);
    exit("DNI no válido.");
}

// Validar el estado
$estadosPermitidos = [
    "",
    "pendiente",
    "confirmada",
    "atendida",
    "cancelada"
];

if (!in_array($estado, $estadosPermitidos, true)) {
    http_response_code(400);
    exit("Estado no válido.");
}

// Enviar los filtros al módulo Java
$comando = [
    $java,
    "-jar",
    $jar,
    $archivo,
    $fechaInicio,
    $fechaFin,
    $dni,
    $estado
];

$entorno = [
    "DB_HOST" => $config["DB_HOST"] ?? "localhost",
    "DB_NAME" => $config["DB_NAME"] ?? "sistema",
    "DB_USER" => $config["DB_USER"],
    "DB_PASSWORD" => $config["DB_PASSWORD"],
    "SystemRoot" => getenv("SystemRoot") ?: "C:\\Windows",
    "TEMP" => getenv("TEMP") ?: "C:\\Windows\\Temp",
    "TMP" => getenv("TMP") ?: "C:\\Windows\\Temp"
];

$descriptores = [
    0 => ["pipe", "r"],
    1 => ["pipe", "w"],
    2 => ["pipe", "w"]
];

$proceso = proc_open(
    $comando,
    $descriptores,
    $tuberias,
    $carpeta,
    $entorno
);

if (!is_resource($proceso)) {
    http_response_code(500);
    exit("No se pudo iniciar Java.");
}

fclose($tuberias[0]);

$salida = stream_get_contents($tuberias[1]);
fclose($tuberias[1]);

$errores = stream_get_contents($tuberias[2]);
fclose($tuberias[2]);

$codigo = proc_close($proceso);

if ($codigo !== 0 || !is_file($archivo) || filesize($archivo) === 0) {
    if (is_file($archivo)) {
        unlink($archivo);
    }

    error_log(
    "Error al generar Excel con Java. Código: " .
    $codigo . " | Detalle: " . $errores .
    " | Salida: " . $salida
);

    http_response_code(500);
    exit("No se pudo generar el reporte Excel.");
}

// Descargar el archivo
header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
header('Content-Disposition: attachment; filename="reporte_citas_java.xlsx"');
header("Content-Length: " . filesize($archivo));
header("Cache-Control: no-store");

readfile($archivo);

// Eliminar el archivo temporal
unlink($archivo);
exit;
