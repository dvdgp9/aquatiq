<?php
/**
 * Aquatiq - Gestión de Grupos
 */

require_once __DIR__ . '/../config/config.php';
requireRole(['superadmin', 'admin']);

$pageTitle = 'Grupos';
$pdo = getDBConnection();

// Procesar acciones
if (isPost()) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('error', 'Token de seguridad inválido.');
        redirect('/admin/grupos.php');
    }
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $nombre = trim($_POST['nombre'] ?? '');
        $nivel_id = !empty($_POST['nivel_id']) ? (int)$_POST['nivel_id'] : null;
        $horario = trim($_POST['horario'] ?? '');
        
        if (empty($nombre)) {
            setFlashMessage('error', 'El nombre del grupo es obligatorio.');
        } else {
            $stmt = $pdo->prepare("INSERT INTO grupos (nombre, nivel_id, horario) VALUES (?, ?, ?)");
            $stmt->execute([$nombre, $nivel_id, $horario]);
            setFlashMessage('success', 'Grupo creado correctamente.');
        }
    }
    
    if ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $nombre = trim($_POST['nombre'] ?? '');
        $nivel_id = !empty($_POST['nivel_id']) ? (int)$_POST['nivel_id'] : null;
        $horario = trim($_POST['horario'] ?? '');
        
        if (empty($nombre)) {
            setFlashMessage('error', 'El nombre del grupo es obligatorio.');
        } else {
            $stmt = $pdo->prepare("UPDATE grupos SET nombre = ?, nivel_id = ?, horario = ? WHERE id = ?");
            $stmt->execute([$nombre, $nivel_id, $horario, $id]);
            setFlashMessage('success', 'Grupo actualizado correctamente.');
        }
    }
    
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("UPDATE grupos SET activo = 0 WHERE id = ?");
        $stmt->execute([$id]);
        setFlashMessage('success', 'Grupo desactivado correctamente.');
    }
    
    if ($action === 'activate') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("UPDATE grupos SET activo = 1 WHERE id = ?");
        $stmt->execute([$id]);
        setFlashMessage('success', 'Grupo activado correctamente.');
    }
    
    redirect('/admin/grupos.php');
}

// Obtener niveles para el select
$niveles = $pdo->query("SELECT id, nombre FROM niveles WHERE activo = 1 ORDER BY orden")->fetchAll();

// Obtener grupos
$showInactive = isset($_GET['inactive']);
$sql = "SELECT g.*, n.nombre as nivel_nombre,
        (SELECT COUNT(*) FROM alumnos a WHERE a.grupo_id = g.id AND a.activo = 1) as total_alumnos
        FROM grupos g
        LEFT JOIN niveles n ON g.nivel_id = n.id";
if (!$showInactive) {
    $sql .= " WHERE g.activo = 1";
}
$sql .= " ORDER BY n.orden ASC, g.nombre ASC";
$grupos = $pdo->query($sql)->fetchAll();

// Obtener grupo para editar
$editGrupo = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM grupos WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editGrupo = $stmt->fetch();
}

include INCLUDES_PATH . '/header.php';
?>

<div class="page-header">
    <h1><i class="iconoir-group"></i> Grupos</h1>
    <div class="actions">
        <?php if ($showInactive): ?>
        <a href="/admin/grupos.php" class="btn btn-secondary">Ver solo activos</a>
        <?php else: ?>
        <a href="/admin/grupos.php?inactive=1" class="btn btn-secondary">Ver inactivos</a>
        <?php endif; ?>
        <button type="button" class="btn btn-primary" onclick="document.getElementById('modal-crear').showModal()">
            + Nuevo Grupo
        </button>
    </div>
</div>

