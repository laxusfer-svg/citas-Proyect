<?php
session_start();
include "conexion.php";

$mensaje = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $usuario = trim($_POST["usuario"] ?? "");
    $password = hash("sha256", $_POST["password"] ?? "");

    $sql = "SELECT id, usuario, password, rol
            FROM usuarios
            WHERE usuario = ? AND password = ?";

    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("ss", $usuario, $password);
    $stmt->execute();

    $resultado = $stmt->get_result();

    if ($resultado->num_rows == 1) {

        $datos = $resultado->fetch_assoc();

        $_SESSION["id"] = $datos["id"];
        $_SESSION["usuario"] = $datos["usuario"];
        $_SESSION["rol"] = (int)$datos["rol"];

        header("Location: panel.php");
        exit;

    } else {

        $mensaje = "Usuario o contraseña incorrectos";

    }
}
?>

<!DOCTYPE html>
<html lang="es">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Iniciar Sesión - Sistema de Citas Médicas</title>


<style>

/* =====================================================
   RESET
===================================================== */

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    font-family:
        Arial,
        Helvetica,
        sans-serif;

    min-height: 100vh;

    background:
        linear-gradient(
            135deg,
            #edf6ff 0%,
            #f8fbff 50%,
            #e8f3ff 100%
        );

    display: flex;
    align-items: center;
    justify-content: center;

    color: #173b68;

    overflow-x: hidden;
}


/* =====================================================
   DECORACIÓN DEL FONDO
===================================================== */

.decoracion {
    position: fixed;

    border-radius: 50%;

    pointer-events: none;

    z-index: 0;
}

.decoracion-1 {
    width: 430px;
    height: 430px;

    background: #dcecff;

    top: -230px;
    left: -180px;
}

.decoracion-2 {
    width: 350px;
    height: 350px;

    background: #e3f1ff;

    bottom: -200px;
    right: -150px;
}

.cruz-fondo {
    position: fixed;

    bottom: 50px;
    left: 45px;

    font-size: 90px;

    color: #cfe5fb;

    opacity: .7;

    font-weight: bold;

    z-index: 0;
}


/* =====================================================
   CONTENEDOR
===================================================== */

.login-wrapper {

    width: 1080px;

    max-width: calc(100% - 40px);

    min-height: 650px;

    display: grid;

    grid-template-columns: 1fr 1fr;

    background: white;

    border-radius: 18px;

    overflow: hidden;

    box-shadow:
        0 20px 55px
        rgba(30, 86, 140, .16);

    position: relative;

    z-index: 2;
}


/* =====================================================
   PANEL IZQUIERDO
===================================================== */

.panel-izquierdo {

    background:
        linear-gradient(
            145deg,
            #1478df,
            #0d4fa8
        );

    color: white;

    padding: 48px;

    position: relative;

    overflow: hidden;
}


/* círculo decorativo */

.panel-izquierdo::before {

    content: "";

    position: absolute;

    width: 330px;
    height: 330px;

    border-radius: 50%;

    background: rgba(255,255,255,.08);

    right: -120px;
    top: 190px;
}

.panel-izquierdo::after {

    content: "";

    position: absolute;

    width: 220px;
    height: 220px;

    border-radius: 50%;

    background: rgba(255,255,255,.05);

    left: -100px;
    bottom: -80px;
}


/* =====================================================
   LOGO
===================================================== */

.logo {

    display: flex;

    align-items: center;

    gap: 15px;

    position: relative;

    z-index: 2;
}

.logo-icon {

    width: 64px;
    height: 64px;

    border-radius: 50%;

    background: rgba(255,255,255,.95);

    color: #1478df;

    display: flex;

    align-items: center;
    justify-content: center;

    font-size: 39px;

    font-weight: bold;
}

.logo h1 {

    font-size: 28px;

    line-height: 1.1;

    margin-bottom: 7px;
}

.logo p {

    font-size: 15px;

    opacity: .88;
}


/* =====================================================
   ILUSTRACIÓN MÉDICA
===================================================== */

.doctor-ilustracion {

    margin-top: 55px;

    position: relative;

    z-index: 2;

    text-align: center;
}

.doctor-circle {

    width: 285px;
    height: 285px;

    margin: auto;

    border-radius: 50%;

    background:
        rgba(255,255,255,.13);

    display: flex;

    align-items: center;

    justify-content: center;

    position: relative;
}


/*
   Ilustración simple hecha con CSS,
   para no depender de imágenes externas.
*/

.doctor {

    width: 125px;
    height: 175px;

    position: relative;
}

.cabeza {

    width: 65px;
    height: 65px;

    border-radius: 50%;

    background: #ffd2b8;

    margin: auto;

    position: relative;

    z-index: 3;
}

.cabello {

    position: absolute;

    width: 75px;
    height: 60px;

    background: #182d4c;

    border-radius:
        50% 50% 35% 35%;

    top: -5px;
    left: -5px;

    z-index: 4;
}

.cuerpo {

    width: 120px;
    height: 110px;

    background: white;

    border-radius:
        35px 35px 10px 10px;

    margin-top: 4px;

    position: relative;
}

