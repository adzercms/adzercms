<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Module;

use AdzerCMS\Core\Entity\ModuleRequest;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class Execution
{
    private const ENGINE = 'adzercms/';

    public function __construct(
        private Requests $requests,
        private Tree $tree,
        private Staging $staging,
        private Sources $sources,
        private TranslatorInterface $translator,
    ) {
    }

    public function apply(ModuleRequest $request): ?string
    {
        $package = $request->getPackage();
        $refusal = $this->requests->refusal($package, $request->getAction());
        if (null !== $refusal) {
            return $refusal->reason->trans($this->translator);
        }

        return match ($request->getAction()) {
            ModuleAction::Enable => $this->toggle($package, true),
            ModuleAction::Disable => $this->toggle($package, false),
            ModuleAction::Install => $this->compose(['require', $package]),
            ModuleAction::Update => $this->compose(['update', str_starts_with($package, self::ENGINE) ? self::ENGINE.'*' : $package, '--with-dependencies']),
            ModuleAction::Remove => $this->compose(['remove', $package]),
        };
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

        $failure = $this->tree->refresh([...$this->sources->environment(), $name => $value]);
        if (null === $failure) {
            $this->tree->choose($name, $value);
        }

        return $failure;
    }

    /** @param list<string> $command */
    private function compose(array $command): ?string
    {
        return $this->staging->run($command, $this->sources->environment());
    }
}
