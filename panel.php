<?php
session_start();
include "conexion.php";

if (!isset($_SESSION["id"]) || !isset($_SESSION["rol"])) {
    header("Location: login.php");
    exit;
}

$usuario_id = (int)$_SESSION["id"];
$rol = (int)$_SESSION["rol"];
$usuario = $_SESSION["usuario"];

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


/* =====================================================
   ADMINISTRADOR
   ROL = 2
===================================================== */

if ($rol == 2) {

    // Total
    $sql = "SELECT COUNT(*) AS total FROM citas";
    $resultado = $conexion->query($sql);
    $totalCitas = (int)$resultado->fetch_assoc()["total"];

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

        ORDER BY c.fecha DESC, c.hora DESC
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


<style>

/* =====================================================
   GENERAL
===================================================== */

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, Helvetica, sans-serif;
    background: #f4f7fb;
    color: #17345c;
}

a {
    text-decoration: none;
}


/* =====================================================
   SIDEBAR
===================================================== */

.sidebar {
    position: fixed;
    left: 0;
    top: 0;

    width: 220px;
    height: 100vh;

    background: linear-gradient(
        180deg,
        #102f55,
        #0d2949
    );

    color: white;

    padding: 20px 12px;

    z-index: 1000;
}

.logo {
    display: flex;
    align-items: center;

    gap: 12px;

    padding: 10px;

    margin-bottom: 25px;
}

.logo-icon {
    width: 42px;
    height: 42px;

    background: #1976e8;

    border-radius: 50%;

    display: flex;
    align-items: center;
    justify-content: center;

    font-size: 23px;
}

.logo-text {
    font-size: 17px;
    font-weight: bold;
    line-height: 1.2;
}

.menu {
    display: flex;
    flex-direction: column;
    gap: 7px;
}

.menu a {
    display: flex;
    align-items: center;

    gap: 12px;

    padding: 13px 12px;

    color: #e6eef8;

    border-radius: 9px;

    font-size: 14px;

    transition: .2s;
}

.menu a:hover {
    background: rgba(255,255,255,.10);
}

.menu a.activo {
    background: #1478e8;
    color: white;
}

.menu-icon {
    width: 25px;
    text-align: center;
    font-size: 18px;
}

.cerrar-sesion {
    position: absolute;

    bottom: 20px;
    left: 12px;
    right: 12px;
}


/* =====================================================
   CONTENIDO
===================================================== */

.contenido {
    margin-left: 220px;

    min-height: 100vh;

    padding: 30px;
}


/* =====================================================
   TOP
===================================================== */

.topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;

    margin-bottom: 25px;
}

.titulo h1 {
    margin: 0;

    font-size: 28px;

    color: #173b68;
}

.titulo p {
    margin: 7px 0 0;

    color: #68809d;

    font-size: 14px;
}

.usuario {
    background: white;

    padding: 10px 15px;

    border-radius: 10px;

    box-shadow: 0 3px 12px rgba(0,0,0,.06);

    font-size: 14px;
}


/* =====================================================
   TARJETAS KPI
===================================================== */

.tarjetas {
    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 16px;

    margin-bottom: 22px;
}

.tarjeta {
    background: white;

    border-radius: 14px;

    padding: 20px;

    box-shadow:
        0 5px 18px rgba(20,60,100,.07);

    border: 1px solid #e8eef5;

    display: flex;

    align-items: center;

    gap: 15px;
}

.tarjeta-icono {
    width: 52px;
    height: 52px;

    border-radius: 50%;

    display: flex;
    align-items: center;
    justify-content: center;

    font-size: 24px;

    flex-shrink: 0;
}

.azul {
    background: #e6f1ff;
    color: #1976e8;
}

.verde {
    background: #e2f8f1;
    color: #10ad7b;
}

.amarillo {
    background: #fff5dd;
    color: #f2a900;
}

.rojo {
    background: #ffe8eb;
    color: #ed3d48;
}

.tarjeta-info {
    min-width: 0;
}

.tarjeta-info h3 {
    margin: 0 0 5px;

    font-size: 13px;

    color: #68809d;

    font-weight: normal;
}

.numero {
    font-size: 28px;

    font-weight: bold;

    color: #173b68;
}


/* =====================================================
   BOTONES
===================================================== */

