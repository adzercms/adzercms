<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Module;

use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Core\Entity\ModuleRequest;
use AdzerCMS\Core\Repository\ModuleRequestRepository;
use Doctrine\ORM\EntityManagerInterface;

use function Symfony\Component\Translation\t;

final readonly class Requests
{
    public function __construct(
        private ModuleRequestRepository $requests,
        private EntityManagerInterface $em,
        private Tree $tree,
    ) {
    }

    /** @return list<ModuleAction> */
    public function actions(CatalogRow $row): array
    {
        return match (true) {
            $this->tree->versioned() => [],
            null === $row->installed => [ModuleAction::Install],
            $row->selected => [ModuleAction::Update, ModuleAction::Disable],
            default => [ModuleAction::Update, ModuleAction::Enable, ModuleAction::Remove],
        };
    }

    public function file(?CatalogRow $row, ModuleAction $action): ?Refusal
    {
        if ($this->tree->versioned()) {
            return new Refusal(t('core.module.refusal.versioned'));
        }
        if (null === $row || !\in_array($action, $this->actions($row), true)) {
            return new Refusal(t('core.module.refusal.action'));
        }
        if (null !== $this->requests->open($row->name)) {
            return new Refusal(t('core.module.refusal.open', ['package' => $row->name]));
        }

        $this->em->persist(new ModuleRequest($row->name, $action));
        $this->em->flush();

        return null;
    }
}
