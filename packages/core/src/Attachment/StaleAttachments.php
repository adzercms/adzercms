<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Attachment;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;

#[AsDoctrineListener(event: Events::onFlush)]
#[AsDoctrineListener(event: Events::postFlush)]
final class StaleAttachments
{
    /** @var list<string> */
    private array $paths = [];

    public function __construct(private readonly Attachments $attachments)
    {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $this->paths = [];

        foreach ($args->getObjectManager()->getUnitOfWork()->getScheduledEntityDeletions() as $entity) {
            if ($entity instanceof AttachmentRecord) {
                $this->paths[] = $this->attachments->path($entity);
            }
        }
    }

    public function postFlush(): void
    {
        foreach ($this->paths as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        $this->paths = [];
    }
}