.camisa {

    position: absolute;

    width: 20px;
    height: 105px;

    background: #dbeeff;

    left: 50px;

    transform: skew(-8deg);
}

.corazon {

    position: absolute;

    right: 17px;
    top: 38px;

    font-size: 20px;

    color: #1478df;
}


/* =====================================================
   BENEFICIOS
===================================================== */

.beneficios {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 12px;

    margin-top: 45px;

    position: relative;

    z-index: 3;
}

.beneficio {

    text-align: center;

    padding: 10px;

    border-right:
        1px solid
        rgba(255,255,255,.25);
}

.beneficio:last-child {
    border-right: none;
}

.beneficio-icono {

    font-size: 25px;

    margin-bottom: 7px;
}

.beneficio strong {

    display: block;

    font-size: 13px;

    line-height: 1.3;
}

.beneficio small {

    display: block;

    margin-top: 3px;

    opacity: .8;

    font-size: 11px;
}


/* =====================================================
   PANEL DERECHO
===================================================== */

.panel-derecho {

    padding: 60px 65px;

    display: flex;

    flex-direction: column;

    justify-content: center;

    background: #ffffff;
}


/* =====================================================
   TITULO LOGIN
===================================================== */

.login-titulo {

    margin-bottom: 38px;
}

.login-titulo h2 {

    font-size: 32px;

    color: #173b68;

    margin-bottom: 10px;
}

.login-titulo p {

    color: #6e86a1;

    font-size: 15px;

    line-height: 1.6;
}


/* =====================================================
   MENSAJE ERROR
===================================================== */

.mensaje {

    background: #ffe8eb;

    border: 1px solid #ffcbd1;

    color: #c82e3b;

    padding: 12px 15px;

    border-radius: 9px;

    margin-bottom: 20px;

    font-size: 14px;
}


/* =====================================================
   FORMULARIO
===================================================== */

.campo {

    margin-bottom: 22px;
}

.campo label {

    display: block;

    margin-bottom: 9px;

    font-size: 14px;

    font-weight: bold;

    color: #173b68;
}

.input-wrapper {

    position: relative;
}

.input-icono {

    position: absolute;

    left: 16px;
    top: 50%;

    transform: translateY(-50%);

    font-size: 19px;

    color: #386594;

    pointer-events: none;
}

.campo input {

    width: 100%;

    height: 55px;

    border: 1px solid #cbdced;

    border-radius: 10px;

    padding:
        0 45px;

    outline: none;

    font-size: 15px;

    color: #173b68;

    transition: .2s;

    background: #fff;
}

.campo input::placeholder {

    color: #91a5ba;
}

.campo input:focus {

    border-color: #1976e8;

    box-shadow:
        0 0 0 3px
        rgba(25,118,232,.10);
}


/* =====================================================
   MOSTRAR CONTRASEÑA
===================================================== */

.ver-password {

    position: absolute;

    right: 16px;

    top: 50%;

    transform: translateY(-50%);

    border: none;

    background: transparent;

    cursor: pointer;

    font-size: 18px;

    color: #55718f;
}


/* =====================================================
   OPCIONES
===================================================== */

.opciones {

    display: flex;

    justify-content: flex-end;

    margin-top: -8px;

    margin-bottom: 25px;
}

.opciones a {

    color: #1478df;

    font-size: 13px;

    font-weight: bold;

    text-decoration: none;
}

.opciones a:hover {
    text-decoration: underline;
}


/* =====================================================
   BOTÓN
===================================================== */

.btn-ingresar {

    width: 100%;

    height: 56px;

    border: none;

    border-radius: 10px;

    background:
        linear-gradient(
            135deg,
            #1976e8,
            #1265c9
        );

    color: white;

    font-size: 16px;

    font-weight: bold;

    cursor: pointer;

    box-shadow:
        0 7px 18px
        rgba(25,118,232,.22);

    transition: .2s;
}

.btn-ingresar:hover {

    transform: translateY(-1px);

    box-shadow:
        0 10px 23px
        rgba(25,118,232,.30);
}


/* =====================================================
   DIVISOR
===================================================== */

.divisor {

    display: flex;

    align-items: center;

    gap: 12px;

    margin: 30px 0 18px;

    color: #9aabbb;

    font-size: 12px;
}

.divisor::before,
.divisor::after {

    content: "";

    flex: 1;

    height: 1px;

    background: #dce5ed;
}


/* =====================================================
   SEGURIDAD
===================================================== */

.seguridad {

    text-align: center;

    color: #8094a9;

    font-size: 12px;

    line-height: 1.5;
}


/* =====================================================
   FOOTER
===================================================== */

.footer {

    position: fixed;

    bottom: 18px;

    left: 0;
    right: 0;

    text-align: center;

    color: #527090;

    font-size: 12px;

    z-index: 3;
}


/* =====================================================
   RESPONSIVE
===================================================== */

