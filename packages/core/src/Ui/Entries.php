<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Ui;

use AdzerCMS\Contracts\Module\CoreKind;
use AdzerCMS\Contracts\Module\Module;
use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Contracts\Ui\AdminEntry;
use AdzerCMS\Contracts\Ui\CabinetEntry;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatableInterface;
use Twig\Attribute\AsTwigFunction;

final readonly class Entries
{
    /** @var list<CoreKind> */
    private array $selected;

    /** @param array<string, class-string> $bundles */
    public function __construct(
        private UrlGeneratorInterface $urls,
        #[Autowire('%kernel.bundles%')]
        array $bundles,
    ) {
        $this->selected = array_values(array_filter(
            [CoreKind::Admin, CoreKind::Cabinet],
            static fn (CoreKind $kind): bool => array_any($bundles, static fn (string $bundle): bool => is_subclass_of($bundle, Module::class) && $kind === $bundle::kind()),
        ));
    }

    /** @param array<string, mixed> $parameters */
    public function url(CabinetEntry|AdminEntry $entry, array $parameters = [], bool $absolute = false): ?string
    {
        if (!\in_array($entry instanceof AdminEntry ? CoreKind::Admin : CoreKind::Cabinet, $this->selected, true)) {
            return null;
        }

        return $this->urls->generate(
            $entry->route(),
            $parameters + ($entry instanceof CabinetEntry ? $entry->parameters() : []),
            $absolute ? UrlGeneratorInterface::ABSOLUTE_URL : UrlGeneratorInterface::ABSOLUTE_PATH,
        );
    }

    public function refusal(TranslatableInterface $reason, CabinetEntry $remedy): Refusal
    {
        return \in_array(CoreKind::Cabinet, $this->selected, true) ? new Refusal($reason, $remedy->route(), $remedy->parameters()) : new Refusal($reason);
    }

    #[AsTwigFunction('home')]
    public function home(): string
    {
        return $this->url(CabinetEntry::Home) ?? '/';
    }

    /** @param array<string, mixed> $parameters */
    #[AsTwigFunction('cabinet')]
    public function link(string $entry, array $parameters = [], bool $absolute = false): ?string
    {
        return $this->url(CabinetEntry::from($entry), $parameters, $absolute);
    }

    /** @param array<string, mixed> $parameters */
    #[AsTwigFunction('admin')]
    public function admin(string $entry, array $parameters = []): ?string
    {
        return $this->url(AdminEntry::from($entry), $parameters);
    }
}
