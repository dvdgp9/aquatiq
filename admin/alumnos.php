<?php
/**
 * Aquatiq - Gestión de Alumnos
 */

require_once __DIR__ . '/../config/config.php';
requireRole(['superadmin', 'admin']);

$pageTitle = 'Alumnos';
$pdo = getDBConnection();

// Procesar acciones
if (isPost()) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('error', 'Token de seguridad inválido.');
        redirect('/admin/alumnos.php');
    }
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $numero_usuario = trim($_POST['numero_usuario'] ?? '');
        $nombre = trim($_POST['nombre'] ?? '');
        $apellido1 = trim($_POST['apellido1'] ?? '');
        $apellido2 = trim($_POST['apellido2'] ?? '');
        $grupo_id = !empty($_POST['grupo_id']) ? (int)$_POST['grupo_id'] : null;
        
        if (empty($nombre) || empty($apellido1)) {
            setFlashMessage('error', 'Nombre y primer apellido son obligatorios.');
        } else {
            $stmt = $pdo->prepare("INSERT INTO alumnos (numero_usuario, nombre, apellido1, apellido2, grupo_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$numero_usuario, $nombre, $apellido1, $apellido2, $grupo_id]);
            setFlashMessage('success', 'Alumno creado correctamente.');
        }
    }
    
    if ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $numero_usuario = trim($_POST['numero_usuario'] ?? '');
        $nombre = trim($_POST['nombre'] ?? '');
        $apellido1 = trim($_POST['apellido1'] ?? '');
        $apellido2 = trim($_POST['apellido2'] ?? '');
        $grupo_id = !empty($_POST['grupo_id']) ? (int)$_POST['grupo_id'] : null;
        
        if (empty($nombre) || empty($apellido1)) {
            setFlashMessage('error', 'Nombre y primer apellido son obligatorios.');
        } else {
            $stmt = $pdo->prepare("UPDATE alumnos SET numero_usuario = ?, nombre = ?, apellido1 = ?, apellido2 = ?, grupo_id = ? WHERE id = ?");
            $stmt->execute([$numero_usuario, $nombre, $apellido1, $apellido2, $grupo_id, $id]);
            setFlashMessage('success', 'Alumno actualizado correctamente.');
        }
    }
    
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("UPDATE alumnos SET activo = 0 WHERE id = ?");
        $stmt->execute([$id]);
        setFlashMessage('success', 'Alumno desactivado correctamente.');
    }
    
    if ($action === 'activate') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("UPDATE alumnos SET activo = 1 WHERE id = ?");
        $stmt->execute([$id]);
        setFlashMessage('success', 'Alumno activado correctamente.');
    }
    
    if ($action === 'import') {
        if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
            $file = fopen($_FILES['csv_file']['tmp_name'], 'r');
            $header = fgetcsv($file, 0, ';'); // Leer cabecera
            
            $imported = 0;
            $errors = 0;
            
            while (($row = fgetcsv($file, 0, ';')) !== false) {
                if (count($row) >= 4) {
                    $numero_usuario = trim($row[0] ?? '');
                    $apellido1 = trim($row[1] ?? '');
                    $apellido2 = trim($row[2] ?? '');
                    $nombre = trim($row[3] ?? '');
                    $grupo_nombre = trim($row[4] ?? '');
                    
                    if (!empty($nombre) && !empty($apellido1)) {
                        // Buscar grupo por nombre
                        $grupo_id = null;
                        if (!empty($grupo_nombre)) {
                            $stmt = $pdo->prepare("SELECT id FROM grupos WHERE nombre LIKE ? AND activo = 1 LIMIT 1");
                            $stmt->execute(['%' . $grupo_nombre . '%']);
                            $grupo = $stmt->fetch();
                            if ($grupo) {
                                $grupo_id = $grupo['id'];
                            }
                        }
                        
                        // Verificar si ya existe
                        $stmt = $pdo->prepare("SELECT id FROM alumnos WHERE numero_usuario = ? AND numero_usuario != ''");
                        $stmt->execute([$numero_usuario]);
                        $existing = $stmt->fetch();
                        
                        if ($existing) {
                            // Actualizar
                            $stmt = $pdo->prepare("UPDATE alumnos SET nombre = ?, apellido1 = ?, apellido2 = ?, grupo_id = ? WHERE id = ?");
                            $stmt->execute([$nombre, $apellido1, $apellido2, $grupo_id, $existing['id']]);
                        } else {
                            // Insertar
                            $stmt = $pdo->prepare("INSERT INTO alumnos (numero_usuario, nombre, apellido1, apellido2, grupo_id) VALUES (?, ?, ?, ?, ?)");
                            $stmt->execute([$numero_usuario, $nombre, $apellido1, $apellido2, $grupo_id]);
                        }
                        $imported++;
                    } else {
                        $errors++;
                    }
                }
            }
            fclose($file);
            
            setFlashMessage('success', "Importación completada: $imported alumnos procesados" . ($errors > 0 ? ", $errors errores" : ""));
        } else {
            setFlashMessage('error', 'Error al subir el archivo.');
        }
    }
    
    redirect('/admin/alumnos.php');
}

