<?php

declare(strict_types=1);

namespace AdzerCMS\Contracts\Ui;

interface Columns
{
    public const ADMIN_USERS = 'admin.users';

    public function listing(): string;

    /** @return list<Column> */
    public function columns(): array;

    /**
     * @param list<int> $subjects
     *
     * @return array<int, array<string, Cell>>
     */
    public function cells(array $subjects): array;

    /** @return list<int> */
    public function search(string $term): array;
}
