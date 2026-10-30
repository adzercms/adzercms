<?php

declare(strict_types=1);

namespace AdzerCMS\Contracts\Account;

use AdzerCMS\Contracts\Refusal;
use Symfony\Contracts\Translation\TranslatableInterface;

interface UserPlaces
{
    public function label(): TranslatableInterface;

    /** @return array<string, string> */
    public function places(): array;

    public function create(string $place, Newcomer $newcomer): ?Refusal;
}
