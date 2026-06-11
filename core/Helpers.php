<?php

// core/Helpers.php

declare(strict_types=1);

use Core\Exceptions\ClientError;
use Core\Exceptions\ClientErrorCode;
use JetBrains\PhpStorm\NoReturn;
use Random\RandomException;

#[NoReturn]
function redirect(string $dest): void
{
    header("Location: " . $dest);
    exit();
}

function isAuthenticated(): bool
{
    return isset($_SESSION["userId"]);
}

function requireAuth(): void
{
    if (!isAuthenticated()) {
        $_SESSION["flash_error"] = "login_error";
        redirect("index.php?route=login");
    }
}

function requireNotAuth(): void
{
    if (isAuthenticated()) {
        redirect("index.php?route=user/dashboard");
    }
}

function requireAdmin(): void
{
    requireAuth();
    if (!isset($_SESSION["role"]) || $_SESSION["role"] < 2) {
        $_SESSION["flash_error"] = "admin_error";
        redirect("index.php?route=user/dashboard");
    }
}

function requireRoot(): void
{
    requireAdmin();
    if ($_SESSION["role"] < 3) {
        $_SESSION["flash_error"] = "root_error";
        redirect("index.php?route=user/dashboard");
    }
}

function isPost(): bool
{
    return $_SERVER["REQUEST_METHOD"] === "POST";
}

function isGet(): bool
{
    return $_SERVER["REQUEST_METHOD"] === "GET";
}