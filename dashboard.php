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

// Grupos del monitor (para mostrar directamente en dashboard)
$gruposMonitor = [];
if (isMonitorLike()) {
    $stmt = $pdo->prepare("
        SELECT g.*, n.nombre as nivel_nombre, n.id as nivel_id,
               (SELECT COUNT(*) FROM alumnos a WHERE a.grupo_id = g.id AND a.activo = 1) as total_alumnos
        FROM grupos g
        INNER JOIN monitores_grupos mg ON g.id = mg.grupo_id
        LEFT JOIN niveles n ON g.nivel_id = n.id
        WHERE mg.monitor_id = ? AND g.activo = 1
        ORDER BY n.orden, g.nombre
    ");
    $stmt->execute([$user['id']]);
    $gruposMonitor = $stmt->fetchAll();
}

include INCLUDES_PATH . '/header.php';
?>

<div class="dashboard-welcome">
    <h1><i class="iconoir-peace-hand"></i> Bienvenido, <?= sanitize($user['nombre']) ?></h1>
    <p>Panel de control de <?= APP_NAME ?> - Piscina Cubierta Municipal</p>
</div>

<?php if (canAccessAdmin()): ?>
<div class="dashboard-grid" style="margin-bottom: 2rem;">
    <div class="stat-card">
        <div class="stat-icon blue"><i class="iconoir-graduation-cap"></i></div>
        <div class="stat-info">
            <h3><?= $stats['alumnos'] ?></h3>
            <p>Alumnos activos</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="iconoir-group"></i></div>
        <div class="stat-info">
            <h3><?= $stats['grupos'] ?></h3>
            <p>Grupos</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon orange"><i class="iconoir-swimming"></i></div>
        <div class="stat-info">
            <h3><?= $stats['monitores'] ?></h3>
            <p>Monitores</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon blue"><i class="iconoir-clipboard-check"></i></div>
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
            <h3 class="card-title"><i class="iconoir-settings"></i> Gestión</h3>
        </div>
        <ul class="menu-list">
            <li><a href="/admin/niveles.php"><i class="iconoir-stats-up-square"></i> Niveles</a></li>
            <li><a href="/admin/grupos.php"><i class="iconoir-group"></i> Grupos</a></li>
            <li><a href="/admin/alumnos.php"><i class="iconoir-graduation-cap"></i> Alumnos</a></li>
            <li><a href="/admin/monitores.php"><i class="iconoir-swimming"></i> Monitores</a></li>
            <li><a href="/admin/plantillas.php"><i class="iconoir-clipboard-check"></i> Plantillas de evaluación</a></li>
        </ul>
    </div>
    <?php endif; ?>
    
<?php if (isMonitorLike()): ?>
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="iconoir-group"></i> Mis Grupos</h3>
        </div>
        
        <?php if (count($gruposMonitor) > 0): ?>
        <div class="dashboard-grid">
            <?php foreach ($gruposMonitor as $grupo): ?>
            <div class="card" style="margin: 0;">
                <div class="card-header">
                    <h3 class="card-title"><?= sanitize($grupo['nombre']) ?></h3>
                    <?php if ($grupo['nivel_nombre']): ?>
                    <span class="badge badge-info"><?= sanitize($grupo['nivel_nombre']) ?></span>
                    <?php endif; ?>
                </div>
                
                <?php if ($grupo['horario']): ?>
                <p style="color: var(--gray-500); margin-bottom: 0.5rem;">
                    🕐 <?= sanitize($grupo['horario']) ?>
                </p>
                <?php endif; ?>
                
                <p style="margin-bottom: 1rem;">
                    <strong><?= $grupo['total_alumnos'] ?></strong> <?= $grupo['total_alumnos'] == 1 ? 'alumna/o' : 'alumnas/os' ?>
                </p>
                
                <div style="display: flex; gap: 0.5rem;">
                    <a href="/monitor/alumnos.php?grupo=<?= $grupo['id'] ?>" class="btn btn-primary">
                        Ver alumnas/os
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <p style="color: var(--gray-500); margin-bottom: 1rem;">Sin grupos asignados.</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <?php if (hasRole('padre')): ?>
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="iconoir-community"></i> Mis Hijos</h3>
        </div>
        <p style="color: var(--gray-500); margin-bottom: 1rem;">Consulta las evaluaciones de tus hijos.</p>
        <a href="/padre/hijos.php" class="btn btn-primary">Ver evaluaciones</a>
    </div>
    <?php endif; ?>
    
    <?php if (hasRole('superadmin')): ?>
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="iconoir-lock"></i> Administración</h3>
        </div>
        <ul class="menu-list">
            <li><a href="/superadmin/usuarios.php"><i class="iconoir-user"></i> Gestión de usuarios</a></li>
        </ul>
    </div>
    <?php endif; ?>
    
</div>

<?php include INCLUDES_PATH . '/footer.php'; ?>
