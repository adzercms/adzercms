<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Build;

use Symfony\Component\Filesystem\Filesystem;

final readonly class Builds
{
    public const ENV = 'APP_BUILD';

    private const POINTER = 'build';
    private const LOCK = '.lock';
    private const NAME = '/^[\w-]+$/';
    private const SET = '[0-9a-f]{16}';

    public function __construct(private string $root)
    {
    }

    public static function root(string $cache, string $set): string
    {
        return '' === $set ? $cache : $cache.'_'.$set;
    }

    /** @return list<string> */
    public static function roots(string $cache): array
    {
        return array_values(array_filter(
            [$cache, ...preg_grep('~_'.self::SET.'$~', glob($cache.'_*', \GLOB_ONLYDIR) ?: []) ?: []],
            is_dir(...),
        ));
    }

    /** @return list<string> */
    public static function strays(string $cache, string $assets): array
    {
        $strays = [];
        foreach (array_diff(is_dir($assets) ? scandir($assets) ?: [] : [], ['.', '..']) as $entry) {
            if (!is_dir(self::root($cache, 1 === preg_match('~^'.self::SET.'$~', $entry) ? $entry : ''))) {
                $strays[] = $assets.'/'.$entry;
            }
        }

        return $strays;
    }

    public static function fixed(): ?string
    {
        $build = (string) ($_SERVER[self::ENV] ?? $_ENV[self::ENV] ?? '');

        return '' === $build ? null : self::checked($build);
    }

    public function current(): ?string
    {
        $pointer = $this->root.'/'.self::POINTER;

        return is_file($pointer) ? self::checked(trim((string) file_get_contents($pointer))) : null;
    }

    public function hold(?string $fixed): ?HeldBuild
    {
        while (true) {
            $build = $fixed ?? $this->current();

            if (null === $build) {
                return null;
            }

            $lock = $this->lock($build);
            flock($lock, \LOCK_SH);
            $stat = fstat($lock);

            if (false !== $stat && $stat['nlink'] > 0) {
                return new HeldBuild($build, $lock);
            }

            fclose($lock);
        }
    }

    public function switchTo(string $build): void
    {
        (new Filesystem())->dumpFile($this->root.'/'.self::POINTER, self::checked($build)."\n");
    }

    /** @return list<string> */
    public function prune(): array
    {
        $current = $this->current();

        return null === $current ? [] : $this->remove(array_diff($this->names(), [$current]));
    }

    /** @return list<string> */
    public function retire(self ...$current): array
    {
        $pointed = $this->pointed();
        $behind = max([0, ...array_map(static fn (self $builds): int => $builds->pointed() ?? 0, $current)]);

        if (null === $pointed || $pointed >= $behind) {
            return [];
        }

        $names = $this->names();
        $removed = $this->remove($names);

        if (\count($removed) === \count($names)) {
            (new Filesystem())->remove($this->root);
        }

        return $removed;
    }

    private function pointed(): ?int
    {
        $pointer = $this->root.'/'.self::POINTER;

        return is_file($pointer) ? (int) filemtime($pointer) : null;
    }

    /** @return list<string> */
    private function names(): array
    {
        return array_values(array_unique(array_map(
            static fn (string $entry): string => str_ends_with($entry, self::LOCK) ? substr($entry, 0, -\strlen(self::LOCK)) : $entry,
            array_diff(scandir($this->root) ?: [], ['.', '..', self::POINTER]),
        )));
    }

    /**
     * @param array<string> $names
     *
     * @return list<string>
     */
    private function remove(array $names): array
    {
        $removed = [];

        foreach ($names as $name) {
            $lock = $this->lock($name);

            if (flock($lock, \LOCK_EX | \LOCK_NB)) {
                (new Filesystem())->remove([$this->root.'/'.$name, $this->root.'/'.$name.self::LOCK]);
                $removed[] = $name;
            }

            fclose($lock);
        }

        sort($removed);

        return $removed;
    }

    /** @return resource */
    private function lock(string $name)
    {
        (new Filesystem())->mkdir($this->root);
        $lock = fopen($this->root.'/'.$name.self::LOCK, 'c');

        return false === $lock ? throw new \RuntimeException(sprintf('lock of build %s is not opened', $name)) : $lock;
    }

    private static function checked(string $build): string
    {
        return 1 === preg_match(self::NAME, $build)
            ? $build
            : throw new \LogicException(sprintf('cache build name "%s" is invalid', $build));
    }
}
