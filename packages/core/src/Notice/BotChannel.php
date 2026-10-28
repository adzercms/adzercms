<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Notice;

use AdzerCMS\Contracts\Module\Credentials;
use AdzerCMS\Contracts\Notice\BotEvents;
use AdzerCMS\Contracts\Notice\BotFailed;
use AdzerCMS\Contracts\Notice\BotGateway;
use AdzerCMS\Core\Repository\ConnectedServiceRepository;
use Symfony\Contracts\Translation\TranslatableInterface;

use function Symfony\Component\Translation\t;

final readonly class BotChannel implements Channel
{
    public function __construct(
        private string $key,
        public BotGateway $gateway,
        public Credentials $credentials,
        private ConnectedServiceRepository $services,
    ) {
    }

    public function key(): string
    {
        return $this->key;
    }

    public function label(): TranslatableInterface
    {
        return t($this->key.'.name');
    }

    public function missing(): TranslatableInterface
    {
        return t('core.channel.missing_bot');
    }

    public function ready(): bool
    {
        return true;
    }

    public function markup(): bool
    {
        return false;
    }

    public function recipients(array $users): array
    {
        $profiles = $this->services->profiles($this->gateway->service(), $users);
        $recipients = [];
        foreach ($users as $at => $user) {
            $profile = null === $user->getId() ? null : $profiles[$user->getId()] ?? null;
            if (null !== $profile) {
                $recipients[$at] = $profile;
            }
        }

        return $recipients;
    }

    public function send(string $recipient, Letter $letter, ?int $notice = null): void
    {
        try {
            $this->gateway->send($this->credentials, $recipient, $letter->text);
        } catch (BotFailed $failure) {
            throw new Undelivered($failure->getMessage(), previous: $failure);
        }
    }

    /** @phpstan-assert-if-true BotEvents $this->gateway */
    public function linksByCode(): bool
    {
        return $this->gateway instanceof BotEvents;
    }

    public function startLink(string $code): ?string
    {
        if (!$this->linksByCode()) {
            return null;
        }

        try {
            return $this->gateway->startLink($this->credentials, $code);
        } catch (BotFailed) {
            return null;
        }
    }
}
