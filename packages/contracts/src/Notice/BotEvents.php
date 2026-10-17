<?php

declare(strict_types=1);

namespace AdzerCMS\Contracts\Notice;

use AdzerCMS\Contracts\Module\Credentials;
use Symfony\Component\HttpFoundation\Request;

interface BotEvents extends BotGateway
{
    public function origin(): string;

    /** @throws BotFailed */
    public function startLink(Credentials $credentials, string $code): string;

    /** @throws BotFailed */
    public function subscribe(Credentials $credentials, string $url, string $secret): void;

    public function authentic(Request $request, string $secret): bool;

    /** @param array<mixed> $update */
    public function incoming(array $update): ?Incoming;
}
