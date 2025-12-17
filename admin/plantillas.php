<?php
/**
 * Aquatiq - Gestión de Plantillas de Evaluación
 */

require_once __DIR__ . '/../config/config.php';
requireRole(['superadmin', 'admin']);

$pageTitle = 'Plantillas de Evaluación';
$pdo = getDBConnection();

// Ver plantilla específica
$verPlantilla = null;
$items = [];
if (isset($_GET['ver'])) {
    $stmt = $pdo->prepare("SELECT p.*, n.nombre as nivel_nombre 
                           FROM plantillas_evaluacion p 
                           JOIN niveles n ON p.nivel_id = n.id 
                           WHERE p.id = ?");
    $stmt->execute([(int)$_GET['ver']]);
    $verPlantilla = $stmt->fetch();
    
    if ($verPlantilla) {
        $stmt = $pdo->prepare("SELECT * FROM items_evaluacion WHERE plantilla_id = ? ORDER BY orden");
        $stmt->execute([$verPlantilla['id']]);
        $items = $stmt->fetchAll();
    }
}

// Procesar acciones
if (isPost()) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('error', 'Token de seguridad inválido.');
        redirect('/admin/plantillas.php');
    }
    
    $action = $_POST['action'] ?? '';
    
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
        redirect('/admin/plantillas.php?ver=' . $plantilla_id);
    }
    
    if ($action === 'update_item') {
        $id = (int)($_POST['id'] ?? 0);
        $plantilla_id = (int)($_POST['plantilla_id'] ?? 0);
        $texto = trim($_POST['texto'] ?? '');
        
        if (empty($texto)) {
            setFlashMessage('error', 'El texto del ítem es obligatorio.');
        } else {
            $stmt = $pdo->prepare("UPDATE items_evaluacion SET texto = ? WHERE id = ?");
            $stmt->execute([$texto, $id]);
            setFlashMessage('success', 'Ítem actualizado correctamente.');
        }
        redirect('/admin/plantillas.php?ver=' . $plantilla_id);
    }
    
    if ($action === 'delete_item') {
        $id = (int)($_POST['id'] ?? 0);
        $plantilla_id = (int)($_POST['plantilla_id'] ?? 0);
        
        $stmt = $pdo->prepare("DELETE FROM items_evaluacion WHERE id = ?");
        $stmt->execute([$id]);
        setFlashMessage('success', 'Ítem eliminado correctamente.');
        redirect('/admin/plantillas.php?ver=' . $plantilla_id);
    }
    
    if ($action === 'reorder') {
        $plantilla_id = (int)($_POST['plantilla_id'] ?? 0);
        $orden = $_POST['orden'] ?? [];
        
        foreach ($orden as $position => $item_id) {
            $stmt = $pdo->prepare("UPDATE items_evaluacion SET orden = ? WHERE id = ? AND plantilla_id = ?");
            $stmt->execute([$position + 1, (int)$item_id, $plantilla_id]);
        }
        
        echo json_encode(['success' => true]);
        exit;
    }
    
    redirect('/admin/plantillas.php');
}

