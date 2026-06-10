<?php

// controllers/User/LogoutController.php

declare(strict_types=1);

namespace Controllers\User;

use Core\Controller;
use Core\Exceptions\ClientError;
use Core\Exceptions\ClientErrorCode;
use Core\Privilege;
use Core\RequirePrivilege;
use JetBrains\PhpStorm\NoReturn;

#[RequirePrivilege(Privilege::USER)]
class LogoutController extends Controller
{
  /**
   * @throws ClientError
   */
  public function get(): void
  {
    throw new ClientError(ClientErrorCode::METHOD_NOT_ALLOWED);
  }

  #[NoReturn]
  public function post(): void
  {
    verifyCSRFToken();

    // Additional security
    if (ini_get("session.use_cookies")) {
      $params = session_get_cookie_params();
      setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"],
      );
    }

    session_unset(); // Delete all session variables
    session_destroy(); // Delete session
    redirect('/login');
  }
}
