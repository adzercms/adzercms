<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Account;

enum ContactOutcome: string
{
    case Sent = 'sent';
    case Former = 'former';
    case Owner = 'owner';
    case Denied = 'denied';
    case Known = 'known';
    case TooOften = 'too_often';
    case Unavailable = 'unavailable';
    case Unreachable = 'unreachable';
    case Malformed = 'malformed';
}
