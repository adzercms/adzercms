<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Module;

use AdzerCMS\Contracts\Module\CoreKind;
use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Core\Entity\ModuleOperation;
use AdzerCMS\Core\Repository\ModuleOperationRepository;
use Doctrine\ORM\EntityManagerInterface;

use function Symfony\Component\Translation\t;

final readonly class Operations
{
    public function __construct(
        private ModuleOperationRepository $operations,
        private EntityManagerInterface $em,
        private Catalog $catalog,
        private Tree $tree,
    ) {
    }

    /** @return list<ModuleAction> */
    public function actions(CatalogRow $row): array
    {
        return match (true) {
            $this->tree->versioned() => [],
            null === $row->installed => [ModuleAction::Install],
            $row->selected && CoreKind::Admin === $row->kind => [ModuleAction::Update],
            $row->selected => [ModuleAction::Update, ModuleAction::Disable],
            default => [ModuleAction::Update, ModuleAction::Enable, ModuleAction::Remove],
        };
    }

    public function refusal(string $package, ModuleAction $action): ?Refusal
    {
        if ($this->tree->versioned()) {
            return new Refusal(t('core.module.refusal.versioned'));
        }
        $row = $this->catalog->row($package);

        return null === $row || !\in_array($action, $this->actions($row), true) ? new Refusal(t('core.module.refusal.action')) : null;
    }

    public function file(string $package, ModuleAction $action): ?Refusal
    {
        $refusal = $this->refusal($package, $action);
        if (null !== $refusal) {
            return $refusal;
        }
        if (null !== $this->operations->open($package)) {
            return new Refusal(t('core.module.refusal.open', ['package' => $package]));
        }

        $this->em->persist(new ModuleOperation($package, $action));
        $this->em->flush();

        return null;
    }
}
