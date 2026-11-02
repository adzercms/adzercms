<?php

declare(strict_types=1);

namespace AdzerCMS\Morphology\Dawg;

final readonly class Dawg
{
    public const int ROOT = 0;

    private const int MASK = 0xFFFFFFFF;
    private const int IS_LEAF = 1 << 31;
    private const int HAS_LEAF = 1 << 8;
    private const int EXTENSION = 1 << 9;
    private const int LABEL = self::IS_LEAF | 0xFF;
    private const int VALUE = ~self::IS_LEAF & self::MASK;

    private function __construct(private string $data, private int $guide)
    {
    }

    public static function load(string $path): self
    {
        $data = file_get_contents($path);
        if (false === $data) {
            throw new \RuntimeException(\sprintf('Cannot read the DAWG file %s', $path));
        }

        return new self($data, 4 + 4 * self::uint32($data, 0) + 4);
    }

    public function followBytes(string $bytes, int $index): ?int
    {
        for ($i = 0, $length = \strlen($bytes); $i < $length; ++$i) {
            $index = $this->follow(\ord($bytes[$i]), $index);
            if (null === $index) {
                return null;
            }
        }

        return $index;
    }

    public function find(string $key): ?int
    {
        $index = $this->followBytes($key, self::ROOT);
        if (null === $index || !$this->hasValue($index)) {
            return null;
        }

        return $this->unit(($index ^ self::offset($this->unit($index))) & self::MASK) & self::VALUE;
    }

    /**
     * @return \Generator<int, string>
     */
    public function completions(int $index): \Generator
    {
        $key = '';
        $stack = [$index];
        $last = self::ROOT;
        while (true) {
            $index = $stack[array_key_last($stack)];
            if (self::ROOT !== $last) {
                $child = $this->child($index);
                if (0 !== $child) {
                    $index = $this->follow($child, $index);
                    if (null === $index) {
                        return;
                    }
                    $key .= \chr($child);
                    $stack[] = $index;
                } else {
                    while (true) {
                        $sibling = $this->sibling($index);
                        $key = substr($key, 0, -1);
                        array_pop($stack);
                        if ([] === $stack) {
                            return;
                        }
                        $index = $stack[array_key_last($stack)];
                        if (0 !== $sibling) {
                            $index = $this->follow($sibling, $index);
                            if (null === $index) {
                                return;
                            }
                            $key .= \chr($sibling);
                            $stack[] = $index;
                            break;
                        }
                    }
                }
            }
            while (!$this->hasValue($index)) {
                $label = $this->child($index);
                $index = $this->follow($label, $index);
                if (null === $index) {
                    return;
                }
                $key .= \chr($label);
                $stack[] = $index;
            }
            $last = $index;
            yield $key;
        }
    }

    private function follow(int $label, int $index): ?int
    {
        $next = ($index ^ self::offset($this->unit($index)) ^ $label) & self::MASK;

        return ($this->unit($next) & self::LABEL) === $label ? $next : null;
    }

    private function hasValue(int $index): bool
    {
        return 0 !== ($this->unit($index) & self::HAS_LEAF);
    }

    private function unit(int $index): int
    {
        return self::uint32($this->data, 4 + 4 * $index);
    }

    /**
     * @return int<0, 255>
     */
    private function child(int $index): int
    {
        return \ord($this->data[$this->guide + 2 * $index]);
    }

    /**
     * @return int<0, 255>
     */
    private function sibling(int $index): int
    {
        return \ord($this->data[$this->guide + 2 * $index + 1]);
    }

    private static function offset(int $unit): int
    {
        return (($unit >> 10) << (($unit & self::EXTENSION) >> 6)) & self::MASK;
    }

    private static function uint32(string $data, int $offset): int
    {
        /** @var array{1: int} $value */
        $value = unpack('V', $data, $offset);

        return $value[1];
    }
}
