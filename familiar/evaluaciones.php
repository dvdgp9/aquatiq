<?php
/**
 * Aquatiq - Panel Familiar: Ver evaluaciones del alumno
 * Acceso directo sin necesidad de usuario registrado
 */

require_once __DIR__ . '/../config/config.php';

// Verificar que es un familiar logueado
if (!isFamiliar()) {
    redirect('/acceso-familiar.php');
}

$pdo = getDBConnection();
$alumno_id = getFamiliarAlumnoId();

// Obtener datos del alumno
$stmt = $pdo->prepare("
    SELECT a.*, g.nombre as grupo_nombre, n.nombre as nivel_nombre
    FROM alumnos a
    LEFT JOIN grupos g ON a.grupo_id = g.id
    LEFT JOIN niveles n ON g.nivel_id = n.id
    WHERE a.id = ? AND a.activo = 1
");
$stmt->execute([$alumno_id]);
$alumno = $stmt->fetch();

if (!$alumno) {
    session_destroy();
    setFlashMessage('error', 'Alumno no encontrado.');
    redirect('/acceso-familiar.php');
}

$pageTitle = 'Evaluaciones de ' . $alumno['nombre'];

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
$stmt->execute([$alumno_id]);
$evaluaciones = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitize($pageTitle) ?> - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/iconoir-icons/iconoir@main/css/iconoir.css">
    <link rel="icon" type="image/png" href="/favicon.png">
    <link rel="stylesheet" href="/assets/css/style.css">
    
    <!-- PWA Meta Tags -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#0077be">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Aquatiq">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
</head>
<body>
    <header class="main-header">
        <div class="container">
            <a href="/familiar/evaluaciones.php" class="logo">
                <img src="/logo-aquatiq.png" alt="<?= APP_NAME ?>">
            </a>
            
            <div class="user-menu">
                <div class="user-info">
                    <span class="user-name"><?= sanitize($alumno['nombre'] . ' ' . $alumno['apellido1']) ?></span>
                    <span class="user-role">Acceso Familiar</span>
                </div>
                <a href="/logout.php" class="btn-logout">Salir</a>
            </div>
        </div>
    </header>
    
    <main class="main-content">
        <div class="container">

<div class="card" style="margin-bottom: 1.5rem;">
    <div style="display: flex; align-items: center; gap: 1rem;">
        <div style="background: var(--accent-light); width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2rem;">
            <i class="iconoir-swimming"></i>
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

<div class="page-header">
    <h1><i class="iconoir-clipboard-check"></i> Evaluaciones</h1>
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
        
        <a href="/familiar/ver-evaluacion.php?id=<?= $eval['id'] ?>" class="btn btn-primary">
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

        </div>
    </main>
    
    <footer class="main-footer">
        <div class="container">
            <p>&copy; <?= date('Y') ?> <?= APP_NAME ?></p>
        </div>
    </footer>
</body>
</html>
