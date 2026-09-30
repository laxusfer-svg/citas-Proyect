<?php
session_start();
include "conexion.php";

if (!isset($_SESSION["id"]) || $_SESSION["rol"] != 2) {
    header("Location: login.php");
    exit;
}

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

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            margin: 0;
            padding: 30px;
        }

        h1 {
            color: #222;
        }

        .contenedor {
            max-width: 1400px;
            margin: auto;
        }

        .tarjetas {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 15px;
            margin: 20px 0;
        }

        .tarjeta {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,.08);
        }

        .tarjeta h3 {
            margin: 0 0 10px;
        }

        .numero {
            font-size: 30px;
            font-weight: bold;
        }

        .filtros {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .filtros form {
            display: flex;
            gap: 15px;
            align-items: end;
            flex-wrap: wrap;
        }

        .campo {
            display: flex;
            flex-direction: column;
        }

        .campo label {
            margin-bottom: 5px;
            font-weight: bold;
        }

        input {
            padding: 9px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }

        button,
        .boton {
            padding: 10px 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            background: #1976d2;
            color: white;
        }

        .boton-limpiar {
            background: #777;
        }

        .seccion {
            background: white;
            padding: 20px;
            margin-bottom: 25px;
            border-radius: 10px;
            overflow-x: auto;
        }

        /* Contenedores de Gráficos */
        .graficos-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 25px;
        }

        .tarjeta-grafico {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,.08);
        }

        .tarjeta-grafico-full {
            grid-column: 1 / -1;
        }

        .contenedor-canvas {
            position: relative;
            height: 320px;
            width: 100%;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 10px;
            border-bottom: 1px solid #ddd;
            text-align: left;
        }

        th {
            background: #f0f2f5;
        }

        .estado-atendida {
            color: green;
            font-weight: bold;
        }

        .estado-pendiente {
            color: #d68910;
            font-weight: bold;
        }

        .estado-confirmada {
            color: #1976d2;
            font-weight: bold;
        }

        .estado-cancelada {
            color: red;
            font-weight: bold;
        }

        @media(max-width: 900px) {
            .tarjetas {
                grid-template-columns: repeat(2, 1fr);
            }
            .graficos-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

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
            <a href="reporte_citas.php" class="boton boton-limpiar">Limpiar</a>
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
                    <th>DNI</th>
                    <th>Paciente</th>
                    <th>Doctor</th>
                    <th>Especialidad</th>
                    <th>Motivo</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($fila = $resultDetalle->fetch_assoc()): ?>
                <tr>
                    <td><?php echo date("d/m/Y", strtotime($fila["fecha"])); ?></td>
                    <td><?php echo substr($fila["hora"], 0, 5); ?></td>
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
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <a href="panel.php" class="boton">⬅ Volver al panel</a>

</div>

<!-- =====================================
     INICIALIZACIÓN DE CHART.JS
===================================== -->
<script>
    // Inyección de variables PHP a JavaScript
    const datosEstados = <?php echo json_encode($estados); ?>;
    const datosEspecialidades = <?php echo json_encode($datosEspecialidades); ?>;
    const datosMes = <?php echo json_encode(array_reverse($datosMes)); ?>; // Orden cronológico ascendente para el gráfico

    // 1. Gráfico Circular (Dona) - Estados de Citas
    new Chart(document.getElementById('chartEstados'), {
        type: 'doughnut',
        data: {
            labels: ['Atendidas', 'Pendientes', 'Confirmadas', 'Canceladas'],
            datasets: [{
                data: [
                    datosEstados.atendida || 0,
                    datosEstados.pendiente || 0,
                    datosEstados.confirmada || 0,
                    datosEstados.cancelada || 0
                ],
                backgroundColor: ['#28a745', '#d68910', '#1976d2', '#dc3545']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom' }
            }
        }
    });

    // 2. Gráfico de Barras - Citas por Especialidad
    new Chart(document.getElementById('chartEspecialidades'), {
        type: 'bar',
        data: {
            labels: datosEspecialidades.map(item => item.especialidad),
            datasets: [{
                label: 'Total Citas',
                data: datosEspecialidades.map(item => item.total),
                backgroundColor: '#1976d2',
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: { beginAtZero: true, ticks: { precision: 0 } }
            },
            plugins: {
                legend: { display: false }
            }
        }
    });

    // 3. Gráfico de Barras Apiladas / Agrupadas - Citas por Mes
    new Chart(document.getElementById('chartMes'), {
        type: 'bar',
        data: {
            labels: datosMes.map(item => item.mes),
            datasets: [
                {
                    label: 'Atendidas',
                    data: datosMes.map(item => item.atendidas),
                    backgroundColor: '#28a745'
                },
                {
                    label: 'Pendientes',
                    data: datosMes.map(item => item.pendientes),
                    backgroundColor: '#d68910'
                },
                {
                    label: 'Confirmadas',
                    data: datosMes.map(item => item.confirmadas),
                    backgroundColor: '#1976d2'
                },
                {
                    label: 'Canceladas',
                    data: datosMes.map(item => item.canceladas),
                    backgroundColor: '#dc3545'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: { beginAtZero: true, ticks: { precision: 0 } }
            },
            plugins: {
                legend: { position: 'bottom' }
            }
        }
    });
</script>

</body>

</html>