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
        ["reportes", "reporte_citas", "📊", "Reportes"],
        ["administracion", "#", "⚙️", "Administración"]
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

    <div class="cerrar-sesion">
        <a href="logout" class="menu">
            <div class="enlace-cerrar-sesion">
                <span>🚪</span>
                <span>Cerrar sesión</span>
            </div>
        </a>
    </div>
</aside>
