<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Build;

final readonly class HeldBuild
{
    /** @param resource $lock */
    public function __construct(
        public string $name,
        private mixed $lock,
    ) {
    }

    public function release(): void
    {
        flock($this->lock, \LOCK_UN);
        fclose($this->lock);
    }
}
