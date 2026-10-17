<?php

declare(strict_types=1);

namespace AdzerCMS\Realtime;

use AdzerCMS\Contracts\Ui\ContentSource;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\HttpFoundation\Request;

final readonly class Hub implements ContentSource
{
    private ?RealtimeHub $module;

    /** @param iterable<RealtimeHub> $modules */
    public function __construct(#[AutowireIterator(RealtimeHub::class)] iterable $modules)
    {
        $this->module = [...$modules][0] ?? null;
    }

    public function ready(): ?RealtimeHub
    {
        return true === $this->module?->ready() ? $this->module : null;
    }

    public function sources(Request $request): array
    {
        $hub = $this->ready();

        return null === $hub ? [] : [self::CONNECT => [$hub->endpoint($request)]];
    }
}
