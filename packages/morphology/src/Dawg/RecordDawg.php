<?php

declare(strict_types=1);

namespace AdzerCMS\Morphology\Dawg;

final readonly class RecordDawg
{
    private const string SEPARATOR = "\x01";

    public function __construct(private Dawg $dawg, private string $format)
    {
    }

    /**
     * @param array<string, list<string>> $replaces
     *
     * @return list<array{string, list<list<int>>}>
     */
    public function similarItems(string $key, array $replaces): array
    {
        return $this->similar('', mb_str_split($key), 0, Dawg::ROOT, $replaces);
    }

    /**
     * @param list<string>                $chars
     * @param array<string, list<string>> $replaces
     *
     * @return list<array{string, list<list<int>>}>
     */
    private function similar(string $prefix, array $chars, int $start, int $index, array $replaces): array
    {
        $items = [];
        for ($position = $start, $count = \count($chars); $position < $count; ++$position) {
            foreach ($replaces[$chars[$position]] ?? [] as $replace) {
                $next = $this->dawg->followBytes($replace, $index);
                if ($next) {
                    $passed = implode('', \array_slice($chars, $start, $position - $start));
                    array_push($items, ...$this->similar($prefix.$passed.$replace, $chars, $position + 1, $next, $replaces));
                }
            }
            $index = $this->dawg->followBytes($chars[$position], $index);
            if (!$index) {
                return $items;
            }
        }
        $index = $this->dawg->followBytes(self::SEPARATOR, $index);
        if ($index) {
            array_unshift($items, [$prefix.implode('', \array_slice($chars, $start)), $this->values($index)]);
        }

        return $items;
    }

    /**
     * @return list<list<int>>
     */
    private function values(int $index): array
    {
        $values = [];
        foreach ($this->dawg->completions($index) as $encoded) {
            /** @var array<string, int> $value */
            $value = unpack($this->format, (string) base64_decode($encoded));
            $values[] = array_values($value);
        }

        return $values;
    }
}
