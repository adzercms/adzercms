<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Analytics\Fixture\Tally;

use AdzerCMS\Contracts\Analytics\Counter;
use AdzerCMS\Contracts\Module\Credentials;
use AdzerCMS\Contracts\Setting\Field;
use AdzerCMS\Contracts\Ui\ContentSource;
use Symfony\Contracts\Translation\TranslatableInterface;

use function Symfony\Component\Translation\t;

final class TallyCounter implements Counter
{
    public const SETTINGS = 'analytics.tally.';
    public const SITE = 'site';
    public const ENTRY = 'tally/counter';
    public const ORIGIN = 'https://tally.test';
    public const BOARD = 'https://board.tally.test';
    public const COOKIES = '^_tally';

    public function name(): TranslatableInterface
    {
        return t('Tally & Co');
    }

    public function credentials(): array
    {
        return [new Field(self::SITE, t('Tally site'), required: true)];
    }

    public function entry(): string
    {
        return self::ENTRY;
    }

    public function settings(Credentials $credentials): array
    {
        return ['site' => $credentials->get(self::SITE)];
    }

    public function origins(): array
    {
        return [ContentSource::SCRIPT => [self::ORIGIN], ContentSource::CONNECT => [self::ORIGIN], ContentSource::FRAME_ANCESTORS => [self::BOARD]];
    }

    public function cookies(): string
    {
        return self::COOKIES;
    }
}
