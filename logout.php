<?php
/**
 * Aquatiq - Logout
 */

require_once __DIR__ . '/config/config.php';

logout();
setFlashMessage('success', 'Has cerrado sesión correctamente.');
redirect('/login.php');
