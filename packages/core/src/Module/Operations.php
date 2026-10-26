<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Module;

use AdzerCMS\Contracts\Module\CoreKind;
use AdzerCMS\Contracts\Module\Kind;
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
        $update = $row->updatable ? [ModuleAction::Update] : [];

        return match (true) {
            null === $row->installed => [ModuleAction::Install],
            $row->selected && CoreKind::Admin === $row->kind => $update,
            $row->selected => [...$update, ModuleAction::Disable],
            default => [...$update, ModuleAction::Enable, ModuleAction::Remove],
        };
    }

    /** @param list<CatalogRow> $rows */
    public static function replaced(CatalogRow $row, array $rows): ?string
    {
        if (!$row->kind instanceof Kind || !$row->kind->single()) {
            return null;
        }
        foreach ($rows as $other) {
            if ($other->selected && $other->name !== $row->name && $other->kind === $row->kind) {
                return $other->name;
            }
        }

        return null;
    }

    public function refusal(string $package, ModuleAction $action): ?Refusal
    {
        $row = $this->catalog->row($package);
        if (null === $row || !\in_array($action, $this->actions($row), true)) {
            return new Refusal(t('core.module.refusal.action'));
        }

        return $this->tree->refusal(...($action->toggles() && $row->kind instanceof Kind ? [$row->kind->environment()] : []));
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
