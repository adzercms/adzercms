<?php

declare(strict_types=1);

namespace AdzerCMS\Mailing\Tests\Fixture;

use AdzerCMS\Core\Entity\User;
use AdzerCMS\Core\Ui\Filter;
use AdzerCMS\Mailing\Audience;
use Symfony\Contracts\Translation\TranslatableInterface;

use function Symfony\Component\Translation\t;

final class CommentAudience implements Audience
{
    public const KEY = 'comment';
    public const COMMENTS = ['North', 'South'];

    public function key(): string
    {
        return self::KEY;
    }

    public function label(): TranslatableInterface
    {
        return t('Commented');
    }

    public function filter(): Filter
    {
        return Filter::choice(self::KEY, array_combine(self::COMMENTS, self::COMMENTS), self::COMMENTS[0]);
    }

    public function select(array $users, string $value): array
    {
        return array_values(array_filter($users, static fn (User $user): bool => $value === $user->getComment()));
    }
}
