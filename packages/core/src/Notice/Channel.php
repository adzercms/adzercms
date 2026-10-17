<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Notice;

use AdzerCMS\Core\Entity\User;
use Symfony\Contracts\Translation\TranslatableInterface;

interface Channel
{
    public function key(): string;

    public function label(): TranslatableInterface;

    public function ready(): bool;

    public function markup(): bool;

    /**
     * @param list<User> $users
     *
     * @return array<int, string>
     */
    public function recipients(array $users): array;

    /** @throws Undelivered */
    public function send(string $recipient, Letter $letter): void;
}
