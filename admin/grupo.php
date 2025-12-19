<?php
/**
 * Aquatiq - Vista de detalle de Grupo
 * Gestión centralizada: monitores, alumnos, configuración
 */

require_once __DIR__ . '/../config/config.php';
requireRole(['superadmin', 'admin']);

$pdo = getDBConnection();

$grupo_id = (int)($_GET['id'] ?? 0);

if (!$grupo_id) {
    setFlashMessage('error', 'Grupo no especificado.');
    redirect('/admin/grupos.php');
}

// Obtener datos del grupo
$stmt = $pdo->prepare("
    SELECT g.*, n.nombre as nivel_nombre 
    FROM grupos g 
    LEFT JOIN niveles n ON g.nivel_id = n.id 
    WHERE g.id = ?
");
$stmt->execute([$grupo_id]);
$grupo = $stmt->fetch();

if (!$grupo) {
    setFlashMessage('error', 'Grupo no encontrado.');
    redirect('/admin/grupos.php');
}

$pageTitle = $grupo['nombre'];

// Procesar acciones POST
if (isPost()) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('error', 'Token de seguridad inválido.');
        redirect("/admin/grupo.php?id=$grupo_id");
    }
    
    $action = $_POST['action'] ?? '';
    
    // Actualizar datos del grupo
    if ($action === 'update_grupo') {
        $nombre = trim($_POST['nombre'] ?? '');
        $nivel_id = !empty($_POST['nivel_id']) ? (int)$_POST['nivel_id'] : null;
        $horario = trim($_POST['horario'] ?? '');
        
        if (empty($nombre)) {
            setFlashMessage('error', 'El nombre del grupo es obligatorio.');
        } else {
            $stmt = $pdo->prepare("UPDATE grupos SET nombre = ?, nivel_id = ?, horario = ? WHERE id = ?");
            $stmt->execute([$nombre, $nivel_id, $horario, $grupo_id]);
            setFlashMessage('success', 'Grupo actualizado correctamente.');
        }
    }
    
    // Añadir monitor al grupo
    if ($action === 'add_monitor') {
        $monitor_id = (int)($_POST['monitor_id'] ?? 0);
        if ($monitor_id) {
            // Verificar que no esté ya asignado
            $stmt = $pdo->prepare("SELECT 1 FROM monitores_grupos WHERE monitor_id = ? AND grupo_id = ?");
            $stmt->execute([$monitor_id, $grupo_id]);
            if (!$stmt->fetch()) {
                $stmt = $pdo->prepare("INSERT INTO monitores_grupos (monitor_id, grupo_id) VALUES (?, ?)");
                $stmt->execute([$monitor_id, $grupo_id]);
                setFlashMessage('success', 'Monitor asignado al grupo.');
            } else {
                setFlashMessage('info', 'El monitor ya está asignado a este grupo.');
            }
        }
    }
    
    // Quitar monitor del grupo
    if ($action === 'remove_monitor') {
        $monitor_id = (int)($_POST['monitor_id'] ?? 0);
        if ($monitor_id) {
            $stmt = $pdo->prepare("DELETE FROM monitores_grupos WHERE monitor_id = ? AND grupo_id = ?");
            $stmt->execute([$monitor_id, $grupo_id]);
            setFlashMessage('success', 'Monitor desasignado del grupo.');
        }
    }
    
    // Añadir alumno al grupo
    if ($action === 'add_alumno') {
        $alumno_id = (int)($_POST['alumno_id'] ?? 0);
        if ($alumno_id) {
            $stmt = $pdo->prepare("UPDATE alumnos SET grupo_id = ? WHERE id = ?");
            $stmt->execute([$grupo_id, $alumno_id]);
            setFlashMessage('success', 'Alumno añadido al grupo.');
        }
    }
    
    // Quitar alumno del grupo
    if ($action === 'remove_alumno') {
        $alumno_id = (int)($_POST['alumno_id'] ?? 0);
        if ($alumno_id) {
            $stmt = $pdo->prepare("UPDATE alumnos SET grupo_id = NULL WHERE id = ?");
            $stmt->execute([$alumno_id]);
            setFlashMessage('success', 'Alumno quitado del grupo.');
        }
    }
    
    redirect("/admin/grupo.php?id=$grupo_id");
}

// Obtener niveles para el select
$niveles = $pdo->query("SELECT id, nombre FROM niveles WHERE activo = 1 ORDER BY orden")->fetchAll();

