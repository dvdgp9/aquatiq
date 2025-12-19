<?php
/**
 * Acceso público: detalle de una evaluación
 */

require_once __DIR__ . '/config/config.php';

$pdo = getDBConnection();
$pageTitle = 'Evaluación';

// Se requiere haber identificado previamente un alumno en /evaluaciones.php
if (!isset($_SESSION['public_alumno_id'])) {
    setFlashMessage('error', 'Busca primero a tu hijo/a para ver sus evaluaciones.');
    redirect('/evaluaciones.php');
}

$alumnoId = (int)$_SESSION['public_alumno_id'];
$evaluacionId = (int)($_GET['id'] ?? 0);

// Obtener evaluación verificando que pertenece al alumno identificado
$stmt = $pdo->prepare("
    SELECT e.*, 
           a.nombre as alumno_nombre, a.apellido1, a.apellido2, a.id as alumno_id,
           g.nombre as grupo_nombre,
           p.nombre as plantilla_nombre,
           n.nombre as nivel_evaluado,
           nr.nombre as nivel_recomendado,
           u.nombre as monitor_nombre
    FROM evaluaciones e
    INNER JOIN alumnos a ON e.alumno_id = a.id
    LEFT JOIN grupos g ON a.grupo_id = g.id
    INNER JOIN plantillas_evaluacion p ON e.plantilla_id = p.id
    INNER JOIN niveles n ON p.nivel_id = n.id
    LEFT JOIN niveles nr ON e.recomendacion_nivel_id = nr.id
    LEFT JOIN usuarios u ON e.monitor_id = u.id
    WHERE e.id = ? AND e.alumno_id = ?
");
$stmt->execute([$evaluacionId, $alumnoId]);
$evaluacion = $stmt->fetch();

if (!$evaluacion) {
    setFlashMessage('error', 'No hemos encontrado esta evaluación para el alumno seleccionado.');
    redirect('/evaluaciones.php');
}

// Obtener respuestas con ítems
$stmt = $pdo->prepare("
    SELECT i.texto, i.orden, r.valor
    FROM items_evaluacion i
    LEFT JOIN respuestas r ON i.id = r.item_id AND r.evaluacion_id = ?
    WHERE i.plantilla_id = ?
    ORDER BY i.orden
");
$stmt->execute([$evaluacionId, $evaluacion['plantilla_id']]);
$items = $stmt->fetchAll();

include INCLUDES_PATH . '/header.php';
?>

<div class="page-header">
    <h1>
        <a href="/evaluaciones.php" style="color: var(--gray-400); margin-right: 0.5rem;">←</a>
        Evaluación
    </h1>
</div>

<!-- Cabecera de la evaluación -->
<div class="card" style="margin-bottom: 1.5rem; background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h2 style="margin: 0; font-size: 1.5rem;">
                <?= sanitize($evaluacion['alumno_nombre'] . ' ' . $evaluacion['apellido1'] . ' ' . $evaluacion['apellido2']) ?>
            </h2>
            <p style="margin: 0.5rem 0 0 0; opacity: 0.9;">
                Nivel evaluado: <strong><?= sanitize($evaluacion['nivel_evaluado']) ?></strong>
            </p>
        </div>
        <div style="text-align: right;">
            <div style="font-size: 1.25rem; font-weight: 700;">
                <?= formatDate($evaluacion['fecha']) ?>
            </div>
            <div style="opacity: 0.9;">
                <?= sanitize(str_replace('_', ' ', ucfirst($evaluacion['periodo']))) ?>
            </div>
        </div>
    </div>
</div>

<!-- Resultados -->
<div class="card" style="margin-bottom: 1.5rem;">
    <div class="card-header">
        <h3 class="card-title"><i class="iconoir-clipboard-check"></i> Resultados de la evaluación</h3>
    </div>
    
    <?php 
    $contadores = ['si' => 0, 'no' => 0, 'a_veces' => 0];
    foreach ($items as $item) {
        if ($item['valor']) {
            $contadores[$item['valor']]++;
        }
    }
    $total = array_sum($contadores);
    ?>
    
    <!-- Resumen visual -->
    <div style="display: flex; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap;">
        <div style="flex: 1; min-width: 100px; background: var(--success-light); padding: 1rem; border-radius: var(--radius-sm); text-align: center;">
            <div style="font-size: 2rem; font-weight: 700; color: var(--success);"><?= $contadores['si'] ?></div>
            <div style="color: #047857; font-weight: 500;">Sí</div>
        </div>
        <div style="flex: 1; min-width: 100px; background: var(--warning-light); padding: 1rem; border-radius: var(--radius-sm); text-align: center;">
            <div style="font-size: 2rem; font-weight: 700; color: var(--warning);"><?= $contadores['a_veces'] ?></div>
            <div style="color: #b45309; font-weight: 500;">A veces</div>
        </div>
        <div style="flex: 1; min-width: 100px; background: var(--danger-light); padding: 1rem; border-radius: var(--radius-sm); text-align: center;">
            <div style="font-size: 2rem; font-weight: 700; color: var(--danger);"><?= $contadores['no'] ?></div>
            <div style="color: #b91c1c; font-weight: 500;">No</div>
        </div>
    </div>
    
    <!-- Detalle de ítems -->
    <?php foreach ($items as $index => $item): ?>
    <div class="evaluacion-item">
        <span style="background: var(--gray-100); padding: 0.25rem 0.6rem; border-radius: 4px; font-size: 0.85rem; margin-right: 1rem; min-width: 30px; text-align: center;">
            <?= $index + 1 ?>
        </span>
        <span class="texto"><?= sanitize($item['texto']) ?></span>
        <div>
            <?php if ($item['valor'] === 'si'): ?>
            <span class="badge badge-success">✓ Sí</span>
            <?php elseif ($item['valor'] === 'a_veces'): ?>
            <span class="badge badge-warning">~ A veces</span>
            <?php elseif ($item['valor'] === 'no'): ?>
            <span class="badge badge-danger">✕ No</span>
            <?php else: ?>
            <span style="color: var(--gray-400);">-</span>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Recomendación -->
<?php if ($evaluacion['nivel_recomendado'] || $evaluacion['observaciones']): ?>
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="iconoir-target"></i> Recomendación del monitor</h3>
    </div>
    
    <?php if ($evaluacion['nivel_recomendado']): ?>
    <div style="background: var(--success-light); padding: 1rem; border-radius: var(--radius-sm); margin-bottom: 1rem;">
        <p style="margin: 0; font-size: 1.1rem;">
            <strong>Nivel recomendado para el próximo curso:</strong>
            <span class="badge badge-success" style="font-size: 1rem; padding: 0.5rem 1rem;"><?= sanitize($evaluacion['nivel_recomendado']) ?></span>
        </p>
    </div>
    <?php endif; ?>
    
    <?php if ($evaluacion['observaciones']): ?>
    <div>
        <strong>Observaciones:</strong>
        <p style="margin-top: 0.5rem; color: var(--gray-700);">
            <?= nl2br(sanitize($evaluacion['observaciones'])) ?>
        </p>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Info del monitor -->
<div style="text-align: center; margin-top: 2rem; color: var(--gray-500); font-size: 0.9rem;">
    <p>
        Evaluación realizada por: <strong><?= sanitize($evaluacion['monitor_nombre']) ?></strong>
    </p>
</div>

<?php include INCLUDES_PATH . '/footer.php'; ?>
