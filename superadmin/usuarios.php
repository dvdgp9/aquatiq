<?php
/**
 * Aquatiq - Superadmin: Gestión de usuarios
 */

require_once __DIR__ . '/../config/config.php';
requireRole(['superadmin']);

$pageTitle = 'Gestión de Usuarios';
$pdo = getDBConnection();

// Procesar acciones
if (isPost()) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('error', 'Token de seguridad inválido.');
        redirect('/superadmin/usuarios.php');
    }
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $nombre = trim($_POST['nombre'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $rol = $_POST['rol'] ?? '';
        
        if (empty($nombre) || empty($email) || empty($password) || empty($rol)) {
            setFlashMessage('error', 'Todos los campos son obligatorios.');
        } elseif (!in_array($rol, array_keys(ROLES))) {
            setFlashMessage('error', 'Rol no válido.');
        } else {
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                setFlashMessage('error', 'Ya existe un usuario con ese email.');
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO usuarios (nombre, email, password, rol) VALUES (?, ?, ?, ?)");
                $stmt->execute([$nombre, $email, $hash, $rol]);
                setFlashMessage('success', 'Usuario creado correctamente.');
            }
        }
    }
    
    if ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $nombre = trim($_POST['nombre'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $rol = $_POST['rol'] ?? '';
        
        if (empty($nombre) || empty($email) || empty($rol)) {
            setFlashMessage('error', 'Nombre, email y rol son obligatorios.');
        } else {
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?");
            $stmt->execute([$email, $id]);
            if ($stmt->fetch()) {
                setFlashMessage('error', 'Ya existe otro usuario con ese email.');
            } else {
                if (!empty($password)) {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE usuarios SET nombre = ?, email = ?, password = ?, rol = ? WHERE id = ?");
                    $stmt->execute([$nombre, $email, $hash, $rol, $id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE usuarios SET nombre = ?, email = ?, rol = ? WHERE id = ?");
                    $stmt->execute([$nombre, $email, $rol, $id]);
                }
                setFlashMessage('success', 'Usuario actualizado correctamente.');
            }
        }
    }
    
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $user = getCurrentUser();
        
        if ($id == $user['id']) {
            setFlashMessage('error', 'No puedes desactivar tu propio usuario.');
        } else {
            $stmt = $pdo->prepare("UPDATE usuarios SET activo = 0 WHERE id = ?");
            $stmt->execute([$id]);
            setFlashMessage('success', 'Usuario desactivado correctamente.');
        }
    }
    
    if ($action === 'activate') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("UPDATE usuarios SET activo = 1 WHERE id = ?");
        $stmt->execute([$id]);
        setFlashMessage('success', 'Usuario activado correctamente.');
    }
    
    redirect('/superadmin/usuarios.php');
}

// Filtros
$filtroRol = $_GET['rol'] ?? '';
$showInactive = isset($_GET['inactive']);
$busqueda = trim($_GET['q'] ?? '');

// Construir query
$sql = "SELECT * FROM usuarios WHERE 1=1";
$params = [];

if (!$showInactive) {
    $sql .= " AND activo = 1";
}

if ($filtroRol && in_array($filtroRol, array_keys(ROLES))) {
    $sql .= " AND rol = ?";
    $params[] = $filtroRol;
}

if ($busqueda) {
    $sql .= " AND (nombre LIKE ? OR email LIKE ?)";
    $params[] = "%$busqueda%";
    $params[] = "%$busqueda%";
}

$sql .= " ORDER BY rol, nombre";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$usuarios = $stmt->fetchAll();

// Obtener usuario para editar
$editUsuario = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editUsuario = $stmt->fetch();
}

include INCLUDES_PATH . '/header.php';
?>

<div class="page-header">
    <h1>👤 Gestión de Usuarios</h1>
    <div class="actions">
        <button type="button" class="btn btn-primary" onclick="document.getElementById('modal-crear').showModal()">
            + Nuevo Usuario
        </button>
    </div>
</div>

<!-- Filtros -->
<div class="card" style="margin-bottom: 1.5rem;">
    <form method="GET" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end;">
        <div class="form-group" style="margin-bottom: 0; flex: 1; min-width: 200px;">
            <label for="q">Buscar</label>
            <input type="text" id="q" name="q" class="form-control" placeholder="Nombre o email..." value="<?= sanitize($busqueda) ?>">
        </div>
        <div class="form-group" style="margin-bottom: 0; min-width: 180px;">
            <label for="rol">Rol</label>
            <select id="rol" name="rol" class="form-control">
                <option value="">Todos los roles</option>
                <?php foreach (ROLES as $key => $label): ?>
                <option value="<?= $key ?>" <?= $filtroRol === $key ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="margin-bottom: 0;">
            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                <input type="checkbox" name="inactive" <?= $showInactive ? 'checked' : '' ?>>
                Ver inactivos
            </label>
        </div>
        <button type="submit" class="btn btn-primary">Filtrar</button>
        <?php if ($busqueda || $filtroRol || $showInactive): ?>
        <a href="/superadmin/usuarios.php" class="btn btn-secondary">Limpiar</a>
        <?php endif; ?>
    </form>
