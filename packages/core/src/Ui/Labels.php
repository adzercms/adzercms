<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Ui;

use AdzerCMS\Contracts\Ui\Titled;
use Symfony\Contracts\Translation\TranslatableInterface;

final class Labels
{
    /**
     * @param list<Titled&\BackedEnum> $cases
     *
     * @return array<string, TranslatableInterface>
     */
    public static function of(array $cases): array
    {
        $labels = [];

        foreach ($cases as $case) {
            $labels[(string) $case->value] = $case->label();
        }

        return $labels;
    }
}