.boton {
    display: inline-flex;

    align-items: center;

    gap: 7px;

    padding: 11px 17px;

    background: #1976e8;

    color: white;

    border-radius: 8px;

    border: none;

    cursor: pointer;

    font-size: 14px;

    transition: .2s;
}

.boton:hover {
    background: #0d64ce;
}

.boton-verde {
    background: #10ad7b;
}

.boton-verde:hover {
    background: #078d64;
}


/* =====================================================
   GRAFICOS
===================================================== */

.graficos {
    display: grid;

    grid-template-columns:
        1fr 1fr;

    gap: 18px;

    margin-bottom: 22px;
}

.grafico {
    background: white;

    border-radius: 14px;

    padding: 20px;

    border: 1px solid #e8eef5;

    box-shadow:
        0 5px 18px rgba(20,60,100,.07);
}

.grafico h2 {
    margin: 0 0 18px;

    font-size: 17px;

    color: #173b68;
}

.canvas-box {
    height: 280px;

    position: relative;
}


/* =====================================================
   TABLAS
===================================================== */

.seccion {
    background: white;

    border-radius: 14px;

    padding: 20px;

    margin-bottom: 22px;

    border: 1px solid #e8eef5;

    box-shadow:
        0 5px 18px rgba(20,60,100,.07);

    overflow-x: auto;
}

.seccion-header {
    display: flex;

    justify-content: space-between;

    align-items: center;

    margin-bottom: 17px;
}

.seccion h2 {
    margin: 0;

    font-size: 18px;

    color: #173b68;
}

table {
    width: 100%;

    border-collapse: collapse;

    font-size: 13px;
}

th {
    background: #eef6ff;

    color: #24517e;

    padding: 13px 10px;

    text-align: left;

    white-space: nowrap;
}

td {
    padding: 13px 10px;

    border-bottom: 1px solid #edf1f5;

    color: #49647f;

    white-space: nowrap;
}

tr:hover td {
    background: #f8fbff;
}


/* =====================================================
   ESTADOS
===================================================== */

.estado {
    display: inline-block;

    padding: 6px 11px;

    border-radius: 20px;

    font-size: 12px;

    font-weight: bold;
}

.estado-atendida {
    background: #dcf8ed;
    color: #07865f;
}

.estado-pendiente {
    background: #fff0c9;
    color: #a96e00;
}

.estado-confirmada {
    background: #dcecff;
    color: #1267c5;
}

.estado-cancelada {
    background: #ffe1e5;
    color: #d42f3b;
}


/* =====================================================
   PERFIL / INFORMACION
===================================================== */

.info-grid {
    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 15px;

    margin-bottom: 22px;
}

.info-card {
    background: white;

    padding: 20px;

    border-radius: 14px;

    border: 1px solid #e8eef5;

    box-shadow:
        0 5px 18px rgba(20,60,100,.07);
}

.info-card small {
    color: #7a91a9;
}

.info-card strong {
    display: block;

    margin-top: 7px;

    color: #173b68;

    font-size: 16px;
}


/* =====================================================
   ACCIONES
===================================================== */

.acciones {
    display: flex;

    gap: 12px;

    flex-wrap: wrap;

    margin-bottom: 22px;
}


/* =====================================================
   RESPONSIVE
===================================================== */

@media(max-width: 1100px) {

    .tarjetas {
        grid-template-columns:
            repeat(2, 1fr);
    }

    .info-grid {
        grid-template-columns:
            repeat(2, 1fr);
    }

}

@media(max-width: 800px) {

    .sidebar {
        width: 70px;

        padding: 15px 8px;
    }

    .logo-text,
    .menu span {
        display: none;
    }

    .logo {
        justify-content: center;
    }

    .menu a {
        justify-content: center;
    }

    .contenido {
        margin-left: 70px;

        padding: 20px;
    }

    .graficos {
        grid-template-columns: 1fr;
    }

}

@media(max-width: 600px) {

    .tarjetas {
        grid-template-columns: 1fr;
    }

    .info-grid {
        grid-template-columns: 1fr;
    }

    .topbar {
        align-items: flex-start;

        flex-direction: column;

        gap: 15px;
    }

}
/* =====================================================
   TARJETAS COMO FILTRO
===================================================== */

