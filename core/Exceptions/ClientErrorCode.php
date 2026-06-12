<?php

// core/ClientErrorCode.php

declare(strict_types=1);

namespace Core\Exceptions;


enum ClientErrorCode: string
{
    case BAD_REQUEST = 'bad_request';
    case METHOD_NOT_ALLOWED = 'method_not_allowed';
    case EMPTY_FIELD = 'empty_field';
    case USER_NOT_FOUND = 'user_not_found';
    case PASSWORD_ERROR = 'password_error';
    case LOGIN_ERROR = 'login_error';
    case ADMIN_ERROR = 'admin_error';
    case IMPOSSIBLE_TO_MODIFY_ADMIN = 'modify_admin_error';
    case USER_ALREADY_EXISTS = 'user_already_exists';
    case PASSWORD_MISMATCH = 'password_mismatch';
    case PASSWORD_LENGTH = 'password_length';
    case NAME_LENGTH = 'username_length';
    case SPECIAL_CHARACTERS = 'special_characters';
    case REGISTRATION_ERROR = 'registration_error';
    case SETTINGS_ERROR = 'settings_error';
    case BAD_PLACE = 'bad_place';
    case INVALID_EMAIL = 'invalid_email';
    case INVALID_PHONE = 'invalid_phone';
    case ACCOUNT_DELETED = 'account_deleted';

    public function message() : string
    {
        return match($this) {
            self::BAD_REQUEST => 'Requête incorrecte',
            self::METHOD_NOT_ALLOWED => 'Méthode non autorisée',
            self::EMPTY_FIELD => 'Champ(s) vide(s)',
            self::USER_NOT_FOUND => 'Utilisateur introuvable',
            self::PASSWORD_ERROR => 'Mot de passe incorrect',
            self::LOGIN_ERROR => 'Vous devez être connecté',
            self::ADMIN_ERROR => 'Vous devez être administrateur',
            self::IMPOSSIBLE_TO_MODIFY_ADMIN => 'Opération interdite sur un compte administrateur.',
            self::USER_ALREADY_EXISTS => 'Cet utilisateur existe déjà',
            self::PASSWORD_MISMATCH => 'Les mots de passe ne correspondent pas',
            self::PASSWORD_LENGTH => 'Le mot de passe doit faire entre 8 et 20 caractères',
            self::NAME_LENGTH => 'Le nom et le prénom doivent contenir entre 1 et 20 caractères',
            self::SPECIAL_CHARACTERS => 'Les caractères spéciaux ne sont pas autorisés dans le nom, le prénom ou la ville',
            self::REGISTRATION_ERROR => 'Erreur lors de l\'inscription',
            self::SETTINGS_ERROR => 'Erreur lors de la sauvegarde des paramètres',
            self::BAD_PLACE => 'Vous ne pouvez accéder à cette page en étant connecté',
            self::INVALID_EMAIL => 'L\'adresse e-mail n\'est pas valide',
            self::INVALID_PHONE => 'Le numéro de téléphone n\'est pas valide (10 chiffres attendus)',
            self::ACCOUNT_DELETED => 'Ce compte a été supprimé',
        };
    }
}