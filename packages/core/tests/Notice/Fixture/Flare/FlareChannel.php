<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Notice\Fixture\Flare;

use AdzerCMS\Contracts\Setting\Field;
use AdzerCMS\Contracts\Setting\Owner;
use AdzerCMS\Contracts\Setting\Type;
use AdzerCMS\Core\Notice\Channel;
use AdzerCMS\Core\Notice\Letter;
use AdzerCMS\Core\Setting\Settings;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatableInterface;

final class FlareChannel implements Channel, Owner
{
    public const READY = 'flare.ready';

    /** @var list<array{string, string}> */
    public array $sent = [];

    public function __construct(private readonly Settings $settings)
    {
    }

    public function settingFields(): array
    {
        return [new Field(self::READY, new TranslatableMessage('Flare ready'), Type::Toggle)];
    }

    public function key(): string
    {
        return 'flare';
    }

    public function label(): TranslatableInterface
    {
        return new TranslatableMessage('Flare');
    }

    public function ready(): bool
    {
        return $this->settings->on(self::READY);
    }

    public function missing(): TranslatableInterface
    {
        return new TranslatableMessage('Light the flare');
    }

    public function markup(): bool
    {
        return false;
    }

    public function recipients(array $users): array
    {
        $recipients = [];
        foreach ($this->ready() ? $users : [] as $at => $user) {
            if (null !== $user->getPhone()) {
                $recipients[$at] = 'flare:'.$user->getPhone();
            }
        }

        return $recipients;
    }

    public function send(string $recipient, Letter $letter): void
    {
        $this->sent[] = [$recipient, $letter->text];
    }
}
