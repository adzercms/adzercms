<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Confirmation;

enum Purpose: string
{
    case Password = 'password';
    case Registration = 'registration';
    case Contact = 'contact';
    case Release = 'release';
    case Revert = 'revert';
    case Deletion = 'deletion';
    case Messenger = 'messenger';
}
