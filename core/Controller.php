<?php

// core/Controller.php

declare(strict_types=1);

namespace Core;

use Core\Exceptions\ClientError;
use Core\Exceptions\ClientErrorCode;
use Core\Exceptions\ServerError;
use Core\Exceptions\ServerErrorCode;
use Random\RandomException;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

abstract class Controller
{
  protected array $data = [];
  protected ?string $view;
  protected ?Model $model;
  private string $folder;
  private string $controllerName;
  private ?Environment $twig;
  protected static array $postFields = [];

  /**
   * @throws ServerError
   */
  public function __construct()
  {
        $config = Config::get("site");

    // Derive model, view, stylesheet and script from namespace and class name
    $fullClassName = get_class($this);
    $parts = explode("\\", $fullClassName);

    // Standardize: remove root namespace if it's 'Controllers'
    if ($parts[0] === 'Controllers') {
      array_shift($parts);
    }

    $className = array_pop($parts);
    $this->controllerName = str_replace("Controller", "", $className);
    $this->folder = implode("/", $parts);

    $assetPath = ($this->folder ? $this->folder . "/" : "") . $this->controllerName;

    // Model (automatic loading if class exists)
    $namespacePath = $this->folder ? str_replace("/", "\\", $this->folder) . "\\" : "";
    $modelClassName = "Models\\" . $namespacePath . $this->controllerName . "Model";
    if (class_exists($modelClassName)) {
      $this->model = new $modelClassName();
    } else {
      $this->model = null;
    }

    // only if method is GET (this means render() will crash if method is not get)
    if (isGet()) {
      // Twig setup
      $loader = new FilesystemLoader(dirname(__DIR__) . "/views");

      $this->twig = new Environment($loader, [
        "cache" => false,
        "debug" => $config["debug"],
      ]);


      // View
      $this->view = "$assetPath.twig";
      if (!file_exists("views/$this->view")) {
        throw new ServerError(
          ServerErrorCode::VIEW_NOT_FOUND,
          $this->view,
        );
      }

      // Data
      $this->data = [
        "site" => Config::get("site")["title"],
        "title" => match($this->controllerName) {
          'Login'       => 'Connexion',
          'Register'    => 'Inscription',
          'Dashboard'   => 'Tableau de bord',
          'Home'        => 'Accueil',
          'About'       => 'À propos',
          'Lines'       => 'Lignes de transport',
          'Schedule'    => 'Horaires',
          'Reservation' => 'Réservation',
          'Cart'        => 'Mon Panier',
          'Confirm'     => 'Confirmation',
          'ProfileEdit' => 'Modifier mon profil',
          'Search'      => 'Recherche d\'itinéraire',
          'Users'       => 'Gestion des clients',
          'Useredit'    => 'Édition client',
          'Linesedit'    => 'Édition ligne',
          default       => $this->controllerName
        },
        "connected" => isAuthenticated(),
        "isAdmin" => isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === 1,
        "controllerName" => $this->controllerName,
        "controllerFolder" => $this->folder,
      ];

      // CSS and JavaScript files if they exist
      $cssFile = "styles/$assetPath.css";
      $jsFile = "scripts/$assetPath.js";

      if (file_exists($cssFile)) {
        $this->data["stylesheet"] = $cssFile;
      }
      if (file_exists($jsFile)) {
        $this->data["javascript"] = $jsFile;
      }

      // ... (rest of the constructor remains the same)

      // Get potential error messages
      if (isset($_SESSION["flash_error"])) {
        $this->data["error"] =
          ClientErrorCode::tryFrom(
            $_SESSION["flash_error"],
          )?->message() ?? "Erreur inconnue";
        unset($_SESSION["flash_error"]);
      }

      // Get potential other messages
      $categories = ["success", "info", "warning"];
      foreach ($categories as $category) {
        if (isset($_SESSION["flash_" . $category])) {
          $this->data[$category] = $_SESSION["flash_" . $category];
          unset($_SESSION["flash_" . $category]);
        }
      }
    }
  }

  /**
   * @throws ClientError
   */
  protected function checkPostFields(): void
  {
    foreach (static::$postFields as $field) {
      if (empty($_POST[$field])) {
        throw new ClientError(ClientErrorCode::EMPTY_FIELD);
      }
    }
  }

  /**
   * @throws SyntaxError
   * @throws RuntimeError
   * @throws LoaderError
   */
  protected function render(): void
  {
    echo $this->twig->render($this->view, $this->data);
  }

  /**
   * @throws RuntimeError
   * @throws SyntaxError
   * @throws LoaderError
   */
  public function get(): void
  {
    // Just render page by default
    $this->render();
  }

  /**
   * @throws ClientError
   */
  public function post(): void
  {
    // If not implemented in subclass, throw error
    throw new ClientError(ClientErrorCode::METHOD_NOT_ALLOWED);
  }
}
