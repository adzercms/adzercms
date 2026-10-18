<?php

declare(strict_types=1);

namespace AdzerCMS\Cabinet\Tests\Fixture;

use AdzerCMS\Contracts\Ui\Fragment;

use function Symfony\Component\Translation\t;

final class Pager implements Fragment
{
    public const HREF = '/pager/';

    public function place(): string
    {
        return self::CABINET_PROFILE;
    }

    public function fragment(?int $subject): array
    {
        return ['template' => '@Theme/_actions.html.twig', 'context' => ['actions' => [['href' => self::HREF.$subject, 'label' => t('Pager')]]]];
    }
}
