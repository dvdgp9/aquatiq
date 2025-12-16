<?php
$currentUser = getCurrentUser();
$pageTitle = $pageTitle ?? APP_NAME;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitize($pageTitle) ?> - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <header class="main-header">
        <div class="container">
            <a href="/dashboard.php" class="logo">
                <img src="/logo-aquatiq.png" alt="<?= APP_NAME ?>">
            </a>
            
            <?php if ($currentUser): ?>
            <nav class="main-nav">
                <ul>
                    <?php if (canAccessAdmin()): ?>
                    <li><a href="/admin/niveles.php">Niveles</a></li>
                    <li><a href="/admin/grupos.php">Grupos</a></li>
                    <li><a href="/admin/alumnos.php">Alumnos</a></li>
                    <li><a href="/admin/monitores.php">Monitores</a></li>
                    <li><a href="/admin/plantillas.php">Plantillas</a></li>
                    <?php endif; ?>
                    
                    <?php if (hasRole('monitor')): ?>
                    <li><a href="/monitor/grupos.php">Mis Grupos</a></li>
                    <li><a href="/monitor/evaluaciones.php">Evaluaciones</a></li>
                    <?php endif; ?>
                    
                    <?php if (hasRole('padre')): ?>
                    <li><a href="/padre/hijos.php">Mis Hijos</a></li>
                    <?php endif; ?>
                    
                    <?php if (hasRole('superadmin')): ?>
                    <li><a href="/superadmin/usuarios.php">Usuarios</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
            
            <div class="user-menu">
                <span class="user-name"><?= sanitize($currentUser['nombre']) ?></span>
                <span class="user-role">(<?= ROLES[$currentUser['rol']] ?>)</span>
                <a href="/logout.php" class="btn-logout">Salir</a>
            </div>
            <?php endif; ?>
        </div>
    </header>
    
    <main class="main-content">
        <div class="container">
            <?php 
            $flash = getFlashMessage();
            if ($flash): 
            ?>
            <div class="alert alert-<?= $flash['type'] ?>">
                <?= sanitize($flash['message']) ?>
            </div>
            <?php endif; ?>
