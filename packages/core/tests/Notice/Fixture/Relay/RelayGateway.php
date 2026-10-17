<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Notice\Fixture\Relay;

use AdzerCMS\Contracts\Module\Credentials;
use AdzerCMS\Contracts\Notice\SmsFailed;
use AdzerCMS\Contracts\Notice\SmsGateway;
use AdzerCMS\Contracts\Setting\Field;
use AdzerCMS\Contracts\Setting\Type;

use function Symfony\Component\Translation\t;

final class RelayGateway implements SmsGateway
{
    public const SETTINGS = 'sms.relay.';
    public const TOKEN = 'token';
    public const SENDER = 'sender';

    /** @var list<array{string, string, string}> */
    public array $sent = [];
    public ?string $refusal = null;

    /** @var array<string, mixed> */
    public array $contract = [];

    public int $polled = 0;

    public function credentials(): array
    {
        return [
            new Field(self::TOKEN, t('Relay token'), Type::Secret, required: true),
            new Field(self::SENDER, t('Relay sender')),
        ];
    }

    public function send(Credentials $credentials, string $phone, string $text): void
    {
        if (null !== $this->refusal) {
            throw new SmsFailed($this->refusal);
        }

        $this->sent[] = [$credentials->get(self::TOKEN), $phone, $text];
    }

    public function contract(Credentials $credentials): array
    {
        if (null !== $this->refusal) {
            throw new SmsFailed($this->refusal);
        }
        ++$this->polled;

        return $this->contract;
    }
}
