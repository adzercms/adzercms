<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Analytics\Fixture\Tally;

use AdzerCMS\Contracts\Showcase\Showcase;
use AdzerCMS\Core\Setting\Settings;

final readonly class TallyShowcase implements Showcase
{
    public function __construct(private Settings $settings)
    {
    }

    public function section(): array
    {
        return array_filter([TallyCounter::SITE => $this->settings->get(TallyCounter::SETTINGS.TallyCounter::SITE)]);
    }
}
