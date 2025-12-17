<?php
/**
 * Aquatiq - Dashboard principal
 */

require_once __DIR__ . '/config/config.php';
requireLogin();

$pageTitle = 'Dashboard';
$user = getCurrentUser();
$pdo = getDBConnection();

// Obtener estadísticas
$stats = [];
if (canAccessAdmin()) {
    $stats['alumnos'] = $pdo->query("SELECT COUNT(*) FROM alumnos WHERE activo = 1")->fetchColumn();
    $stats['grupos'] = $pdo->query("SELECT COUNT(*) FROM grupos WHERE activo = 1")->fetchColumn();
    $stats['monitores'] = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE rol = 'monitor' AND activo = 1")->fetchColumn();
    $stats['evaluaciones'] = $pdo->query("SELECT COUNT(*) FROM evaluaciones")->fetchColumn();
}

include INCLUDES_PATH . '/header.php';
?>

<div class="dashboard-welcome">
    <h1>👋 Bienvenido, <?= sanitize($user['nombre']) ?></h1>
    <p>Panel de control de <?= APP_NAME ?> - Piscina Cubierta Municipal</p>
</div>

<?php if (canAccessAdmin()): ?>
<div class="dashboard-grid" style="margin-bottom: 2rem;">
    <div class="stat-card">
        <div class="stat-icon blue">🎓</div>
        <div class="stat-info">
            <h3><?= $stats['alumnos'] ?></h3>
            <p>Alumnos activos</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green">👥</div>
        <div class="stat-info">
            <h3><?= $stats['grupos'] ?></h3>
            <p>Grupos</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon orange">🏊</div>
        <div class="stat-info">
            <h3><?= $stats['monitores'] ?></h3>
            <p>Monitores</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon blue">📋</div>
        <div class="stat-info">
            <h3><?= $stats['evaluaciones'] ?></h3>
            <p>Evaluaciones</p>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="dashboard-grid">
    
    <?php if (canAccessAdmin()): ?>
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">⚙️ Gestión</h3>
        </div>
        <ul class="menu-list">
            <li><a href="/admin/niveles.php"><span class="icon">📊</span> Niveles</a></li>
            <li><a href="/admin/grupos.php"><span class="icon">👥</span> Grupos</a></li>
            <li><a href="/admin/alumnos.php"><span class="icon">🎓</span> Alumnos</a></li>
            <li><a href="/admin/monitores.php"><span class="icon">🏊</span> Monitores</a></li>
            <li><a href="/admin/plantillas.php"><span class="icon">📋</span> Plantillas de evaluación</a></li>
        </ul>
    </div>
    <?php endif; ?>
    
    <?php if (hasRole('monitor')): ?>
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">👥 Mis Grupos</h3>
        </div>
        <p style="color: var(--gray-500); margin-bottom: 1rem;">Accede a tus grupos asignados para evaluar alumnos.</p>
        <a href="/monitor/grupos.php" class="btn btn-primary">Ver mis grupos</a>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">📋 Evaluaciones</h3>
        </div>
        <p style="color: var(--gray-500); margin-bottom: 1rem;">Crea y gestiona evaluaciones de tus alumnos.</p>
        <a href="/monitor/evaluaciones.php" class="btn btn-primary">Mis evaluaciones</a>
    </div>
    <?php endif; ?>
    
    <?php if (hasRole('padre')): ?>
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">👨‍👧‍👦 Mis Hijos</h3>
        </div>
        <p style="color: var(--gray-500); margin-bottom: 1rem;">Consulta las evaluaciones de tus hijos.</p>
        <a href="/padre/hijos.php" class="btn btn-primary">Ver evaluaciones</a>
    </div>
    <?php endif; ?>
    
    <?php if (hasRole('superadmin')): ?>
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">🔐 Administración</h3>
        </div>
        <ul class="menu-list">
            <li><a href="/superadmin/usuarios.php"><span class="icon">👤</span> Gestión de usuarios</a></li>
        </ul>
    </div>
    <?php endif; ?>
    
</div>

<?php include INCLUDES_PATH . '/footer.php'; ?>
