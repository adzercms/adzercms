<?php

declare(strict_types=1);

namespace AdzerCMS\Contracts\Ui;

enum AdminEntry: string
{
    case Dashboard = 'dashboard';
    case User = 'user';

    public function route(): string
    {
        return match ($this) {
            self::Dashboard => 'admin_dashboard',
            self::User => 'admin_user',
        };
    }
}
