<?php
session_start();
include "conexion.php";
require_once "reglas_citas.php";

if (!isset($_SESSION["id"]) || !isset($_SESSION["rol"])) {
    header("Location: /");
    exit;
}

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$usuario_id = (int)$_SESSION["id"];
$rol = (int)$_SESSION["rol"];
$usuario = $_SESSION["usuario"];
$transicionesEstadoCita = TRANSICIONES_ESTADO_CITA;
$etiquetasEstadoCita = ETIQUETAS_ESTADO_CITA;

/* =====================================================
   VARIABLES GENERALES
===================================================== */

$nombreUsuario = $usuario;

$totalCitas = 0;
$atendidas = 0;
$pendientes = 0;
$confirmadas = 0;
$canceladas = 0;

$citas = [];
$datosEspecialidades = [];
$pacientesRegistrados = 0;
$doctoresRegistrados = 0;
$citasDeHoy = 0;
$citasProximosSieteDias = 0;


/* =====================================================
   ADMINISTRADOR
   ROL = 2
===================================================== */

if ($rol == 2) {

    $sqlResumen = "
        SELECT
            (SELECT COUNT(*) FROM pacientes) AS pacientes,
            (SELECT COUNT(*) FROM doctores) AS doctores,
            (SELECT COUNT(*) FROM citas) AS total_citas,
            (
                SELECT COUNT(*)
                FROM citas
                WHERE fecha = CURDATE()
                    AND estado <> 'cancelada'
            ) AS citas_hoy,
            (
                SELECT COUNT(*)
                FROM citas
                WHERE fecha > CURDATE()
                    AND fecha <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
                    AND estado IN ('pendiente', 'confirmada')
            ) AS citas_proximos_siete_dias
    ";

    $resumenAdmin = $conexion->query($sqlResumen)->fetch_assoc();
    $pacientesRegistrados = (int)$resumenAdmin["pacientes"];
    $doctoresRegistrados = (int)$resumenAdmin["doctores"];
    $totalCitas = (int)$resumenAdmin["total_citas"];
    $citasDeHoy = (int)$resumenAdmin["citas_hoy"];
    $citasProximosSieteDias = (int)$resumenAdmin["citas_proximos_siete_dias"];

    // Estados
    $sql = "
        SELECT estado, COUNT(*) AS cantidad
        FROM citas
        GROUP BY estado
    ";

    $resultado = $conexion->query($sql);

    while ($fila = $resultado->fetch_assoc()) {

        if ($fila["estado"] == "atendida") {
            $atendidas = (int)$fila["cantidad"];
        }

        if ($fila["estado"] == "pendiente") {
            $pendientes = (int)$fila["cantidad"];
        }

        if ($fila["estado"] == "confirmada") {
            $confirmadas = (int)$fila["cantidad"];
        }

        if ($fila["estado"] == "cancelada") {
            $canceladas = (int)$fila["cantidad"];
        }
    }

    // Especialidades
    $sql = "
        SELECT
            d.especialidad,
            COUNT(*) AS total
        FROM citas c
        INNER JOIN doctores d
            ON c.doctor_id = d.id
        GROUP BY d.especialidad
        ORDER BY total DESC
    ";

    $resultado = $conexion->query($sql);

    while ($fila = $resultado->fetch_assoc()) {
        $datosEspecialidades[] = $fila;
    }

    // Últimas citas
    $sql = "
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

        ORDER BY c.fecha DESC, c.hora DESC, c.id DESC
        LIMIT 12
    ";

    $resultado = $conexion->query($sql);

    while ($fila = $resultado->fetch_assoc()) {
        $citas[] = $fila;
    }
}


/* =====================================================
   DOCTOR
   ROL = 1
===================================================== */

