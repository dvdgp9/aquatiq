<?php
/**
 * Aquatiq - Panel Padre: Ver hijos
 */

require_once __DIR__ . '/../config/config.php';
requireRole(['padre']);

$pageTitle = 'Mis Hijos';
$pdo = getDBConnection();
$user = getCurrentUser();

// Obtener hijos asignados al padre
$stmt = $pdo->prepare("
    SELECT a.*, g.nombre as grupo_nombre, n.nombre as nivel_nombre,
           (SELECT COUNT(*) FROM evaluaciones e WHERE e.alumno_id = a.id) as total_evaluaciones,
           (SELECT MAX(e.fecha) FROM evaluaciones e WHERE e.alumno_id = a.id) as ultima_evaluacion
    FROM alumnos a
    LEFT JOIN grupos g ON a.grupo_id = g.id
    LEFT JOIN niveles n ON g.nivel_id = n.id
    WHERE a.padre_id = ? AND a.activo = 1
    ORDER BY a.apellido1, a.apellido2, a.nombre
");
$stmt->execute([$user['id']]);
$hijos = $stmt->fetchAll();

include INCLUDES_PATH . '/header.php';
?>

<div class="page-header">
    <h1>👨‍👧‍👦 Mis Hijos</h1>
</div>

<?php if (count($hijos) > 0): ?>
<div class="dashboard-grid">
    <?php foreach ($hijos as $hijo): ?>
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <?= sanitize($hijo['nombre'] . ' ' . $hijo['apellido1']) ?>
            </h3>
        </div>
        
        <?php if ($hijo['grupo_nombre']): ?>
        <p style="color: var(--gray-500); margin-bottom: 0.5rem;">
            👥 <?= sanitize($hijo['grupo_nombre']) ?>
        </p>
        <?php endif; ?>
        
        <?php if ($hijo['nivel_nombre']): ?>
        <p style="margin-bottom: 0.5rem;">
            <span class="badge badge-info"><?= sanitize($hijo['nivel_nombre']) ?></span>
        </p>
        <?php endif; ?>
        
        <p style="margin-bottom: 1rem;">
            📋 <strong><?= $hijo['total_evaluaciones'] ?></strong> evaluación<?= $hijo['total_evaluaciones'] != 1 ? 'es' : '' ?>
            <?php if ($hijo['ultima_evaluacion']): ?>
            <br><small style="color: var(--gray-500);">Última: <?= formatDate($hijo['ultima_evaluacion']) ?></small>
            <?php endif; ?>
        </p>
        
        <a href="/padre/evaluaciones.php?hijo=<?= $hijo['id'] ?>" class="btn btn-primary">
            Ver evaluaciones
        </a>
    </div>
    <?php endforeach; ?>
</div>
<?php else: ?>
<div class="card">
    <div class="empty-state">
        <div class="empty-state-icon">👨‍👧‍👦</div>
        <h3>Sin hijos asignados</h3>
        <p>Contacta con el administrador para que asigne a tus hijos a tu cuenta.</p>
    </div>
</div>
<?php endif; ?>

<?php include INCLUDES_PATH . '/footer.php'; ?>
