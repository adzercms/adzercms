<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Notice\Fixture\Relay;

use AdzerCMS\Contracts\Setting\Field;
use AdzerCMS\Contracts\Setting\Owner;

use function Symfony\Component\Translation\t;

final class RelaySettings implements Owner
{
    public const SIGNATURE = RelayGateway::SETTINGS.'signature';

    public function settingFields(): array
    {
        return [new Field(self::SIGNATURE, t('Relay signature'))];
    }
}
