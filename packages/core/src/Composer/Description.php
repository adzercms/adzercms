<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Composer;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class Description implements TranslatableInterface
{
    /** @param array<string, string> $translations */
    public function __construct(public string $source, public array $translations)
    {
    }

    /** @param array<mixed> $manifest */
    public static function of(array $manifest): self
    {
        $translations = [];
        foreach (\is_array($manifest['extra']['adzercms']['description'] ?? null) ? $manifest['extra']['adzercms']['description'] : [] as $language => $text) {
            $text = Package::text($text);
            if (\is_string($language) && null !== $text) {
                $translations[$language] = $text;
            }
        }

        return new self(Package::text($manifest['description'] ?? null) ?? '', $translations);
    }

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $this->translations[$locale ?? $translator->getLocale()] ?? $this->source;
    }
}
