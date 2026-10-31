<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Account\Fixture\Lobby;

use AdzerCMS\Contracts\Module\CoreKind;
use AdzerCMS\Contracts\Module\Kind;
use AdzerCMS\Contracts\Module\Module;

final class LobbyBundle extends Module
{
    public static function kind(): Kind
    {
        return CoreKind::Cabinet;
    }
}
