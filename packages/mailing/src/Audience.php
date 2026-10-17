<?php

declare(strict_types=1);

namespace AdzerCMS\Mailing;

use AdzerCMS\Core\Entity\User;
use AdzerCMS\Core\Ui\Filter;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Contracts\Translation\TranslatableInterface;

#[AutoconfigureTag]
interface Audience
{
    public function key(): string;

    public function label(): TranslatableInterface;

    public function filter(): ?Filter;

    /**
     * @param list<User> $users
     *
     * @return list<User>
     */
    public function select(array $users, string $value): array;
}
