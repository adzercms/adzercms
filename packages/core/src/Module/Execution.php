<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Module;

use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Core\Entity\ModuleOperation;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class Execution
{
    private const ENGINE = 'adzercms/';

    public function __construct(
        private Operations $operations,
        private Tree $tree,
        private Staging $staging,
        private Sources $sources,
        private TranslatorInterface $translator,
    ) {
    }

    public function apply(ModuleOperation $operation): ?string
    {
        $package = $operation->getPackage();
        $action = $operation->getAction();
        $subject = \sprintf('%s module %s', ucfirst($action->value), $package);

        $outcome = $this->operations->refusal($package, $action) ?? match ($action) {
            ModuleAction::Enable => $this->toggle($subject, $package, true),
            ModuleAction::Disable => $this->toggle($subject, $package, false),
            ModuleAction::Install => $this->compose($subject, ['require', $package]),
            ModuleAction::Update => $this->compose($subject, ['update', str_starts_with($package, self::ENGINE) ? self::ENGINE.'*' : $package, '--with-dependencies']),
            ModuleAction::Remove => $this->compose($subject, ['remove', $package]),
        };

        return $outcome instanceof Refusal ? $outcome->reason->trans($this->translator) : $outcome;
    }

    private function toggle(string $subject, string $package, bool $on): Refusal|string|null
    {
        $registry = Registry::installed();
        $module = $registry->package($package) ?? throw new \LogicException(\sprintf('module package %s is not installed', $package));

        try {
            [$name, $value] = Selection::toggled($registry, Selection::environment(...), $module, $on);
        } catch (\LogicException $refusal) {
            return $refusal->getMessage();
        }

        return $this->tree->refresh($subject, $this->sources->environment(), [$name => $value]);
    }

    /** @param list<string> $command */
    private function compose(string $subject, array $command): Refusal|string|null
    {
        return $this->staging->run($subject, $command, $this->sources->environment());
    }
}
