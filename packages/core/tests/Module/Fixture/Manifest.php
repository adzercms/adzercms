<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Module\Fixture;

use AdzerCMS\Contracts\Module\CoreKind;
use AdzerCMS\Contracts\Module\Module;
use AdzerCMS\Core\Module\Registry;

final class Manifest
{
    /**
     * @param class-string<Module> $bundle
     *
     * @return array<string, mixed>
     */
    public static function of(string $bundle, string ...$requires): array
    {
        return [
            'require' => array_fill_keys(['php', ...$requires], '*'),
            'provide' => CoreKind::Feature === $bundle::kind() ? [] : [Registry::implementation($bundle::kind()) => '*'],
            'extra' => ['adzercms' => ['bundle' => $bundle]],
        ];
    }
}
