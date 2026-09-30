<?php
session_start();
include "conexion.php";

$mensaje = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $usuario = trim($_POST["usuario"] ?? "");
    $password = $_POST["password"] ?? "";

    $sql = "SELECT id, usuario, password, rol
            FROM usuarios
            WHERE usuario = ?";

    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("s", $usuario);
    $stmt->execute();

    $resultado = $stmt->get_result();

    if ($resultado->num_rows == 1) {

        $datos = $resultado->fetch_assoc();
        $passwordValido = password_verify($password, $datos["password"]);

        if (!$passwordValido && hash_equals(hash("sha256", $password), $datos["password"])) {
            $passwordActualizada = password_hash($password, PASSWORD_DEFAULT);
            $stmtActualizarPassword = $conexion->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
            $stmtActualizarPassword->bind_param("si", $passwordActualizada, $datos["id"]);
            $stmtActualizarPassword->execute();
            $passwordValido = true;
        }

        if (!$passwordValido) {
            $mensaje = "Usuario o contraseña incorrectos";
        } else {
            session_regenerate_id(true);

            $_SESSION["id"] = $datos["id"];
            $_SESSION["usuario"] = $datos["usuario"];
            $_SESSION["rol"] = (int)$datos["rol"];

            header("Location: panel.php");
            exit;
        }

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




    <link rel="stylesheet" href="css/style.css">
</head>


<body class="page-login">


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