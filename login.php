<?php
/**
 * Aquatiq - Login
 */

require_once __DIR__ . '/config/config.php';

if (isLoggedIn()) {
    redirect('/dashboard.php');
}

$error = '';

if (isPost()) {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Por favor, completa todos los campos.';
    } elseif (login($email, $password)) {
        redirect('/dashboard.php');
    } else {
        $error = 'Email o contraseña incorrectos.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <a href="/" class="logo">
                <img src="/logo-aquatiq.png" alt="<?= APP_NAME ?>">
            </a>
            
            <h1>Iniciar Sesión</h1>
            <p class="login-subtitle">Sistema de evaluación de natación</p>
            
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
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" class="form-control" 
                           value="<?= sanitize($_POST['email'] ?? '') ?>" required autofocus>
                </div>
                
                <div class="form-group">
                    <label for="password">Contraseña</label>
                    <input type="password" id="password" name="password" class="form-control" required>
                </div>
                
                <button type="submit" class="btn btn-primary" style="width: 100%;">Entrar</button>
            </form>
        </div>
    </div>
</body>
</html>