.tarjeta-filtro {
    width: 100%;
    border: 2px solid transparent;
    cursor: pointer;
    text-align: left;

    transition:
        transform .2s,
        box-shadow .2s,
        border-color .2s,
        background .2s;
}

.tarjeta-filtro:hover {
    transform: translateY(-3px);

    box-shadow:
        0 8px 25px rgba(20,60,100,.12);
}

/* TARJETA SELECCIONADA */

.tarjeta-filtro.activo {
    border-color: #1976e8;

    background: #f7fbff;

    box-shadow:
        0 7px 22px rgba(25,118,232,.15);
}

/* Indicador superior */

.tarjeta-filtro.activo::before {
    content: "";

    position: absolute;

    top: 0;
    left: 18px;
    right: 18px;

    height: 4px;

    background: #1976e8;

    border-radius:
        0 0 5px 5px;
}


/* =====================================================
   MENSAJE DEL FILTRO
===================================================== */

.filtro-resultado {
    display: flex;

    align-items: center;

    justify-content: space-between;

    background: #eef6ff;

    border: 1px solid #d5e8fb;

    color: #24517e;

    padding: 12px 15px;

    border-radius: 9px;

    margin-bottom: 15px;

    font-size: 13px;
}

.filtro-resultado strong {
    color: #173b68;
}

.tarjeta {
    position: relative;
}

</style>

</head>


<body>


<!-- =====================================================
     SIDEBAR
===================================================== -->

<aside class="sidebar">

    <div class="logo">

        <div class="logo-icon">
            ✚
        </div>

        <div class="logo-text">
            Sistema de<br>
            Citas Médicas
        </div>

    </div>


    <nav class="menu">

        <a href="panel.php" class="activo">
            <div class="menu-icon">⌂</div>
            <span>Inicio</span>
        </a>


        <?php if ($rol == 2): ?>

            <a href="#">
                <div class="menu-icon">👥</div>
                <span>Pacientes</span>
            </a>

            <a href="#">
                <div class="menu-icon">👨‍⚕️</div>
                <span>Doctores</span>
            </a>

            <a href="reporte_citas.php">
                <div class="menu-icon">📅</div>
                <span>Citas</span>
            </a>

            <a href="reporte_citas.php">
                <div class="menu-icon">📊</div>
                <span>Reportes</span>
            </a>

            <a href="#">
                <div class="menu-icon">⚙️</div>
                <span>Administración</span>
            </a>

        <?php elseif ($rol == 1): ?>

            <a href="panel.php">
                <div class="menu-icon">📅</div>
                <span>Mis citas</span>
            </a>

        <?php elseif ($rol == 0): ?>

            <a href="sacar_cita.php">
                <div class="menu-icon">📅</div>
                <span>Sacar cita</span>
            </a>

            <a href="panel.php">
                <div class="menu-icon">📋</div>
                <span>Mis citas</span>
            </a>

        <?php endif; ?>

    </nav>


    <div class="cerrar-sesion">

        <a href="logout.php"
           class="menu">

            <div style="
                display:flex;
                align-items:center;
                gap:12px;
                padding:13px 12px;
                color:white;
                border-radius:9px;
            ">

                <span>🚪</span>

                <span>Cerrar sesión</span>

            </div>

        </a>

    </div>

