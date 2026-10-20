<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Module;

use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Core\Entity\ModuleRequest;
use AdzerCMS\Core\Repository\ModuleRequestRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use function Symfony\Component\Translation\t;

final readonly class Requests
{
    public function __construct(
        private ModuleRequestRepository $requests,
        private EntityManagerInterface $em,
        private Catalog $catalog,
        private Tree $tree,
        private TranslatorInterface $translator,
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

    public function file(string $package, ModuleAction $action): ?Refusal
    {
        $refusal = $this->refusal($package, $action);
        if (null !== $refusal) {
            return $refusal;
        }
        if (null !== $this->requests->open($package)) {
            return new Refusal(t('core.module.refusal.open', ['package' => $package]));
        }

        $this->em->persist(new ModuleRequest($package, $action));
        $this->em->flush();

        return null;
    }

    public function apply(ModuleRequest $request): ?string
    {
        $refusal = $this->refusal($request->getPackage(), $request->getAction());
        if (null !== $refusal) {
            return $refusal->reason->trans($this->translator);
        }

        return match ($request->getAction()) {
            ModuleAction::Enable => $this->toggle($request->getPackage(), true),
            ModuleAction::Disable => $this->toggle($request->getPackage(), false),
            default => throw new \LogicException(\sprintf('%s requests are not applied', $request->getAction()->value)),
        };
    }

    private function refusal(string $package, ModuleAction $action): ?Refusal
    {
        if ($this->tree->versioned()) {
            return new Refusal(t('core.module.refusal.versioned'));
        }
        $row = $this->catalog->row($package);

        return null === $row || !\in_array($action, $this->actions($row), true) ? new Refusal(t('core.module.refusal.action')) : null;
    }

    private function toggle(string $package, bool $on): ?string
    {
        $registry = Registry::installed();
        $module = $registry->package($package) ?? throw new \LogicException(\sprintf('module package %s is not installed', $package));

        try {
            [$name, $value] = Selection::toggled($registry, Selection::environment(...), $module, $on);
        } catch (\LogicException $refusal) {
            return $refusal->getMessage();
        }

        $failure = $this->tree->refresh([$name => $value]);
        if (null === $failure) {
            $this->tree->choose($name, $value);
        }

        return $failure;
    }
}
