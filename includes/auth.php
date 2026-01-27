<?php
/**
 * Funciones de autenticación
 */

function login(string $email, string $password): bool {
    $pdo = getDBConnection();
    
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

function loginPadre(string $nombre_hijo, string $codigo): bool {
    $pdo = getDBConnection();
    
    // Normalizar nombre del input (quitar acentos, mayúsculas, espacios extra)
    $nombre_normalizado = normalizeString($nombre_hijo);
    
    // Buscar alumno por codigo (numero_usuario) primero, luego comparar nombre normalizado
    $stmt = $pdo->prepare("
        SELECT a.*, u.id as usuario_id, u.nombre as padre_nombre, u.email as padre_email, u.rol
        FROM alumnos a
        INNER JOIN usuarios u ON a.padre_id = u.id
        WHERE a.numero_usuario = ? 
          AND a.activo = 1 
          AND u.activo = 1
          AND u.rol = 'padre'
    ");
    $stmt->execute([$codigo]);
    $results = $stmt->fetchAll();
    
    // Comparar nombre normalizado con cada resultado
    foreach ($results as $result) {
        if (normalizeString($result['nombre']) === $nombre_normalizado) {
            $_SESSION['user_id'] = $result['usuario_id'];
            $_SESSION['user_nombre'] = $result['padre_nombre'];
            $_SESSION['user_email'] = $result['padre_email'];
            $_SESSION['user_rol'] = $result['rol'];
            return true;
        }
    }
    
    return false;
}

function normalizeString(string $str): string {
    // Quitar espacios extra y convertir a minúsculas
    $str = trim(strtolower($str));
    
    // Reemplazar acentos
    $acentos = ['á', 'é', 'í', 'ó', 'ú', 'ü', 'ñ', 'à', 'è', 'ì', 'ò', 'ù'];
    $sinAcentos = ['a', 'e', 'i', 'o', 'u', 'u', 'n', 'a', 'e', 'i', 'o', 'u'];
    $str = str_replace($acentos, $sinAcentos, $str);
    
    // También normalizar mayúsculas con acentos
    $acentosMay = ['Á', 'É', 'Í', 'Ó', 'Ú', 'Ü', 'Ñ', 'À', 'È', 'Ì', 'Ò', 'Ù'];
    $str = str_replace($acentosMay, $sinAcentos, $str);
    
    return $str;
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
