<?php

// controllers/User/SettingsController.php

declare(strict_types=1);

namespace Controllers\User;

use Core\Controller;
use Core\Exceptions\ClientError;
use Core\Exceptions\ClientErrorCode;
use Core\Privilege;
use Core\RequirePrivilege;
use Exception;
use Models\User\SettingsModel;

#[RequirePrivilege(Privilege::USER)]
class SettingsController extends Controller
{
    protected static array $postFields = ["language"];
    private static array $valid_languages = ["en", "fr", "de"];

    public function get(): void
    {
        $this->data["csrf_token"] = $_SESSION["csrf_token"];
        $this->data["language"] = $_SESSION["language"];
        $this->data["language_options"] = self::$valid_languages;
        $this->render();
    }

    /**
     * @throws ClientError
     * @throws Exception
     */
    public function post(): void
    {
        verifyCSRFToken();
        $this->checkPostFields();

        $this->model = new SettingsModel();

        $language = $_POST["language"];

        if (!in_array($language, self::$valid_languages))
            throw new ClientError(ClientErrorCode::BAD_REQUEST);

        $this->model->changeSettings([$language, $_SESSION['userId']]);
        $_SESSION['language'] = $language;
        $_SESSION['flash_success'] = 'settings_changed';
        redirect('/user/settings');
    }
}
