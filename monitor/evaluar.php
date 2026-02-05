<?php
/**
 * Aquatiq - Panel Monitor: Crear/Editar Evaluación
 */

require_once __DIR__ . '/../config/config.php';
requireRole(['monitor', 'coordinador', 'admin', 'superadmin']);

$pdo = getDBConnection();
$user = getCurrentUser();

$alumno_id = (int)($_GET['alumno'] ?? 0);
$evaluacion_id = (int)($_GET['evaluacion'] ?? 0);

if (canAccessAdmin()) {
    // Admin/Superadmin: acceso a cualquier alumno activo
    $stmt = $pdo->prepare("
        SELECT a.*, g.nombre as grupo_nombre, g.id as grupo_id, n.id as nivel_id, n.nombre as nivel_nombre
        FROM alumnos a
        INNER JOIN grupos g ON a.grupo_id = g.id
        LEFT JOIN niveles n ON g.nivel_id = n.id
        WHERE a.id = ? AND a.activo = 1
    ");
    $stmt->execute([$alumno_id]);
    $alumno = $stmt->fetch();
} else {
    // Obtener datos del alumno y verificar acceso
    $stmt = $pdo->prepare("
        SELECT a.*, g.nombre as grupo_nombre, g.id as grupo_id, n.id as nivel_id, n.nombre as nivel_nombre
        FROM alumnos a
        INNER JOIN grupos g ON a.grupo_id = g.id
        INNER JOIN monitores_grupos mg ON g.id = mg.grupo_id
        LEFT JOIN niveles n ON g.nivel_id = n.id
        WHERE a.id = ? AND mg.monitor_id = ? AND a.activo = 1
    ");
    $stmt->execute([$alumno_id, $user['id']]);
    $alumno = $stmt->fetch();
}

if (!$alumno) {
    setFlashMessage('error', 'No tienes acceso a esta alumna/o.');
    redirect('/monitor/grupos.php');
}

$pageTitle = 'Evaluar: ' . $alumno['nombre'];

// Si estamos editando, cargar evaluación existente
$evaluacion = null;
$respuestas = [];
if ($evaluacion_id) {
    $stmt = $pdo->prepare("SELECT * FROM evaluaciones WHERE id = ? AND alumno_id = ? AND monitor_id = ?");
    $stmt->execute([$evaluacion_id, $alumno_id, $user['id']]);
    $evaluacion = $stmt->fetch();
    
    if ($evaluacion) {
        $stmt = $pdo->prepare("SELECT item_id, valor FROM respuestas WHERE evaluacion_id = ?");
        $stmt->execute([$evaluacion_id]);
        while ($row = $stmt->fetch()) {
            $respuestas[$row['item_id']] = $row['valor'];
        }
    }
}

// Obtener todas las plantillas activas (cualquier nivel)
$stmt = $pdo->prepare("
    SELECT p.*, n.nombre as nivel_nombre, n.orden
    FROM plantillas_evaluacion p
    INNER JOIN niveles n ON p.nivel_id = n.id
    WHERE p.activo = 1 AND n.activo = 1
    ORDER BY n.orden
");
$stmt->execute();
$plantillas = $stmt->fetchAll();

// Obtener niveles para recomendación
$niveles = $pdo->query("SELECT id, nombre FROM niveles WHERE activo = 1 ORDER BY orden")->fetchAll();

// Plantilla seleccionada
$plantilla_id = (int)($_GET['plantilla'] ?? ($evaluacion['plantilla_id'] ?? 0));
$items = [];

if ($plantilla_id) {
    $stmt = $pdo->prepare("SELECT * FROM items_evaluacion WHERE plantilla_id = ? AND activo = 1 ORDER BY orden");
    $stmt->execute([$plantilla_id]);
    $items = $stmt->fetchAll();
}

// Procesar formulario
if (isPost()) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('error', 'Token de seguridad inválido.');
        redirect("/monitor/evaluar.php?alumno=$alumno_id");
    }
    
    $plantilla_id = (int)($_POST['plantilla_id'] ?? 0);
    $periodo = trim($_POST['periodo'] ?? '');
    $fecha = $_POST['fecha'] ?? date('Y-m-d');
    $observaciones = trim($_POST['observaciones'] ?? '');
    $recomendacion_nivel_id = !empty($_POST['recomendacion_nivel_id']) ? (int)$_POST['recomendacion_nivel_id'] : null;
    $respuestas_post = $_POST['respuestas'] ?? [];
    
    if (empty($periodo) || empty($plantilla_id)) {
        setFlashMessage('error', 'Período y plantilla son obligatorios.');
    } else {
        if ($evaluacion_id) {
            // Actualizar evaluación existente
            $stmt = $pdo->prepare("
                UPDATE evaluaciones 
                SET plantilla_id = ?, periodo = ?, fecha = ?, observaciones = ?, recomendacion_nivel_id = ?
                WHERE id = ?
            ");
            $stmt->execute([$plantilla_id, $periodo, $fecha, $observaciones, $recomendacion_nivel_id, $evaluacion_id]);
            
            // Eliminar respuestas anteriores
            $pdo->prepare("DELETE FROM respuestas WHERE evaluacion_id = ?")->execute([$evaluacion_id]);
            $eval_id = $evaluacion_id;
        } else {
            // Crear nueva evaluación
            $stmt = $pdo->prepare("
                INSERT INTO evaluaciones (alumno_id, plantilla_id, monitor_id, periodo, fecha, observaciones, recomendacion_nivel_id)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$alumno_id, $plantilla_id, $user['id'], $periodo, $fecha, $observaciones, $recomendacion_nivel_id]);
            $eval_id = $pdo->lastInsertId();
        }
        
        // Guardar respuestas
        $stmt = $pdo->prepare("INSERT INTO respuestas (evaluacion_id, item_id, valor) VALUES (?, ?, ?)");
        foreach ($respuestas_post as $item_id => $valor) {
            if (in_array($valor, ['si', 'no', 'a_veces'])) {
                $stmt->execute([$eval_id, (int)$item_id, $valor]);
            }
        }
        
        setFlashMessage('success', 'Evaluación guardada correctamente.');
        redirect("/monitor/alumnos.php?grupo=" . $alumno['grupo_id']);
    }
}

include INCLUDES_PATH . '/header.php';
?>

<div class="page-header">
    <h1>
        <a href="/monitor/alumnos.php?grupo=<?= $alumno['grupo_id'] ?>" style="color: var(--gray-400); margin-right: 0.5rem;">←</a>
        <?= $evaluacion ? 'Editar' : 'Nueva' ?> Evaluación
    </h1>
</div>

<div class="card student-summary" style="margin-bottom: 1.5rem;">
    <div class="student-summary-header">
        <div class="student-avatar">
            <i class="iconoir-graduation-cap"></i>
        </div>
        <div>
            <p class="student-label">Alumno/a</p>
            <h2 class="student-name"><?= sanitize($alumno['apellido1'] . ' ' . $alumno['apellido2'] . ', ' . $alumno['nombre']) ?></h2>
            <div class="student-tags">
                <?php if (!empty($alumno['grupo_nombre'])): ?>
                <span class="badge badge-info"><?= sanitize($alumno['grupo_nombre']) ?></span>
                <?php endif; ?>
                <?php if (!empty($alumno['nivel_nombre'])): ?>
                <span class="badge badge-success"><?= sanitize($alumno['nivel_nombre']) ?></span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="student-meta">
        <?php if (!empty($alumno['numero_usuario'])): ?>
        <div class="student-meta-item">
            <span>Nº usuario</span>
            <strong><?= sanitize($alumno['numero_usuario']) ?></strong>
        </div>
        <?php endif; ?>
        <?php if (!empty($alumno['fecha_nacimiento'])): ?>
        <div class="student-meta-item">
            <span>Fecha nacimiento</span>
            <strong><?= formatDate($alumno['fecha_nacimiento']) ?></strong>
        </div>
        <?php endif; ?>
    </div>
</div>

<form method="POST" class="evaluacion-form">
    <?= csrfField() ?>
    
    <div class="card" style="margin-bottom: 1.5rem;">
        <div class="card-header">
            <h3 class="card-title">Datos de la evaluación</h3>
        </div>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
            <div class="form-group" style="margin-bottom: 0;">
                <label for="periodo">Período *</label>
                <select id="periodo" name="periodo" class="form-control" required>
                    <option value="">Seleccionar...</option>
                    <option value="enero_<?= date('Y') ?>" <?= ($evaluacion['periodo'] ?? '') === 'enero_' . date('Y') ? 'selected' : '' ?>>
                        Enero <?= date('Y') ?>
                    </option>
                    <option value="mayo_<?= date('Y') ?>" <?= ($evaluacion['periodo'] ?? '') === 'mayo_' . date('Y') ? 'selected' : '' ?>>
                        Mayo <?= date('Y') ?>
                    </option>
                    <option value="final_<?= date('Y') ?>" <?= ($evaluacion['periodo'] ?? '') === 'final_' . date('Y') ? 'selected' : '' ?>>
                        Final de curso <?= date('Y') ?>
                    </option>
                </select>
            </div>
            
            <div class="form-group" style="margin-bottom: 0;">
                <label for="fecha">Fecha</label>
                <input type="date" id="fecha" name="fecha" class="form-control" 
                       value="<?= $evaluacion['fecha'] ?? date('Y-m-d') ?>">
            </div>
            
            <div class="form-group" style="margin-bottom: 0;">
                <label for="plantilla_id">Nivel a evaluar *</label>
                <select id="plantilla_id" name="plantilla_id" class="form-control" required onchange="changePlantilla(this.value)">
                    <option value="">Seleccionar nivel...</option>
                    <?php foreach ($plantillas as $p): ?>
                    <option value="<?= $p['id'] ?>" <?= $plantilla_id == $p['id'] ? 'selected' : '' ?>>
                        <?= sanitize($p['nivel_nombre']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>
    
    <?php if ($plantilla_id && count($items) > 0): ?>
    <div class="card" style="margin-bottom: 1.5rem;">
        <div class="card-header">
            <h3 class="card-title">Ítems de evaluación (<?= count($items) ?>)</h3>
            <small style="color: var(--gray-500);">Marca la respuesta para cada ítem</small>
        </div>
        
        <?php foreach ($items as $index => $item): ?>
        <div class="evaluacion-item">
            <span class="orden-num" style="background: var(--gray-100); padding: 0.25rem 0.6rem; border-radius: 4px; font-size: 0.85rem; margin-right: 1rem; min-width: 30px; text-align: center;">
                <?= $index + 1 ?>
            </span>
            <span class="texto"><?= sanitize($item['texto']) ?></span>
            <div class="opciones">
                <label class="si">
                    <input type="radio" name="respuestas[<?= $item['id'] ?>]" value="si" 
                           <?= ($respuestas[$item['id']] ?? '') === 'si' ? 'checked' : '' ?> required>
                    Sí
                </label>
                <label class="a_veces">
                    <input type="radio" name="respuestas[<?= $item['id'] ?>]" value="a_veces"
                           <?= ($respuestas[$item['id']] ?? '') === 'a_veces' ? 'checked' : '' ?>>
                    A veces / casi
                </label>
                <label class="no">
                    <input type="radio" name="respuestas[<?= $item['id'] ?>]" value="no"
                           <?= ($respuestas[$item['id']] ?? '') === 'no' ? 'checked' : '' ?>>
                    No
                </label>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <div class="card" style="margin-bottom: 1.5rem;">
        <div class="card-header">
            <h3 class="card-title">Recomendación y observaciones</h3>
        </div>
        
        <div class="form-group">
            <label for="recomendacion_nivel_id">Nivel recomendado para siguiente curso</label>
            <select id="recomendacion_nivel_id" name="recomendacion_nivel_id" class="form-control">
                <option value="">-- Sin recomendación --</option>
                <?php foreach ($niveles as $nivel): ?>
                <option value="<?= $nivel['id'] ?>" <?= ($evaluacion['recomendacion_nivel_id'] ?? '') == $nivel['id'] ? 'selected' : '' ?>>
                    <?= sanitize($nivel['nombre']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="form-group" style="margin-bottom: 0;">
            <label for="observaciones">Observaciones</label>
            <textarea id="observaciones" name="observaciones" class="form-control" rows="3" 
                      placeholder="Notas adicionales sobre el alumno..."><?= sanitize($evaluacion['observaciones'] ?? '') ?></textarea>
        </div>
    </div>
    
    <div style="display: flex; gap: 1rem; justify-content: flex-end;">
        <a href="/monitor/alumnos.php?grupo=<?= $alumno['grupo_id'] ?>" class="btn btn-secondary">Cancelar</a>
        <button type="submit" class="btn btn-primary btn-lg">
            <i class="iconoir-save-floppy-disk"></i> Guardar Evaluación
        </button>
    </div>
    <?php elseif ($plantilla_id): ?>
    <div class="card">
        <div class="empty-state">
            <div class="empty-state-icon"><i class="iconoir-edit-pencil"></i></div>
            <h3>Sin ítems</h3>
            <p>Esta plantilla no tiene ítems configurados.</p>
        </div>
    </div>
    <?php else: ?>
    <div class="card">
        <div class="empty-state">
            <div class="empty-state-icon"><i class="iconoir-clipboard-check"></i></div>
            <h3>Selecciona un nivel</h3>
            <p>Elige el nivel a evaluar para ver los ítems.</p>
        </div>
    </div>
    <?php endif; ?>
</form>

<script>
function changePlantilla(plantillaId) {
    if (plantillaId) {
        const url = new URL(window.location.href);
        url.searchParams.set('plantilla', plantillaId);
        window.location.href = url.toString();
    }
}
</script>

<?php include INCLUDES_PATH . '/footer.php'; ?>
