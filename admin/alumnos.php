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
    
    if ($action === 'bulk_import') {
        $rows = json_decode($_POST['rows'] ?? '[]', true);
        
        if (!is_array($rows) || count($rows) === 0) {
            setFlashMessage('error', 'No hay datos para importar.');
            redirect('/admin/alumnos.php');
        }
        
        // Cache de niveles existentes
        $nivelesCache = [];
        $stmtNiveles = $pdo->query("SELECT id, LOWER(TRIM(nombre)) as nombre_lower, nombre FROM niveles WHERE activo = 1");
        while ($nivel = $stmtNiveles->fetch(PDO::FETCH_ASSOC)) {
            $nivelesCache[$nivel['nombre_lower']] = $nivel;
        }
        
        // Cache de grupos existentes
        $gruposCache = [];
        $stmtGrupos = $pdo->query("SELECT id, LOWER(TRIM(nombre)) as nombre_lower, nombre, nivel_id FROM grupos WHERE activo = 1");
        while ($grupo = $stmtGrupos->fetch(PDO::FETCH_ASSOC)) {
            $gruposCache[$grupo['nombre_lower']] = $grupo;
        }
        
        // Cache de monitores existentes (por email)
        $monitoresCache = [];
        $stmtMonitores = $pdo->query("SELECT id, LOWER(TRIM(email)) as email_lower, email, nombre FROM usuarios WHERE rol = 'monitor' AND activo = 1");
        while ($monitor = $stmtMonitores->fetch(PDO::FETCH_ASSOC)) {
            $monitoresCache[$monitor['email_lower']] = $monitor;
        }
        
        $stats = [
            'alumnos_creados' => 0,
            'alumnos_actualizados' => 0,
            'grupos_creados' => 0,
            'monitores_asignados' => 0,
            'advertencias' => []
        ];
        
        $pdo->beginTransaction();
        
        try {
            foreach ($rows as $idx => $row) {
                $lineNum = $idx + 1;
                
                $numero_usuario = trim($row['numero_usuario'] ?? '');
                $apellido1 = trim($row['apellido1'] ?? '');
                $apellido2 = trim($row['apellido2'] ?? '');
                $nombre = trim($row['nombre'] ?? '');
                $nivel_nombre = trim($row['nivel'] ?? '');
                $grupo_nombre = trim($row['grupo'] ?? '');
                $monitor_email = trim($row['monitor'] ?? '');
                
                // Validar campos obligatorios
                if (empty($nombre) || empty($apellido1)) {
                    $stats['advertencias'][] = "Línea $lineNum: Falta nombre o apellido1";
                    continue;
                }
                
                // --- Buscar o crear nivel ---
                $nivel_id = null;
                if (!empty($nivel_nombre)) {
                    $nivelKey = mb_strtolower(trim($nivel_nombre), 'UTF-8');
                    if (isset($nivelesCache[$nivelKey])) {
                        $nivel_id = $nivelesCache[$nivelKey]['id'];
                    } else {
                        $stats['advertencias'][] = "Línea $lineNum: Nivel '$nivel_nombre' no encontrado";
                    }
                }
                
                // --- Buscar o crear grupo ---
                $grupo_id = null;
                if (!empty($grupo_nombre)) {
                    $grupoKey = mb_strtolower(trim($grupo_nombre), 'UTF-8');
                    
                    if (isset($gruposCache[$grupoKey])) {
                        $grupo_id = $gruposCache[$grupoKey]['id'];
                        
                        // Si el grupo existe pero el nivel es diferente, actualizar nivel del grupo
                        if ($nivel_id && $gruposCache[$grupoKey]['nivel_id'] != $nivel_id) {
                            $stmtUpdateGrupo = $pdo->prepare("UPDATE grupos SET nivel_id = ? WHERE id = ?");
                            $stmtUpdateGrupo->execute([$nivel_id, $grupo_id]);
                            $gruposCache[$grupoKey]['nivel_id'] = $nivel_id;
                        }
                    } else {
                        // Crear grupo nuevo
                        $stmtGrupo = $pdo->prepare("INSERT INTO grupos (nombre, nivel_id) VALUES (?, ?)");
                        $stmtGrupo->execute([$grupo_nombre, $nivel_id]);
                        $grupo_id = $pdo->lastInsertId();
                        
                        $gruposCache[$grupoKey] = [
                            'id' => $grupo_id,
                            'nombre_lower' => $grupoKey,
                            'nombre' => $grupo_nombre,
                            'nivel_id' => $nivel_id
                        ];
                        $stats['grupos_creados']++;
                    }
                }
                
                // --- Buscar monitor y asignar al grupo ---
                if (!empty($monitor_email) && $grupo_id) {
                    $monitorKey = mb_strtolower(trim($monitor_email), 'UTF-8');
                    
                    if (isset($monitoresCache[$monitorKey])) {
                        $monitor_id = $monitoresCache[$monitorKey]['id'];
                        
                        // Verificar si ya está asignado al grupo
                        $stmtCheck = $pdo->prepare("SELECT 1 FROM monitores_grupos WHERE monitor_id = ? AND grupo_id = ?");
                        $stmtCheck->execute([$monitor_id, $grupo_id]);
                        
                        if (!$stmtCheck->fetch()) {
                            $stmtAsignar = $pdo->prepare("INSERT INTO monitores_grupos (monitor_id, grupo_id) VALUES (?, ?)");
                            $stmtAsignar->execute([$monitor_id, $grupo_id]);
                            $stats['monitores_asignados']++;
                        }
                    } else {
                        $stats['advertencias'][] = "Línea $lineNum: Monitor '$monitor_email' no encontrado";
                    }
                }
                
                // --- Crear o actualizar alumno ---
                $existing = null;
                if (!empty($numero_usuario)) {
                    $stmtExist = $pdo->prepare("SELECT id FROM alumnos WHERE numero_usuario = ?");
                    $stmtExist->execute([$numero_usuario]);
                    $existing = $stmtExist->fetch();
                }
                
                if ($existing) {
                    // Actualizar alumno existente
                    $stmtUpdate = $pdo->prepare("UPDATE alumnos SET nombre = ?, apellido1 = ?, apellido2 = ?, grupo_id = ?, activo = 1 WHERE id = ?");
                    $stmtUpdate->execute([$nombre, $apellido1, $apellido2, $grupo_id, $existing['id']]);
                    $stats['alumnos_actualizados']++;
                } else {
                    // Crear alumno nuevo
                    $stmtInsert = $pdo->prepare("INSERT INTO alumnos (numero_usuario, nombre, apellido1, apellido2, grupo_id) VALUES (?, ?, ?, ?, ?)");
                    $stmtInsert->execute([$numero_usuario, $nombre, $apellido1, $apellido2, $grupo_id]);
                    $stats['alumnos_creados']++;
                }
            }
            
            $pdo->commit();
            
            // Construir mensaje de resultado
            $partes = [];
            if ($stats['alumnos_creados'] > 0) {
                $partes[] = $stats['alumnos_creados'] . " alumno(s) creado(s)";
            }
            if ($stats['alumnos_actualizados'] > 0) {
                $partes[] = $stats['alumnos_actualizados'] . " alumno(s) actualizado(s)";
            }
            if ($stats['grupos_creados'] > 0) {
                $partes[] = $stats['grupos_creados'] . " grupo(s) creado(s)";
            }
            if ($stats['monitores_asignados'] > 0) {
                $partes[] = $stats['monitores_asignados'] . " monitor(es) asignado(s)";
            }
            
            $mensaje = "Importación completada: " . (implode(', ', $partes) ?: 'sin cambios');
            
            if (count($stats['advertencias']) > 0) {
                $mensaje .= ". Advertencias: " . count($stats['advertencias']);
                // Guardar advertencias en sesión para mostrar
                $_SESSION['import_warnings'] = $stats['advertencias'];
            }
            
            setFlashMessage('success', $mensaje);
            
        } catch (Exception $e) {
            $pdo->rollBack();
            setFlashMessage('error', 'Error durante la importación: ' . $e->getMessage());
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
        <button type="button" class="btn btn-secondary" onclick="document.getElementById('modal-import').showModal()">
            <i class="iconoir-download"></i> Importar CSV
        </button>
        <button type="button" class="btn btn-success" onclick="document.getElementById('modal-bulk-import').showModal(); initBulkImportTable();">
            <i class="iconoir-page-edit"></i> Importar desde Excel
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

<!-- Modal Importar desde Excel -->
<dialog id="modal-bulk-import" class="modal">
    <div class="modal-content" style="max-width: 900px;">
        <div class="modal-header">
            <h2><i class="iconoir-page-edit"></i> Importar desde Excel</h2>
            <button type="button" onclick="this.closest('dialog').close()" class="modal-close">&times;</button>
        </div>
        
        <div style="background: var(--accent-light); padding: 1rem; border-radius: var(--radius-sm); margin-bottom: 1rem;">
            <strong><i class="iconoir-info-circle"></i> Cómo usar:</strong>
            <p style="margin: 0.5rem 0 0 0; font-size: 0.9rem;">Copia las columnas desde Excel y pégalas directamente en la tabla. El sistema creará automáticamente los grupos que no existan y asignará los monitores.</p>
        </div>
        
        <div style="background: var(--gray-100); padding: 1rem; border-radius: var(--radius-sm); margin-bottom: 1rem;">
            <strong>Columnas esperadas (en este orden):</strong>
            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; margin-top: 0.5rem;">
                <span class="badge badge-info">1. Nº Usuario</span>
                <span class="badge badge-info">2. Apellido 1</span>
                <span class="badge badge-info">3. Apellido 2</span>
                <span class="badge badge-info">4. Nombre</span>
                <span class="badge badge-info">5. Nivel</span>
                <span class="badge badge-info">6. Grupo</span>
                <span class="badge badge-info">7. Monitor/a (email)</span>
            </div>
        </div>
        
        <div class="form-group">
            <div style="overflow-x: auto; border: 1px solid var(--gray-200); border-radius: var(--radius-sm);">
                <table class="table" id="bulk-import-table" style="margin: 0; font-size: 0.85rem;">
                    <thead>
                        <tr style="background: var(--gray-100);">
                            <th style="width: 10%;">Nº Usuario</th>
                            <th style="width: 14%;">Apellido 1 *</th>
                            <th style="width: 14%;">Apellido 2</th>
                            <th style="width: 14%;">Nombre *</th>
                            <th style="width: 12%;">Nivel</th>
                            <th style="width: 14%;">Grupo</th>
                            <th style="width: 18%;">Monitor (email)</th>
                            <th style="width: 4%;"></th>
                        </tr>
                    </thead>
                    <tbody id="bulk-import-body">
                        <!-- Filas dinámicas -->
                    </tbody>
                </table>
            </div>
            <div style="margin-top: 0.75rem; display: flex; gap: 0.5rem; align-items: center;">
                <button type="button" class="btn btn-sm btn-secondary" onclick="addBulkImportRow()">
                    <i class="iconoir-plus"></i> Añadir fila
                </button>
                <button type="button" class="btn btn-sm btn-secondary" onclick="clearBulkImportTable()">
                    <i class="iconoir-trash"></i> Limpiar
                </button>
                <span id="bulk-import-count" style="margin-left: auto; color: var(--gray-500); font-size: 0.85rem;">0 filas con datos</span>
            </div>
        </div>
        
        <p style="font-size: 0.85rem; color: var(--gray-500); margin-bottom: 1rem;">
            <i class="iconoir-info-circle"></i> Si el alumno ya existe (mismo nº usuario), se actualizarán sus datos y grupo. Si el grupo no existe, se creará. Si el monitor no existe, se mostrará una advertencia.
        </p>
        
        <form method="POST" id="bulk-import-form">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="bulk_import">
            <input type="hidden" name="rows" id="bulk-import-rows">
            
            <div class="modal-footer">
                <button type="button" onclick="this.closest('dialog').close()" class="btn btn-secondary">Cancelar</button>
                <button type="submit" class="btn btn-primary" onclick="return prepareBulkImport()">
                    <i class="iconoir-upload"></i> Importar Alumnos
                </button>
            </div>
        </form>
    </div>
</dialog>

<style>
#bulk-import-table input {
    width: 100%;
    padding: 0.4rem 0.5rem;
    border: 1px solid var(--gray-200);
    border-radius: var(--radius-sm);
    font-size: 0.85rem;
}
#bulk-import-table input:focus {
    outline: none;
    border-color: var(--accent);
    box-shadow: 0 0 0 2px var(--accent-light);
}
#bulk-import-table td {
    padding: 0.25rem;
    vertical-align: middle;
}
#bulk-import-table .btn-remove-row {
    background: none;
    border: none;
    color: var(--danger);
    cursor: pointer;
    padding: 0.25rem;
    font-size: 1.1rem;
    opacity: 0.6;
}
#bulk-import-table .btn-remove-row:hover {
    opacity: 1;
}
</style>

