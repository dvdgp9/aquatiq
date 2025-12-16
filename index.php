<?php
/**
 * Aquatiq - Página principal
 * Redirige al dashboard o al login según estado de sesión
 */

require_once __DIR__ . '/config/config.php';

if (isLoggedIn()) {
    redirect('/dashboard.php');
} else {
    redirect('/login.php');
}
