<?php
/**
 * Aquatiq - Panel Monitor: Mis Grupos
 */

require_once __DIR__ . '/../config/config.php';
requireRole(['monitor', 'coordinador', 'admin', 'superadmin']);

$pageTitle = canAccessAdmin() ? 'Evaluar Grupos' : 'Mis Grupos';
$pdo = getDBConnection();
$user = getCurrentUser();

if (canAccessAdmin()) {
    // Admin/Superadmin: ver todos los grupos activos
    $stmt = $pdo->query("
        SELECT g.*, n.nombre as nivel_nombre, n.id as nivel_id,
               (SELECT COUNT(*) FROM alumnos a WHERE a.grupo_id = g.id AND a.activo = 1) as total_alumnos
        FROM grupos g
        LEFT JOIN niveles n ON g.nivel_id = n.id
        WHERE g.activo = 1
        ORDER BY n.orden, g.nombre
    ");
    $grupos = $stmt->fetchAll();
} else {
    // Obtener grupos asignados al monitor/coordinador
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
}

$totalGrupos = count($grupos);
$totalAlumnos = 0;
foreach ($grupos as $grupoItem) {
    $totalAlumnos += (int)($grupoItem['total_alumnos'] ?? 0);
}

include INCLUDES_PATH . '/header.php';
?>

<div class="page-header">
    <h1><i class="iconoir-group"></i> <?= canAccessAdmin() ? 'Evaluar grupos' : 'Mis Grupos' ?></h1>
    <?php if (canAccessAdmin()): ?>
    <div class="actions">
        <span class="badge badge-info">Vista administrador</span>
    </div>
    <?php endif; ?>
</div>

<?php if (count($grupos) > 0): ?>
<div class="groups-toolbar card" style="margin-bottom: 1.5rem;">
    <div class="groups-toolbar-left">
        <div class="groups-kpi">
            <span class="groups-kpi-value"><?= $totalGrupos ?></span>
            <span class="groups-kpi-label">grupos</span>
        </div>
        <div class="groups-kpi">
            <span class="groups-kpi-value"><?= $totalAlumnos ?></span>
            <span class="groups-kpi-label">alumnas/os</span>
        </div>
    </div>
    <div class="groups-toolbar-right">
        <label for="group-search" class="sr-only">Buscar grupos</label>
        <div class="groups-search">
            <i class="iconoir-search"></i>
            <input id="group-search" type="text" placeholder="Buscar por grupo, nivel u horario">
        </div>
    </div>
</div>

<div class="dashboard-grid groups-grid" id="groups-grid">
    <?php foreach ($grupos as $grupo): ?>
    <div class="card group-card" data-search="<?= sanitize(strtolower($grupo['nombre'] . ' ' . ($grupo['nivel_nombre'] ?? '') . ' ' . ($grupo['horario'] ?? ''))) ?>">
        <div class="card-header">
            <h3 class="card-title"><?= sanitize($grupo['nombre']) ?></h3>
            <?php if ($grupo['nivel_nombre']): ?>
            <span class="badge badge-info"><?= sanitize($grupo['nivel_nombre']) ?></span>
            <?php endif; ?>
        </div>
        
        <div class="group-meta">
            <?php if ($grupo['horario']): ?>
            <div class="group-meta-item">
                <i class="iconoir-clock"></i>
                <span><?= sanitize($grupo['horario']) ?></span>
            </div>
            <?php endif; ?>
            <div class="group-meta-item">
                <i class="iconoir-graduation-cap"></i>
                <span><strong><?= $grupo['total_alumnos'] ?></strong> <?= $grupo['total_alumnos'] == 1 ? 'alumna/o' : 'alumnas/os' ?></span>
            </div>
        </div>
        
        <div class="group-actions">
            <a href="/monitor/alumnos.php?grupo=<?= $grupo['id'] ?>" class="btn btn-primary">
                Evaluar alumnas/os
            </a>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="card empty-state" id="groups-empty" style="display: none;">
    <div class="empty-state-icon"><i class="iconoir-search"></i></div>
    <h3>Sin resultados</h3>
    <p>No se encontraron grupos con ese criterio.</p>
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

<?php if (count($grupos) > 0): ?>
<script>
    (function () {
        const input = document.getElementById('group-search');
        const grid = document.getElementById('groups-grid');
        const empty = document.getElementById('groups-empty');
        if (!input || !grid || !empty) return;

        const cards = Array.from(grid.querySelectorAll('.group-card'));
        const normalize = (value) => value.toLowerCase().trim();

        input.addEventListener('input', () => {
            const query = normalize(input.value);
            let visible = 0;
            cards.forEach((card) => {
                const haystack = card.getAttribute('data-search') || '';
                const match = haystack.includes(query);
                card.style.display = match ? '' : 'none';
                if (match) visible += 1;
            });
            empty.style.display = visible === 0 ? '' : 'none';
        });
    })();
</script>
<?php endif; ?>

<?php include INCLUDES_PATH . '/footer.php'; ?>
