<?php
/**
 * Aquatiq - Panel Monitor: Historial de evaluaciones de un alumno
 */

require_once __DIR__ . '/../config/config.php';
requireRole(['monitor']);

$pdo = getDBConnection();
$user = getCurrentUser();

$alumno_id = (int)($_GET['alumno'] ?? 0);

// Obtener datos del alumno y verificar acceso
$stmt = $pdo->prepare("
    SELECT a.*, g.nombre as grupo_nombre, g.id as grupo_id, n.nombre as nivel_nombre
    FROM alumnos a
    INNER JOIN grupos g ON a.grupo_id = g.id
    INNER JOIN monitores_grupos mg ON g.id = mg.grupo_id
    LEFT JOIN niveles n ON g.nivel_id = n.id
    WHERE a.id = ? AND mg.monitor_id = ? AND a.activo = 1
");
$stmt->execute([$alumno_id, $user['id']]);
$alumno = $stmt->fetch();

if (!$alumno) {
    setFlashMessage('error', 'No tienes acceso a este alumno.');
    redirect('/monitor/grupos.php');
}

$pageTitle = 'Historial: ' . $alumno['nombre'];

// Obtener evaluaciones del alumno
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
$stmt->execute([$alumno_id]);
$evaluaciones = $stmt->fetchAll();

include INCLUDES_PATH . '/header.php';
?>

<div class="page-header">
    <h1>
        <a href="/monitor/alumnos.php?grupo=<?= $alumno['grupo_id'] ?>" style="color: var(--gray-400); margin-right: 0.5rem;">←</a>
        Historial de evaluaciones
    </h1>
    <div class="actions">
        <a href="/monitor/evaluar.php?alumno=<?= $alumno_id ?>" class="btn btn-primary">
            + Nueva evaluación
        </a>
    </div>
</div>

<div class="card" style="margin-bottom: 1.5rem;">
    <div style="display: flex; align-items: center; gap: 1rem;">
        <div style="background: var(--accent-light); width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
            🎓
        </div>
        <div>
            <h3 style="margin: 0;"><?= sanitize($alumno['apellido1'] . ' ' . $alumno['apellido2'] . ', ' . $alumno['nombre']) ?></h3>
            <p style="margin: 0; color: var(--gray-500);">
                <?= sanitize($alumno['grupo_nombre']) ?>
                <?php if ($alumno['nivel_nombre']): ?>
                 • <?= sanitize($alumno['nivel_nombre']) ?>
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
            📅 Período: <strong><?= sanitize(str_replace('_', ' ', ucfirst($eval['periodo']))) ?></strong>
        </p>
        
        <?php if ($eval['nivel_recomendado']): ?>
        <p style="margin-bottom: 0.5rem;">
            🎯 Recomendación: <span class="badge badge-success"><?= sanitize($eval['nivel_recomendado']) ?></span>
        </p>
        <?php endif; ?>
        
        <p style="color: var(--gray-400); font-size: 0.85rem; margin-bottom: 1rem;">
            Por: <?= sanitize($eval['monitor_nombre']) ?>
        </p>
        
        <div style="display: flex; gap: 0.5rem;">
            <a href="/monitor/ver-evaluacion.php?id=<?= $eval['id'] ?>" class="btn btn-sm btn-secondary">
                Ver detalle
            </a>
            <?php if ($eval['monitor_id'] == $user['id']): ?>
            <a href="/monitor/evaluar.php?alumno=<?= $alumno_id ?>&evaluacion=<?= $eval['id'] ?>" class="btn btn-sm btn-primary">
                Editar
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php else: ?>
<div class="card">
    <div class="empty-state">
        <div class="empty-state-icon">📋</div>
        <h3>Sin evaluaciones</h3>
        <p>Este alumno aún no tiene evaluaciones.</p>
        <a href="/monitor/evaluar.php?alumno=<?= $alumno_id ?>" class="btn btn-primary" style="margin-top: 1rem;">
            Crear primera evaluación
        </a>
    </div>
</div>
<?php endif; ?>

<?php include INCLUDES_PATH . '/footer.php'; ?>