if ($rol == 1) {

    $sql = "
        SELECT
            id,
            nombres,
            apellidos,
            especialidad,
            cmp,
            hora_inicio,
            hora_fin,
            refrigerio_inicio,
            refrigerio_fin
        FROM doctores
        WHERE usuario_id = ?
        LIMIT 1
    ";

    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();

    $doctor = $stmt->get_result()->fetch_assoc();

    if (!$doctor) {
        die("No se encontró el perfil del doctor.");
    }

    $doctor_id = (int)$doctor["id"];

    $nombreUsuario = $doctor["nombres"] . " " . $doctor["apellidos"];

    // Total de citas del doctor
    $sql = "
        SELECT COUNT(*) AS total
        FROM citas
        WHERE doctor_id = ?
    ";

    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("i", $doctor_id);
    $stmt->execute();

    $totalCitas = (int)$stmt->get_result()->fetch_assoc()["total"];


    // Estados del doctor
    $sql = "
        SELECT estado, COUNT(*) AS cantidad
        FROM citas
        WHERE doctor_id = ?
        GROUP BY estado
    ";

    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("i", $doctor_id);
    $stmt->execute();

    $resultado = $stmt->get_result();

    while ($fila = $resultado->fetch_assoc()) {

        if ($fila["estado"] == "atendida") {
            $atendidas = (int)$fila["cantidad"];
        }

        if ($fila["estado"] == "pendiente") {
            $pendientes = (int)$fila["cantidad"];
        }

        if ($fila["estado"] == "confirmada") {
            $confirmadas = (int)$fila["cantidad"];
        }

        if ($fila["estado"] == "cancelada") {
            $canceladas = (int)$fila["cantidad"];
        }
    }


    // Citas del doctor
    $sql = "
        SELECT
            c.id,
            c.fecha,
            c.hora,
            c.modalidad,
            c.estado,
            c.motivo,

            p.dni,
            p.nombres,
            p.apellidos

        FROM citas c

        INNER JOIN pacientes p
            ON c.paciente_id = p.id

        WHERE c.doctor_id = ?

        ORDER BY c.fecha ASC, c.hora ASC
    ";

    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("i", $doctor_id);
    $stmt->execute();

    $resultado = $stmt->get_result();

    while ($fila = $resultado->fetch_assoc()) {
        $citas[] = $fila;
    }
}


/* =====================================================
   PACIENTE
   ROL = 0
===================================================== */

if ($rol == 0) {

    $sql = "
        SELECT
            id,
            nombres,
            apellidos,
            dni
        FROM pacientes
        WHERE usuario_id = ?
        LIMIT 1
    ";

    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();

    $paciente = $stmt->get_result()->fetch_assoc();

    if (!$paciente) {
        die("No se encontró el perfil del paciente.");
    }

    $paciente_id = (int)$paciente["id"];

    $nombreUsuario =
        $paciente["nombres"] . " " .
        $paciente["apellidos"];


    // Total de citas
    $sql = "
        SELECT COUNT(*) AS total
        FROM citas
        WHERE paciente_id = ?
    ";

    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("i", $paciente_id);
    $stmt->execute();

    $totalCitas = (int)$stmt->get_result()->fetch_assoc()["total"];


    // Estados
    $sql = "
        SELECT estado, COUNT(*) AS cantidad
        FROM citas
        WHERE paciente_id = ?
        GROUP BY estado
    ";

    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("i", $paciente_id);
    $stmt->execute();

    $resultado = $stmt->get_result();

    while ($fila = $resultado->fetch_assoc()) {

        if ($fila["estado"] == "atendida") {
            $atendidas = (int)$fila["cantidad"];
        }

        if ($fila["estado"] == "pendiente") {
            $pendientes = (int)$fila["cantidad"];
        }

        if ($fila["estado"] == "confirmada") {
            $confirmadas = (int)$fila["cantidad"];
        }

        if ($fila["estado"] == "cancelada") {
            $canceladas = (int)$fila["cantidad"];
        }
    }


    // Citas del paciente
    $sql = "
        SELECT
            c.id,
            c.fecha,
            c.hora,
            c.modalidad,
            c.estado,
            c.motivo,

            d.nombres AS doctor_nombres,
            d.apellidos AS doctor_apellidos,
            d.especialidad

        FROM citas c

        INNER JOIN doctores d
            ON c.doctor_id = d.id

        WHERE c.paciente_id = ?

        ORDER BY c.fecha DESC, c.hora DESC
    ";

    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("i", $paciente_id);
    $stmt->execute();

    $resultado = $stmt->get_result();

    while ($fila = $resultado->fetch_assoc()) {
        $citas[] = $fila;
    }
}

