<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Ui;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class Sum implements TranslatableInterface
{
    private const PATTERN = '{0, number, ::currency/%s unit-width-narrow rounding-mode-half-up %s}';
    private const WHOLE = 'precision-integer';
    private const KOPECKS = 'precision-currency-standard/w';

    public function __construct(
        private string|float|int $value,
        private bool $kopecks = false,
    ) {
    }

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        $pattern = \sprintf(self::PATTERN, Money::CURRENCY, $this->kopecks ? self::KOPECKS : self::WHOLE);

        return \MessageFormatter::formatMessage($locale ?? $translator->getLocale(), $pattern, [(float) $this->value])
            ?: throw new \LogicException(\sprintf('ICU refused the sum pattern %s', $pattern));
    }
}
