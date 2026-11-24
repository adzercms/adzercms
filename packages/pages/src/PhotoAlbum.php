<?php

declare(strict_types=1);

namespace AdzerCMS\Pages;

use AdzerCMS\Core\Photo\Photos;
use AdzerCMS\Pages\Entity\BlockRecord;
use Symfony\Component\HttpFoundation\File\File;

final readonly class PhotoAlbum implements Album
{
    /**
     * @param \Closure(string): string $src
     * @param \Closure(): list<string> $held
     */
    public function __construct(
        private Photos $photos,
        private string $place,
        private int $side,
        private \Closure $src,
        private \Closure $held,
    ) {
    }

    public function store(File $picture): string
    {
        return $this->photos->store($picture, $this->place, $this->side);
    }

    public function path(string $name): ?string
    {
        return $this->photos->path($this->place, $name);
    }

    public function shown(string $name): ?array
    {
        $path = $this->path($name);
        $size = null === $path ? false : getimagesize($path);

        return false === $size ? null : ['src' => ($this->src)($name), 'width' => $size[0], 'height' => $size[1]];
    }

    public function listed(): array
    {
        $names = $this->photos->names($this->place);
        $times = array_map(fn (string $name): int => (int) filemtime((string) $this->path($name)), $names);
        array_multisort($times, \SORT_DESC, $names);

        return array_map($this->entry(...), $names);
    }

    public function entry(string $name): array
    {
        return ['src' => ($this->src)($name), 'name' => $name];
    }

    public function drop(array $values): void
    {
        $this->photos->drop($this->place, array_values(array_diff(self::names($values), ($this->held)())));
    }

    public function clear(): void
    {
        $this->photos->clear($this->place);
    }

    public function sweep(int $before): int
    {
        $stale = array_values(array_filter(array_diff($this->photos->names($this->place), ($this->held)()), fn (string $name): bool => filemtime((string) $this->path($name)) < $before));
        $this->photos->drop($this->place, $stale);

        return \count($stale);
    }

    /**
     * @param iterable<array{values: array<string, string>, style: array<string, array<string, array<string, string>>>}> $blocks
     *
     * @return list<string>
     */
    public static function blocks(iterable $blocks): array
    {
        $names = [];
        foreach ($blocks as $block) {
            $names = [...$names, ...self::names($block['values']), ...BlockStyle::pictures($block['style'])];
        }

        return array_values(array_unique($names));
    }

    /**
     * @param iterable<BlockRecord> $records
     *
     * @return list<string>
     */
    public static function records(iterable $records): array
    {
        $blocks = [];
        foreach ($records as $record) {
            $blocks[] = ['values' => $record->getValues(), 'style' => $record->getStyle()];
        }

        return self::blocks($blocks);
    }

    /**
     * @param array<array-key, string> $values
     *
     * @return list<string>
     */
    public static function names(array $values): array
    {
        $names = [];
        foreach ($values as $value) {
            $rows = json_validate($value) ? json_decode($value, true) : null;
            if (\is_array($rows)) {
                array_walk_recursive($rows, static function (mixed $cell) use (&$names): void {
                    if (\is_string($cell)) {
                        $names[] = $cell;
                    }
                });
            } else {
                $names[] = $value;
            }
        }

        return $names;
    }
}
