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
    case Forgot = 'forgot';
    case PasswordChange = 'password_change';
    case Deletion = 'deletion';
    case ContactConfirm = 'contact_confirm';
    case ContactRelease = 'contact_release';

    public function route(): string
    {
        return match ($this) {
            self::Home => 'cabinet_account',
            self::Profile, self::Security => 'cabinet_profile',
            self::Theme => 'cabinet_profile_theme',
            self::Login => 'cabinet_login',
            self::Forgot => 'cabinet_forgot',
            self::PasswordChange => 'cabinet_password_change',
            self::Deletion => 'cabinet_delete',
            self::ContactConfirm => 'cabinet_contact_confirm',
            self::ContactRelease => 'cabinet_contact_release',
        };
    }

    /** @return array<string, string> */
    public function parameters(): array
    {
        return self::Security === $this ? ['tab' => 'security'] : [];
    }
}
