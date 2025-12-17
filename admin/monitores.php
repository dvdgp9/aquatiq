<?php
/**
 * Aquatiq - Gestión de Monitores
 */

require_once __DIR__ . '/../config/config.php';
requireRole(['superadmin', 'admin']);

$pageTitle = 'Monitores';
$pdo = getDBConnection();

// Procesar acciones
if (isPost()) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('error', 'Token de seguridad inválido.');
        redirect('/admin/monitores.php');
    }
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $nombre = trim($_POST['nombre'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $grupos_ids = $_POST['grupos'] ?? [];
        
        if (empty($nombre) || empty($email) || empty($password)) {
            setFlashMessage('error', 'Todos los campos son obligatorios.');
        } else {
            // Verificar email único
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                setFlashMessage('error', 'Ya existe un usuario con ese email.');
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO usuarios (nombre, email, password, rol) VALUES (?, ?, ?, 'monitor')");
                $stmt->execute([$nombre, $email, $hash]);
                $monitor_id = $pdo->lastInsertId();
                
                // Asignar grupos
                if (!empty($grupos_ids)) {
                    $stmt = $pdo->prepare("INSERT INTO monitores_grupos (monitor_id, grupo_id) VALUES (?, ?)");
                    foreach ($grupos_ids as $grupo_id) {
                        $stmt->execute([$monitor_id, (int)$grupo_id]);
                    }
                }
                
                setFlashMessage('success', 'Monitor creado correctamente.');
            }
        }
    }
    
    if ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $nombre = trim($_POST['nombre'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $grupos_ids = $_POST['grupos'] ?? [];
        
        if (empty($nombre) || empty($email)) {
            setFlashMessage('error', 'Nombre y email son obligatorios.');
        } else {
            // Verificar email único (excluyendo el actual)
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?");
            $stmt->execute([$email, $id]);
            if ($stmt->fetch()) {
                setFlashMessage('error', 'Ya existe otro usuario con ese email.');
            } else {
                if (!empty($password)) {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE usuarios SET nombre = ?, email = ?, password = ? WHERE id = ?");
                    $stmt->execute([$nombre, $email, $hash, $id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE usuarios SET nombre = ?, email = ? WHERE id = ?");
                    $stmt->execute([$nombre, $email, $id]);
                }
                
                // Actualizar grupos
                $pdo->prepare("DELETE FROM monitores_grupos WHERE monitor_id = ?")->execute([$id]);
                if (!empty($grupos_ids)) {
                    $stmt = $pdo->prepare("INSERT INTO monitores_grupos (monitor_id, grupo_id) VALUES (?, ?)");
                    foreach ($grupos_ids as $grupo_id) {
                        $stmt->execute([$id, (int)$grupo_id]);
                    }
                }
                
                setFlashMessage('success', 'Monitor actualizado correctamente.');
            }
        }
    }
    
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("UPDATE usuarios SET activo = 0 WHERE id = ?");
        $stmt->execute([$id]);
        setFlashMessage('success', 'Monitor desactivado correctamente.');
    }
    
    if ($action === 'activate') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("UPDATE usuarios SET activo = 1 WHERE id = ?");
        $stmt->execute([$id]);
        setFlashMessage('success', 'Monitor activado correctamente.');
    }
    
    redirect('/admin/monitores.php');
}