</div>

<p style="color: var(--gray-500); margin-bottom: 1rem;">
    Mostrando <?= count($usuarios) ?> usuario<?= count($usuarios) != 1 ? 's' : '' ?>
</p>

<?php if (count($usuarios) > 0): ?>
<div class="table-container">
    <table class="table">
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Email</th>
                <th width="150">Rol</th>
                <th width="100">Estado</th>
                <th width="150">Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($usuarios as $usuario): ?>
            <tr>
                <td><strong><?= sanitize($usuario['nombre']) ?></strong></td>
                <td><?= sanitize($usuario['email']) ?></td>
                <td>
                    <?php
                    $rolColors = [
                        'superadmin' => 'badge-danger',
                        'admin' => 'badge-warning',
                        'monitor' => 'badge-info',
                        'padre' => 'badge-success'
                    ];
                    ?>
                    <span class="badge <?= $rolColors[$usuario['rol']] ?? 'badge-info' ?>">
                        <?= ROLES[$usuario['rol']] ?? $usuario['rol'] ?>
                    </span>
                </td>
                <td>
                    <?php if ($usuario['activo']): ?>
                    <span class="badge badge-success">Activo</span>
                    <?php else: ?>
                    <span class="badge badge-danger">Inactivo</span>
                    <?php endif; ?>
                </td>
                <td class="actions">
                    <a href="/superadmin/usuarios.php?edit=<?= $usuario['id'] ?>" class="btn btn-sm btn-secondary">Editar</a>
                    <?php if ($usuario['id'] != getCurrentUser()['id']): ?>
                        <?php if ($usuario['activo']): ?>
                        <form method="POST" style="display:inline;">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $usuario['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-danger" data-confirm="¿Desactivar este usuario?">✕</button>
                        </form>
                        <?php else: ?>
                        <form method="POST" style="display:inline;">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="activate">
                            <input type="hidden" name="id" value="<?= $usuario['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-success">✓</button>
                        </form>
                        <?php endif; ?>
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
        <div class="empty-state-icon">👤</div>
        <h3>No hay usuarios</h3>
        <p>No se encontraron usuarios con los filtros seleccionados.</p>
    </div>
</div>
<?php endif; ?>

<!-- Modal Crear -->
<dialog id="modal-crear" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Nuevo Usuario</h2>
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
                <label for="rol">Rol *</label>
                <select id="rol" name="rol" class="form-control" required>
                    <option value="">Seleccionar...</option>
                    <?php foreach (ROLES as $key => $label): ?>
                    <option value="<?= $key ?>"><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="modal-footer">
                <button type="button" onclick="this.closest('dialog').close()" class="btn btn-secondary">Cancelar</button>
                <button type="submit" class="btn btn-primary">Crear Usuario</button>
            </div>
        </form>
    </div>
</dialog>

<!-- Modal Editar -->
<?php if ($editUsuario): ?>
<dialog id="modal-editar" class="modal" open>
    <div class="modal-content">
        <div class="modal-header">
            <h2>Editar Usuario</h2>
            <a href="/superadmin/usuarios.php" class="modal-close">&times;</a>
        </div>
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" value="<?= $editUsuario['id'] ?>">
            
            <div class="form-group">
                <label for="edit-nombre">Nombre completo *</label>
                <input type="text" id="edit-nombre" name="nombre" class="form-control" required 
                       value="<?= sanitize($editUsuario['nombre']) ?>">
            </div>
            
            <div class="form-group">
                <label for="edit-email">Email *</label>
                <input type="email" id="edit-email" name="email" class="form-control" required 
                       value="<?= sanitize($editUsuario['email']) ?>">
            </div>
            
            <div class="form-group">
                <label for="edit-password">Nueva contraseña</label>
                <input type="password" id="edit-password" name="password" class="form-control" minlength="6">
                <small style="color: var(--gray-500);">Dejar vacío para mantener la actual</small>
            </div>
            
            <div class="form-group">
                <label for="edit-rol">Rol *</label>
                <select id="edit-rol" name="rol" class="form-control" required>
                    <?php foreach (ROLES as $key => $label): ?>
                    <option value="<?= $key ?>" <?= $editUsuario['rol'] === $key ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="modal-footer">
                <a href="/superadmin/usuarios.php" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary">Guardar Cambios</button>
            </div>
        </form>
    </div>
</dialog>
<?php endif; ?>

<?php include INCLUDES_PATH . '/footer.php'; ?>
