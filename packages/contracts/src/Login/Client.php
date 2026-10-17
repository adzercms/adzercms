<?php

declare(strict_types=1);

namespace AdzerCMS\Contracts\Login;

use AdzerCMS\Contracts\Module\Credentials;
use AdzerCMS\Contracts\Setting\Field;
use AdzerCMS\Contracts\Setting\Type;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum Client: string implements TranslatableInterface
{
    case Id = 'client_id';
    case Secret = 'client_secret';

    public function field(): Field
    {
        return new Field($this->value, $this, self::Secret === $this ? Type::Secret : Type::Text, required: true);
    }

    /** @return array{Authorization: string} */
    public static function basic(Credentials $credentials): array
    {
        return ['Authorization' => 'Basic '.base64_encode($credentials->get(self::Id->value).':'.$credentials->get(self::Secret->value))];
    }

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return match ($this) {
            self::Id => $translator->trans('core.login.client_id', locale: $locale),
            self::Secret => $translator->trans('core.login.client_secret', locale: $locale),
        };
    }
}
