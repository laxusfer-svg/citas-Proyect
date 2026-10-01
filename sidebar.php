<?php
$paginaActiva = $paginaActiva ?? "panel";

$menusPorRol = [
    0 => [
        ["panel", "panel", "⌂", "Inicio"],
        ["sacar_cita", "sacar_cita", "📅", "Sacar cita"],
        ["citas", "panel", "📋", "Mis citas"]
    ],
    1 => [
        ["panel", "panel", "⌂", "Inicio"],
        ["citas", "panel", "📅", "Mis citas"]
    ],
    2 => [
        ["panel", "panel", "⌂", "Inicio"],
        ["pacientes", "pacientes", "👥", "Pacientes"],
        ["doctores", "doctores", "👨‍⚕️", "Doctores"],
        ["citas", "citas_admin", "📅", "Citas"],
        ["reportes", "reporte_citas", "📊", "Reportes"]
    ]
];

$opcionesSidebar = $menusPorRol[(int)($_SESSION["rol"] ?? -1)] ?? [];
?>
<aside class="sidebar">
    <div class="logo">
        <div class="logo-icon">✚</div>
        <div class="logo-text">Sistema de<br>Citas Médicas</div>
    </div>

    <nav class="menu" aria-label="Navegación principal">
        <?php foreach ($opcionesSidebar as [$clave, $href, $icono, $etiqueta]): ?>
            <a href="<?php echo htmlspecialchars($href, ENT_QUOTES, "UTF-8"); ?>" class="<?php echo $paginaActiva === $clave ? "activo" : ""; ?>">
                <div class="menu-icon"><?php echo $icono; ?></div>
                <span><?php echo htmlspecialchars($etiqueta, ENT_QUOTES, "UTF-8"); ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <form class="cerrar-sesion" method="POST" action="logout">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION["csrf_token"] ?? "", ENT_QUOTES, "UTF-8"); ?>">
        <button type="submit" class="logout-button" aria-label="Cerrar sesión">
            <span>🚪</span>
            <span>Cerrar sesión</span>
        </button>
    </form>
</aside>
