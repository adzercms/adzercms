<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Schema;

use Doctrine\Migrations\Version\Comparator;
use Doctrine\Migrations\Version\Version;

final class MigrationOrder implements Comparator
{
    private const PREFIX = 'Version';

    public function compare(Version $a, Version $b): int
    {
        return self::moment($a) <=> self::moment($b);
    }

    public static function moment(Version $version): string
    {
        $name = (string) $version;
        $position = strrpos($name, self::PREFIX);

        return false === $position ? $name : substr($name, $position + \strlen(self::PREFIX));
    }
}