// Obtener grupos para el select
$grupos = $pdo->query("SELECT g.id, g.nombre, n.nombre as nivel_nombre 
                       FROM grupos g 
                       LEFT JOIN niveles n ON g.nivel_id = n.id 
                       WHERE g.activo = 1 
                       ORDER BY n.orden, g.nombre")->fetchAll();

// Filtros
$filtroGrupo = isset($_GET['grupo']) ? (int)$_GET['grupo'] : null;
$showInactive = isset($_GET['inactive']);
$busqueda = trim($_GET['q'] ?? '');

// Construir query
$sql = "SELECT a.*, g.nombre as grupo_nombre, n.nombre as nivel_nombre
        FROM alumnos a
        LEFT JOIN grupos g ON a.grupo_id = g.id
        LEFT JOIN niveles n ON g.nivel_id = n.id
        WHERE 1=1";
$params = [];

if (!$showInactive) {
    $sql .= " AND a.activo = 1";
}

if ($filtroGrupo) {
    $sql .= " AND a.grupo_id = ?";
    $params[] = $filtroGrupo;
}

if ($busqueda) {
    $sql .= " AND (a.nombre LIKE ? OR a.apellido1 LIKE ? OR a.apellido2 LIKE ? OR a.numero_usuario LIKE ?)";
    $params[] = "%$busqueda%";
    $params[] = "%$busqueda%";
    $params[] = "%$busqueda%";
    $params[] = "%$busqueda%";
}

$sql .= " ORDER BY a.apellido1, a.apellido2, a.nombre";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$alumnos = $stmt->fetchAll();

// Obtener alumno para editar
$editAlumno = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM alumnos WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editAlumno = $stmt->fetch();
}

include INCLUDES_PATH . '/header.php';
?>

<div class="page-header">
    <h1>🎓 Alumnos</h1>
    <div class="actions">
        <button type="button" class="btn btn-secondary" onclick="document.getElementById('modal-import').showModal()">
            📥 Importar CSV
        </button>
        <button type="button" class="btn btn-primary" onclick="document.getElementById('modal-crear').showModal()">
            + Nuevo Alumno
        </button>
    </div>
</div>

