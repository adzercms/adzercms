<?php

declare(strict_types=1);

namespace AdzerCMS\Leads;

use Symfony\Contracts\Translation\TranslatableInterface;

use function Symfony\Component\Clock\now;
use function Symfony\Component\Translation\t;

enum Slice: string
{
    case Fresh = 'new';
    case Work = 'work';
    case Closed = 'closed';
    case Overdue = 'overdue';
    case Today = 'today';
    case Week = 'week';

    public const FUNNEL = [self::Fresh, self::Work, self::Closed];
    public const WATCH = [self::Fresh, self::Overdue, self::Today, self::Week];

    private const WEEK = 7;

    public function label(int $count): TranslatableInterface
    {
        return t('leads.slice.'.$this->value, ['count' => $count]);
    }

    /** @return list<StageGroup> */
    public function groups(): array
    {
        return match ($this) {
            self::Overdue => array_values(array_filter(StageGroup::cases(), static fn (StageGroup $group): bool => self::Closed !== $group->slice())),
            self::Today, self::Week => StageGroup::cases(),
            default => array_values(array_filter(StageGroup::cases(), fn (StageGroup $group): bool => $group->slice() === $this)),
        };
    }

    public function since(): ?\DateTimeImmutable
    {
        return match ($this) {
            self::Today => now()->setTime(0, 0),
            self::Week => now()->setTime(0, 0)->modify(\sprintf('-%d days', self::WEEK - 1)),
            default => null,
        };
    }
}
