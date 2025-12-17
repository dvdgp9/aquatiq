<?php
/**
 * Funciones de autenticación
 */

function login(string $email, string $password): bool {
    $pdo = getDBConnection();
    
    if ($pdo === null) {
        error_log("Aquatiq: No se pudo conectar a la BD en login");
        return false;
    }
    
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ? AND activo = 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_nombre'] = $user['nombre'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_rol'] = $user['rol'];
        return true;
    }
    
    return false;
}

function logout(): void {
    session_unset();
    session_destroy();
}

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

function getCurrentUser(): ?array {
    if (!isLoggedIn()) {
        return null;
    }
    
    return [
        'id' => $_SESSION['user_id'],
        'nombre' => $_SESSION['user_nombre'],
        'email' => $_SESSION['user_email'],
        'rol' => $_SESSION['user_rol']
    ];
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        setFlashMessage('error', 'Debes iniciar sesión para acceder.');
        redirect('/login.php');
    }
}

function requireRole(array $roles): void {
    requireLogin();
    
    $currentRole = $_SESSION['user_rol'] ?? '';
    if (!in_array($currentRole, $roles)) {
        setFlashMessage('error', 'No tienes permisos para acceder a esta sección.');
        redirect('/dashboard.php');
    }
}

function hasRole(string $role): bool {
    return isset($_SESSION['user_rol']) && $_SESSION['user_rol'] === $role;
}

function canAccessAdmin(): bool {
    return hasRole('superadmin') || hasRole('admin');
}