?>
<!DOCTYPE html>
<html lang="es">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Panel - Sistema de Citas Médicas</title>

<?php if ($rol == 2): ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<?php endif; ?>




    <link rel="stylesheet" href="css/style.css?v=20261011-nav">
</head>


<body class="page-panel has-sidebar" data-user-role="<?php echo (int)$rol; ?>">

<?php
$paginaActiva = "panel";
include "sidebar.php";
?>


<!-- =====================================================
     CONTENIDO
===================================================== -->

<main class="contenido">


    <!-- CABECERA -->

    <div class="topbar">

        <div class="titulo">

            <?php if ($rol == 2): ?>

                <h1>Panel de Administración</h1>

                <p>
                    Resumen general de citas médicas del sistema.
                </p>

            <?php elseif ($rol == 1): ?>

                <h1>Panel del Doctor</h1>

                <p>
                    Consulta y gestiona tus citas médicas.
                </p>

            <?php else: ?>

                <h1>Mi Panel</h1>

                <p>
                    Consulta tus citas médicas y próximas atenciones.
                </p>

            <?php endif; ?>

        </div>


        <div class="usuario">

            👤

            <?php echo htmlspecialchars($nombreUsuario); ?>

        </div>

    </div>


    <!-- =================================================
         ADMINISTRADOR
    ================================================== -->

    <?php if ($rol == 2): ?>


        <div class="tarjetas admin-tarjetas">

            <!-- TOTAL -->
            <button
                type="button"
                class="tarjeta tarjeta-filtro activo"
                data-filtro="todos"
            >

                <div class="tarjeta-icono azul">
                    📅
                </div>

                <div class="tarjeta-info">

                    <h3>Total de citas</h3>

                    <div class="numero">
                        <?php echo $totalCitas; ?>
                    </div>

                </div>

            </button>


            <!-- ATENDIDAS -->
            <button
                type="button"
                class="tarjeta tarjeta-filtro"
                data-filtro="atendida"
            >

                <div class="tarjeta-icono verde">
                    ✓
                </div>

                <div class="tarjeta-info">

                    <h3>Atendidas</h3>

                    <div class="numero">
                        <?php echo $atendidas; ?>
                    </div>

                </div>

            </button>


            <!-- PENDIENTES -->
            <button
                type="button"
                class="tarjeta tarjeta-filtro"
                data-filtro="pendiente"
            >

                <div class="tarjeta-icono amarillo">
                    ◷
                </div>

                <div class="tarjeta-info">

                    <h3>Pendientes</h3>

                    <div class="numero">
                        <?php echo $pendientes; ?>
                    </div>

                </div>

            </button>


            <!-- CONFIRMADAS -->
            <button
                type="button"
                class="tarjeta tarjeta-filtro"
                data-filtro="confirmada"
            >

                <div class="tarjeta-icono azul">
                    ✓
                </div>

                <div class="tarjeta-info">

                    <h3>Confirmadas</h3>

                    <div class="numero">
                        <?php echo $confirmadas; ?>
                    </div>

                </div>

            </button>

            <button
                type="button"
                class="tarjeta tarjeta-filtro"
                data-filtro="cancelada"
            >

                <div class="tarjeta-icono rojo">
                    ×
                </div>

                <div class="tarjeta-info">

                    <h3>Canceladas</h3>

                    <div class="numero">
                        <?php echo $canceladas; ?>
                    </div>

                </div>

            </button>

        </div>


        <div class="info-grid admin-info-grid">

            <div class="info-card">
                <small>Pacientes registrados</small>
                <strong><?php echo $pacientesRegistrados; ?></strong>
            </div>

            <div class="info-card">
                <small>Doctores registrados</small>
                <strong><?php echo $doctoresRegistrados; ?></strong>
            </div>

            <div class="info-card">
                <small>Citas de hoy</small>
                <strong><?php echo $citasDeHoy; ?></strong>
            </div>

            <div class="info-card">
                <small>Citas próximos 7 días</small>
                <strong><?php echo $citasProximosSieteDias; ?></strong>
            </div>

        </div>


        <!-- GRÁFICOS -->

        <div class="graficos">


            <div class="grafico">

                <h2>🍩 Citas por estado</h2>

                <div class="canvas-box">

                    <canvas id="graficoEstados"></canvas>

                </div>

            </div>


            <div class="grafico">

                <h2>📊 Citas por especialidad</h2>

                <div class="canvas-box">

                    <canvas id="graficoEspecialidades"></canvas>

                </div>

            </div>

        </div>


        <!-- DETALLE -->

        <div class="seccion">

            <div class="seccion-header">

                <h2>
                    📋 Últimas 12 citas
                </h2>

                <a href="reporte_citas"
                   class="boton">

                    📊 Ver reporte completo

                </a>

            </div>


            <div class="filtro-resultado">
                <span>
                    Mostrando: <strong class="textoFiltro">Todas las últimas 12</strong>
                </span>
                <span>
                    <strong class="cantidadFiltro"><?php echo count($citas); ?></strong> resultado(s)
                </span>
            </div>


            <table>

                <thead>

                    <tr>

                        <th>Fecha</th>
                        <th>Hora</th>
                        <th>Modalidad</th>
                        <th>Paciente</th>
                        <th>DNI</th>
                        <th>Doctor</th>
                        <th>Especialidad</th>
                        <th>Estado</th>
                        <th>Acción</th>

                    </tr>

                </thead>


                <tbody>

                <?php foreach ($citas as $fila): ?>

                    <tr
                        class="fila-cita"
                        data-estado="<?php echo htmlspecialchars($fila["estado"]); ?>"
                    >

                        <td>
                            <?php
                            echo date(
                                "d/m/Y",
                                strtotime($fila["fecha"])
                            );
                            ?>
                        </td>

                        <td>
                            <?php
                            echo substr(
                                $fila["hora"],
                                0,
                                5
                            );
                            ?>
                        </td>

                        <td>
                            <span class="badge-modalidad modalidad-<?php echo htmlspecialchars($fila["modalidad"], ENT_QUOTES, "UTF-8"); ?>">
                                <?php echo $fila["modalidad"] === "virtual" ? "Virtual" : "Presencial"; ?>
                            </span>
                        </td>

                        <td>

                            <?php

                            echo htmlspecialchars(
                                $fila["paciente_nombres"]
                                . " "
                                . $fila["paciente_apellidos"]
                            );

                            ?>

                        </td>

                        <td>
                            <?php
                            echo htmlspecialchars(
                                $fila["dni"]
                            );
                            ?>
                        </td>

                        <td>

                            <?php

                            echo htmlspecialchars(
                                "Dr. "
                                . $fila["doctor_nombres"]
                                . " "
                                . $fila["doctor_apellidos"]
                            );

                            ?>

                        </td>

                        <td>

                            <?php

                            echo htmlspecialchars(
                                $fila["especialidad"]
                            );

                            ?>

                        </td>

                        <td>

                            <span class="
                                estado
                                estado-<?php
                                    echo $fila["estado"];
                                ?>
                            ">

                                <?php

                                $textoEstado = [
                                    "pendiente" => "Pendiente",
                                    "confirmada" => "Confirmada",
                                    "atendida" => "Atendida",
                                    "cancelada" => "Cancelada"
                                ];

                                echo $textoEstado[
                                    $fila["estado"]
                                ] ?? $fila["estado"];

                                ?>

                            </span>

                        </td>

                        <td>
                            <?php $opcionesEstado = $transicionesEstadoCita[$fila["estado"]] ?? []; ?>
                            <?php if ($opcionesEstado): ?>
                                <form method="POST" action="actualizar_cita" class="form-estado-cita">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION["csrf_token"], ENT_QUOTES, "UTF-8"); ?>">
                                    <input type="hidden" name="cita_id" value="<?php echo (int)$fila["id"]; ?>">
                                    <label class="sr-only" for="estado-cita-<?php echo (int)$fila["id"]; ?>">Nuevo estado de cita</label>
                                    <select id="estado-cita-<?php echo (int)$fila["id"]; ?>" name="estado" required>
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
                                <a class="reprogramar-link" href="reprogramar_cita?id=<?php echo (int)$fila["id"]; ?>&origen=panel">Reprogramar</a>
                            <?php endif; ?>
                        </td>

                    </tr>

                <?php endforeach; ?>

                <tr class="fila-sin-resultados">
                    <td colspan="9" class="mensaje-tabla-vacio">
                        No hay citas con el estado seleccionado.
                    </td>
                </tr>

                <?php if (count($citas) == 0): ?>

                    <tr>

                        <td colspan="9" class="mensaje-tabla-vacio">

                            No hay citas registradas.

                        </td>

                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>


    <!-- =================================================
         DOCTOR
    ================================================== -->

    <?php elseif ($rol == 1): ?>


        <div class="tarjetas">

            <button type="button" class="tarjeta tarjeta-filtro activo" data-filtro="todos">
                <div class="tarjeta-icono azul">📅</div>
                <div class="tarjeta-info">
                    <h3>Total de citas</h3>
                    <div class="numero"><?php echo $totalCitas; ?></div>
                </div>
            </button>

            <button type="button" class="tarjeta tarjeta-filtro" data-filtro="atendida">
                <div class="tarjeta-icono verde">✓</div>
                <div class="tarjeta-info">
                    <h3>Atendidas</h3>
                    <div class="numero"><?php echo $atendidas; ?></div>
                </div>
            </button>

            <button type="button" class="tarjeta tarjeta-filtro" data-filtro="pendiente">
                <div class="tarjeta-icono amarillo">◷</div>
                <div class="tarjeta-info">
                    <h3>Pendientes</h3>
                    <div class="numero"><?php echo $pendientes; ?></div>
                </div>
            </button>

            <button type="button" class="tarjeta tarjeta-filtro" data-filtro="confirmada">
                <div class="tarjeta-icono azul">✓</div>
                <div class="tarjeta-info">
                    <h3>Confirmadas</h3>
                    <div class="numero"><?php echo $confirmadas; ?></div>
                </div>
            </button>

        </div>


        <!-- INFORMACIÓN DEL DOCTOR -->

        <div class="info-grid">

            <div class="info-card">

                <small>Especialidad</small>

                <strong>
                    <?php
                    echo htmlspecialchars(
                        $doctor["especialidad"]
                    );
                    ?>
                </strong>

            </div>


            <div class="info-card">

                <small>CMP</small>

                <strong>
                    <?php
                    echo htmlspecialchars(
                        $doctor["cmp"]
                    );
                    ?>
                </strong>

            </div>


            <div class="info-card">

                <small>Horario de atención</small>

                <strong>

                    <?php
                    echo substr(
                        $doctor["hora_inicio"],
                        0,
                        5
                    );
                    ?>

                    -

                    <?php
                    echo substr(
                        $doctor["hora_fin"],
                        0,
                        5
                    );
                    ?>

                </strong>

            </div>

        </div>


        <!-- CITAS DEL DOCTOR -->

        <div class="seccion">

            <div class="seccion-header">

                <h2>
                    📋 Mis citas médicas
                </h2>

            </div>


            <div class="filtro-resultado">

                <span>
                    Mostrando:
                    <strong class="textoFiltro">
                        Todas las citas
                    </strong>
                </span>

                <span>
                    <strong class="cantidadFiltro">
                        <?php echo $totalCitas; ?>
                    </strong>
                    resultado(s)
                </span>

            </div>


            <table>

                <thead>

                    <tr>

                        <th>Fecha</th>
                        <th>Hora</th>
                        <th>Modalidad</th>
                        <th>Paciente</th>
                        <th>DNI</th>
                        <th>Motivo</th>
                        <th>Estado</th>
                        <th>Acción</th>

                    </tr>

                </thead>


                <tbody>

                <?php foreach ($citas as $fila): ?>

                    <tr class="fila-cita" data-estado="<?php echo htmlspecialchars($fila["estado"]); ?>">

                        <td>
                            <?php
                            echo date(
                                "d/m/Y",
                                strtotime($fila["fecha"])
                            );
                            ?>
                        </td>

                        <td>
                            <?php
                            echo substr(
                                $fila["hora"],
                                0,
                                5
                            );
                            ?>
                        </td>

                        <td>
                            <span class="badge-modalidad modalidad-<?php echo htmlspecialchars($fila["modalidad"], ENT_QUOTES, "UTF-8"); ?>">
                                <?php echo $fila["modalidad"] === "virtual" ? "Virtual" : "Presencial"; ?>
                            </span>
                        </td>

                        <td>

                            <?php

                            echo htmlspecialchars(
                                $fila["nombres"]
                                . " "
                                . $fila["apellidos"]
                            );

                            ?>

                        </td>

                        <td>
                            <?php
                            echo htmlspecialchars(
                                $fila["dni"]
                            );
                            ?>
                        </td>

                        <td>
                            <?php
                            echo htmlspecialchars(
                                $fila["motivo"]
                            );
                            ?>
                        </td>

                        <td>

                            <span class="
                                estado
                                estado-<?php
                                    echo $fila["estado"];
                                ?>
                            ">

                                <?php

                                $textoEstado = [
                                    "pendiente" => "Pendiente",
                                    "confirmada" => "Confirmada",
                                    "atendida" => "Atendida",
                                    "cancelada" => "Cancelada"
                                ];

                                echo $textoEstado[
                                    $fila["estado"]
                                ] ?? $fila["estado"];

                                ?>

                            </span>

                        </td>

                        <td>
                            <?php $opcionesEstado = $transicionesEstadoCita[$fila["estado"]] ?? []; ?>
                            <?php if ($opcionesEstado): ?>
                                <form method="POST" action="actualizar_cita" class="form-estado-cita">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION["csrf_token"], ENT_QUOTES, "UTF-8"); ?>">
                                    <input type="hidden" name="cita_id" value="<?php echo (int)$fila["id"]; ?>">
                                    <label class="sr-only" for="estado-cita-<?php echo (int)$fila["id"]; ?>">Nuevo estado de cita</label>
                                    <select id="estado-cita-<?php echo (int)$fila["id"]; ?>" name="estado" required>
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
                        </td>

                    </tr>

                <?php endforeach; ?>

                <tr class="fila-sin-resultados">
                    <td colspan="8" class="mensaje-tabla-vacio">
                        No hay citas con el estado seleccionado.
                    </td>
                </tr>

                <?php if (count($citas) == 0): ?>

                    <tr>

                        <td colspan="8" class="mensaje-tabla-vacio">

                            No tienes citas registradas.

                        </td>

                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>


    <!-- =================================================
         PACIENTE
    ================================================== -->

    <?php elseif ($rol == 0): ?>


        <div class="tarjetas">

            <button type="button" class="tarjeta tarjeta-filtro activo" data-filtro="todos">
                <div class="tarjeta-icono azul">📅</div>
                <div class="tarjeta-info">
                    <h3>Total de citas</h3>
                    <div class="numero"><?php echo $totalCitas; ?></div>
                </div>
            </button>

            <button type="button" class="tarjeta tarjeta-filtro" data-filtro="atendida">
                <div class="tarjeta-icono verde">✓</div>
                <div class="tarjeta-info">
                    <h3>Atendidas</h3>
                    <div class="numero"><?php echo $atendidas; ?></div>
                </div>
            </button>

            <button type="button" class="tarjeta tarjeta-filtro" data-filtro="pendiente">
                <div class="tarjeta-icono amarillo">◷</div>
                <div class="tarjeta-info">
                    <h3>Pendientes</h3>
                    <div class="numero"><?php echo $pendientes; ?></div>
                </div>
            </button>

            <button type="button" class="tarjeta tarjeta-filtro" data-filtro="confirmada">
                <div class="tarjeta-icono azul">✓</div>
                <div class="tarjeta-info">
                    <h3>Confirmadas</h3>
                    <div class="numero"><?php echo $confirmadas; ?></div>
                </div>
            </button>

        </div>


        <div class="acciones">

            <a href="sacar_cita"
               class="boton boton-verde">

                ➕ Sacar nueva cita

            </a>

        </div>


        <!-- DATOS DEL PACIENTE -->

        <div class="info-grid">

            <div class="info-card">

                <small>Paciente</small>

                <strong>

                    <?php

                    echo htmlspecialchars(
                        $paciente["nombres"]
                        . " "
                        . $paciente["apellidos"]
                    );

                    ?>

                </strong>

            </div>


            <div class="info-card">

                <small>DNI</small>

                <strong>

                    <?php

                    echo htmlspecialchars(
                        $paciente["dni"]
                    );

                    ?>

                </strong>

            </div>


            <div class="info-card">

                <small>Próxima atención</small>

                <strong>

                    <?php

                    $proxima = null;

                    foreach ($citas as $cita) {

                        if (
                            $cita["estado"] != "cancelada"
                            &&
                            $cita["fecha"] >= date("Y-m-d")
                        ) {
                            $proxima = $cita;
                            break;
                        }

                    }

                    if ($proxima) {

                        echo date(
                            "d/m/Y",
                            strtotime(
                                $proxima["fecha"]
                            )
                        );

                    } else {

                        echo "Sin citas";

                    }

                    ?>

                </strong>

            </div>

        </div>


        <!-- CITAS DEL PACIENTE -->

        <div class="seccion">

            <div class="seccion-header">

                <h2>
                    📋 Mis citas médicas
                </h2>

            </div>


            <div class="filtro-resultado">
                <span>
                    Mostrando: <strong class="textoFiltro">Todas las citas</strong>
                </span>
                <span>
                    <strong class="cantidadFiltro"><?php echo count($citas); ?></strong> resultado(s)
                </span>
            </div>


            <table>

                <thead>

                    <tr>

                        <th>Fecha</th>
                        <th>Hora</th>
                        <th>Modalidad</th>
                        <th>Doctor</th>
                        <th>Especialidad</th>
                        <th>Motivo</th>
                        <th>Estado</th>

                    </tr>

                </thead>


                <tbody>

                <?php foreach ($citas as $fila): ?>

                    <tr class="fila-cita" data-estado="<?php echo htmlspecialchars($fila["estado"]); ?>">

                        <td>

                            <?php

                            echo date(
                                "d/m/Y",
                                strtotime(
                                    $fila["fecha"]
                                )
                            );

                            ?>

                        </td>

                        <td>
                            <?php
                            echo substr($fila["hora"], 0, 5);
                            ?>
                        </td>

                        <td>
                            <span class="badge-modalidad modalidad-<?php echo htmlspecialchars($fila["modalidad"], ENT_QUOTES, "UTF-8"); ?>">
                                <?php echo $fila["modalidad"] === "virtual" ? "Virtual" : "Presencial"; ?>
                            </span>

                        </td>

                        <td>

                            <?php

                            echo htmlspecialchars(
                                "Dr. "
                                . $fila["doctor_nombres"]
                                . " "
                                . $fila["doctor_apellidos"]
                            );

                            ?>

                        </td>

                        <td>

                            <?php

                            echo htmlspecialchars(
                                $fila["especialidad"]
                            );

                            ?>

                        </td>

                        <td>

                            <?php

                            echo htmlspecialchars(
                                $fila["motivo"]
                            );

                            ?>

                        </td>

                        <td>

                            <span class="
                                estado
                                estado-<?php
                                    echo $fila["estado"];
                                ?>
                            ">

                                <?php

                                $textoEstado = [
                                    "pendiente" => "Pendiente",
                                    "confirmada" => "Confirmada",
                                    "atendida" => "Atendida",
                                    "cancelada" => "Cancelada"
                                ];

                                echo $textoEstado[
                                    $fila["estado"]
                                ] ?? $fila["estado"];

                                ?>

                            </span>

                        </td>

                    </tr>

                <?php endforeach; ?>

                <tr class="fila-sin-resultados">
                    <td colspan="7" class="mensaje-tabla-vacio">
                        No hay citas con el estado seleccionado.
                    </td>
                </tr>

                <?php if (count($citas) == 0): ?>

                    <tr>

                        <td colspan="7" class="mensaje-tabla-vacio">

                            No tienes citas registradas.

                        </td>

                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    <?php endif; ?>


</main>


<?php if ($rol == 2): ?>

<script type="application/json" id="panel-chart-data"><?php
echo json_encode([
    "atendidas" => $atendidas,
    "pendientes" => $pendientes,
    "confirmadas" => $confirmadas,
    "canceladas" => $canceladas,
    "especialidades" => $datosEspecialidades
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
?></script>

<?php endif; ?>

<script src="js/javascript.js" defer></script>

</body>

</html>
