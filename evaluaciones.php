<?php
/**
 * Acceso público: búsqueda de evaluaciones por número de usuario y nombre del alumno
 */

require_once __DIR__ . '/config/config.php';

$pdo = getDBConnection();
$pageTitle = 'Evaluaciones';

// Permitir reiniciar la búsqueda
if (isset($_GET['reset'])) {
    unset($_SESSION['public_alumno_id'], $_SESSION['public_alumno_nombre']);
    redirect('/evaluaciones.php');
}

$alumno = null;
$evaluaciones = [];

// Si ya se había encontrado un alumno en esta sesión, cargarlo
if (isset($_SESSION['public_alumno_id'])) {
    $stmt = $pdo->prepare("
        SELECT a.*, g.nombre as grupo_nombre, n.nombre as nivel_nombre
        FROM alumnos a
        LEFT JOIN grupos g ON a.grupo_id = g.id
        LEFT JOIN niveles n ON g.nivel_id = n.id
        WHERE a.id = ? AND a.activo = 1
    ");
    $stmt->execute([$_SESSION['public_alumno_id']]);
    $alumno = $stmt->fetch();
}

if (isPost()) {
    $numeroUsuario = trim($_POST['numero_usuario'] ?? '');
    $nombreHijo = trim($_POST['nombre_hijo'] ?? '');

    if ($numeroUsuario === '' || $nombreHijo === '') {
        setFlashMessage('error', 'Introduce el número de usuario y el nombre de tu hijo/a.');
        redirect('/evaluaciones.php');
    }

    // Buscar alumno por número de usuario y nombre (case-insensitive, sin espacios)
    $stmt = $pdo->prepare("
        SELECT a.*, g.nombre as grupo_nombre, n.nombre as nivel_nombre
        FROM alumnos a
        LEFT JOIN grupos g ON a.grupo_id = g.id
        LEFT JOIN niveles n ON g.nivel_id = n.id
        WHERE a.numero_usuario = ?
          AND a.activo = 1
          AND LOWER(REPLACE(CONCAT_WS(' ', a.nombre, a.apellido1, a.apellido2), ' ', '')) = LOWER(REPLACE(?, ' ', ''))
        LIMIT 1
    ");
    $stmt->execute([$numeroUsuario, $nombreHijo]);
    $alumno = $stmt->fetch();

    if (!$alumno) {
        setFlashMessage('error', 'No hemos encontrado un alumno con esos datos. Revisa el número de usuario y el nombre.');
        redirect('/evaluaciones.php');
    }

    $_SESSION['public_alumno_id'] = $alumno['id'];
    $_SESSION['public_alumno_nombre'] = $alumno['nombre'];
}

if ($alumno) {
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
    $stmt->execute([$alumno['id']]);
    $evaluaciones = $stmt->fetchAll();
}

include INCLUDES_PATH . '/header.php';
?>

<div class="page-header">
    <h1><i class="iconoir-clipboard-check"></i> Evaluaciones</h1>
</div>

<div class="card" style="margin-bottom: 1.5rem;">
    <div class="card-header">
        <h3 class="card-title">Accede a las evaluaciones</h3>
    </div>
    <form method="post" class="form" style="display: grid; gap: 1rem;">
        <div>
            <label for="numero_usuario" class="form-label">Número de usuario</label>
            <input type="text" id="numero_usuario" name="numero_usuario" class="input" value="<?= isset($numeroUsuario) ? sanitize($numeroUsuario) : '' ?>" required>
        </div>
        <div>
            <label for="nombre_hijo" class="form-label">Nombre y apellidos del alumno</label>
            <input type="text" id="nombre_hijo" name="nombre_hijo" class="input" placeholder="Ej: Ana García López" value="<?= isset($nombreHijo) ? sanitize($nombreHijo) : '' ?>" required>
            <small style="color: var(--gray-500);">Debe coincidir con el nombre registrado.</small>
        </div>
        <div style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
            <button type="submit" class="btn btn-primary"><i class="iconoir-search"></i> Buscar evaluaciones</button>
            <?php if ($alumno): ?>
            <a href="/evaluaciones.php?reset=1" class="btn btn-secondary btn-ghost">Buscar otro alumno</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<?php if ($alumno): ?>
<div class="card" style="margin-bottom: 1.5rem;">
    <div style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
        <div style="background: var(--accent-light); width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2rem;">
            <i class="iconoir-graduation-cap"></i>
        </div>
        <div>
            <h2 style="margin: 0;"><?= sanitize($alumno['nombre'] . ' ' . $alumno['apellido1'] . ' ' . $alumno['apellido2']) ?></h2>
            <p style="margin: 0; color: var(--gray-500);">
                <?php if ($alumno['grupo_nombre']): ?>
                <?= sanitize($alumno['grupo_nombre']) ?>
                <?php endif; ?>
                <?php if ($alumno['nivel_nombre']): ?>
                 • <span class="badge badge-info"><?= sanitize($alumno['nivel_nombre']) ?></span>
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
        
        <a href="/evaluacion.php?id=<?= $eval['id'] ?>" class="btn btn-primary">
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
        <p>Aún no hay evaluaciones registradas para <?= sanitize($alumno['nombre']) ?>.</p>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>

<?php include INCLUDES_PATH . '/footer.php'; ?>