</aside>


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


        <div class="tarjetas">

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
                    📋 Últimas citas
                </h2>

                <a href="reporte_citas.php"
                   class="boton">

                    📊 Ver reporte completo

                </a>

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
                        <th>Paciente</th>
                        <th>DNI</th>
                        <th>Doctor</th>
                        <th>Especialidad</th>
                        <th>Estado</th>

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

                    </tr>

                <?php endforeach; ?>

                <tr class="fila-sin-resultados" style="display:none;">
                    <td colspan="7" style="text-align:center;padding:30px;">
                        No hay citas con el estado seleccionado.
                    </td>
                </tr>

                <?php if (count($citas) == 0): ?>

                    <tr>

                        <td colspan="7"
                            style="text-align:center;padding:30px;">

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
                        <th>Paciente</th>
                        <th>DNI</th>
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

                    </tr>

                <?php endforeach; ?>

                <tr class="fila-sin-resultados" style="display:none;">
                    <td colspan="6" style="text-align:center;padding:30px;">
                        No hay citas con el estado seleccionado.
                    </td>
                </tr>

                <?php if (count($citas) == 0): ?>

                    <tr>

                        <td colspan="6"
                            style="text-align:center;padding:30px;">

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

            <a href="sacar_cita.php"
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

                            echo substr(
                                $fila["hora"],
                                0,
                                5
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

                <tr class="fila-sin-resultados" style="display:none;">
                    <td colspan="6" style="text-align:center;padding:30px;">
                        No hay citas con el estado seleccionado.
                    </td>
                </tr>

                <?php if (count($citas) == 0): ?>

                    <tr>

                        <td colspan="6"
                            style="
                                text-align:center;
                                padding:30px;
                            ">

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

<script>

/* =====================================================
   DATOS PHP -> JAVASCRIPT
===================================================== */

const atendidas = <?php echo $atendidas; ?>;
const pendientes = <?php echo $pendientes; ?>;
const confirmadas = <?php echo $confirmadas; ?>;
const canceladas = <?php echo $canceladas; ?>;

const especialidades =
    <?php echo json_encode($datosEspecialidades); ?>;


/* =====================================================
   GRÁFICO CIRCULAR
===================================================== */

new Chart(
    document.getElementById("graficoEstados"),
    {
        type: "doughnut",

        data: {

            labels: [
                "Atendidas",
                "Pendientes",
                "Confirmadas",
                "Canceladas"
            ],

            datasets: [{

                data: [
                    atendidas,
                    pendientes,
                    confirmadas,
                    canceladas
                ],

                backgroundColor: [
                    "#10ad7b",
                    "#f2a900",
                    "#1976e8",
                    "#ed3d48"
                ],

                borderWidth: 3,

                borderColor: "#ffffff"

            }]

        },

        options: {

            responsive: true,

            maintainAspectRatio: false,

            cutout: "65%",

            plugins: {

                legend: {
                    position: "bottom"
                }

            }

        }

    }
);


/* =====================================================
   GRÁFICO DE BARRAS
===================================================== */

new Chart(
    document.getElementById("graficoEspecialidades"),
    {
        type: "bar",

        data: {

            labels:
                especialidades.map(
                    item => item.especialidad
                ),

            datasets: [{

                label: "Citas",

                data:
                    especialidades.map(
                        item => item.total
                    ),

                backgroundColor: "#1976e8",

                borderRadius: 7

            }]

        },

        options: {

            responsive: true,

            maintainAspectRatio: false,

            scales: {

                y: {

                    beginAtZero: true,

                    ticks: {
                        precision: 0
                    }

                }

            },

            plugins: {

                legend: {
                    display: false
                }

            }

        }

    }
);

</script>

<?php endif; ?>

<script>

document.addEventListener("DOMContentLoaded", function () {
    const tarjetas = document.querySelectorAll(".tarjeta-filtro");
    const filas = document.querySelectorAll(".fila-cita");
    const mensajes = document.querySelectorAll(".fila-sin-resultados");
    const textos = document.querySelectorAll(".textoFiltro");
    const cantidades = document.querySelectorAll(".cantidadFiltro");

    if (!tarjetas.length) return;

    tarjetas.forEach(function (tarjeta) {
        tarjeta.addEventListener("click", function () {
            const filtro = this.getAttribute("data-filtro");
            let cantidad = 0;

            tarjetas.forEach(function (item) {
                item.classList.remove("activo");
            });
            this.classList.add("activo");

            filas.forEach(function (fila) {
                const estado = fila.getAttribute("data-estado");
                const mostrar = filtro === "todos" || estado === filtro;
                fila.style.display = mostrar ? "" : "none";
                if (mostrar) cantidad++;
            });

            mensajes.forEach(function (mensaje) {
                mensaje.style.display = cantidad === 0 ? "table-row" : "none";
            });

            let texto = "Todas las citas";
            if (filtro === "atendida") texto = "Citas atendidas";
            if (filtro === "pendiente") texto = "Citas pendientes";
            if (filtro === "confirmada") texto = "Citas confirmadas";

            textos.forEach(function (elemento) {
                elemento.textContent = texto;
            });
            cantidades.forEach(function (elemento) {
                elemento.textContent = cantidad;
            });
        });
    });
});

</script>

</body>

</html>