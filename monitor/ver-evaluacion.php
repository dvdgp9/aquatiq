<?php
/**
 * Aquatiq - Ver detalle de evaluación
 */

require_once __DIR__ . '/../config/config.php';
requireRole(['monitor', 'admin', 'superadmin']);

$pdo = getDBConnection();
$user = getCurrentUser();

$evaluacion_id = (int)($_GET['id'] ?? 0);

// Obtener evaluación
$stmt = $pdo->prepare("
    SELECT e.*, 
           a.nombre as alumno_nombre, a.apellido1, a.apellido2, a.id as alumno_id,
           g.nombre as grupo_nombre, g.id as grupo_id,
           p.nombre as plantilla_nombre,
           n.nombre as nivel_evaluado,
           nr.nombre as nivel_recomendado,
           u.nombre as monitor_nombre
    FROM evaluaciones e
    INNER JOIN alumnos a ON e.alumno_id = a.id
    INNER JOIN grupos g ON a.grupo_id = g.id
    INNER JOIN plantillas_evaluacion p ON e.plantilla_id = p.id
    INNER JOIN niveles n ON p.nivel_id = n.id
    LEFT JOIN niveles nr ON e.recomendacion_nivel_id = nr.id
    LEFT JOIN usuarios u ON e.monitor_id = u.id
    WHERE e.id = ?
");
$stmt->execute([$evaluacion_id]);
$evaluacion = $stmt->fetch();

if (!$evaluacion) {
    setFlashMessage('error', 'Evaluación no encontrada.');
    redirect('/monitor/grupos.php');
}

// Verificar acceso (monitor del grupo o admin)
if (hasRole('monitor')) {
    $stmt = $pdo->prepare("SELECT 1 FROM monitores_grupos WHERE monitor_id = ? AND grupo_id = ?");
    $stmt->execute([$user['id'], $evaluacion['grupo_id']]);
    if (!$stmt->fetch()) {
        setFlashMessage('error', 'No tienes acceso a esta evaluación.');
        redirect('/monitor/grupos.php');
    }
}

$pageTitle = 'Evaluación - ' . $evaluacion['alumno_nombre'];

// Obtener respuestas con ítems
$stmt = $pdo->prepare("
    SELECT i.texto, i.orden, r.valor
    FROM items_evaluacion i
    LEFT JOIN respuestas r ON i.id = r.item_id AND r.evaluacion_id = ?
    WHERE i.plantilla_id = ?
    ORDER BY i.orden
");
$stmt->execute([$evaluacion_id, $evaluacion['plantilla_id']]);
$items = $stmt->fetchAll();

include INCLUDES_PATH . '/header.php';
?>

<div class="page-header">
    <h1>
        <a href="/monitor/historial.php?alumno=<?= $evaluacion['alumno_id'] ?>" style="color: var(--gray-400); margin-right: 0.5rem;">←</a>
        Detalle de Evaluación
    </h1>
    <div class="actions">
        <?php if ($evaluacion['monitor_id'] == $user['id']): ?>
        <a href="/monitor/evaluar.php?alumno=<?= $evaluacion['alumno_id'] ?>&evaluacion=<?= $evaluacion_id ?>" class="btn btn-primary">
            Editar
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Info del alumno -->
<div class="card" style="margin-bottom: 1.5rem;">
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem;">
        <div>
            <small style="color: var(--gray-500);">Alumno</small>
            <p style="font-weight: 600; margin: 0;">
                <?= sanitize($evaluacion['apellido1'] . ' ' . $evaluacion['apellido2'] . ', ' . $evaluacion['alumno_nombre']) ?>
            </p>
        </div>
        <div>
            <small style="color: var(--gray-500);">Grupo</small>
            <p style="margin: 0;"><?= sanitize($evaluacion['grupo_nombre']) ?></p>
        </div>
        <div>
            <small style="color: var(--gray-500);">Nivel evaluado</small>
            <p style="margin: 0;"><span class="badge badge-info"><?= sanitize($evaluacion['nivel_evaluado']) ?></span></p>
        </div>
        <div>
            <small style="color: var(--gray-500);">Fecha</small>
            <p style="margin: 0;"><?= formatDate($evaluacion['fecha']) ?></p>
        </div>
        <div>
            <small style="color: var(--gray-500);">Período</small>
            <p style="margin: 0;"><?= sanitize(str_replace('_', ' ', ucfirst($evaluacion['periodo']))) ?></p>
        </div>
        <div>
            <small style="color: var(--gray-500);">Monitor</small>
            <p style="margin: 0;"><?= sanitize($evaluacion['monitor_nombre']) ?></p>
        </div>
    </div>
</div>

<!-- Resultados -->
<div class="card" style="margin-bottom: 1.5rem;">
    <div class="card-header">
        <h3 class="card-title">Resultados de la evaluación</h3>
    </div>
    
    <?php 
    $contadores = ['si' => 0, 'no' => 0, 'a_veces' => 0];
    foreach ($items as $item) {
        if ($item['valor']) {
            $contadores[$item['valor']]++;
        }
    }
    ?>
    
    <div style="display: flex; gap: 2rem; margin-bottom: 1.5rem; padding: 1rem; background: var(--gray-100); border-radius: var(--radius-sm);">
        <div style="text-align: center;">
            <div style="font-size: 1.5rem; font-weight: 700; color: var(--success);"><?= $contadores['si'] ?></div>
            <small style="color: var(--gray-500);">Sí</small>
        </div>
        <div style="text-align: center;">
            <div style="font-size: 1.5rem; font-weight: 700; color: var(--warning);"><?= $contadores['a_veces'] ?></div>
            <small style="color: var(--gray-500);">A veces</small>
        </div>
        <div style="text-align: center;">
            <div style="font-size: 1.5rem; font-weight: 700; color: var(--danger);"><?= $contadores['no'] ?></div>
            <small style="color: var(--gray-500);">No</small>
        </div>
    </div>
    
    <?php foreach ($items as $index => $item): ?>
    <div class="evaluacion-item">
        <span style="background: var(--gray-100); padding: 0.25rem 0.6rem; border-radius: 4px; font-size: 0.85rem; margin-right: 1rem;">
            <?= $index + 1 ?>
        </span>
        <span class="texto"><?= sanitize($item['texto']) ?></span>
        <div>
            <?php if ($item['valor'] === 'si'): ?>
            <span class="badge badge-success">Sí</span>
            <?php elseif ($item['valor'] === 'a_veces'): ?>
            <span class="badge badge-warning">A veces</span>
            <?php elseif ($item['valor'] === 'no'): ?>
            <span class="badge badge-danger">No</span>
            <?php else: ?>
            <span style="color: var(--gray-400);">-</span>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Recomendación y observaciones -->
<?php if ($evaluacion['nivel_recomendado'] || $evaluacion['observaciones']): ?>
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Recomendación y observaciones</h3>
    </div>
    
    <?php if ($evaluacion['nivel_recomendado']): ?>
    <p style="margin-bottom: 1rem;">
        <strong>Nivel recomendado:</strong> 
        <span class="badge badge-success"><?= sanitize($evaluacion['nivel_recomendado']) ?></span>
    </p>
    <?php endif; ?>
    
    <?php if ($evaluacion['observaciones']): ?>
    <p style="color: var(--gray-700);">
        <?= nl2br(sanitize($evaluacion['observaciones'])) ?>
    </p>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php include INCLUDES_PATH . '/footer.php'; ?>
