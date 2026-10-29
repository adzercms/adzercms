<?php

declare(strict_types=1);

namespace AdzerCMS\Contracts\Ui;

enum AdminEntry: string
{
    case Dashboard = 'dashboard';
    case Users = 'users';
    case User = 'user';
    case Modules = 'modules';

    public function route(): string
    {
        return match ($this) {
            self::Dashboard => 'admin_dashboard',
            self::Users => 'admin_users',
            self::User => 'admin_user',
            self::Modules => 'admin_modules',
        };
    }
}
