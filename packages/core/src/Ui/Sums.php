<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Ui;

use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Attribute\AsTwigFilter;

final readonly class Sums
{
    public function __construct(private TranslatorInterface $translator)
    {
    }

    #[AsTwigFilter('money')]
    public function rubles(string|float|int $value): string
    {
        return Money::rubles($value)->trans($this->translator);
    }

    #[AsTwigFilter('precise')]
    public function precise(string|float|int $value): string
    {
        return Money::precise($value)->trans($this->translator);
    }
}
