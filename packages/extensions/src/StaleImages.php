<?php

declare(strict_types=1);

namespace AdzerCMS\Extensions;

use AdzerCMS\Extensions\Entity\Extension;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;

#[AsDoctrineListener(event: Events::onFlush)]
#[AsDoctrineListener(event: Events::postFlush)]
final class StaleImages
{
    private const FIELD = 'images';

    /** @var list<string> */
    private array $names = [];

    public function __construct(private readonly ExtensionImages $images)
    {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $work = $args->getObjectManager()->getUnitOfWork();
        $this->names = [];

        foreach ($work->getScheduledEntityUpdates() as $entity) {
            $change = $entity instanceof Extension ? $work->getEntityChangeSet($entity)[self::FIELD] ?? null : null;
            if (null !== $change) {
                $this->names = [...$this->names, ...array_diff(self::names($change[0]), self::names($change[1]))];
            }
        }

        foreach ($work->getScheduledEntityDeletions() as $entity) {
            if ($entity instanceof Extension) {
                $this->names = [...$this->names, ...self::names($work->getOriginalEntityData($entity)[self::FIELD] ?? [])];
            }
        }
    }

    public function postFlush(): void
    {
        $names = $this->names;
        $this->names = [];
        $this->images->drop($names);
    }

    /** @return list<string> */
    private static function names(mixed $value): array
    {
        return \is_array($value) ? array_values(array_filter($value, \is_string(...))) : [];
    }
}
