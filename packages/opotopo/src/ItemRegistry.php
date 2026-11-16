<?php

declare(strict_types=1);

namespace AdzerCMS\Opotopo;

use AdzerCMS\Opotopo\Entity\GitSource;
use AdzerCMS\Opotopo\Entity\Item;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Opotopo\SellerApi\Sync\ItemState;
use Opotopo\SellerApi\Sync\ItemStore;
use Symfony\Contracts\Service\ResetInterface;

final class ItemRegistry implements ItemStore, ResetInterface
{
    /** @var array<string, array<string, ?string>> */
    private array $known = [];

    /** @var array<string, array<string, string>> */
    private array $keys = [];

    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /** @return array<string, ItemState> */
    public function load(string $catalog): array
    {
        /** @var list<array{itemKey: string, sku: string, advertisementId: ?string, fingerprint: ?string, error: ?string, errorDetails: ?array<string, mixed>, syncedAt: ?\DateTimeImmutable}> $rows */
        $rows = $this->em->createQuery(\sprintf('SELECT i.itemKey, i.sku, i.advertisementId, i.fingerprint, i.error, i.errorDetails, i.syncedAt FROM %s i WHERE i.catalog = :catalog', Item::class))
            ->setParameter('catalog', $catalog)
            ->getArrayResult();

        $states = [];
        $this->known[$catalog] = [];
        $this->keys[$catalog] = [];
        foreach ($rows as $row) {
            $states[$row['itemKey']] = new ItemState($row['itemKey'], $row['sku'], $row['advertisementId'], $row['fingerprint'], $row['error'], $row['errorDetails'], $row['syncedAt']);
            $this->known[$catalog][$row['itemKey']] = $row['fingerprint'];
            $this->keys[$catalog][$row['sku']] = $row['itemKey'];
        }

        return $states;
    }

    public function save(string $catalog, ItemState $state): void
    {
        $known = \array_key_exists($state->key, $this->known[$catalog] ?? []);
        $answered = $known && $this->known[$catalog][$state->key] !== $state->fingerprint;
        $this->known[$catalog][$state->key] = $state->fingerprint;
        $this->keys[$catalog][$state->sku] = $state->key;

        if (!$known) {
            $item = new Item($catalog, $state);
            $this->em->persist($item);
            $this->em->flush();
            $this->em->detach($item);

            return;
        }

        if ($answered) {
            $this->em->createQuery(\sprintf('DELETE FROM %s s WHERE IDENTITY(s.item) IN (SELECT i.id FROM %s i WHERE i.catalog = :catalog AND i.itemKey = :key)', GitSource::class, Item::class))
                ->setParameter('catalog', $catalog)
                ->setParameter('key', $state->key)
                ->execute();
        }

        $this->em->createQuery(\sprintf('UPDATE %s i SET i.sku = :sku, i.advertisementId = :advertisement, i.fingerprint = :fingerprint, i.error = :error, i.errorDetails = :details, i.syncedAt = :synced WHERE i.catalog = :catalog AND i.itemKey = :key', Item::class))
            ->setParameter('sku', $state->sku)
            ->setParameter('advertisement', $state->advertisementId)
            ->setParameter('fingerprint', $state->fingerprint)
            ->setParameter('error', $state->error)
            ->setParameter('details', $state->errorDetails, Types::JSON)
            ->setParameter('synced', $state->syncedAt, Types::DATETIME_IMMUTABLE)
            ->setParameter('catalog', $catalog)
            ->setParameter('key', $state->key)
            ->execute();
    }

    /** @param list<string> $keys */
    public function delete(string $catalog, array $keys): void
    {
        $this->em->createQuery(\sprintf('DELETE FROM %s i WHERE i.catalog = :catalog AND i.itemKey IN (:keys)', Item::class))
            ->setParameter('catalog', $catalog)
            ->setParameter('keys', $keys, ArrayParameterType::STRING)
            ->execute();

        foreach ($keys as $key) {
            unset($this->known[$catalog][$key]);
        }
        $this->keys[$catalog] = array_diff($this->keys[$catalog] ?? [], $keys);
    }

    /** @param array<mixed> $reported */
    public function sources(array $reported): void
    {
        $answers = [];
        foreach ($reported as $entry) {
            if (!\is_array($entry) || !\is_string($entry['catalog'] ?? null) || !\is_scalar($entry['sku'] ?? null) || !\is_array($entry['source'] ?? null)) {
                continue;
            }
            $key = $this->keys[$entry['catalog']][(string) $entry['sku']] ?? null;
            if (null !== $key) {
                $answers[$entry['catalog']][$key] = $entry['source'];
            }
        }

        foreach ($answers as $catalog => $sources) {
            /** @var list<Item> $items */
            $items = $this->em->createQuery(\sprintf('SELECT i FROM %s i WHERE i.catalog = :catalog AND i.itemKey IN (:keys)', Item::class))
                ->setParameter('catalog', $catalog)
                ->setParameter('keys', array_map('strval', array_keys($sources)), ArrayParameterType::STRING)
                ->getResult();
            $held = [];
            foreach ($this->em->createQuery(\sprintf('SELECT s FROM %s s WHERE s.item IN (:items)', GitSource::class))->setParameter('items', $items)->getResult() as $source) {
                $held[spl_object_id($source->getItem())] = $source;
            }

            foreach ($items as $item) {
                $source = $held[spl_object_id($item)] ?? new GitSource($item);
                $source->absorb($sources[$item->getKey()]);
                $this->em->persist($source);
            }
        }

        $this->em->flush();
    }

    /** @return list<Item> */
    public function failed(): array
    {
        return $this->em->createQuery(\sprintf('SELECT i FROM %s i WHERE i.error IS NOT NULL ORDER BY i.catalog, i.sku', Item::class))->getResult();
    }

    /** @return list<GitSource> */
    public function sourced(): array
    {
        return $this->em->createQuery(\sprintf('SELECT s, i FROM %s s JOIN s.item i ORDER BY i.catalog, i.sku', GitSource::class))->getResult();
    }

    public function reset(): void
    {
        $this->known = [];
        $this->keys = [];
    }
}
