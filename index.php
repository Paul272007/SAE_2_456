<?php

// index.php

declare(strict_types=1);

require_once 'vendor/autoload.php';
require_once 'core/Helpers.php';

use Core\Config;
use Core\Exceptions\ServerError;

use Core\Router;

// Load config
try {
    Config::load('config.json');
} catch (ServerError $e) {
    die($e->getMessage());
}

// Show errors if debug mode is on
$siteConfig = Config::get('site');
$debugMode = $siteConfig['debug'] ?? false;
if ($debugMode) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(0);
}

// Set security settings
session_set_cookie_params([
    'lifetime' => 0,                    // The lifetime of the session cookie (here: until browser is closed)
    'path' => '/',                      // The path on the server in which the cookie is accessible (here: all folders and subfolders)
    'domain' => '',                     // The domain for which the cookie is valid (here: current domain)
    'secure' => $siteConfig['https'],   // Cookie is sent only in https (not here since we're using http)
    'httponly' => true,                 // Cookie is not accessible for client-side scripts (JS), so isn't stolen
    'samesite' => 'Strict'              // Strict => cookie is sent only if the request comes from this website
]);

// Start session
session_start();

// Cookie d'identification pour les clients non inscrits (F3/F4)
// Permet de conserver les données de réservation entre les pages sans compte
if (!isset($_COOKIE['guest_token'])) {
    $guestToken = bin2hex(random_bytes(16));
    setcookie('guest_token', $guestToken, [
        'expires'  => time() + 30 * 24 * 3600, // 30 jours
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Strict',
        'secure'   => $siteConfig['https'] ?? false,
    ]);
    $_COOKIE['guest_token'] = $guestToken;
}

$router = new Router();
$router->run();