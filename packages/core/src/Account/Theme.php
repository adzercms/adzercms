<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Account;

use AdzerCMS\Contracts\Ui\Titled;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Contracts\Translation\TranslatableInterface;

use function Symfony\Component\Translation\t;

enum Theme: string implements Titled
{
    case Light = 'light';
    case Dark = 'dark';

    public const COOKIE = 'theme';
    private const KEPT = '+1 year';

    public function cookie(): Cookie
    {
        return Cookie::create(self::COOKIE, $this->value, self::KEPT);
    }

    public function label(): TranslatableInterface
    {
        return t('core.theme.'.$this->value);
    }

    public function other(): self
    {
        return self::Dark === $this ? self::Light : self::Dark;
    }
}
