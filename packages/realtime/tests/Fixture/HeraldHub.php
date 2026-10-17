<?php

declare(strict_types=1);

namespace AdzerCMS\Realtime\Tests\Fixture;

use AdzerCMS\Realtime\RealtimeHub;
use Symfony\Component\HttpFoundation\Request;

final class HeraldHub implements RealtimeHub
{
    public bool $ready = true;

    /** @var list<list<string>> */
    private array $published = [];

    public function ready(): bool
    {
        return $this->ready;
    }

    public function endpoint(Request $request): string
    {
        return 'wss://herald.example.org/'.$request->getHost();
    }

    public function personal(string $subject): string
    {
        return 'herald:'.$subject;
    }

    public function publish(array $channels, array $data): void
    {
        $this->published[] = $channels;
    }

    public function connectionToken(string $subject): string
    {
        return 'connection '.$subject;
    }

    public function subscriptionToken(string $subject, string $channel): string
    {
        return 'subscription '.$subject.' '.$channel;
    }

    public function client(): string
    {
        return 'bundles/herald/client.js';
    }

    /**
     * @phpstan-impure
     *
     * @return list<list<string>>
     */
    public function released(): array
    {
        $published = $this->published;
        $this->published = [];

        return $published;
    }
}
