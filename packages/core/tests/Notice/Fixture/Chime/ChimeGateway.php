<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Notice\Fixture\Chime;

use AdzerCMS\Contracts\Module\Credentials;
use AdzerCMS\Contracts\Notice\BotGateway;
use AdzerCMS\Contracts\Setting\Field;
use AdzerCMS\Contracts\Setting\Type;

use function Symfony\Component\Translation\t;

final class ChimeGateway implements BotGateway
{
    public const SETTINGS = 'bot.chime.';
    public const SERVICE = 'chime';

    /** @var list<array{string, string}> */
    public array $sent = [];

    public function credentials(): array
    {
        return [new Field(self::TOKEN, t('Chime token'), Type::Secret, required: true)];
    }

    public function service(): string
    {
        return self::SERVICE;
    }

    public function send(Credentials $credentials, string $recipient, string $text): void
    {
        $this->sent[] = [$recipient, $text];
    }
}
