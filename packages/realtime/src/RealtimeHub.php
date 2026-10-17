<?php

declare(strict_types=1);

namespace AdzerCMS\Realtime;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\HttpFoundation\Request;

#[AutoconfigureTag]
interface RealtimeHub
{
    public function ready(): bool;

    public function endpoint(Request $request): string;

    public function personal(string $subject): string;

    /**
     * @param list<string>          $channels
     * @param array<string, string> $data
     */
    public function publish(array $channels, array $data): void;

    public function connectionToken(string $subject): string;

    public function subscriptionToken(string $subject, string $channel): string;

    public function client(): string;
}
