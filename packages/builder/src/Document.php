<?php

declare(strict_types=1);

namespace AdzerCMS\Builder;

use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Pages\Album;
use AdzerCMS\Pages\Entity\BlockRecord;

interface Document
{
    public function title(): string;

    /** @return list<BlockRecord> */
    public function blocks(): array;

    public function block(string $type): BlockRecord;

    public function album(): Album;

    /** @param list<array{array<string, string>, array<string, array<string, array<string, string>>>}> $blocks */
    public function sizeRefusal(array $blocks): ?Refusal;

    public function picturesRefusal(int $added): ?Refusal;

    public function touch(): void;

    /** @return array{title: string, description: string, heading: ?string, tabs: list<array{label: string, href: string, active: bool}>, blocks: list<array<string, mixed>>, style: string, schema: list<array<string, mixed>>} */
    public function view(): array;

    /** @return array{string, array<string, int>} */
    public function route(): array;

    /** @return array{string, array<string, int>} */
    public function exit(): array;

    /** @return array{string, array<string, int>} */
    public function picture(): array;
}