<!-- Filtros -->
<div class="card" style="margin-bottom: 1.5rem;">
    <form method="GET" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end;">
        <div class="form-group" style="margin-bottom: 0; flex: 1; min-width: 200px;">
            <label for="q">Buscar</label>
            <input type="text" id="q" name="q" class="form-control" placeholder="Nombre, apellido o nº usuario..." value="<?= sanitize($busqueda) ?>">
        </div>
        <div class="form-group" style="margin-bottom: 0; min-width: 200px;">
            <label for="grupo">Grupo</label>
            <select id="grupo" name="grupo" class="form-control">
                <option value="">Todos los grupos</option>
                <?php foreach ($grupos as $g): ?>
                <option value="<?= $g['id'] ?>" <?= $filtroGrupo == $g['id'] ? 'selected' : '' ?>>
                    <?= sanitize($g['nombre']) ?> <?= $g['nivel_nombre'] ? "({$g['nivel_nombre']})" : '' ?>
                </option>
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
        <?php if ($busqueda || $filtroGrupo || $showInactive): ?>
        <a href="/admin/alumnos.php" class="btn btn-secondary">Limpiar</a>
        <?php endif; ?>
    </form>
</div>

<p style="color: var(--gray-500); margin-bottom: 1rem;">
    Mostrando <?= count($alumnos) ?> alumno<?= count($alumnos) != 1 ? 's' : '' ?>
</p>

