<?php

declare(strict_types=1);

namespace AdzerCMS\Morphology;

final class Seen
{
    /** @var array<string, true> */
    private array $keys = [];

    public function add(string $word, Tag $tag, ?int $paradigm): bool
    {
        $key = $word."\0".$tag->grammemes."\0".($paradigm ?? '');
        if (isset($this->keys[$key])) {
            return false;
        }
        $this->keys[$key] = true;

        return true;
    }

    public function empty(): bool
    {
        return [] === $this->keys;
    }
}
