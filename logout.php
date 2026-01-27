<?php
/**
 * Aquatiq - Logout
 */

require_once __DIR__ . '/config/config.php';

$isFamiliar = isFamiliar();
logout();
setFlashMessage('success', 'Has cerrado sesión correctamente.');

if ($isFamiliar) {
    redirect('/acceso-familiar.php');
} else {
    redirect('/login.php');
}
