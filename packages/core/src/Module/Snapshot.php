<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Module;

use Psr\Cache\CacheItemPoolInterface;

use function Symfony\Component\Clock\now;

final class Snapshot
{
    public const LIFETIME = 900;
    public const RESCAN = 'X-Rescan';

    public function __construct(private readonly CacheItemPoolInterface $cache)
    {
    }

    /** @return array{at: \DateTimeImmutable, rows: array<string, mixed>}|null */
    public function read(string $key): ?array
    {
        $held = $this->cache->getItem($key)->get();

        if (!\is_array($held) || !\is_int($held['at'] ?? null) || !\is_array($held['rows'] ?? null)) {
            return null;
        }

        return ['at' => now()->setTimestamp($held['at']), 'rows' => $held['rows']];
    }

    /** @param array<string, mixed> $rows */
    public function write(string $key, array $rows, int $lifetime = self::LIFETIME): \DateTimeImmutable
    {
        $taken = now();
        $item = $this->cache->getItem($key);
        $item->set(['at' => $taken->getTimestamp(), 'rows' => $rows])->expiresAfter($lifetime);
        $this->cache->save($item);

        return $taken;
    }
}
