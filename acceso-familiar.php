<?php
/**
 * Aquatiq - Acceso Familiar (Padres/Madres/Tutores)
 * Login mediante nombre del hijo/a + código de usuario
 */

require_once __DIR__ . '/config/config.php';

if (isLoggedIn()) {
    if (isFamiliar()) {
        redirect('/familiar/evaluaciones.php');
    } else {
        redirect('/dashboard.php');
    }
}

$error = '';

if (isPost()) {
    $nombre_hijo = trim($_POST['nombre_hijo'] ?? '');
    $codigo = trim($_POST['codigo'] ?? '');
    
    if (empty($nombre_hijo) || empty($codigo)) {
        $error = 'Por favor, introduce el nombre y el código.';
    } elseif (loginFamiliar($nombre_hijo, $codigo)) {
        redirect('/familiar/evaluaciones.php');
    } else {
        $error = 'Nombre o código incorrectos. Verifica los datos e inténtalo de nuevo.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso Familiar - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/iconoir-icons/iconoir@main/css/iconoir.css">
    <link rel="icon" type="image/png" href="/favicon.png">
    <link rel="stylesheet" href="/assets/css/style.css">
    
    <!-- PWA Meta Tags -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#0077be">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Aquatiq">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    
    <!-- PWA Service Worker -->
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/sw.js')
                    .then(reg => console.log('Aquatiq SW registrado'))
                    .catch(err => console.log('SW error:', err));
            });
        }
    </script>
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <a href="/" class="logo">
                <img src="/logo-aquatiq.png" alt="<?= APP_NAME ?>">
            </a>
            
            <h1>Acceso Familiar</h1>
            <p class="login-subtitle">Consulta las evaluaciones de tu hija/o</p>
            
            <?php if ($error): ?>
            <div class="alert alert-error"><?= sanitize($error) ?></div>
            <?php endif; ?>
            
            <?php 
            $flash = getFlashMessage();
            if ($flash): 
            ?>
            <div class="alert alert-<?= $flash['type'] ?>">
                <?= sanitize($flash['message']) ?>
            </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <?= csrfField() ?>
                
                <div class="form-group">
                    <label for="nombre_hijo">Nombre de tu hija/o</label>
                    <input type="text" id="nombre_hijo" name="nombre_hijo" class="form-control" 
                           placeholder="Ej: Sofía, Nico, Adrián..."
                           value="<?= sanitize($_POST['nombre_hijo'] ?? '') ?>" required autofocus>
                    <small style="color: var(--gray-500);">Solo el nombre, sin apellidos</small>
                </div>
                
                <div class="form-group">
                    <label for="codigo">Código de acceso</label>
                    <input type="text" id="codigo" name="codigo" class="form-control" 
                           placeholder="Ej: 6515"
                           inputmode="numeric" pattern="[0-9]*" required>
                    <small style="color: var(--gray-500);">El código de 4 dígitos que te proporcionó el club</small>
                </div>
                
                <button type="submit" class="btn btn-primary" style="width: 100%;">
                    <i class="iconoir-community"></i> Acceder
                </button>
            </form>
            
            <div style="margin-top: 1.5rem; text-align: center; font-size: 0.85rem; color: var(--gray-500);">
                <p>¿Eres monitor o administrador?</p>
                <a href="/login.php" style="color: var(--primary);">Accede aquí con tu email</a>
            </div>
        </div>
    </div>
</body>
</html>
