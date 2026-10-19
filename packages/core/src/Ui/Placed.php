<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Ui;

interface Placed
{
    public function getId(): ?int;

    public function place(int $position): void;
}
