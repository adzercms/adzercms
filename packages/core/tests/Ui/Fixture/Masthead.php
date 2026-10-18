<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Ui\Fixture;

use AdzerCMS\Contracts\Ui\Fragment;

final class Masthead implements Fragment
{
    public function place(): string
    {
        return self::TOP;
    }

    public function fragment(?int $subject): array
    {
        return ['template' => '@Theme/_brand.html.twig', 'context' => []];
    }
}
