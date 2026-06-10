<?php

// index.php

declare(strict_types=1);

require_once 'vendor/autoload.php';
require_once 'core/Helpers.php';

use Core\Config;
use Core\Exceptions\ServerError;
use Core\Language;
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

Language::load($_SESSION['language'] ?? Config::get('site')["default_language"] ?? 'en');

$router = new Router();
$router->run();