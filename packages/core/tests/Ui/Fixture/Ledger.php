<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Ui\Fixture;

use AdzerCMS\Contracts\Ui\Cell;
use AdzerCMS\Contracts\Ui\Column;
use AdzerCMS\Contracts\Ui\Columns;
use Symfony\Component\Translation\TranslatableMessage;

final class Ledger implements Columns
{
    public const BALANCE = 'balance';
    public const LABEL = 'Ledger balance';

    /** @var array<int, string> */
    public array $balances = [];

    /** @var list<list<int>> */
    public array $asked = [];

    public function listing(): string
    {
        return self::ADMIN_USERS;
    }

    public function columns(): array
    {
        return [new Column(self::BALANCE, new TranslatableMessage(self::LABEL))];
    }

    public function cells(array $subjects): array
    {
        $this->asked[] = $subjects;
        $cells = [];
        foreach (array_intersect_key($this->balances, array_flip($subjects)) as $user => $balance) {
            $cells[$user] = [self::BALANCE => new Cell($balance, str_starts_with($balance, '-') ? -1 : 0)];
        }

        return $cells;
    }

    public function search(string $term): array
    {
        return array_keys(array_filter($this->balances, static fn (string $balance): bool => $balance === $term));
    }
}
