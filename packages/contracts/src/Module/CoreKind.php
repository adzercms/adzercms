<?php

declare(strict_types=1);

namespace AdzerCMS\Contracts\Module;

use Symfony\Contracts\Translation\TranslatableInterface;

use function Symfony\Component\Translation\t;

enum CoreKind: string implements Kind
{
    case Feature = 'MODULES';
    case Admin = 'ADMIN_MODULE';
    case Cabinet = 'CABINET_MODULE';
    case Theme = 'THEME_MODULE';
    case Login = 'LOGIN_MODULES';
    case Sms = 'SMS_MODULES';
    case Bot = 'BOT_MODULES';
    case Captcha = 'CAPTCHA_MODULES';

    public function key(): string
    {
        return strtolower($this->name);
    }

    public function label(): TranslatableInterface
    {
        return t('core.kind.'.$this->key());
    }

    public function environment(): string
    {
        return $this->value;
    }

    public function single(): bool
    {
        return match ($this) {
            self::Admin, self::Cabinet, self::Theme => true,
            default => false,
        };
    }
}