<?php if (count($alumnos) > 0): ?>
<div class="table-container">
    <table class="table">
        <thead>
            <tr>
                <th width="100">Nº Usuario</th>
                <th>Nombre completo</th>
                <th>Grupo</th>
                <th width="100">Estado</th>
                <th width="150">Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($alumnos as $alumno): ?>
            <tr>
                <td><?= sanitize($alumno['numero_usuario'] ?: '-') ?></td>
                <td>
                    <strong><?= sanitize($alumno['apellido1'] . ' ' . $alumno['apellido2']) ?></strong>, 
                    <?= sanitize($alumno['nombre']) ?>
                </td>
                <td>
                    <?php if ($alumno['grupo_nombre']): ?>
                    <?= sanitize($alumno['grupo_nombre']) ?>
                    <?php if ($alumno['nivel_nombre']): ?>
                    <br><small class="badge badge-info"><?= sanitize($alumno['nivel_nombre']) ?></small>
                    <?php endif; ?>
                    <?php else: ?>
                    <span style="color: var(--gray-400);">Sin grupo</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($alumno['activo']): ?>
                    <span class="badge badge-success">Activo</span>
                    <?php else: ?>
                    <span class="badge badge-danger">Inactivo</span>
                    <?php endif; ?>
                </td>
                <td class="actions">
                    <a href="/admin/alumnos.php?edit=<?= $alumno['id'] ?>" class="btn btn-sm btn-secondary">Editar</a>
                    <?php if ($alumno['activo']): ?>
                    <form method="POST" style="display:inline;">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $alumno['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-danger" data-confirm="¿Desactivar este alumno?">✕</button>
                    </form>
                    <?php else: ?>
                    <form method="POST" style="display:inline;">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="activate">
                        <input type="hidden" name="id" value="<?= $alumno['id'] ?>">
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
        <div class="empty-state-icon">🎓</div>
        <h3>No hay alumnos</h3>
        <p>Crea el primer alumno o importa desde un CSV.</p>
    </div>
</div>
<?php endif; ?>

<!-- Modal Crear -->
<dialog id="modal-crear" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Nuevo Alumno</h2>
            <button type="button" onclick="this.closest('dialog').close()" class="modal-close">&times;</button>
        </div>
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="create">
            
            <div class="form-group">
                <label for="numero_usuario">Nº Usuario</label>
                <input type="text" id="numero_usuario" name="numero_usuario" class="form-control">
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label for="apellido1">Primer apellido *</label>
                    <input type="text" id="apellido1" name="apellido1" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="apellido2">Segundo apellido</label>
                    <input type="text" id="apellido2" name="apellido2" class="form-control">
                </div>
            </div>
            
            <div class="form-group">
                <label for="nombre">Nombre *</label>
                <input type="text" id="nombre" name="nombre" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label for="grupo_id">Grupo</label>
                <select id="grupo_id" name="grupo_id" class="form-control">
                    <option value="">-- Sin grupo asignado --</option>
                    <?php foreach ($grupos as $g): ?>
                    <option value="<?= $g['id'] ?>">
                        <?= sanitize($g['nombre']) ?> <?= $g['nivel_nombre'] ? "({$g['nivel_nombre']})" : '' ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="modal-footer">
                <button type="button" onclick="this.closest('dialog').close()" class="btn btn-secondary">Cancelar</button>
                <button type="submit" class="btn btn-primary">Crear Alumno</button>
            </div>
        </form>
    </div>
</dialog>

<!-- Modal Importar -->
<dialog id="modal-import" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Importar Alumnos desde CSV</h2>
            <button type="button" onclick="this.closest('dialog').close()" class="modal-close">&times;</button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="import">
            
            <div class="form-group">
                <label>Formato esperado del CSV (separado por ;)</label>
                <code style="display: block; background: var(--gray-100); padding: 1rem; border-radius: var(--radius-sm); font-size: 0.85rem;">
                    N.º USUARIO;APELLIDO 1;APELLIDO 2;NOMBRE;CURSO/GRUPO
                </code>
            </div>
            
            <div class="form-group">
                <label for="csv_file">Archivo CSV</label>
                <input type="file" id="csv_file" name="csv_file" class="form-control" accept=".csv" required>
            </div>
            
            <p style="font-size: 0.85rem; color: var(--gray-500);">
                Si el alumno ya existe (mismo nº usuario), se actualizarán sus datos.
            </p>
            
            <div class="modal-footer">
                <button type="button" onclick="this.closest('dialog').close()" class="btn btn-secondary">Cancelar</button>
                <button type="submit" class="btn btn-primary">Importar</button>
            </div>
        </form>
    </div>
</dialog>

<!-- Modal Editar -->
<?php if ($editAlumno): ?>
<dialog id="modal-editar" class="modal" open>
    <div class="modal-content">
        <div class="modal-header">
            <h2>Editar Alumno</h2>
            <a href="/admin/alumnos.php" class="modal-close">&times;</a>
        </div>
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" value="<?= $editAlumno['id'] ?>">
            
            <div class="form-group">
                <label for="edit-numero_usuario">Nº Usuario</label>
                <input type="text" id="edit-numero_usuario" name="numero_usuario" class="form-control" 
                       value="<?= sanitize($editAlumno['numero_usuario'] ?? '') ?>">
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label for="edit-apellido1">Primer apellido *</label>
                    <input type="text" id="edit-apellido1" name="apellido1" class="form-control" required 
                           value="<?= sanitize($editAlumno['apellido1']) ?>">
                </div>
                <div class="form-group">
                    <label for="edit-apellido2">Segundo apellido</label>
                    <input type="text" id="edit-apellido2" name="apellido2" class="form-control" 
                           value="<?= sanitize($editAlumno['apellido2'] ?? '') ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label for="edit-nombre">Nombre *</label>
                <input type="text" id="edit-nombre" name="nombre" class="form-control" required 
                       value="<?= sanitize($editAlumno['nombre']) ?>">
            </div>
            
            <div class="form-group">
                <label for="edit-grupo_id">Grupo</label>
                <select id="edit-grupo_id" name="grupo_id" class="form-control">
                    <option value="">-- Sin grupo asignado --</option>
                    <?php foreach ($grupos as $g): ?>
                    <option value="<?= $g['id'] ?>" <?= $editAlumno['grupo_id'] == $g['id'] ? 'selected' : '' ?>>
                        <?= sanitize($g['nombre']) ?> <?= $g['nivel_nombre'] ? "({$g['nivel_nombre']})" : '' ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="modal-footer">
                <a href="/admin/alumnos.php" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary">Guardar Cambios</button>
            </div>
        </form>
    </div>
</dialog>
<?php endif; ?>

<?php include INCLUDES_PATH . '/footer.php'; ?>
