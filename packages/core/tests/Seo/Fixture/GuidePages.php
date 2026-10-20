<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Seo\Fixture;

use AdzerCMS\Contracts\Seo\Page;
use AdzerCMS\Contracts\Seo\Pages;
use AdzerCMS\Core\Entity\Text;
use Symfony\Component\Translation\TranslatableMessage;

final readonly class GuidePages implements Pages
{
    public function pages(): array
    {
        return [
            new Page('fixture_guide', new TranslatableMessage('fixture.guide'), ['fixture_guide_part', 'fixture_guide_file'], [Text::class]),
            new Page('fixture_shelf', new TranslatableMessage('fixture.shelf'), ['fixture_shelf_file'], parameters: ['name' => 'tales']),
        ];
    }
}
