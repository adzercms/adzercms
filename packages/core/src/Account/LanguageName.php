<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Account;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class LanguageName implements TranslatableInterface
{
    public function __construct(public string $locale)
    {
    }

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return mb_ucfirst(\Locale::getDisplayLanguage($this->locale, $this->locale) ?: $this->locale);
    }
}
