<?php
/**
 * Aquatiq - Panel Padre: Ver evaluaciones de una hija/o
 */

require_once __DIR__ . '/../config/config.php';
requireRole(['padre']);

$pdo = getDBConnection();
$user = getCurrentUser();

$hijo_id = (int)($_GET['hijo'] ?? 0);

// Verificar que la hija/o pertenece a este padre
$stmt = $pdo->prepare("
    SELECT a.*, g.nombre as grupo_nombre, n.nombre as nivel_nombre
    FROM alumnos a
    LEFT JOIN grupos g ON a.grupo_id = g.id
    LEFT JOIN niveles n ON g.nivel_id = n.id
    WHERE a.id = ? AND a.padre_id = ? AND a.activo = 1
");
$stmt->execute([$hijo_id, $user['id']]);
$hijo = $stmt->fetch();

if (!$hijo) {
    setFlashMessage('error', 'No tienes acceso a esta alumna/o.');
    redirect('/padre/hijos.php');
}

$pageTitle = 'Evaluaciones de ' . $hijo['nombre'];

// Obtener evaluaciones
$stmt = $pdo->prepare("
    SELECT e.*, p.nombre as plantilla_nombre, n.nombre as nivel_evaluado,
           nr.nombre as nivel_recomendado, u.nombre as monitor_nombre
    FROM evaluaciones e
    INNER JOIN plantillas_evaluacion p ON e.plantilla_id = p.id
    INNER JOIN niveles n ON p.nivel_id = n.id
    LEFT JOIN niveles nr ON e.recomendacion_nivel_id = nr.id
    LEFT JOIN usuarios u ON e.monitor_id = u.id
    WHERE e.alumno_id = ?
    ORDER BY e.fecha DESC, e.created_at DESC
");
$stmt->execute([$hijo_id]);
$evaluaciones = $stmt->fetchAll();

include INCLUDES_PATH . '/header.php';
?>

<div class="page-header">
    <h1>
        <a href="/padre/hijos.php" style="color: var(--gray-400); margin-right: 0.5rem;">←</a>
        Evaluaciones
    </h1>
</div>

<div class="card" style="margin-bottom: 1.5rem;">
    <div style="display: flex; align-items: center; gap: 1rem;">
        <div style="background: var(--accent-light); width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2rem;">
            <i class="iconoir-graduation-cap"></i>
        </div>
        <div>
            <h2 style="margin: 0;"><?= sanitize($hijo['nombre'] . ' ' . $hijo['apellido1'] . ' ' . $hijo['apellido2']) ?></h2>
            <p style="margin: 0; color: var(--gray-500);">
                <?php if ($hijo['grupo_nombre']): ?>
                <?= sanitize($hijo['grupo_nombre']) ?>
                <?php endif; ?>
                <?php if ($hijo['nivel_nombre']): ?>
                 • <span class="badge badge-info"><?= sanitize($hijo['nivel_nombre']) ?></span>
                <?php endif; ?>
            </p>
        </div>
    </div>
</div>

<?php if (count($evaluaciones) > 0): ?>
<div class="dashboard-grid">
    <?php foreach ($evaluaciones as $eval): ?>
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <?= sanitize($eval['nivel_evaluado']) ?>
            </h3>
            <span class="badge badge-info"><?= formatDate($eval['fecha']) ?></span>
        </div>
        
        <p style="color: var(--gray-500); margin-bottom: 0.5rem;">
            <i class="iconoir-calendar"></i> <?= sanitize(str_replace('_', ' ', ucfirst($eval['periodo']))) ?>
        </p>
        
        <?php if ($eval['nivel_recomendado']): ?>
        <p style="margin-bottom: 1rem;">
            <i class="iconoir-target"></i> Recomendación: <span class="badge badge-success"><?= sanitize($eval['nivel_recomendado']) ?></span>
        </p>
        <?php endif; ?>
        
        <a href="/padre/ver-evaluacion.php?id=<?= $eval['id'] ?>" class="btn btn-primary">
            Ver detalle
        </a>
    </div>
    <?php endforeach; ?>
</div>
<?php else: ?>
<div class="card">
    <div class="empty-state">
        <div class="empty-state-icon"><i class="iconoir-clipboard-check"></i></div>
        <h3>Sin evaluaciones</h3>
        <p>Aún no hay evaluaciones registradas para <?= sanitize($hijo['nombre']) ?>.</p>
    </div>
</div>
<?php endif; ?>

<?php include INCLUDES_PATH . '/footer.php'; ?>
