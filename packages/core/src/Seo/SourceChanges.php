<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Seo;

use AdzerCMS\Contracts\Seo\Page;
use AdzerCMS\Core\Entity\SourceChange;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;

use function Symfony\Component\Clock\now;

#[AsDoctrineListener(event: Events::onFlush)]
final readonly class SourceChanges
{
    public function __construct(private PageMap $pages)
    {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $em = $args->getObjectManager();
        $uow = $em->getUnitOfWork();
        $tracked = array_flip(array_merge(...array_map(static fn (Page $page): array => PageMap::sources($em, $page), $this->pages->all())));
        $changed = [];
        foreach ([...$uow->getScheduledEntityInsertions(), ...$uow->getScheduledEntityUpdates(), ...$uow->getScheduledEntityDeletions()] as $entity) {
            $source = $em->getClassMetadata($entity::class)->getTableName();
            if (isset($tracked[$source])) {
                $changed[$source] = true;
            }
        }
        if ([] === $changed) {
            return;
        }

        $metadata = $em->getClassMetadata(SourceChange::class);
        $known = [];
        foreach ($em->getRepository(SourceChange::class)->findBy(['source' => array_keys($changed)]) as $change) {
            $known[$change->getSource()] = $change;
        }
        foreach (array_keys($changed) as $source) {
            $change = $known[$source] ?? null;
            if (null === $change) {
                $em->persist($change = new SourceChange($source, now()));
                $uow->computeChangeSet($metadata, $change);
            } else {
                $change->touch(now());
                $uow->recomputeSingleEntityChangeSet($metadata, $change);
            }
        }
    }
}
