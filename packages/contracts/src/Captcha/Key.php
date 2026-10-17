<?php

declare(strict_types=1);

namespace AdzerCMS\Contracts\Captcha;

use AdzerCMS\Contracts\Setting\Field;
use AdzerCMS\Contracts\Setting\Type;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum Key: string implements TranslatableInterface
{
    case Site = 'site_key';
    case Secret = 'secret';

    public function field(): Field
    {
        return new Field($this->value, $this, self::Secret === $this ? Type::Secret : Type::Text, required: true);
    }

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return match ($this) {
            self::Site => $translator->trans('core.captcha.site_key', locale: $locale),
            self::Secret => $translator->trans('core.captcha.secret', locale: $locale),
        };
    }
}
