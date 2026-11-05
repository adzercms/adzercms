<?php

declare(strict_types=1);

namespace AdzerCMS\Pages;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final class Blocks
{
    /** @var array<string, Block>|null */
    private ?array $types = null;

    /** @param iterable<Block> $declared */
    public function __construct(
        #[AutowireIterator(Block::class)]
        private readonly iterable $declared,
    ) {
    }

    public function find(string $key): ?Block
    {
        return $this->all()[$key] ?? null;
    }

    /** @return array<string, Block> */
    public function all(): array
    {
        if (null === $this->types) {
            $this->types = [];
            foreach ($this->declared as $type) {
                if (isset($this->types[$type->key()])) {
                    throw new \LogicException(\sprintf('block type %s is declared twice', $type->key()));
                }
                $this->types[$type->key()] = $type;
            }
        }

        return $this->types;
    }
}