// Obtener plantillas con conteo de ítems
$plantillas = $pdo->query("SELECT p.*, n.nombre as nivel_nombre, n.orden as nivel_orden,
                           (SELECT COUNT(*) FROM items_evaluacion i WHERE i.plantilla_id = p.id) as total_items
                           FROM plantillas_evaluacion p
                           JOIN niveles n ON p.nivel_id = n.id
                           WHERE p.activo = 1
                           ORDER BY n.orden")->fetchAll();

include INCLUDES_PATH . '/header.php';
?>

<?php if ($verPlantilla): ?>
<!-- Vista de plantilla específica -->
<div class="page-header">
    <h1>
        <a href="/admin/plantillas.php" style="color: var(--gray-400); margin-right: 0.5rem;">←</a>
        <?= sanitize($verPlantilla['nombre']) ?>
    </h1>
    <div class="actions">
        <span class="badge badge-info"><?= sanitize($verPlantilla['nivel_nombre']) ?></span>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Ítems de evaluación (<?= count($items) ?>)</h3>
        <button type="button" class="btn btn-sm btn-primary" onclick="document.getElementById('modal-item').showModal()">
            + Añadir ítem
        </button>
    </div>
    
    <?php if (count($items) > 0): ?>
    <div id="items-list">
        <?php foreach ($items as $index => $item): ?>
        <div class="evaluacion-item" data-id="<?= $item['id'] ?>">
            <span class="drag-handle" style="cursor: grab; padding: 0 0.5rem; color: var(--gray-400);">⋮⋮</span>
            <span class="orden-num" style="background: var(--gray-100); padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.85rem; margin-right: 0.75rem;">
                <?= $index + 1 ?>
            </span>
            <span class="texto" style="flex: 1;"><?= sanitize($item['texto']) ?></span>
            <div class="actions" style="display: flex; gap: 0.5rem;">
                <button type="button" class="btn btn-sm btn-secondary" 
                        onclick="editItem(<?= $item['id'] ?>, '<?= addslashes(sanitize($item['texto'])) ?>')">
                    Editar
                </button>
                <form method="POST" style="display:inline;">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="delete_item">
                    <input type="hidden" name="id" value="<?= $item['id'] ?>">
                    <input type="hidden" name="plantilla_id" value="<?= $verPlantilla['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-danger" data-confirm="¿Eliminar este ítem?">✕</button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="empty-state">
        <div class="empty-state-icon"><i class="iconoir-edit-pencil"></i></div>
        <h3>Sin ítems</h3>
        <p>Añade el primer ítem de evaluación.</p>
    </div>
    <?php endif; ?>
</div>

<!-- Modal Añadir Ítem -->
<dialog id="modal-item" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Nuevo Ítem</h2>
            <button type="button" onclick="this.closest('dialog').close()" class="modal-close">&times;</button>
        </div>
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="create_item">
            <input type="hidden" name="plantilla_id" value="<?= $verPlantilla['id'] ?>">
            
            <div class="form-group">
                <label for="texto">Texto del ítem</label>
                <textarea id="texto" name="texto" class="form-control" rows="3" required 
                          placeholder="Ej: Se desplaza sin problemas 25m a crol."></textarea>
            </div>
            
            <div class="modal-footer">
                <button type="button" onclick="this.closest('dialog').close()" class="btn btn-secondary">Cancelar</button>
                <button type="submit" class="btn btn-primary">Añadir Ítem</button>
            </div>
        </form>
    </div>
</dialog>

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
            <input type="hidden" name="plantilla_id" value="<?= $verPlantilla['id'] ?>">
            
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

<?php else: ?>
<!-- Lista de plantillas -->
<div class="page-header">
    <h1><i class="iconoir-clipboard-check"></i> Plantillas de Evaluación</h1>
</div>

<p style="color: var(--gray-500); margin-bottom: 1.5rem;">
    Gestiona los ítems de evaluación para cada nivel. Cada nivel tiene su plantilla con ítems específicos.
</p>

<div class="dashboard-grid">
    <?php foreach ($plantillas as $plantilla): ?>
    <div class="card" style="cursor: pointer;" onclick="window.location='/admin/plantillas.php?ver=<?= $plantilla['id'] ?>'">
        <div class="card-header">
            <h3 class="card-title"><?= sanitize($plantilla['nivel_nombre']) ?></h3>
            <span class="badge badge-info"><?= $plantilla['total_items'] ?> ítems</span>
        </div>
        <p style="color: var(--gray-500); font-size: 0.9rem;">
            <?= sanitize($plantilla['nombre']) ?>
        </p>
        <div style="margin-top: 1rem;">
            <a href="/admin/plantillas.php?ver=<?= $plantilla['id'] ?>" class="btn btn-sm btn-primary">
                Ver ítems →
            </a>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php if (count($plantillas) === 0): ?>
<div class="card">
    <div class="empty-state">
        <div class="empty-state-icon"><i class="iconoir-clipboard-check"></i></div>
        <h3>No hay plantillas</h3>
        <p>Las plantillas se crean automáticamente al crear niveles.</p>
    </div>
</div>
<?php endif; ?>

<?php endif; ?>

<?php include INCLUDES_PATH . '/footer.php'; ?>
