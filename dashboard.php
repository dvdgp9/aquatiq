<?php
/**
 * Aquatiq - Dashboard principal
 */

require_once __DIR__ . '/config/config.php';
requireLogin();

$pageTitle = 'Dashboard';
$user = getCurrentUser();

include INCLUDES_PATH . '/header.php';
?>

<div class="page-header">
    <h1>Bienvenido, <?= sanitize($user['nombre']) ?></h1>
</div>

<div class="dashboard-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem;">
    
    <?php if (canAccessAdmin()): ?>
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Gestión</h3>
        </div>
        <ul style="list-style: none;">
            <li style="padding: 0.5rem 0;"><a href="/admin/niveles.php">📊 Niveles</a></li>
            <li style="padding: 0.5rem 0;"><a href="/admin/grupos.php">👥 Grupos</a></li>
            <li style="padding: 0.5rem 0;"><a href="/admin/alumnos.php">🎓 Alumnos</a></li>
            <li style="padding: 0.5rem 0;"><a href="/admin/monitores.php">🏊 Monitores</a></li>
            <li style="padding: 0.5rem 0;"><a href="/admin/plantillas.php">📋 Plantillas de evaluación</a></li>
        </ul>
    </div>
    <?php endif; ?>
    
    <?php if (hasRole('monitor')): ?>
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Mis Grupos</h3>
        </div>
        <p>Accede a tus grupos asignados para evaluar alumnos.</p>
        <a href="/monitor/grupos.php" class="btn btn-primary" style="margin-top: 1rem;">Ver mis grupos</a>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Evaluaciones</h3>
        </div>
        <p>Crea y gestiona evaluaciones de tus alumnos.</p>
        <a href="/monitor/evaluaciones.php" class="btn btn-primary" style="margin-top: 1rem;">Mis evaluaciones</a>
    </div>
    <?php endif; ?>
    
    <?php if (hasRole('padre')): ?>
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Mis Hijos</h3>
        </div>
        <p>Consulta las evaluaciones de tus hijos.</p>
        <a href="/padre/hijos.php" class="btn btn-primary" style="margin-top: 1rem;">Ver evaluaciones</a>
    </div>
    <?php endif; ?>
    
    <?php if (hasRole('superadmin')): ?>
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Administración</h3>
        </div>
        <ul style="list-style: none;">
            <li style="padding: 0.5rem 0;"><a href="/superadmin/usuarios.php">👤 Gestión de usuarios</a></li>
        </ul>
    </div>
    <?php endif; ?>
    
</div>

<?php include INCLUDES_PATH . '/footer.php'; ?>