// Obtener monitores asignados a este grupo
$stmt = $pdo->prepare("
    SELECT u.id, u.nombre, u.email 
    FROM usuarios u 
    INNER JOIN monitores_grupos mg ON u.id = mg.monitor_id 
    WHERE mg.grupo_id = ? AND u.activo = 1
    ORDER BY u.nombre
");
$stmt->execute([$grupo_id]);
$monitoresAsignados = $stmt->fetchAll();

// Obtener monitores disponibles (no asignados a este grupo)
$stmt = $pdo->prepare("
    SELECT u.id, u.nombre, u.email 
    FROM usuarios u 
    WHERE u.rol = 'monitor' AND u.activo = 1 
    AND u.id NOT IN (SELECT monitor_id FROM monitores_grupos WHERE grupo_id = ?)
    ORDER BY u.nombre
");
$stmt->execute([$grupo_id]);
$monitoresDisponibles = $stmt->fetchAll();

// Obtener alumnos del grupo
$stmt = $pdo->prepare("
    SELECT a.*, 
           (SELECT COUNT(*) FROM evaluaciones e WHERE e.alumno_id = a.id) as total_evaluaciones
    FROM alumnos a 
    WHERE a.grupo_id = ? AND a.activo = 1
    ORDER BY a.apellido1, a.apellido2, a.nombre
");
$stmt->execute([$grupo_id]);
$alumnosGrupo = $stmt->fetchAll();

// Obtener alumnos sin grupo (para poder añadirlos)
$alumnosSinGrupo = $pdo->query("
    SELECT id, nombre, apellido1, apellido2, numero_usuario 
    FROM alumnos 
    WHERE (grupo_id IS NULL OR grupo_id = 0) AND activo = 1
    ORDER BY apellido1, apellido2, nombre
")->fetchAll();

// Refrescar datos del grupo después de posibles cambios
$stmt = $pdo->prepare("
    SELECT g.*, n.nombre as nivel_nombre 
    FROM grupos g 
    LEFT JOIN niveles n ON g.nivel_id = n.id 
    WHERE g.id = ?
");
$stmt->execute([$grupo_id]);
$grupo = $stmt->fetch();

include INCLUDES_PATH . '/header.php';
?>

<div class="page-header">
    <h1>
        <a href="/admin/grupos.php" style="color: var(--gray-400); margin-right: 0.5rem;">←</a>
        <?= sanitize($grupo['nombre']) ?>
    </h1>
    <div class="actions">
        <?php if ($grupo['nivel_nombre']): ?>
        <span class="badge badge-info" style="font-size: 1rem; padding: 0.5rem 1rem;">
            <?= sanitize($grupo['nivel_nombre']) ?>
        </span>
        <?php endif; ?>
        <?php if (!$grupo['activo']): ?>
        <span class="badge badge-danger">Inactivo</span>
        <?php endif; ?>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
    
    <!-- Columna izquierda: Info del grupo y Monitores -->
    <div>
        <!-- Card: Información del grupo -->
        <div class="card" style="margin-bottom: 1.5rem;">
            <div class="card-header">
                <h3 class="card-title"><i class="iconoir-settings"></i> Configuración del grupo</h3>
            </div>
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="update_grupo">
                
                <div class="form-group">
                    <label for="nombre">Nombre del grupo</label>
                    <input type="text" id="nombre" name="nombre" class="form-control" required
                           value="<?= sanitize($grupo['nombre']) ?>">
                </div>
                
                <div class="form-group">
                    <label for="nivel_id">Nivel</label>
                    <select id="nivel_id" name="nivel_id" class="form-control">
                        <option value="">-- Sin nivel asignado --</option>
                        <?php foreach ($niveles as $nivel): ?>
                        <option value="<?= $nivel['id'] ?>" <?= $grupo['nivel_id'] == $nivel['id'] ? 'selected' : '' ?>>
                            <?= sanitize($nivel['nombre']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="horario">Horario</label>
                    <input type="text" id="horario" name="horario" class="form-control"
                           value="<?= sanitize($grupo['horario'] ?? '') ?>" placeholder="Ej: L-X 17:00-18:00">
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="iconoir-check"></i> Guardar cambios
                </button>
            </form>
        </div>
        
        <!-- Card: Monitores -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="iconoir-swimming"></i> Monitores (<?= count($monitoresAsignados) ?>)</h3>
            </div>
            
            <?php if (count($monitoresAsignados) > 0): ?>
            <div style="display: flex; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 1rem;">
                <?php foreach ($monitoresAsignados as $monitor): ?>
                <div style="display: flex; align-items: center; gap: 0.5rem; background: var(--gray-100); padding: 0.5rem 0.75rem; border-radius: var(--radius-sm);">
                    <span><?= sanitize($monitor['nombre']) ?></span>
                    <form method="POST" style="display: inline;">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="remove_monitor">
                        <input type="hidden" name="monitor_id" value="<?= $monitor['id'] ?>">
                        <button type="submit" style="background: none; border: none; color: var(--danger); cursor: pointer; padding: 0;" 
                                data-confirm="¿Quitar a <?= sanitize($monitor['nombre']) ?> de este grupo?">
                            <i class="iconoir-xmark"></i>
                        </button>
                    </form>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p style="color: var(--gray-500); margin-bottom: 1rem;">Sin monitores asignados</p>
            <?php endif; ?>
            
            <?php if (count($monitoresDisponibles) > 0): ?>
            <form method="POST" style="display: flex; gap: 0.5rem;">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="add_monitor">
                <select name="monitor_id" class="form-control" style="flex: 1;">
                    <option value="">-- Seleccionar monitor --</option>
                    <?php foreach ($monitoresDisponibles as $monitor): ?>
                    <option value="<?= $monitor['id'] ?>"><?= sanitize($monitor['nombre']) ?> (<?= sanitize($monitor['email']) ?>)</option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-success">
                    <i class="iconoir-plus"></i> Añadir
                </button>
            </form>
            <?php else: ?>
            <p style="font-size: 0.85rem; color: var(--gray-500);">
                <i class="iconoir-info-circle"></i> No hay más monitores disponibles. 
                <a href="/admin/monitores.php">Crear nuevo monitor</a>
            </p>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Columna derecha: Alumnos -->
    <div>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="iconoir-graduation-cap"></i> Alumnos (<?= count($alumnosGrupo) ?>)</h3>
            </div>
            
            <?php if (count($alumnosGrupo) > 0): ?>
            <div class="table-container" style="margin: 0 -1.5rem;">
                <table class="table" style="margin: 0;">
                    <thead>
                        <tr>
                            <th>Alumno</th>
                            <th width="80">Eval.</th>
                            <th width="60"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($alumnosGrupo as $alumno): ?>
                        <tr>
                            <td>
                                <strong><?= sanitize($alumno['apellido1'] . ' ' . $alumno['apellido2']) ?></strong>, 
                                <?= sanitize($alumno['nombre']) ?>
                                <?php if ($alumno['numero_usuario']): ?>
                                <br><small style="color: var(--gray-500);">#<?= sanitize($alumno['numero_usuario']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-info"><?= $alumno['total_evaluaciones'] ?></span>
                            </td>
                            <td>
                                <form method="POST" style="display: inline;">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="remove_alumno">
                                    <input type="hidden" name="alumno_id" value="<?= $alumno['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger" 
                                            data-confirm="¿Quitar a <?= sanitize($alumno['nombre']) ?> de este grupo?">
                                        <i class="iconoir-xmark"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <p style="color: var(--gray-500); margin-bottom: 1rem;">Sin alumnos asignados</p>
            <?php endif; ?>
            
            <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--gray-100);">
                <?php if (count($alumnosSinGrupo) > 0): ?>
                <form method="POST" style="display: flex; gap: 0.5rem;">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="add_alumno">
                    <select name="alumno_id" class="form-control" style="flex: 1;">
                        <option value="">-- Añadir alumno sin grupo --</option>
                        <?php foreach ($alumnosSinGrupo as $alumno): ?>
                        <option value="<?= $alumno['id'] ?>">
                            <?= sanitize($alumno['apellido1'] . ' ' . $alumno['apellido2'] . ', ' . $alumno['nombre']) ?>
                            <?= $alumno['numero_usuario'] ? "(#{$alumno['numero_usuario']})" : '' ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-success">
                        <i class="iconoir-plus"></i> Añadir
                    </button>
                </form>
                <?php endif; ?>
                
                <p style="font-size: 0.85rem; color: var(--gray-500); margin-top: 0.75rem;">
                    <i class="iconoir-info-circle"></i> 
                    <a href="/admin/alumnos.php">Ir a Alumnos</a> para crear nuevos o importar desde Excel.
                </p>
            </div>
        </div>
    </div>
    
</div>

<?php include INCLUDES_PATH . '/footer.php'; ?>
