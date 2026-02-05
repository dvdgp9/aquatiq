<?php
/**
 * Aquatiq - Panel Monitor: Mis Grupos
 */

require_once __DIR__ . '/../config/config.php';
requireRole(['monitor', 'coordinador']);

$pageTitle = 'Mis Grupos';
$pdo = getDBConnection();
$user = getCurrentUser();

// Obtener grupos asignados al monitor
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
$grupos = $stmt->fetchAll();

include INCLUDES_PATH . '/header.php';
?>

<div class="page-header">
    <h1><i class="iconoir-group"></i> Mis Grupos</h1>
</div>

<?php if (count($grupos) > 0): ?>
<div class="dashboard-grid">
    <?php foreach ($grupos as $grupo): ?>
    <div class="card">
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
<div class="card">
    <div class="empty-state">
        <div class="empty-state-icon"><i class="iconoir-group"></i></div>
        <h3>Sin grupos asignados</h3>
        <p>Contacta con el administrador para que te asigne grupos.</p>
    </div>
</div>
<?php endif; ?>

<?php include INCLUDES_PATH . '/footer.php'; ?>
