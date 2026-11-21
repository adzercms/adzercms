<?php

declare(strict_types=1);

namespace AdzerCMS\Builder;

use AdzerCMS\Pages\Entity\BlockRecord;

final class VersionBlock extends BlockRecord
{
    /**
     * @param array<string, string>                               $values
     * @param array<string, array<string, array<string, string>>> $style
     */
    public function __construct(
        private readonly int $number,
        string $type,
        array $values,
        array $style,
    ) {
        parent::__construct($type, $values);
        $this->restyle($style);
    }

    public function getId(): int
    {
        return $this->number;
    }
}
