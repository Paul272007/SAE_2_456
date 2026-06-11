<?php

// core/Router.php

declare(strict_types=1);

namespace Core;

use Controllers\ErrorController;
use Core\Exceptions\ClientError;
use Core\Exceptions\ClientErrorCode;
use Core\Exceptions\ServerError;
use Core\Exceptions\ServerErrorCode;
use Exception;
use ReflectionClass;
use ReflectionException;

class Router
{
  /**
   * @throws ReflectionException
   */
  private function checkPrivileges(string $controllerClass): void
  {
    $reflection = new ReflectionClass($controllerClass);
    $attributes = $reflection->getAttributes(RequirePrivilege::class);

    if (!empty($attributes)) {
      $attribute = $attributes[0]->newInstance();
      $required = $attribute->privilege;

      switch ($required) {
        case Privilege::GUEST:
          break;
        case Privilege::USER:
          requireAuth();
          break;
        case Privilege::ADMIN:
          requireAdmin();
          break;
        case Privilege::ROOT:
          requireRoot();
          break;
      }
    }
  }

  public function run(): void
  {
    try {
      // 1. Get requested page from GET parameter
      $uri = $_GET['route'] ?? '';
      $uri = trim($uri, '/');

      // Array containing all URL elements
      $segments = $uri === '' ? [] : explode('/', $uri);

      // 2. Call controller and execute requested method
      // Determine controller namespace based on segments
      if (empty($segments)) {
        $controllerNamespace = "Controllers\\HomeController";
      } else {
        // /a/b/c -> Controllers\A\B\CController
        $className = ucfirst(array_pop($segments)) . "Controller";
        $path = implode("\\", array_map('ucfirst', $segments));
        $controllerNamespace = "Controllers\\" . ($path ? $path . "\\" : "") . $className;
      }

      if (!class_exists($controllerNamespace)) {
        $debug_mode = Config::get('site')['debug'];
        if ($debug_mode)
          throw new ServerError(ServerErrorCode::CONTROLLER_NOT_FOUND, $controllerNamespace);
        else
          throw new ServerError(ServerErrorCode::ERROR_404, $uri);
      }

      // 3. Check permissions via Attribute
      $this->checkPrivileges($controllerNamespace);

      // 4. Add security headers
      // User cannot return to previous pages in history after logout
      header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
      header("Pragma: no-cache"); // HTTP 1.0.
      header("Expires: 0"); // Proxies.

      $controller = new $controllerNamespace();

      // The name of the method to execute is the request method in lowercase
      $method = strtolower($_SERVER['REQUEST_METHOD']);

      if (is_callable([$controller, $method])) {
        $controller->$method();
      } else {
        throw new ClientError(ClientErrorCode::METHOD_NOT_ALLOWED);
      }
    } catch (ClientError $e1) { // Client errors redirect to previous page with error message
      if (isPost()) {
        // If method is POST then just redirect to the GET version
        $baseUrl = strtok($_SERVER['REQUEST_URI'], '?');
      } else {
        // Redirect to previous page (or homepage if there is no previous page)
        // !! Some browsers block HTTP_REFERER, so it will redirect the user to the homepage (bad)
        $referer = $_SERVER['HTTP_REFERER'] ?? '/SAE_2_456/';

        // If previous page was not on my website redirect to homepage
        $refererHost = parse_url($referer, PHP_URL_HOST);
        $myHost = $_SERVER['HTTP_HOST'];

        if ($refererHost !== null && $refererHost !== $myHost) {
          $referer = '/';
        }

        // Delete all parameters from previous request
        $urlParts = explode('?', $referer);
        $baseUrl = $urlParts[0];
      }

      $_SESSION['flash_error'] = $e1->getErrorCode()->value;
      redirect($baseUrl);
    } catch (ServerError $e2) { // Server errors redirect to an error message simply showing the error
      $errorController = new ErrorController();
      try {
        $errorController->get($e2->getMessage());
      } catch (Exception $e4) {
        die($e4->getMessage());
      }
    } catch (Exception $e3) { // Only for unexpected errors
      die("<span style='color:red;'>Unexpected error: </span>" . $e3->getMessage());
    }
  }
}
