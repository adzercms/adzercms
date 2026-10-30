<?php

declare(strict_types=1);

namespace AdzerCMS\Admin\Tests\Fixture;

use AdzerCMS\Contracts\Account\Newcomer;
use AdzerCMS\Contracts\Account\UserCreation;
use AdzerCMS\Contracts\Account\UserPlaces;
use AdzerCMS\Contracts\Refusal;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatableInterface;

final class Branches implements UserPlaces, UserCreation
{
    public const LABEL = 'Branch';

    /** @var array<string, string> */
    public array $branches = [];

    public bool $open = true;

    public ?Refusal $refusal = null;

    /** @var array<string, Newcomer> */
    public array $created = [];

    public function label(): TranslatableInterface
    {
        return new TranslatableMessage(self::LABEL);
    }

    public function places(): array
    {
        return $this->branches;
    }

    public function create(string $place, Newcomer $newcomer): ?Refusal
    {
        if (null === $this->refusal) {
            $this->created[$place] = $newcomer;
        }

        return $this->refusal;
    }

    public function here(): bool
    {
        return $this->open;
    }
}
