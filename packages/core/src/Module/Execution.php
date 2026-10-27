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
            ModuleAction::Install => $this->compose($subject, ['require', $package], fn (string $stage): array|string => $this->tree->enabling($stage, $package)),
            ModuleAction::Update => $this->compose($subject, ['update', str_starts_with($package, self::ENGINE) ? self::ENGINE.'*' : $package, '--with-dependencies'], static fn (): array => []),
            ModuleAction::Remove => $this->remove($subject, $package),
            ModuleAction::Preview => $this->preview($package),
        };

        return $outcome instanceof Refusal ? $outcome->reason->trans($this->translator) : $outcome;
    }

    private function toggle(string $subject, string $package, bool $on): Refusal|string|null
    {
        $selection = Selection::switched($package, $on);

        return \is_string($selection) ? $selection : $this->tree->refresh($subject, $this->sources->environment(), $selection);
    }

    private function preview(string $package): ?string
    {
        $selection = Selection::switched($package, true);

        return \is_string($selection) ? $selection : $this->tree->preview($selection);
    }

    private function remove(string $subject, string $package): Refusal|string|null
    {
        $selection = Selection::switched($package, false);

        return \is_string($selection) ? $selection : $this->compose($subject, ['remove', $package], static fn (): array => $selection);
    }

    /**
     * @param list<string>                                     $command
     * @param \Closure(string): (array<string, string>|string) $selection
     */
    private function compose(string $subject, array $command, \Closure $selection): Refusal|string|null
    {
        return $this->staging->run($subject, $command, $this->sources->environment(), $selection);
    }
}
