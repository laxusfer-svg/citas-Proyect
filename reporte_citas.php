<?php
session_start();
include "conexion.php";

if (!isset($_SESSION["id"]) || $_SESSION["rol"] != 2) {
    header("Location: /");
    exit;
}

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$transicionesEstadoCita = [
    "pendiente" => ["confirmada", "cancelada"],
    "confirmada" => ["atendida", "cancelada"],
    "atendida" => [],
    "cancelada" => []
];
$etiquetasEstadoCita = [
    "pendiente" => "Pendiente",
    "confirmada" => "Confirmada",
    "atendida" => "Atendida",
    "cancelada" => "Cancelada"
];

$fecha_inicio = $_GET["fecha_inicio"] ?? "";
$fecha_fin = $_GET["fecha_fin"] ?? "";
$dni = trim($_GET["dni"] ?? "");

/* ==========================================
   CONDICIONES DEL FILTRO
========================================== */

$where = [];
$params = [];
$types = "";

if ($fecha_inicio != "") {
    $where[] = "c.fecha >= ?";
    $params[] = $fecha_inicio;
    $types .= "s";
}

if ($fecha_fin != "") {
    $where[] = "c.fecha <= ?";
    $params[] = $fecha_fin;
    $types .= "s";
}

if ($dni != "") {
    $where[] = "p.dni LIKE ?";
    $params[] = "%" . $dni . "%";
    $types .= "s";
}

$whereSQL = "";

if (count($where) > 0) {
    $whereSQL = "WHERE " . implode(" AND ", $where);
}


/* ==========================================
   TOTAL DE CITAS
========================================== */

$sqlTotal = "
    SELECT COUNT(*) AS total
    FROM citas c
    INNER JOIN pacientes p ON c.paciente_id = p.id
    $whereSQL
";

$stmt = $conexion->prepare($sqlTotal);

