<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Analytics\Fixture\Tally;

use AdzerCMS\Contracts\Showcase\Showcase;
use AdzerCMS\Core\Setting\Settings;

final readonly class TallyShowcase implements Showcase
{
    public const SITES = 'sites';
    public const MIRROR = 'mirror';

    public function __construct(private Settings $settings)
    {
    }

    public function section(): array
    {
        $site = (string) $this->settings->get(TallyCounter::SETTINGS.TallyCounter::SITE);

        return '' === $site ? [] : [TallyCounter::SITE => $site, self::SITES => [$site, self::MIRROR]];
    }
}
