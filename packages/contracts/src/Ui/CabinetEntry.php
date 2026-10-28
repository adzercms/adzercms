<?php

declare(strict_types=1);

namespace AdzerCMS\Contracts\Ui;

enum CabinetEntry: string
{
    case Home = 'home';
    case Profile = 'profile';
    case Security = 'security';
    case Theme = 'theme';
    case Login = 'login';
    case Register = 'register';
    case Forgot = 'forgot';
    case PasswordChange = 'password_change';
    case Deletion = 'deletion';
    case ContactConfirm = 'contact_confirm';
    case ContactRelease = 'contact_release';
    case ContactRevert = 'contact_revert';
    case ContactVerify = 'contact_verify';

    public function route(): string
    {
        return match ($this) {
            self::Home => 'cabinet_account',
            self::Profile, self::Security => 'cabinet_profile',
            self::Theme => 'cabinet_profile_theme',
            self::Login => 'cabinet_login',
            self::Register => 'cabinet_register',
            self::Forgot => 'cabinet_forgot',
            self::PasswordChange => 'cabinet_password_change',
            self::Deletion => 'cabinet_delete',
            self::ContactConfirm => 'cabinet_contact_confirm',
            self::ContactRelease => 'cabinet_contact_release',
            self::ContactRevert => 'cabinet_contact_revert',
            self::ContactVerify => 'cabinet_contact_verify',
        };
    }

    /** @return array<string, string> */
    public function parameters(): array
    {
        return self::Security === $this ? ['tab' => 'security'] : [];
    }
}