<?php if (count($grupos) > 0): ?>
<div class="table-container">
    <table class="table">
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Nivel</th>
                <th>Horario</th>
                <th width="100">Alumnos</th>
                <th width="100">Estado</th>
                <th width="150">Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($grupos as $grupo): ?>
            <tr>
                <td><a href="/admin/grupo.php?id=<?= $grupo['id'] ?>" style="font-weight: 600; color: var(--primary);"><?= sanitize($grupo['nombre']) ?></a></td>
                <td>
                    <?php if ($grupo['nivel_nombre']): ?>
                    <span class="badge badge-info"><?= sanitize($grupo['nivel_nombre']) ?></span>
                    <?php else: ?>
                    <span style="color: var(--gray-400);">Sin nivel</span>
                    <?php endif; ?>
                </td>
                <td><?= sanitize($grupo['horario'] ?: '-') ?></td>
                <td><?= $grupo['total_alumnos'] ?></td>
                <td>
                    <?php if ($grupo['activo']): ?>
                    <span class="badge badge-success">Activo</span>
                    <?php else: ?>
                    <span class="badge badge-danger">Inactivo</span>
                    <?php endif; ?>
                </td>
                <td class="actions">
                    <a href="/admin/grupos.php?edit=<?= $grupo['id'] ?>" class="btn btn-sm btn-secondary">Editar</a>
                    <?php if ($grupo['activo']): ?>
                    <form method="POST" style="display:inline;">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $grupo['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-danger" data-confirm="¿Desactivar este grupo?">Desactivar</button>
                    </form>
                    <?php else: ?>
                    <form method="POST" style="display:inline;">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="activate">
                        <input type="hidden" name="id" value="<?= $grupo['id'] ?>">
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
        <div class="empty-state-icon"><i class="iconoir-group"></i></div>
        <h3>No hay grupos</h3>
        <p>Crea el primer grupo para comenzar.</p>
    </div>
</div>
<?php endif; ?>

<!-- Modal Crear -->
<dialog id="modal-crear" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Nuevo Grupo</h2>
            <button type="button" onclick="this.closest('dialog').close()" class="modal-close">&times;</button>
        </div>
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="create">
            
            <div class="form-group">
                <label for="nombre">Nombre del grupo</label>
                <input type="text" id="nombre" name="nombre" class="form-control" required 
                       placeholder="Ej: Lunes y Miércoles 17:00">
            </div>
            
            <div class="form-group">
                <label for="nivel_id">Nivel</label>
                <select id="nivel_id" name="nivel_id" class="form-control">
                    <option value="">-- Sin nivel asignado --</option>
                    <?php foreach ($niveles as $nivel): ?>
                    <option value="<?= $nivel['id'] ?>"><?= sanitize($nivel['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="horario">Horario</label>
                <input type="text" id="horario" name="horario" class="form-control" 
                       placeholder="Ej: L-X 17:00-18:00">
            </div>
            
            <div class="modal-footer">
                <button type="button" onclick="this.closest('dialog').close()" class="btn btn-secondary">Cancelar</button>
                <button type="submit" class="btn btn-primary">Crear Grupo</button>
            </div>
        </form>
    </div>
</dialog>

<!-- Modal Editar -->
<?php if ($editGrupo): ?>
<dialog id="modal-editar" class="modal" open>
    <div class="modal-content">
        <div class="modal-header">
            <h2>Editar Grupo</h2>
            <a href="/admin/grupos.php" class="modal-close">&times;</a>
        </div>
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" value="<?= $editGrupo['id'] ?>">
            
            <div class="form-group">
                <label for="edit-nombre">Nombre del grupo</label>
                <input type="text" id="edit-nombre" name="nombre" class="form-control" required 
                       value="<?= sanitize($editGrupo['nombre']) ?>">
            </div>
            
            <div class="form-group">
                <label for="edit-nivel_id">Nivel</label>
                <select id="edit-nivel_id" name="nivel_id" class="form-control">
                    <option value="">-- Sin nivel asignado --</option>
                    <?php foreach ($niveles as $nivel): ?>
                    <option value="<?= $nivel['id'] ?>" <?= $editGrupo['nivel_id'] == $nivel['id'] ? 'selected' : '' ?>>
                        <?= sanitize($nivel['nombre']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="edit-horario">Horario</label>
                <input type="text" id="edit-horario" name="horario" class="form-control" 
                       value="<?= sanitize($editGrupo['horario'] ?? '') ?>">
            </div>
            
            <div class="modal-footer">
                <a href="/admin/grupos.php" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary">Guardar Cambios</button>
            </div>
        </form>
    </div>
</dialog>
<?php endif; ?>

<?php include INCLUDES_PATH . '/footer.php'; ?>
