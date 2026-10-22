<?php

declare(strict_types=1);

namespace AdzerCMS\Realtime;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Service\ResetInterface;

#[AsDoctrineListener(event: Events::onFlush)]
#[AsDoctrineListener(event: Events::postFlush)]
#[AsEventListener(event: KernelEvents::TERMINATE, method: 'release')]
#[AsEventListener(event: ConsoleEvents::TERMINATE, method: 'release')]
final class LiveChanges implements ResetInterface
{
    private const EVENT = ['event' => 'changed'];

    /** @var array<string, true> */
    private array $channels = [];

    /** @param iterable<LiveEntities> $sources */
    public function __construct(
        private readonly Hub $hub,
        private readonly Channels $names,
        #[AutowireIterator(LiveEntities::class)]
        private readonly iterable $sources,
    ) {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $hub = $this->hub->ready();
        if (null === $hub) {
            return;
        }

        $unit = $args->getObjectManager()->getUnitOfWork();

        foreach ($unit->getScheduledEntityUpdates() as $entity) {
            $this->changed($hub, $entity, array_keys($unit->getEntityChangeSet($entity)));
        }

        foreach ([...$unit->getScheduledEntityInsertions(), ...$unit->getScheduledEntityDeletions()] as $entity) {
            $this->changed($hub, $entity, null);
        }
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        if (!$args->getObjectManager()->getConnection()->isTransactionActive()) {
            $this->release();
        }
    }

    public function release(): void
    {
        if ([] === $this->channels) {
            return;
        }

        $channels = array_keys($this->channels);
        $this->channels = [];
        $this->hub->ready()?->publish($channels, self::EVENT);
    }

    public function reset(): void
    {
        $this->channels = [];
    }

    /** @param list<string>|null $changed */
    private function changed(RealtimeHub $hub, object $entity, ?array $changed): void
    {
        foreach ($this->sources as $source) {
            foreach ($source->fields() as $class => $fields) {
                if (!$entity instanceof $class || (null !== $changed && [] !== $fields && [] === array_intersect($changed, $fields))) {
                    continue;
                }

                $owner = $source->owner($entity);
                if (null !== $owner?->getId()) {
                    $this->channels[$hub->personal($this->names->subject($owner))] = true;
                }
                if ($source->staff()) {
                    $this->channels[$this->names->staff()] = true;
                }
            }
        }
    }
}
