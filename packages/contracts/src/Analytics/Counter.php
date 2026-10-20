<?php

declare(strict_types=1);

namespace AdzerCMS\Contracts\Analytics;

use AdzerCMS\Contracts\Module\Credentials;
use AdzerCMS\Contracts\Module\Switchable;
use AdzerCMS\Contracts\Ui\ContentSource;
use Symfony\Contracts\Translation\TranslatableInterface;

interface Counter extends Switchable
{
    public function name(): TranslatableInterface;

    public function entry(): string;

    /** @return array<string, mixed> */
    public function settings(Credentials $credentials): array;

    /** @return array<ContentSource::SCRIPT|ContentSource::CONNECT|ContentSource::IMG|ContentSource::FRAME|ContentSource::WORKER|ContentSource::FRAME_ANCESTORS, list<string>> */
    public function origins(): array;

    public function cookies(): string;
}