<script>
function initBulkImportTable() {
    const tbody = document.getElementById('bulk-import-body');
    if (tbody.children.length === 0) {
        for (let i = 0; i < 5; i++) {
            addBulkImportRow();
        }
    }
    tbody.addEventListener('paste', handleBulkImportPaste);
    updateBulkImportCount();
}

function addBulkImportRow() {
    const tbody = document.getElementById('bulk-import-body');
    const row = document.createElement('tr');
    row.innerHTML = `
        <td><input type="text" class="bulk-numero" placeholder="12345"></td>
        <td><input type="text" class="bulk-apellido1" placeholder="García"></td>
        <td><input type="text" class="bulk-apellido2" placeholder="López"></td>
        <td><input type="text" class="bulk-nombre" placeholder="Ana"></td>
        <td><input type="text" class="bulk-nivel" placeholder="Burbuja"></td>
        <td><input type="text" class="bulk-grupo" placeholder="L-X 17:00"></td>
        <td><input type="text" class="bulk-monitor" placeholder="monitor@email.com"></td>
        <td><button type="button" class="btn-remove-row" onclick="removeBulkImportRow(this)">&times;</button></td>
    `;
    tbody.appendChild(row);
    updateBulkImportCount();
}

function removeBulkImportRow(btn) {
    const row = btn.closest('tr');
    if (row) {
        row.remove();
        updateBulkImportCount();
    }
}

