<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Notice;

use AdzerCMS\Contracts\Notice\SmsFailed;
use AdzerCMS\Contracts\Notice\SmsGateway;
use AdzerCMS\Core\Account\ContactKind;
use AdzerCMS\Core\Module\Switches;
use Symfony\Contracts\Translation\TranslatableInterface;

use function Symfony\Component\Translation\t;

final class SmsChannel extends ContactChannel
{
    public function __construct(private readonly Switches $switches)
    {
    }

    public function label(): TranslatableInterface
    {
        return t('core.channel.sms');
    }

    public function ready(): bool
    {
        return null !== $this->switches->first(SmsGateway::class);
    }

    public function markup(): bool
    {
        return false;
    }

    public function send(string $recipient, Letter $letter): void
    {
        [$gateway, $credentials] = $this->switches->first(SmsGateway::class) ?? throw new Undelivered('no message gateway is enabled');

        try {
            $gateway->send($credentials, $recipient, $letter->text);
        } catch (SmsFailed $failure) {
            throw new Undelivered($failure->getMessage(), previous: $failure);
        }
    }

    protected function kind(): ContactKind
    {
        return ContactKind::Phone;
    }
}
