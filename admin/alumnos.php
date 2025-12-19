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
            $header = fgetcsv($file, 0, ';');
            
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
                        $grupo_id = null;
                        if (!empty($grupo_nombre)) {
                            $stmt = $pdo->prepare("SELECT id FROM grupos WHERE nombre LIKE ? AND activo = 1 LIMIT 1");
                            $stmt->execute(['%' . $grupo_nombre . '%']);
                            $grupo = $stmt->fetch();
                            if ($grupo) {
                                $grupo_id = $grupo['id'];
                            }
                        }
                        
                        $stmt = $pdo->prepare("SELECT id FROM alumnos WHERE numero_usuario = ? AND numero_usuario != ''");
                        $stmt->execute([$numero_usuario]);
                        $existing = $stmt->fetch();
                        
                        if ($existing) {
                            $stmt = $pdo->prepare("UPDATE alumnos SET nombre = ?, apellido1 = ?, apellido2 = ?, grupo_id = ? WHERE id = ?");
                            $stmt->execute([$nombre, $apellido1, $apellido2, $grupo_id, $existing['id']]);
                        } else {
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
    
    if ($action === 'bulk_import') {
        $rawData = trim($_POST['bulk_data'] ?? '');
        
        if (empty($rawData)) {
            setFlashMessage('error', 'No se recibieron datos para importar.');
            redirect('/admin/alumnos.php');
        }
        
        $lines = explode("\n", $rawData);
        $stats = [
            'alumnos_creados' => 0,
            'alumnos_actualizados' => 0,
            'grupos_creados' => 0,
            'grupos_reutilizados' => 0,
            'advertencias' => [],
            'errores' => []
        ];
        
        $pdo->beginTransaction();
        
        try {
            foreach ($lines as $idx => $line) {
                $lineNum = $idx + 1;
                $line = trim($line);
                if (empty($line)) continue;
                
                $cols = preg_split('/\t/', $line);
                if (count($cols) < 7) {
                    $stats['errores'][] = "Línea $lineNum: formato incorrecto (se esperan 7 columnas)";
                    continue;
                }
                
                $numero_usuario = trim($cols[0]);
                $apellido1 = trim($cols[1]);
                $apellido2 = trim($cols[2]);
                $nombre = trim($cols[3]);
                $nivel_nombre = trim($cols[4]);
                $grupo_nombre = trim($cols[5]);
                $monitor_email = trim($cols[6]);
                
                if (empty($nombre) || empty($apellido1)) {
                    $stats['errores'][] = "Línea $lineNum: nombre y apellido1 obligatorios";
                    continue;
                }
                
                if (empty($nivel_nombre)) {
                    $stats['errores'][] = "Línea $lineNum: nivel obligatorio";
                    continue;
                }
                
                if (empty($grupo_nombre)) {
                    $stats['errores'][] = "Línea $lineNum: grupo obligatorio";
                    continue;
                }
                
                $stmt = $pdo->prepare("SELECT id FROM niveles WHERE LOWER(TRIM(nombre)) = LOWER(?) AND activo = 1 LIMIT 1");
                $stmt->execute([$nivel_nombre]);
                $nivel = $stmt->fetch();
                
                if (!$nivel) {
                    $stats['errores'][] = "Línea $lineNum: nivel '$nivel_nombre' no encontrado";
                    continue;
                }
                $nivel_id = $nivel['id'];
                
                $stmt = $pdo->prepare("SELECT id FROM grupos WHERE LOWER(TRIM(nombre)) = LOWER(?) AND nivel_id = ? AND activo = 1 LIMIT 1");
                $stmt->execute([$grupo_nombre, $nivel_id]);
                $grupo = $stmt->fetch();
                
                if ($grupo) {
                    $grupo_id = $grupo['id'];
                    if (!isset($stats['_grupos_usados'][$grupo_id])) {
                        $stats['grupos_reutilizados']++;
                        $stats['_grupos_usados'][$grupo_id] = true;
                    }
                } else {
                    $stmt = $pdo->prepare("INSERT INTO grupos (nombre, nivel_id) VALUES (?, ?)");
                    $stmt->execute([$grupo_nombre, $nivel_id]);
                    $grupo_id = $pdo->lastInsertId();
                    $stats['grupos_creados']++;
                    $stats['_grupos_usados'][$grupo_id] = true;
                }
                
                if (!empty($monitor_email)) {
                    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE LOWER(TRIM(email)) = LOWER(?) AND rol = 'monitor' AND activo = 1 LIMIT 1");
                    $stmt->execute([$monitor_email]);
                    $monitor = $stmt->fetch();
                    
                    if ($monitor) {
                        $monitor_id = $monitor['id'];
                        $stmt = $pdo->prepare("SELECT 1 FROM monitores_grupos WHERE monitor_id = ? AND grupo_id = ?");
                        $stmt->execute([$monitor_id, $grupo_id]);
                        if (!$stmt->fetch()) {
                            $stmt = $pdo->prepare("INSERT INTO monitores_grupos (monitor_id, grupo_id) VALUES (?, ?)");
                            $stmt->execute([$monitor_id, $grupo_id]);
                        }
                    } else {
                        $stats['advertencias'][] = "Línea $lineNum: monitor '$monitor_email' no encontrado, grupo sin monitor asignado";
                    }
                }
                
                if (!empty($numero_usuario)) {
                    $stmt = $pdo->prepare("SELECT id FROM alumnos WHERE numero_usuario = ?");
                    $stmt->execute([$numero_usuario]);
                    $existing = $stmt->fetch();
                    
                    if ($existing) {
                        $stmt = $pdo->prepare("UPDATE alumnos SET nombre = ?, apellido1 = ?, apellido2 = ?, grupo_id = ? WHERE id = ?");
                        $stmt->execute([$nombre, $apellido1, $apellido2, $grupo_id, $existing['id']]);
                        $stats['alumnos_actualizados']++;
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO alumnos (numero_usuario, nombre, apellido1, apellido2, grupo_id) VALUES (?, ?, ?, ?, ?)");
                        $stmt->execute([$numero_usuario, $nombre, $apellido1, $apellido2, $grupo_id]);
                        $stats['alumnos_creados']++;
                    }
                } else {
                    $stmt = $pdo->prepare("INSERT INTO alumnos (nombre, apellido1, apellido2, grupo_id) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$nombre, $apellido1, $apellido2, $grupo_id]);
                    $stats['alumnos_creados']++;
                }
            }
            
            $pdo->commit();
            
            $mensaje = "Importación completada: ";
            $partes = [];
            if ($stats['alumnos_creados'] > 0) $partes[] = "{$stats['alumnos_creados']} alumno(s) creado(s)";
            if ($stats['alumnos_actualizados'] > 0) $partes[] = "{$stats['alumnos_actualizados']} alumno(s) actualizado(s)";
            if ($stats['grupos_creados'] > 0) $partes[] = "{$stats['grupos_creados']} grupo(s) creado(s)";
            if ($stats['grupos_reutilizados'] > 0) $partes[] = "{$stats['grupos_reutilizados']} grupo(s) reutilizado(s)";
            $mensaje .= implode(', ', $partes) ?: "sin cambios";
            
            if (count($stats['advertencias']) > 0) {
                $mensaje .= ". Advertencias: " . implode('; ', array_slice($stats['advertencias'], 0, 3));
                if (count($stats['advertencias']) > 3) $mensaje .= " (+" . (count($stats['advertencias']) - 3) . " más)";
            }
            if (count($stats['errores']) > 0) {
                $mensaje .= ". Errores: " . implode('; ', array_slice($stats['errores'], 0, 3));
                if (count($stats['errores']) > 3) $mensaje .= " (+" . (count($stats['errores']) - 3) . " más)";
            }
            
            setFlashMessage(count($stats['errores']) > 0 ? 'error' : 'success', $mensaje);
            
        } catch (Exception $e) {
            $pdo->rollBack();
            setFlashMessage('error', 'Error en la importación: ' . $e->getMessage());
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

// Campo padre/tutor eliminado de la gestión de alumnos (ya no se utiliza)

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
    <h1><i class="iconoir-graduation-cap"></i> Alumnos</h1>
    <div class="actions">
        <button type="button" class="btn btn-secondary" onclick="document.getElementById('modal-bulk-import').showModal()">
            <i class="iconoir-paste-clipboard"></i> Pegar desde Excel
        </button>
        <button type="button" class="btn btn-secondary" onclick="document.getElementById('modal-import').showModal()">
            <i class="iconoir-download"></i> Importar CSV
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
        <div class="empty-state-icon"><i class="iconoir-graduation-cap"></i></div>
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

<!-- Modal Importar desde Excel (pegar) -->
<dialog id="modal-bulk-import" class="modal">
    <div class="modal-content" style="max-width: 700px;">
        <div class="modal-header">
            <h2>Importar desde Excel</h2>
            <button type="button" onclick="this.closest('dialog').close()" class="modal-close">&times;</button>
        </div>
        <form method="POST" id="form-bulk-import">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="bulk_import">
            <input type="hidden" name="bulk_data" id="bulk_data_hidden">
            
            <div class="form-group">
                <label>Formato esperado (7 columnas separadas por tabulador)</label>
                <code style="display: block; background: var(--gray-100); padding: 1rem; border-radius: var(--radius-sm); font-size: 0.85rem; overflow-x: auto; white-space: nowrap;">
                    Nº Usuario &nbsp;&nbsp; Apellido1 &nbsp;&nbsp; Apellido2 &nbsp;&nbsp; Nombre &nbsp;&nbsp; Nivel &nbsp;&nbsp; Grupo &nbsp;&nbsp; Monitor/a (email)
                </code>
            </div>
            
            <div class="form-group">
                <label for="bulk_data">Pega aquí las filas copiadas desde Excel</label>
                <textarea id="bulk_data" class="form-control" rows="10" placeholder="Selecciona y copia las filas desde Excel (sin cabecera) y pégalas aquí...&#10;&#10;Ejemplo:&#10;12345	García	López	Ana	Burbujita	Infantil Lunes	monitor@aquatiq.es&#10;12346	Pérez	Ruiz	Carlos	Medusa	Infantil Martes	monitor@aquatiq.es" style="font-family: monospace; font-size: 0.9rem;" required></textarea>
            </div>
            
            <div style="background: var(--info-light); padding: 1rem; border-radius: var(--radius-sm); margin-bottom: 1rem; font-size: 0.9rem;">
                <strong>Instrucciones:</strong>
                <ul style="margin: 0.5rem 0 0 1.5rem; padding: 0;">
                    <li>Selecciona las filas en Excel (sin la cabecera)</li>
                    <li>Copia (Ctrl+C o Cmd+C)</li>
                    <li>Pega en el área de texto</li>
                    <li>El <strong>Nivel</strong> debe existir en el sistema</li>
                    <li>Si el <strong>Grupo</strong> no existe, se creará automáticamente con ese nivel</li>
                    <li>Si el <strong>Monitor</strong> (email) no existe, se mostrará advertencia</li>
                    <li>Si el <strong>Nº Usuario</strong> ya existe, se actualizarán los datos del alumno</li>
                </ul>
            </div>
            
            <div class="modal-footer">
                <button type="button" onclick="this.closest('dialog').close()" class="btn btn-secondary">Cancelar</button>
                <button type="submit" class="btn btn-primary">Importar Alumnos</button>
            </div>
        </form>
    </div>
</dialog>

<!-- Modal Importar CSV -->
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

<script>
// Manejo del formulario de importación masiva
document.getElementById('form-bulk-import')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const textarea = document.getElementById('bulk_data');
    const hiddenInput = document.getElementById('bulk_data_hidden');
    
    // Copiar el contenido del textarea al campo oculto para envío POST
    hiddenInput.value = textarea.value;
    
    // Enviar el formulario
    this.submit();
});
</script>

<?php include INCLUDES_PATH . '/footer.php'; ?>
