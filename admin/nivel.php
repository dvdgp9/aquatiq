<?php
/**
 * Aquatiq - Vista de detalle de Nivel
 * Gestión centralizada: configuración, grupos, plantillas de evaluación
 */

require_once __DIR__ . '/../config/config.php';
requireRole(['superadmin', 'admin']);

$pdo = getDBConnection();

$nivel_id = (int)($_GET['id'] ?? 0);

if (!$nivel_id) {
    setFlashMessage('error', 'Nivel no especificado.');
    redirect('/admin/niveles.php');
}

// Obtener datos del nivel
$stmt = $pdo->prepare("SELECT * FROM niveles WHERE id = ?");
$stmt->execute([$nivel_id]);
$nivel = $stmt->fetch();

if (!$nivel) {
    setFlashMessage('error', 'Nivel no encontrado.');
    redirect('/admin/niveles.php');
}

$pageTitle = $nivel['nombre'];

// Procesar acciones POST
if (isPost()) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('error', 'Token de seguridad inválido.');
        redirect("/admin/nivel.php?id=$nivel_id");
    }
    
    $action = $_POST['action'] ?? '';
    
    // Actualizar datos del nivel
    if ($action === 'update_nivel') {
        $nombre = trim($_POST['nombre'] ?? '');
        $orden = (int)($_POST['orden'] ?? 0);
        
        if (empty($nombre)) {
            setFlashMessage('error', 'El nombre del nivel es obligatorio.');
        } else {
            $stmt = $pdo->prepare("UPDATE niveles SET nombre = ?, orden = ? WHERE id = ?");
            $stmt->execute([$nombre, $orden, $nivel_id]);
            setFlashMessage('success', 'Nivel actualizado correctamente.');
        }
    }
    
    // Crear plantilla de evaluación
    if ($action === 'create_plantilla') {
        $nombre = trim($_POST['nombre'] ?? '');
        
        if (empty($nombre)) {
            $nombre = "Evaluación " . $nivel['nombre'];
        }
        
        $stmt = $pdo->prepare("INSERT INTO plantillas_evaluacion (nivel_id, nombre) VALUES (?, ?)");
        $stmt->execute([$nivel_id, $nombre]);
        setFlashMessage('success', 'Plantilla creada correctamente.');
    }
    
    // Añadir ítem a plantilla
    if ($action === 'create_item') {
        $plantilla_id = (int)($_POST['plantilla_id'] ?? 0);
        $texto = trim($_POST['texto'] ?? '');
        
        if (empty($texto)) {
            setFlashMessage('error', 'El texto del ítem es obligatorio.');
        } else {
            // Obtener siguiente orden
            $stmt = $pdo->prepare("SELECT COALESCE(MAX(orden), 0) + 1 FROM items_evaluacion WHERE plantilla_id = ?");
            $stmt->execute([$plantilla_id]);
            $orden = $stmt->fetchColumn();
            
            $stmt = $pdo->prepare("INSERT INTO items_evaluacion (plantilla_id, texto, orden) VALUES (?, ?, ?)");
            $stmt->execute([$plantilla_id, $texto, $orden]);
            setFlashMessage('success', 'Ítem añadido correctamente.');
        }
    }
    
    // Actualizar ítem
    if ($action === 'update_item') {
        $id = (int)($_POST['id'] ?? 0);
        $texto = trim($_POST['texto'] ?? '');
        
        if (empty($texto)) {
            setFlashMessage('error', 'El texto del ítem es obligatorio.');
        } else {
            $stmt = $pdo->prepare("UPDATE items_evaluacion SET texto = ? WHERE id = ?");
            $stmt->execute([$texto, $id]);
            setFlashMessage('success', 'Ítem actualizado correctamente.');
        }
    }
    
    // Eliminar ítem
    if ($action === 'delete_item') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM items_evaluacion WHERE id = ?");
        $stmt->execute([$id]);
        setFlashMessage('success', 'Ítem eliminado correctamente.');
    }
    
    // Asignar grupo a este nivel
    if ($action === 'add_grupo') {
        $grupo_id = (int)($_POST['grupo_id'] ?? 0);
        if ($grupo_id) {
            $stmt = $pdo->prepare("UPDATE grupos SET nivel_id = ? WHERE id = ?");
            $stmt->execute([$nivel_id, $grupo_id]);
            setFlashMessage('success', 'Grupo asignado a este nivel.');
        }
    }
    
    // Quitar grupo de este nivel
    if ($action === 'remove_grupo') {
        $grupo_id = (int)($_POST['grupo_id'] ?? 0);
        if ($grupo_id) {
            $stmt = $pdo->prepare("UPDATE grupos SET nivel_id = NULL WHERE id = ?");
            $stmt->execute([$grupo_id]);
            setFlashMessage('success', 'Grupo quitado de este nivel.');
        }
    }
    
    redirect("/admin/nivel.php?id=$nivel_id");
}

