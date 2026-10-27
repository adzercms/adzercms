<?php

declare(strict_types=1);

namespace AdzerCMS\Contracts\Setting;

use Symfony\Contracts\Translation\TranslatableInterface;

final readonly class Field
{
    /** @param array<string, TranslatableInterface> $choices */
    public function __construct(
        public string $name,
        public TranslatableInterface $label,
        public Type $type = Type::Text,
        public bool $required = false,
        public array $choices = [],
        public ?string $group = null,
        public ?TranslatableInterface $section = null,
        public ?Tab $tab = null,
    ) {
    }

    public function basename(): string
    {
        $position = strrpos($this->name, '.');

        return false === $position ? $this->name : substr($this->name, $position + 1);
    }
}
