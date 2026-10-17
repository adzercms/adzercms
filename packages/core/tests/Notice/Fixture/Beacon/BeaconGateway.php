<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Notice\Fixture\Beacon;

use AdzerCMS\Contracts\Module\Switchable;
use AdzerCMS\Contracts\Setting\Field;

use function Symfony\Component\Translation\t;

final class BeaconGateway implements Switchable
{
    public const SETTINGS = 'sms.beacon.';
    public const LOGIN = 'login';

    public function credentials(): array
    {
        return [new Field(self::LOGIN, t('Beacon login'), required: true)];
    }
}
