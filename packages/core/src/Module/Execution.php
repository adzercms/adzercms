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
            ModuleAction::Remove => $this->compose($subject, ['remove', $package], $this->selection($package, false)),
        };

        return $outcome instanceof Refusal ? $outcome->reason->trans($this->translator) : $outcome;
    }

    private function toggle(string $subject, string $package, bool $on): Refusal|string|null
    {
        $selection = $this->selection($package, $on);

        return \is_string($selection) ? $selection : $this->tree->refresh($subject, $this->sources->environment(), $selection);
    }

    /**
     * @param list<string>                 $command
     * @param array<string, string>|string $selection
     */
    private function compose(string $subject, array $command, array|string $selection = []): Refusal|string|null
    {
        return \is_string($selection) ? $selection : $this->staging->run($subject, $command, $this->sources->environment(), $selection);
    }

    /** @return array<string, string>|string */
    private function selection(string $package, bool $on): array|string
    {
        $registry = Registry::installed();
        $module = $registry->package($package) ?? throw new \LogicException(\sprintf('module package %s is not installed', $package));
        if ($on === Selection::selects($module, Selection::environment(...))) {
            return [];
        }

        try {
            [$name, $value] = Selection::toggled($registry, Selection::environment(...), $module, $on);
        } catch (\LogicException $refusal) {
            return $refusal->getMessage();
        }

        return [$name => $value];
    }
}
