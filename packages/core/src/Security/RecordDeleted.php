<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Security;

use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;

final class RecordDeleted extends CustomUserMessageAccountStatusException
{
    public function __construct()
    {
        parent::__construct('core.account.deleted');
    }
}
