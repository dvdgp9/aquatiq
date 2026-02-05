<?php
/**
 * Aquatiq - Panel Monitor/a: Alumnas/os de un grupo
 */

require_once __DIR__ . '/../config/config.php';
requireRole(['monitor', 'coordinador']);

$pdo = getDBConnection();
$user = getCurrentUser();

$grupo_id = (int)($_GET['grupo'] ?? 0);

// Verificar que el monitor tiene acceso a este grupo
$stmt = $pdo->prepare("
    SELECT g.*, n.nombre as nivel_nombre, n.id as nivel_id
    FROM grupos g
    INNER JOIN monitores_grupos mg ON g.id = mg.grupo_id
    LEFT JOIN niveles n ON g.nivel_id = n.id
    WHERE mg.monitor_id = ? AND g.id = ? AND g.activo = 1
");
$stmt->execute([$user['id'], $grupo_id]);
$grupo = $stmt->fetch();

if (!$grupo) {
    setFlashMessage('error', 'No tienes acceso a este grupo.');
    redirect('/monitor/grupos.php');
}

$pageTitle = $grupo['nombre'];

// Obtener alumnas/os del grupo
$stmt = $pdo->prepare("
    SELECT a.*,
           (SELECT COUNT(*) FROM evaluaciones e WHERE e.alumno_id = a.id) as total_evaluaciones,
           (SELECT MAX(e.fecha) FROM evaluaciones e WHERE e.alumno_id = a.id) as ultima_evaluacion
    FROM alumnos a
    WHERE a.grupo_id = ? AND a.activo = 1
    ORDER BY a.apellido1, a.apellido2, a.nombre
");
$stmt->execute([$grupo_id]);
$alumnos = $stmt->fetchAll();

include INCLUDES_PATH . '/header.php';
?>

<div class="page-header">
    <h1>
        <a href="/monitor/grupos.php" style="color: var(--gray-400); margin-right: 0.5rem;">←</a>
        <?= sanitize($grupo['nombre']) ?>
    </h1>
    <div class="actions">
        <?php if ($grupo['nivel_nombre']): ?>
        <span class="badge badge-info"><?= sanitize($grupo['nivel_nombre']) ?></span>
        <?php endif; ?>
    </div>
</div>

<?php if (count($alumnos) > 0): ?>
<div class="table-container">
    <table class="table">
        <thead>
            <tr>
                <th>Alumna/o</th>
                <th width="120">Evaluaciones</th>
                <th width="150">Última evaluación</th>
                <th width="200">Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($alumnos as $alumno): ?>
            <tr>
                <td>
                    <strong><?= sanitize($alumno['apellido1'] . ' ' . $alumno['apellido2']) ?></strong>, 
                    <?= sanitize($alumno['nombre']) ?>
                </td>
                <td>
                    <span class="badge badge-info"><?= $alumno['total_evaluaciones'] ?></span>
                </td>
                <td>
                    <?php if ($alumno['ultima_evaluacion']): ?>
                    <?= formatDate($alumno['ultima_evaluacion']) ?>
                    <?php else: ?>
                    <span style="color: var(--gray-400);">Sin evaluar</span>
                    <?php endif; ?>
                </td>
                <td class="actions">
                    <a href="/monitor/evaluar.php?alumno=<?= $alumno['id'] ?>" class="btn btn-sm btn-primary">
                        + Nueva evaluación
                    </a>
                    <a href="/monitor/historial.php?alumno=<?= $alumno['id'] ?>" class="btn btn-sm btn-secondary">
                        Historial
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php else: ?>
<div class="card">
    <div class="empty-state">
        <div class="empty-state-icon"><i class="iconoir-graduation-cap"></i></div>
        <h3>Sin alumnos</h3>
        <p>Este grupo no tiene alumnos asignados.</p>
    </div>
</div>
<?php endif; ?>

<?php include INCLUDES_PATH . '/footer.php'; ?>
