<?php

declare(strict_types=1);

namespace AdzerCMS\Contracts\Login;

use Symfony\Contracts\Translation\TranslatableInterface;

final class LoginFailed extends \RuntimeException
{
    public function __construct(public readonly TranslatableInterface $reason, ?\Throwable $previous = null)
    {
        parent::__construct('the login service refused to sign in', previous: $previous);
    }
}