// Obtener grupos para el select
$grupos = $pdo->query("SELECT g.id, g.nombre, n.nombre as nivel_nombre 
                       FROM grupos g 
                       LEFT JOIN niveles n ON g.nivel_id = n.id 
                       WHERE g.activo = 1 
                       ORDER BY n.orden, g.nombre")->fetchAll();

// Obtener monitores
$showInactive = isset($_GET['inactive']);
$sql = "SELECT u.*, 
        GROUP_CONCAT(g.nombre SEPARATOR ', ') as grupos_nombres,
        COUNT(DISTINCT mg.grupo_id) as total_grupos
        FROM usuarios u
        LEFT JOIN monitores_grupos mg ON u.id = mg.monitor_id
        LEFT JOIN grupos g ON mg.grupo_id = g.id
        WHERE u.rol = 'monitor'";
if (!$showInactive) {
    $sql .= " AND u.activo = 1";
}
$sql .= " GROUP BY u.id ORDER BY u.nombre";
$monitores = $pdo->query($sql)->fetchAll();

// Obtener monitor para editar
$editMonitor = null;
$editMonitorGrupos = [];
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ? AND rol = 'monitor'");
    $stmt->execute([(int)$_GET['edit']]);
    $editMonitor = $stmt->fetch();
    
    if ($editMonitor) {
        $stmt = $pdo->prepare("SELECT grupo_id FROM monitores_grupos WHERE monitor_id = ?");
        $stmt->execute([$editMonitor['id']]);
        $editMonitorGrupos = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}

include INCLUDES_PATH . '/header.php';
?>

<div class="page-header">
    <h1><i class="iconoir-swimming"></i> Monitores</h1>
    <div class="actions">
        <?php if ($showInactive): ?>
        <a href="/admin/monitores.php" class="btn btn-secondary">Ver solo activos</a>
        <?php else: ?>
        <a href="/admin/monitores.php?inactive=1" class="btn btn-secondary">Ver inactivos</a>
        <?php endif; ?>
        <button type="button" class="btn btn-primary" onclick="document.getElementById('modal-crear').showModal()">
            + Nuevo Monitor
        </button>
    </div>
</div>

<?php if (count($monitores) > 0): ?>
<div class="table-container">
    <table class="table">
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Email</th>
                <th>Grupos asignados</th>
                <th width="100">Estado</th>
                <th width="150">Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($monitores as $monitor): ?>
            <tr>
                <td><strong><?= sanitize($monitor['nombre']) ?></strong></td>
                <td><?= sanitize($monitor['email']) ?></td>
                <td>
                    <?php if ($monitor['grupos_nombres']): ?>
                    <?= sanitize($monitor['grupos_nombres']) ?>
                    <?php else: ?>
                    <span style="color: var(--gray-400);">Sin grupos</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($monitor['activo']): ?>
                    <span class="badge badge-success">Activo</span>
                    <?php else: ?>
                    <span class="badge badge-danger">Inactivo</span>
                    <?php endif; ?>
                </td>
                <td class="actions">
                    <a href="/admin/monitores.php?edit=<?= $monitor['id'] ?>" class="btn btn-sm btn-secondary">Editar</a>
                    <?php if ($monitor['activo']): ?>
                    <form method="POST" style="display:inline;">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $monitor['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-danger" data-confirm="¿Desactivar este monitor?">✕</button>
                    </form>
                    <?php else: ?>
                    <form method="POST" style="display:inline;">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="activate">
                        <input type="hidden" name="id" value="<?= $monitor['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-success">✓</button>
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
        <div class="empty-state-icon"><i class="iconoir-swimming"></i></div>
        <h3>No hay monitores</h3>
        <p>Crea el primer monitor para comenzar.</p>
    </div>
</div>
<?php endif; ?>

<!-- Modal Crear -->
<dialog id="modal-crear" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Nuevo Monitor</h2>
            <button type="button" onclick="this.closest('dialog').close()" class="modal-close">&times;</button>
        </div>
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="create">
            
            <div class="form-group">
                <label for="nombre">Nombre completo *</label>
                <input type="text" id="nombre" name="nombre" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label for="email">Email *</label>
                <input type="email" id="email" name="email" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label for="password">Contraseña *</label>
                <input type="password" id="password" name="password" class="form-control" required minlength="6">
            </div>
            
            <div class="form-group">
                <label>Grupos asignados</label>
                <div style="max-height: 150px; overflow-y: auto; border: 1px solid var(--gray-200); border-radius: var(--radius-sm); padding: 0.5rem;">
                    <?php foreach ($grupos as $g): ?>
                    <label style="display: flex; align-items: center; gap: 0.5rem; padding: 0.4rem; cursor: pointer;">
                        <input type="checkbox" name="grupos[]" value="<?= $g['id'] ?>">
                        <?= sanitize($g['nombre']) ?> 
                        <?php if ($g['nivel_nombre']): ?>
                        <small style="color: var(--gray-500);">(<?= sanitize($g['nivel_nombre']) ?>)</small>
                        <?php endif; ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" onclick="this.closest('dialog').close()" class="btn btn-secondary">Cancelar</button>
                <button type="submit" class="btn btn-primary">Crear Monitor</button>
            </div>
        </form>
    </div>
</dialog>

<!-- Modal Editar -->
<?php if ($editMonitor): ?>
<dialog id="modal-editar" class="modal" open>
    <div class="modal-content">
        <div class="modal-header">
            <h2>Editar Monitor</h2>
            <a href="/admin/monitores.php" class="modal-close">&times;</a>
        </div>
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" value="<?= $editMonitor['id'] ?>">
            
            <div class="form-group">
                <label for="edit-nombre">Nombre completo *</label>
                <input type="text" id="edit-nombre" name="nombre" class="form-control" required 
                       value="<?= sanitize($editMonitor['nombre']) ?>">
            </div>
            
            <div class="form-group">
                <label for="edit-email">Email *</label>
                <input type="email" id="edit-email" name="email" class="form-control" required 
                       value="<?= sanitize($editMonitor['email']) ?>">
            </div>
            
            <div class="form-group">
                <label for="edit-password">Nueva contraseña</label>
                <input type="password" id="edit-password" name="password" class="form-control" minlength="6">
                <small style="color: var(--gray-500);">Dejar vacío para mantener la actual</small>
            </div>
            
            <div class="form-group">
                <label>Grupos asignados</label>
                <div style="max-height: 150px; overflow-y: auto; border: 1px solid var(--gray-200); border-radius: var(--radius-sm); padding: 0.5rem;">
                    <?php foreach ($grupos as $g): ?>
                    <label style="display: flex; align-items: center; gap: 0.5rem; padding: 0.4rem; cursor: pointer;">
                        <input type="checkbox" name="grupos[]" value="<?= $g['id'] ?>" 
                               <?= in_array($g['id'], $editMonitorGrupos) ? 'checked' : '' ?>>
                        <?= sanitize($g['nombre']) ?> 
                        <?php if ($g['nivel_nombre']): ?>
                        <small style="color: var(--gray-500);">(<?= sanitize($g['nivel_nombre']) ?>)</small>
                        <?php endif; ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="modal-footer">
                <a href="/admin/monitores.php" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary">Guardar Cambios</button>
            </div>
        </form>
    </div>
</dialog>
<?php endif; ?>

<?php include INCLUDES_PATH . '/footer.php'; ?>
