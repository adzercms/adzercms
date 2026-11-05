<?php

declare(strict_types=1);

namespace AdzerCMS\Contracts\Setting;

use AdzerCMS\Contracts\Ui\Scheme;
use Symfony\Contracts\Translation\TranslatableInterface;

final readonly class Field
{
    /**
     * @param array<string, TranslatableInterface>|\Closure(): array<string, TranslatableInterface> $choices
     * @param list<Scheme>                                                                          $schemes
     * @param list<Field>                                                                           $fields
     */
    public function __construct(
        public string $name,
        public TranslatableInterface $label,
        public Type $type = Type::Text,
        public bool $required = false,
        private array|\Closure $choices = [],
        public ?string $group = null,
        public ?TranslatableInterface $section = null,
        public ?Page $page = null,
        public array $schemes = [],
        public array $fields = [],
    ) {
        if ((Type::Rows === $type) === ([] === $fields) || [] !== array_filter($fields, static fn (Field $field): bool => !$field->type->cell())) {
            throw new \LogicException(\sprintf('setting key %s: only a group of rows declares fields, each of a single value type', $name));
        }
    }

    /** @return array<string, TranslatableInterface> */
    public function choices(): array
    {
        return $this->choices instanceof \Closure ? ($this->choices)() : $this->choices;
    }

    public function prefixed(string $prefix): self
    {
        return new self(
            $prefix.$this->name,
            $this->label,
            $this->type,
            $this->required,
            $this->choices,
            null === $this->group ? null : $prefix.$this->group,
            $this->section,
            $this->page,
            $this->schemes,
            $this->fields,
        );
    }

    public function basename(): string
    {
        $position = strrpos($this->name, '.');

        return false === $position ? $this->name : substr($this->name, $position + 1);
    }
}
