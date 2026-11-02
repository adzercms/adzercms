<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Composer;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class Localized implements TranslatableInterface
{
    public const SOURCE = 'en';

    /** @param array<string, string> $translations */
    public function __construct(public string $source, public array $translations)
    {
    }

    /** @param array<mixed> $manifest */
    public static function description(array $manifest): self
    {
        return new self(Package::text($manifest['description'] ?? null) ?? '', self::texts($manifest['extra']['adzercms']['description'] ?? null));
    }

    /** @param array<mixed> $manifest */
    public static function title(array $manifest): self
    {
        $texts = self::texts($manifest['extra']['adzercms']['title'] ?? null);

        return new self($texts[self::SOURCE] ?? '', array_diff_key($texts, [self::SOURCE => true]));
    }

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $this->translations[$locale ?? $translator->getLocale()] ?? $this->source;
    }

    /** @return array<string, string> */
    private static function texts(mixed $languages): array
    {
        $texts = [];
        foreach (\is_array($languages) ? $languages : [] as $language => $text) {
            $text = Package::text($text);
            if (\is_string($language) && null !== $text) {
                $texts[$language] = $text;
            }
        }

        return $texts;
    }
}
