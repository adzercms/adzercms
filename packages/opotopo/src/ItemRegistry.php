<?php

declare(strict_types=1);

namespace AdzerCMS\Opotopo;

use AdzerCMS\Opotopo\Entity\Item;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Opotopo\SellerApi\Sync\ItemState;
use Opotopo\SellerApi\Sync\ItemStore;
use Symfony\Contracts\Service\ResetInterface;

final class ItemRegistry implements ItemStore, ResetInterface
{
    /** @var array<string, array<string, true>> */
    private array $known = [];

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
        foreach ($rows as $row) {
            $states[$row['itemKey']] = new ItemState($row['itemKey'], $row['sku'], $row['advertisementId'], $row['fingerprint'], $row['error'], $row['errorDetails'], $row['syncedAt']);
        }
        $this->known[$catalog] = array_fill_keys(array_keys($states), true);

        return $states;
    }

    public function save(string $catalog, ItemState $state): void
    {
        if (!isset($this->known[$catalog][$state->key])) {
            $item = new Item($catalog, $state);
            $this->em->persist($item);
            $this->em->flush();
            $this->em->detach($item);
            $this->known[$catalog][$state->key] = true;

            return;
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
    }

    /** @return list<Item> */
    public function failed(): array
    {
        return $this->em->createQuery(\sprintf('SELECT i FROM %s i WHERE i.error IS NOT NULL ORDER BY i.catalog, i.sku', Item::class))->getResult();
    }

    public function reset(): void
    {
        $this->known = [];
    }
}