// Obtener grupos de este nivel
$stmt = $pdo->prepare("
    SELECT g.*, 
           (SELECT COUNT(*) FROM alumnos a WHERE a.grupo_id = g.id AND a.activo = 1) as total_alumnos,
           (SELECT GROUP_CONCAT(u.nombre SEPARATOR ', ') 
            FROM usuarios u 
            INNER JOIN monitores_grupos mg ON u.id = mg.monitor_id 
            WHERE mg.grupo_id = g.id) as monitores
    FROM grupos g 
    WHERE g.nivel_id = ? AND g.activo = 1
    ORDER BY g.nombre
");
$stmt->execute([$nivel_id]);
$grupos = $stmt->fetchAll();

// Obtener grupos sin nivel asignado (para poder añadirlos)
$gruposSinNivel = $pdo->query("
    SELECT id, nombre, horario 
    FROM grupos 
    WHERE (nivel_id IS NULL OR nivel_id = 0) AND activo = 1
    ORDER BY nombre
")->fetchAll();

// Obtener plantillas de este nivel con sus ítems
$stmt = $pdo->prepare("SELECT * FROM plantillas_evaluacion WHERE nivel_id = ? AND activo = 1");
$stmt->execute([$nivel_id]);
$plantillas = $stmt->fetchAll();

// Cargar ítems para cada plantilla
foreach ($plantillas as &$plantilla) {
    $stmt = $pdo->prepare("SELECT * FROM items_evaluacion WHERE plantilla_id = ? ORDER BY orden");
    $stmt->execute([$plantilla['id']]);
    $plantilla['items'] = $stmt->fetchAll();
}
unset($plantilla);

// Refrescar datos del nivel
$stmt = $pdo->prepare("SELECT * FROM niveles WHERE id = ?");
$stmt->execute([$nivel_id]);
$nivel = $stmt->fetch();

include INCLUDES_PATH . '/header.php';
?>

<div class="page-header">
    <h1>
        <a href="/admin/niveles.php" style="color: var(--gray-400); margin-right: 0.5rem;">←</a>
        <?= sanitize($nivel['nombre']) ?>
    </h1>
    <div class="actions">
        <span class="badge badge-info" style="font-size: 1rem; padding: 0.5rem 1rem;">
            Orden: <?= $nivel['orden'] ?>
        </span>
        <?php if (!$nivel['activo']): ?>
        <span class="badge badge-danger">Inactivo</span>
        <?php endif; ?>
    </div>
</div>

<div style="display: grid; grid-template-columns: 350px 1fr; gap: 1.5rem;">
    
    <!-- Columna izquierda: Configuración y Grupos -->
    <div>
        <!-- Card: Configuración del nivel -->
        <div class="card" style="margin-bottom: 1.5rem;">
            <div class="card-header">
                <h3 class="card-title"><i class="iconoir-settings"></i> Configuración</h3>
            </div>
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="update_nivel">
                
                <div class="form-group">
                    <label for="nombre">Nombre del nivel</label>
                    <input type="text" id="nombre" name="nombre" class="form-control" required
                           value="<?= sanitize($nivel['nombre']) ?>">
                </div>
                
                <div class="form-group">
                    <label for="orden">Orden de progresión</label>
                    <input type="number" id="orden" name="orden" class="form-control" min="1"
                           value="<?= $nivel['orden'] ?>">
                    <small style="color: var(--gray-500);">Determina el orden en la progresión</small>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="iconoir-check"></i> Guardar
                </button>
            </form>
        </div>
        
        <!-- Card: Grupos de este nivel -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="iconoir-group"></i> Grupos (<?= count($grupos) ?>)</h3>
            </div>
            
            <?php if (count($grupos) > 0): ?>
            <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                <?php foreach ($grupos as $grupo): ?>
                <a href="/admin/grupo.php?id=<?= $grupo['id'] ?>" 
                   style="display: block; padding: 0.75rem; background: var(--gray-50); border-radius: var(--radius-sm); text-decoration: none; color: inherit; transition: var(--transition);"
                   onmouseover="this.style.background='var(--gray-100)'" 
                   onmouseout="this.style.background='var(--gray-50)'">
                    <div style="font-weight: 600; color: var(--primary);"><?= sanitize($grupo['nombre']) ?></div>
                    <div style="font-size: 0.85rem; color: var(--gray-500);">
                        <?= $grupo['total_alumnos'] ?> <?= $grupo['total_alumnos'] == 1 ? 'alumna/o' : 'alumnas/os' ?>
                        <?php if ($grupo['monitores']): ?>
                        • <?= sanitize($grupo['monitores']) ?>
                        <?php endif; ?>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p style="color: var(--gray-500);">No hay grupos con este nivel asignado.</p>
            <?php endif; ?>
            
            <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--gray-100);">
                <?php if (count($gruposSinNivel) > 0): ?>
                <form method="POST" style="display: flex; gap: 0.5rem;">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="add_grupo">
                    <select name="grupo_id" class="form-control" style="flex: 1;">
                        <option value="">-- Asignar grupo --</option>
                        <?php foreach ($gruposSinNivel as $g): ?>
                        <option value="<?= $g['id'] ?>">
                            <?= sanitize($g['nombre']) ?>
                            <?= $g['horario'] ? "({$g['horario']})" : '' ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-success">
                        <i class="iconoir-plus"></i>
                    </button>
                </form>
                <?php endif; ?>
                <p style="font-size: 0.85rem; color: var(--gray-500); margin-top: 0.75rem;">
                    <a href="/admin/grupos.php">Ir a Grupos</a> para crear nuevos.
                </p>
            </div>
        </div>
    </div>
    
    <!-- Columna derecha: Plantillas de evaluación -->
    <div>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="iconoir-clipboard-check"></i> Plantillas de Evaluación</h3>
                <?php if (count($plantillas) === 0): ?>
                <form method="POST" style="display: inline;">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="create_plantilla">
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="iconoir-plus"></i> Crear plantilla
                    </button>
                </form>
                <?php endif; ?>
            </div>
            
            <?php if (count($plantillas) > 0): ?>
                <?php foreach ($plantillas as $plantilla): ?>
                <div style="margin-bottom: 1.5rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--gray-100);">
                        <h4 style="margin: 0;"><?= sanitize($plantilla['nombre']) ?></h4>
                        <span class="badge badge-info"><?= count($plantilla['items']) ?> ítems</span>
                    </div>
                    
                    <?php if (count($plantilla['items']) > 0): ?>
                    <div style="display: flex; flex-direction: column; gap: 0.5rem; margin-bottom: 1rem;">
                        <?php foreach ($plantilla['items'] as $index => $item): ?>
                        <div class="evaluacion-item" style="display: flex; align-items: flex-start; gap: 0.75rem; padding: 0.5rem; background: var(--gray-50); border-radius: var(--radius-sm);">
                            <span style="background: var(--gray-200); padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.8rem; font-weight: 600; min-width: 28px; text-align: center;">
                                <?= $index + 1 ?>
                            </span>
                            <span style="flex: 1; font-size: 0.9rem;"><?= sanitize($item['texto']) ?></span>
                            <div style="display: flex; gap: 0.25rem;">
                                <button type="button" class="btn btn-sm btn-secondary" 
                                        onclick="editItem(<?= $item['id'] ?>, '<?= addslashes(sanitize($item['texto'])) ?>')">
                                    <i class="iconoir-edit-pencil"></i>
                                </button>
                                <form method="POST" style="display:inline;">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="delete_item">
                                    <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger" data-confirm="¿Eliminar este ítem?">
                                        <i class="iconoir-xmark"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <p style="color: var(--gray-500); margin-bottom: 1rem;">Sin ítems de evaluación.</p>
                    <?php endif; ?>
                    
                    <!-- Formulario para añadir ítem -->
                    <form method="POST" style="display: flex; gap: 0.5rem;">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="create_item">
                        <input type="hidden" name="plantilla_id" value="<?= $plantilla['id'] ?>">
                        <input type="text" name="texto" class="form-control" style="flex: 1;" 
                               placeholder="Nuevo ítem de evaluación..." required>
                        <button type="submit" class="btn btn-success">
                            <i class="iconoir-plus"></i> Añadir
                        </button>
                    </form>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
            <div class="empty-state">
                <div class="empty-state-icon"><i class="iconoir-clipboard-check"></i></div>
                <h3>Sin plantillas</h3>
                <p>Crea una plantilla de evaluación para este nivel.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
</div>

<!-- Modal Editar Ítem -->
<dialog id="modal-edit-item" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Editar Ítem</h2>
            <button type="button" onclick="this.closest('dialog').close()" class="modal-close">&times;</button>
        </div>
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="update_item">
            <input type="hidden" name="id" id="edit-item-id">
            
            <div class="form-group">
                <label for="edit-texto">Texto del ítem</label>
                <textarea id="edit-texto" name="texto" class="form-control" rows="3" required></textarea>
            </div>
            
            <div class="modal-footer">
                <button type="button" onclick="this.closest('dialog').close()" class="btn btn-secondary">Cancelar</button>
                <button type="submit" class="btn btn-primary">Guardar</button>
            </div>
        </form>
    </div>
</dialog>

<script>
function editItem(id, texto) {
    document.getElementById('edit-item-id').value = id;
    document.getElementById('edit-texto').value = texto;
    document.getElementById('modal-edit-item').showModal();
}
</script>

<?php include INCLUDES_PATH . '/footer.php'; ?>
