<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Notice\Fixture;

use AdzerCMS\Contracts\Notice\BotListener;
use AdzerCMS\Contracts\Notice\Incoming;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatableInterface;

final class EchoListener implements BotListener
{
    public const HEARD = 'ping';

    public function heard(string $bot, string $service, Incoming $message): ?TranslatableInterface
    {
        return self::HEARD === $message->text ? new TranslatableMessage($bot.' '.$service.' pong') : null;
    }
}
