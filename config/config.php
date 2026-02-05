<?php
/**
 * Configuración general de la aplicación
 */

session_start();

define('APP_NAME', 'Aquatiq');
define('APP_URL', 'https://aquatiq.ebone.es');
define('APP_VERSION', '1.0.0');

define('BASE_PATH', dirname(__DIR__));
define('CONFIG_PATH', BASE_PATH . '/config');
define('INCLUDES_PATH', BASE_PATH . '/includes');
define('ASSETS_PATH', BASE_PATH . '/assets');

require_once CONFIG_PATH . '/database.php';
require_once INCLUDES_PATH . '/functions.php';
require_once INCLUDES_PATH . '/auth.php';

date_default_timezone_set('Europe/Madrid');

const ROLES = [
    'superadmin' => 'Superadmin',
    'admin' => 'Administrador',
    'monitor' => 'Monitor',
    'coordinador' => 'Coordinador',
    'padre' => 'Padre/Tutor'
];

const VALORES_EVALUACION = [
    'si' => 'Sí',
    'no' => 'No',
    'a_veces' => 'A veces / Casi'
];
