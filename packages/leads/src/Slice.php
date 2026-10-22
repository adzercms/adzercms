<?php

declare(strict_types=1);

namespace AdzerCMS\Leads;

use Symfony\Contracts\Translation\TranslatableInterface;

use function Symfony\Component\Translation\t;

enum Slice: string
{
    case Fresh = 'new';
    case Work = 'work';
    case Closed = 'closed';

    /** @param array<string, int> $counts */
    public function label(array $counts): TranslatableInterface
    {
        $count = 0;
        foreach ($this->groups() as $group) {
            $count += $counts[$group->value] ?? 0;
        }

        return t('leads.slice.'.$this->value, ['count' => $count]);
    }

    /** @return list<StageGroup> */
    public function groups(): array
    {
        return array_values(array_filter(StageGroup::cases(), fn (StageGroup $group): bool => $group->slice() === $this));
    }
}
