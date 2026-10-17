<?php

declare(strict_types=1);

namespace AdzerCMS\Contracts\Ui;

enum AdminEntry: string
{
    case User = 'user';

    public function route(): string
    {
        return match ($this) {
            self::User => 'admin_user',
        };
    }
}