@media(max-width: 850px) {

    body {
        padding: 25px 0 80px;
    }

    .login-wrapper {

        grid-template-columns: 1fr;

        max-width: 500px;
    }

    .panel-izquierdo {

        padding: 35px;

        min-height: 430px;
    }

    .doctor-ilustracion {
        margin-top: 35px;
    }

    .doctor-circle {

        width: 200px;
        height: 200px;
    }

    .beneficios {
        margin-top: 30px;
    }

    .panel-derecho {

        padding: 40px;
    }

    .footer {
        position: static;

        margin-top: 20px;
    }
}


@media(max-width: 500px) {

    body {
        padding: 15px;
    }

    .login-wrapper {

        max-width: 100%;

        border-radius: 14px;
    }

    .panel-izquierdo {

        padding: 28px 22px;
    }

    .logo h1 {
        font-size: 22px;
    }

    .logo-icon {

        width: 52px;
        height: 52px;

        font-size: 30px;
    }

    .doctor-ilustracion {
        display: none;
    }

    .beneficios {
        margin-top: 35px;
    }

    .panel-derecho {

        padding: 35px 25px;
    }

    .login-titulo h2 {
        font-size: 27px;
    }

}

</style>

</head>


<body>


<!-- DECORACIÓN -->

<div class="decoracion decoracion-1"></div>

<div class="decoracion decoracion-2"></div>

<div class="cruz-fondo">+</div>


<!-- =====================================================
     LOGIN
===================================================== -->

<div class="login-wrapper">


    <!-- =================================================
         IZQUIERDA
    ================================================== -->

    <section class="panel-izquierdo">


        <div class="logo">

            <div class="logo-icon">
                +
            </div>

            <div>

                <h1>
                    Sistema de<br>
                    Citas Médicas
                </h1>

                <p>
                    Tu salud, nuestra prioridad
                </p>

            </div>

        </div>


        <!-- ILUSTRACIÓN -->

        <div class="doctor-ilustracion">

            <div class="doctor-circle">

                <div class="doctor">

                    <div class="cabello"></div>

                    <div class="cabeza"></div>

                    <div class="cuerpo">

                        <div class="camisa"></div>

                        <div class="corazon">
                            ♥
                        </div>

                    </div>

                </div>

            </div>

        </div>


        <!-- BENEFICIOS -->

        <div class="beneficios">

            <div class="beneficio">

                <div class="beneficio-icono">
                    📅
                </div>

                <strong>
                    Agenda tu cita
                </strong>

                <small>
                    Fácil y rápido
                </small>

            </div>


            <div class="beneficio">

                <div class="beneficio-icono">
                    🛡️
                </div>

                <strong>
                    Atención segura
                </strong>

                <small>
                    Información protegida
                </small>

            </div>


            <div class="beneficio">

                <div class="beneficio-icono">
                    ♥
                </div>

                <strong>
                    Tu bienestar
                </strong>

                <small>
                    Siempre primero
                </small>

            </div>

        </div>


    </section>


    <!-- =================================================
         DERECHA
    ================================================== -->

    <section class="panel-derecho">


        <div class="login-titulo">

            <h2>
                Iniciar Sesión
            </h2>

            <p>
                Accede a tu cuenta para gestionar
                tus citas médicas.
            </p>

        </div>


        <?php if ($mensaje != ""): ?>

            <div class="mensaje">

                ⚠️

                <?php
                echo htmlspecialchars($mensaje);
                ?>

            </div>

        <?php endif; ?>


        <form method="POST"
              action="">


            <!-- USUARIO -->

            <div class="campo">

                <label for="usuario">
                    Usuario
                </label>

                <div class="input-wrapper">

                    <span class="input-icono">
                        👤
                    </span>

                    <input
                        type="text"
                        id="usuario"
                        name="usuario"
                        placeholder="Ingresa tu usuario"
                        autocomplete="username"
                        required
                    >

                </div>

            </div>


            <!-- CONTRASEÑA -->

            <div class="campo">

                <label for="password">
                    Contraseña
                </label>

                <div class="input-wrapper">

                    <span class="input-icono">
                        🔒
                    </span>

                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Ingresa tu contraseña"
                        autocomplete="current-password"
                        required
                    >

                    <button
                        type="button"
                        class="ver-password"
                        onclick="mostrarPassword()"
                        id="botonPassword"
                    >
                        👁
                    </button>

                </div>

            </div>


            <!-- RECUPERAR -->

            <div class="opciones">

                <a href="#">
                    ¿Olvidaste tu contraseña?
                </a>

            </div>


            <!-- INGRESAR -->

            <button
                type="submit"
                class="btn-ingresar"
            >

                ➜ &nbsp; Ingresar

            </button>


        </form>


        <div class="divisor">
            o
        </div>


        <div class="seguridad">

            🔐 Tus datos están protegidos.

            <br>

            Sistema de Citas Médicas

        </div>


    </section>


</div>


<!-- FOOTER -->

<div class="footer">

    Sistema de Citas Médicas
    &nbsp;©&nbsp;
    2026
    -
    Todos los derechos reservados

</div>


<script>

function mostrarPassword() {

    const password =
        document.getElementById("password");

    const boton =
        document.getElementById("botonPassword");


    if (password.type === "password") {

        password.type = "text";

        boton.textContent = "🙈";

    } else {

        password.type = "password";

        boton.textContent = "👁";

    }

}

</script>


</body>

</html>