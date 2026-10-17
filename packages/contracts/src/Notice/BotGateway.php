<?php

declare(strict_types=1);

namespace AdzerCMS\Contracts\Notice;

use AdzerCMS\Contracts\Module\Credentials;
use AdzerCMS\Contracts\Module\Switchable;

interface BotGateway extends Switchable
{
    public const TOKEN = 'token';

    public function service(): string;

    /** @throws BotFailed */
    public function send(Credentials $credentials, string $recipient, string $text): void;
}
