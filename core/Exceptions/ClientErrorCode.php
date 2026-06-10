<?php

// core/ClientErrorCode.php

declare(strict_types=1);

namespace Core\Exceptions;

use Core\Language;

enum ClientErrorCode: string
{
    case BAD_REQUEST = 'bad_request';
    case METHOD_NOT_ALLOWED = 'method_not_allowed';
    case EMPTY_FIELD = 'empty_field';
    case USER_NOT_FOUND = 'user_not_found';
    case PASSWORD_ERROR = 'password_error';
    case CSRF_ERROR = 'csrf_error';
    case LOGIN_ERROR = 'login_error';
    case ADMIN_ERROR = 'admin_error';
    case ROOT_ERROR = 'root_error';
    case USER_ALREADY_EXISTS = 'user_already_exists';
    case PASSWORD_MISMATCH = 'password_mismatch';
    case PASSWORD_LENGTH = 'password_length';
    case USERNAME_LENGTH = 'username_length';
    case SPECIAL_CHARACTERS = 'special_characters';
    case REGISTRATION_ERROR = 'registration_error';
    case SETTINGS_ERROR = 'settings_error';

    public function message() : string
    {
        return Language::get($this->value);
    }
}