function clearBulkImportTable() {
    const tbody = document.getElementById('bulk-import-body');
    tbody.innerHTML = '';
    for (let i = 0; i < 5; i++) {
        addBulkImportRow();
    }
    updateBulkImportCount();
}

function updateBulkImportCount() {
    const tbody = document.getElementById('bulk-import-body');
    const rows = tbody.querySelectorAll('tr');
    let filledRows = 0;
    
    rows.forEach(row => {
        const apellido1 = row.querySelector('.bulk-apellido1')?.value?.trim() || '';
        const nombre = row.querySelector('.bulk-nombre')?.value?.trim() || '';
        if (apellido1 || nombre) filledRows++;
    });
    
    const countEl = document.getElementById('bulk-import-count');
    if (countEl) {
        countEl.textContent = `${filledRows} fila(s) con datos`;
    }
}

function handleBulkImportPaste(event) {
    const clipboardData = event.clipboardData || window.clipboardData;
    const pastedText = clipboardData.getData('text');
    
    if (!pastedText) return;
    
    // Detectar si viene de Excel (tiene tabs o múltiples líneas)
    if (pastedText.includes('\t') || pastedText.split('\n').length > 1) {
        event.preventDefault();
        
        const lines = pastedText.split('\n').filter(line => line.trim());
        const tbody = document.getElementById('bulk-import-body');
        
        // Limpiar tabla existente
        tbody.innerHTML = '';
        
        lines.forEach(line => {
            const cols = line.split('\t');
            
            const numero = (cols[0] || '').trim();
            const apellido1 = (cols[1] || '').trim();
            const apellido2 = (cols[2] || '').trim();
            const nombre = (cols[3] || '').trim();
            const nivel = (cols[4] || '').trim();
            const grupo = (cols[5] || '').trim();
            const monitor = (cols[6] || '').trim();
            
            const row = document.createElement('tr');
            row.innerHTML = `
                <td><input type="text" class="bulk-numero" value="${escapeHtml(numero)}"></td>
                <td><input type="text" class="bulk-apellido1" value="${escapeHtml(apellido1)}"></td>
                <td><input type="text" class="bulk-apellido2" value="${escapeHtml(apellido2)}"></td>
                <td><input type="text" class="bulk-nombre" value="${escapeHtml(nombre)}"></td>
                <td><input type="text" class="bulk-nivel" value="${escapeHtml(nivel)}"></td>
                <td><input type="text" class="bulk-grupo" value="${escapeHtml(grupo)}"></td>
                <td><input type="text" class="bulk-monitor" value="${escapeHtml(monitor)}"></td>
                <td><button type="button" class="btn-remove-row" onclick="removeBulkImportRow(this)">&times;</button></td>
            `;
            tbody.appendChild(row);
        });
        
        // Añadir algunas filas vacías al final
        for (let i = 0; i < 3; i++) {
            addBulkImportRow();
        }
        
        updateBulkImportCount();
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function prepareBulkImport() {
    const tbody = document.getElementById('bulk-import-body');
    const rows = tbody.querySelectorAll('tr');
    const data = [];
    
    rows.forEach(row => {
        const numero = row.querySelector('.bulk-numero')?.value?.trim() || '';
        const apellido1 = row.querySelector('.bulk-apellido1')?.value?.trim() || '';
        const apellido2 = row.querySelector('.bulk-apellido2')?.value?.trim() || '';
        const nombre = row.querySelector('.bulk-nombre')?.value?.trim() || '';
        const nivel = row.querySelector('.bulk-nivel')?.value?.trim() || '';
        const grupo = row.querySelector('.bulk-grupo')?.value?.trim() || '';
        const monitor = row.querySelector('.bulk-monitor')?.value?.trim() || '';
        
        // Solo incluir filas con datos mínimos
        if (apellido1 || nombre) {
            data.push({
                numero_usuario: numero,
                apellido1: apellido1,
                apellido2: apellido2,
                nombre: nombre,
                nivel: nivel,
                grupo: grupo,
                monitor: monitor
            });
        }
    });
    
    if (data.length === 0) {
        alert('No hay datos para importar. Añade al menos un alumno con nombre y apellido.');
        return false;
    }
    
    document.getElementById('bulk-import-rows').value = JSON.stringify(data);
    return true;
}

// Actualizar contador cuando se escriba en los inputs
document.addEventListener('input', function(e) {
    if (e.target.closest('#bulk-import-table')) {
        updateBulkImportCount();
    }
});
</script>

<?php include INCLUDES_PATH . '/footer.php'; ?>
