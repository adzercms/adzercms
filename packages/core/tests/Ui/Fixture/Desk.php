<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Ui\Fixture;

use AdzerCMS\Contracts\Ui\Tab;
use AdzerCMS\Contracts\Ui\Tabs;
use Symfony\Component\HttpFoundation\Request;

use function Symfony\Component\Translation\t;

final class Desk implements Tabs
{
    public bool $open = false;

    public function page(): string
    {
        return self::CABINET_HOME;
    }

    public function tabs(?int $subject): array
    {
        return $this->open ? [new Tab('desk', t('Desk'), 100)] : [];
    }

    public function tab(string $key, ?int $subject, Request $request, array $sticky): array
    {
        return ['template' => '@Theme/_actions.html.twig', 'context' => ['actions' => []]];
    }
}