if ($types != "") {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$total = $stmt->get_result()->fetch_assoc()["total"];


/* ==========================================
   CITAS POR ESTADO
========================================== */

$sqlEstados = "
    SELECT c.estado, COUNT(*) AS cantidad
    FROM citas c
    INNER JOIN pacientes p ON c.paciente_id = p.id
    $whereSQL
    GROUP BY c.estado
    ORDER BY cantidad DESC
";

$stmt = $conexion->prepare($sqlEstados);

if ($types != "") {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$resultEstados = $stmt->get_result();

$estados = [
    "atendida" => 0,
    "pendiente" => 0,
    "confirmada" => 0,
    "cancelada" => 0
];

while ($fila = $resultEstados->fetch_assoc()) {
    $estados[$fila["estado"]] = (int)$fila["cantidad"];
}


/* ==========================================
   CITAS POR ESPECIALIDAD
========================================== */

$sqlEspecialidades = "
    SELECT
        d.especialidad,
        COUNT(*) AS total,
        SUM(c.estado = 'atendida') AS atendidas,
        SUM(c.estado = 'pendiente') AS pendientes,
        SUM(c.estado = 'confirmada') AS confirmadas,
        SUM(c.estado = 'cancelada') AS canceladas
    FROM citas c
    INNER JOIN pacientes p ON c.paciente_id = p.id
    INNER JOIN doctores d ON c.doctor_id = d.id
    $whereSQL
    GROUP BY d.especialidad
    ORDER BY total DESC
";

$stmt = $conexion->prepare($sqlEspecialidades);

if ($types != "") {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$resultEspecialidades = $stmt->get_result();

$datosEspecialidades = [];
while ($fila = $resultEspecialidades->fetch_assoc()) {
    $datosEspecialidades[] = $fila;
}


/* ==========================================
   CITAS POR MES
========================================== */

$sqlMes = "
    SELECT
        DATE_FORMAT(c.fecha, '%Y-%m') AS mes,
        COUNT(*) AS total,
        SUM(c.estado = 'atendida') AS atendidas,
        SUM(c.estado = 'pendiente') AS pendientes,
        SUM(c.estado = 'confirmada') AS confirmadas,
        SUM(c.estado = 'cancelada') AS canceladas
    FROM citas c
    INNER JOIN pacientes p ON c.paciente_id = p.id
    $whereSQL
    GROUP BY DATE_FORMAT(c.fecha, '%Y-%m')
    ORDER BY mes DESC
";

$stmt = $conexion->prepare($sqlMes);

if ($types != "") {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$resultMes = $stmt->get_result();

$datosMes = [];
while ($fila = $resultMes->fetch_assoc()) {
    $datosMes[] = $fila;
}


/* ==========================================
   DETALLE DE CITAS
========================================== */

$sqlDetalle = "
    SELECT
        c.id,
        c.fecha,
        c.hora,
        c.modalidad,
        c.estado,
        c.motivo,

        p.dni,
        p.nombres AS paciente_nombres,
        p.apellidos AS paciente_apellidos,

        d.nombres AS doctor_nombres,
        d.apellidos AS doctor_apellidos,
        d.especialidad

    FROM citas c

    INNER JOIN pacientes p
        ON c.paciente_id = p.id

    INNER JOIN doctores d
        ON c.doctor_id = d.id

    $whereSQL

    ORDER BY c.fecha DESC, c.hora DESC
";

$stmt = $conexion->prepare($sqlDetalle);

if ($types != "") {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$resultDetalle = $stmt->get_result();

?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Reporte de Citas</title>
    <!-- Librería Chart.js para gráficos -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    
    <link rel="stylesheet" href="css/style.css">
</head>

<body class="page-reporte">

<div class="contenedor">

    <h1>📊 Reporte de Citas</h1>

    <p>
        Administrador:
        <strong><?php echo htmlspecialchars($_SESSION["usuario"]); ?></strong>
    </p>

    <!-- =====================================
         FILTROS
    ===================================== -->
    <div class="filtros">
        <h2>🔎 Filtrar citas</h2>
        <form method="GET">
            <div class="campo">
                <label>Fecha inicio</label>
                <input type="date" name="fecha_inicio" value="<?php echo htmlspecialchars($fecha_inicio); ?>">
            </div>

            <div class="campo">
                <label>Fecha fin</label>
                <input type="date" name="fecha_fin" value="<?php echo htmlspecialchars($fecha_fin); ?>">
            </div>

            <div class="campo">
                <label>DNI del paciente</label>
                <input type="text" name="dni" placeholder="Ingrese DNI" value="<?php echo htmlspecialchars($dni); ?>">
            </div>

            <button type="submit">🔎 Buscar</button>
            <a href="reporte_citas" class="boton boton-limpiar">Limpiar</a>
        </form>
    </div>

    <!-- =====================================
         RESUMEN (TARJETAS)
    ===================================== -->
    <div class="tarjetas">
        <div class="tarjeta">
            <h3>Total</h3>
            <div class="numero"><?php echo $total; ?></div>
        </div>

        <div class="tarjeta">
            <h3>Atendidas</h3>
            <div class="numero"><?php echo $estados["atendida"]; ?></div>
        </div>

        <div class="tarjeta">
            <h3>Pendientes</h3>
            <div class="numero"><?php echo $estados["pendiente"]; ?></div>
        </div>

        <div class="tarjeta">
            <h3>Confirmadas</h3>
            <div class="numero"><?php echo $estados["confirmada"]; ?></div>
        </div>

        <div class="tarjeta">
            <h3>Canceladas</h3>
            <div class="numero"><?php echo $estados["cancelada"]; ?></div>
        </div>
    </div>

    <!-- =====================================
         GRÁFICOS ESTADÍSTICOS
    ===================================== -->
    <div class="graficos-grid">
        <div class="tarjeta-grafico">
            <h2>🍩 Citas por Estado</h2>
            <div class="contenedor-canvas">
                <canvas id="chartEstados"></canvas>
            </div>
        </div>

        <div class="tarjeta-grafico">
            <h2>📊 Total Citas por Especialidad</h2>
            <div class="contenedor-canvas">
                <canvas id="chartEspecialidades"></canvas>
            </div>
        </div>

        <div class="tarjeta-grafico tarjeta-grafico-full">
            <h2>📈 Evolución Mensual por Estado</h2>
            <div class="contenedor-canvas">
                <canvas id="chartMes"></canvas>
            </div>
        </div>
    </div>

    <!-- =====================================
         POR MES (TABLA)
    ===================================== -->
    <div class="seccion">
        <h2>📅 Citas por mes</h2>
        <table>
            <thead>
                <tr>
                    <th>Mes</th>
                    <th>Total</th>
                    <th>Atendidas</th>
                    <th>Pendientes</th>
                    <th>Confirmadas</th>
                    <th>Canceladas</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($datosMes as $fila): ?>
                <tr>
                    <td><?php echo htmlspecialchars($fila["mes"]); ?></td>
                    <td><?php echo $fila["total"]; ?></td>
                    <td class="estado-atendida"><?php echo $fila["atendidas"]; ?></td>
                    <td class="estado-pendiente"><?php echo $fila["pendientes"]; ?></td>
                    <td class="estado-confirmada"><?php echo $fila["confirmadas"]; ?></td>
                    <td class="estado-cancelada"><?php echo $fila["canceladas"]; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- =====================================
         POR ESPECIALIDAD (TABLA)
    ===================================== -->
    <div class="seccion">
        <h2>🩺 Reporte por especialidad</h2>
        <table>
            <thead>
                <tr>
                    <th>Especialidad</th>
                    <th>Total</th>
                    <th>Atendidas</th>
                    <th>Pendientes</th>
                    <th>Confirmadas</th>
                    <th>Canceladas</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($datosEspecialidades as $fila): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($fila["especialidad"]); ?></strong></td>
                    <td><?php echo $fila["total"]; ?></td>
                    <td><?php echo $fila["atendidas"]; ?></td>
                    <td><?php echo $fila["pendientes"]; ?></td>
                    <td><?php echo $fila["confirmadas"]; ?></td>
                    <td><?php echo $fila["canceladas"]; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- =====================================
         DETALLE DE CITAS (TABLA)
    ===================================== -->
    <div class="seccion">
        <h2>📋 Detalle de citas</h2>
        <table>
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Hora</th>
                    <th>Modalidad</th>
                    <th>DNI</th>
                    <th>Paciente</th>
                    <th>Doctor</th>
                    <th>Especialidad</th>
                    <th>Motivo</th>
                    <th>Estado</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($fila = $resultDetalle->fetch_assoc()): ?>
                <tr>
                    <td><?php echo date("d/m/Y", strtotime($fila["fecha"])); ?></td>
                    <td><?php echo substr($fila["hora"], 0, 5); ?></td>
                    <td><span class="badge-modalidad modalidad-<?php echo htmlspecialchars($fila["modalidad"], ENT_QUOTES, "UTF-8"); ?>"><?php echo $fila["modalidad"] === "virtual" ? "Virtual" : "Presencial"; ?></span></td>
                    <td><?php echo htmlspecialchars($fila["dni"]); ?></td>
                    <td><?php echo htmlspecialchars($fila["paciente_nombres"] . " " . $fila["paciente_apellidos"]); ?></td>
                    <td><?php echo htmlspecialchars("Dr. " . $fila["doctor_nombres"] . " " . $fila["doctor_apellidos"]); ?></td>
                    <td><?php echo htmlspecialchars($fila["especialidad"]); ?></td>
                    <td><?php echo htmlspecialchars($fila["motivo"]); ?></td>
                    <td class="estado-<?php echo $fila["estado"]; ?>">
                        <?php
                        $estadosTexto = [
                            "pendiente" => "Pendiente",
                            "confirmada" => "Confirmada",
                            "atendida" => "Atendida",
                            "cancelada" => "Cancelada"
                        ];
                        echo $estadosTexto[$fila["estado"]] ?? $fila["estado"];
                        ?>
                    </td>
                    <td>
                        <?php $opcionesEstado = $transicionesEstadoCita[$fila["estado"]] ?? []; ?>
                        <?php if ($opcionesEstado): ?>
                            <form method="POST" action="actualizar_cita" class="form-estado-cita">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION["csrf_token"], ENT_QUOTES, "UTF-8"); ?>">
                                <input type="hidden" name="cita_id" value="<?php echo (int)$fila["id"]; ?>">
                                <input type="hidden" name="origen" value="reporte">
                                <label class="sr-only" for="estado-reporte-<?php echo (int)$fila["id"]; ?>">Nuevo estado de cita</label>
                                <select id="estado-reporte-<?php echo (int)$fila["id"]; ?>" name="estado" required>
                                    <option value="" selected disabled>Cambiar estado</option>
                                    <?php foreach ($opcionesEstado as $opcionEstado): ?>
                                        <option value="<?php echo htmlspecialchars($opcionEstado); ?>"><?php echo htmlspecialchars($etiquetasEstadoCita[$opcionEstado]); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit">Guardar</button>
                            </form>
                        <?php else: ?>
                            <span class="accion-finalizada">Sin cambios</span>
                        <?php endif; ?>
                        <?php if (in_array($fila["estado"], ["pendiente", "confirmada"], true)): ?>
                            <a class="reprogramar-link" href="reprogramar_cita?id=<?php echo (int)$fila["id"]; ?>&origen=reporte">Reprogramar</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <a href="panel" class="boton">⬅ Volver al panel</a>

</div>

<!-- =====================================
     INICIALIZACIÓN DE CHART.JS
===================================== -->
<script type="application/json" id="report-chart-data"><?php
echo json_encode([
    "estados" => $estados,
    "especialidades" => $datosEspecialidades,
    "meses" => array_reverse($datosMes)
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
?></script>
<script src="js/javascript.js" defer></script>

</body>

</html>