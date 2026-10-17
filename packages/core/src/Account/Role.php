<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Account;

use AdzerCMS\Contracts\Ui\Titled;
use Symfony\Contracts\Translation\TranslatableInterface;

use function Symfony\Component\Translation\t;

enum Role: string implements Titled
{
    case User = 'user';
    case Admin = 'admin';

    public const USER = 'ROLE_USER';
    public const ADMIN = 'ROLE_ADMIN';

    public function security(): string
    {
        return match ($this) {
            self::User => self::USER,
            self::Admin => self::ADMIN,
        };
    }

    public function label(): TranslatableInterface
    {
        return t('core.role.'.$this->value);
    }
}
