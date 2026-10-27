<?php

declare(strict_types=1);

namespace AdzerCMS\Theme;

use AdzerCMS\Contracts\Ui\Palette;
use AdzerCMS\Contracts\Ui\Scheme;

use function Symfony\Component\Translation\t;

final class ThemePalette implements Palette
{
    public function schemes(): array
    {
        return [
            new Scheme('blue', t('theme.scheme.blue'), '#0066cc'),
            new Scheme('violet', t('theme.scheme.violet'), '#7c3aed'),
            new Scheme('teal', t('theme.scheme.teal'), '#0f766e'),
            new Scheme('green', t('theme.scheme.green'), '#16a34a'),
            new Scheme('orange', t('theme.scheme.orange'), '#ea580c'),
            new Scheme('red', t('theme.scheme.red'), '#dc2626'),
            new Scheme('pink', t('theme.scheme.pink'), '#db2777'),
            new Scheme('graphite', t('theme.scheme.graphite'), '#475569'),
        ];
    }
}
