<?php
/**
 * Aquatiq - Gestión de Niveles
 */

require_once __DIR__ . '/../config/config.php';
requireRole(['superadmin', 'admin']);

$pageTitle = 'Niveles';
$pdo = getDBConnection();

// Procesar acciones
if (isPost()) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('error', 'Token de seguridad inválido.');
        redirect('/admin/niveles.php');
    }
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $nombre = trim($_POST['nombre'] ?? '');
        $orden = (int)($_POST['orden'] ?? 0);
        
        if (empty($nombre)) {
            setFlashMessage('error', 'El nombre del nivel es obligatorio.');
        } else {
            $stmt = $pdo->prepare("INSERT INTO niveles (nombre, orden) VALUES (?, ?)");
            $stmt->execute([$nombre, $orden]);
            setFlashMessage('success', 'Nivel creado correctamente.');
        }
    }
    
    if ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $nombre = trim($_POST['nombre'] ?? '');
        $orden = (int)($_POST['orden'] ?? 0);
        
        if (empty($nombre)) {
            setFlashMessage('error', 'El nombre del nivel es obligatorio.');
        } else {
            $stmt = $pdo->prepare("UPDATE niveles SET nombre = ?, orden = ? WHERE id = ?");
            $stmt->execute([$nombre, $orden, $id]);
            setFlashMessage('success', 'Nivel actualizado correctamente.');
        }
    }
    
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("UPDATE niveles SET activo = 0 WHERE id = ?");
        $stmt->execute([$id]);
        setFlashMessage('success', 'Nivel eliminado correctamente.');
    }
    
    if ($action === 'activate') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("UPDATE niveles SET activo = 1 WHERE id = ?");
        $stmt->execute([$id]);
        setFlashMessage('success', 'Nivel activado correctamente.');
    }
    
    redirect('/admin/niveles.php');
}

// Obtener niveles
$showInactive = isset($_GET['inactive']);
$sql = "SELECT n.*, 
        (SELECT COUNT(*) FROM grupos g WHERE g.nivel_id = n.id) as total_grupos,
        (SELECT COUNT(*) FROM plantillas_evaluacion p WHERE p.nivel_id = n.id) as total_plantillas
        FROM niveles n";
if (!$showInactive) {
    $sql .= " WHERE n.activo = 1";
}
$sql .= " ORDER BY n.orden ASC, n.nombre ASC";
$niveles = $pdo->query($sql)->fetchAll();

// Obtener nivel para editar
$editNivel = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM niveles WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editNivel = $stmt->fetch();
}

include INCLUDES_PATH . '/header.php';
?>

<div class="page-header">
    <h1>📊 Niveles</h1>
    <div class="actions">
        <?php if ($showInactive): ?>
        <a href="/admin/niveles.php" class="btn btn-secondary">Ver solo activos</a>
        <?php else: ?>
        <a href="/admin/niveles.php?inactive=1" class="btn btn-secondary">Ver inactivos</a>
        <?php endif; ?>
        <button type="button" class="btn btn-primary" onclick="document.getElementById('modal-crear').showModal()">
            + Nuevo Nivel
        </button>
    </div>
</div>

<?php if (count($niveles) > 0): ?>
<div class="table-container">
    <table class="table">
        <thead>
            <tr>
                <th width="60">Orden</th>
                <th>Nombre</th>
                <th width="100">Grupos</th>
                <th width="100">Plantillas</th>
                <th width="100">Estado</th>
                <th width="150">Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($niveles as $nivel): ?>
            <tr>
                <td><?= $nivel['orden'] ?></td>
                <td><strong><?= sanitize($nivel['nombre']) ?></strong></td>
                <td><?= $nivel['total_grupos'] ?></td>
                <td><?= $nivel['total_plantillas'] ?></td>
                <td>
                    <?php if ($nivel['activo']): ?>
                    <span class="badge badge-success">Activo</span>
                    <?php else: ?>
                    <span class="badge badge-danger">Inactivo</span>
                    <?php endif; ?>
                </td>
                <td class="actions">
                    <a href="/admin/niveles.php?edit=<?= $nivel['id'] ?>" class="btn btn-sm btn-secondary">Editar</a>
                    <?php if ($nivel['activo']): ?>
                    <form method="POST" style="display:inline;">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $nivel['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-danger" data-confirm="¿Desactivar este nivel?">Desactivar</button>
                    </form>
                    <?php else: ?>
                    <form method="POST" style="display:inline;">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="activate">
                        <input type="hidden" name="id" value="<?= $nivel['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-success">Activar</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php else: ?>
<div class="card">
    <div class="empty-state">
        <div class="empty-state-icon">📊</div>
        <h3>No hay niveles</h3>
        <p>Crea el primer nivel para comenzar.</p>
    </div>
</div>
<?php endif; ?>

<!-- Modal Crear -->
<dialog id="modal-crear" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Nuevo Nivel</h2>
            <button type="button" onclick="this.closest('dialog').close()" class="modal-close">&times;</button>
        </div>
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="create">
            
            <div class="form-group">
                <label for="nombre">Nombre del nivel</label>
                <input type="text" id="nombre" name="nombre" class="form-control" required 
                       placeholder="Ej: Burbujita, Medusa, Tortuga...">
            </div>
            
            <div class="form-group">
                <label for="orden">Orden de progresión</label>
                <input type="number" id="orden" name="orden" class="form-control" value="<?= count($niveles) + 1 ?>" min="1">
                <small style="color: var(--gray-500);">Determina el orden en la progresión de niveles</small>
            </div>
            
            <div class="modal-footer">
                <button type="button" onclick="this.closest('dialog').close()" class="btn btn-secondary">Cancelar</button>
                <button type="submit" class="btn btn-primary">Crear Nivel</button>
            </div>
        </form>
    </div>
</dialog>

<!-- Modal Editar -->
<?php if ($editNivel): ?>
<dialog id="modal-editar" class="modal" open>
    <div class="modal-content">
        <div class="modal-header">
            <h2>Editar Nivel</h2>
            <a href="/admin/niveles.php" class="modal-close">&times;</a>
        </div>
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" value="<?= $editNivel['id'] ?>">
            
            <div class="form-group">
                <label for="edit-nombre">Nombre del nivel</label>
                <input type="text" id="edit-nombre" name="nombre" class="form-control" required 
                       value="<?= sanitize($editNivel['nombre']) ?>">
            </div>
            
            <div class="form-group">
                <label for="edit-orden">Orden de progresión</label>
                <input type="number" id="edit-orden" name="orden" class="form-control" 
                       value="<?= $editNivel['orden'] ?>" min="1">
            </div>
            
            <div class="modal-footer">
                <a href="/admin/niveles.php" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary">Guardar Cambios</button>
            </div>
        </form>
    </div>
</dialog>
<?php endif; ?>

<?php include INCLUDES_PATH . '/footer.php'; ?>
