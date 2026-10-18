<?php

declare(strict_types=1);

namespace AdzerCMS\Contracts\Notice;

use Symfony\Contracts\Translation\TranslatableInterface;

interface BotListener
{
    public function heard(string $bot, string $service, Incoming $message): ?TranslatableInterface;
}
