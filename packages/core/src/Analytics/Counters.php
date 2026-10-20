<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Analytics;

use AdzerCMS\Contracts\Analytics\Counter;
use AdzerCMS\Contracts\Module\Credentials;
use AdzerCMS\Contracts\Ui\ContentSource;
use AdzerCMS\Core\Account\Role;
use AdzerCMS\Core\Module\Switches;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Attribute\AsTwigFunction;

final readonly class Counters implements ContentSource
{
    private const IMPERSONATOR = 'IS_IMPERSONATOR';

    public function __construct(
        private Switches $switches,
        private Security $security,
        private TranslatorInterface $translator,
    ) {
    }

    /** @return list<array{Counter, Credentials}> */
    public function active(): array
    {
        if ($this->security->isGranted(Role::ADMIN) || $this->security->isGranted(self::IMPERSONATOR)) {
            return [];
        }

        return array_values($this->switches->enabled(Counter::class));
    }

    public function sources(Request $request): array
    {
        $sources = [];
        foreach ($this->active() as [$counter]) {
            foreach ($counter->origins() as $directive => $origins) {
                $sources[$directive] = [...$sources[$directive] ?? [], ...$origins];
            }
        }

        return $sources;
    }

    #[AsTwigFunction('consent', isSafe: ['html'])]
    public function consent(string $class = ''): string
    {
        if ([] === $this->active()) {
            return '';
        }

        return \sprintf(
            '<button type="button"%s data-consent-open>%s</button>',
            '' === $class ? '' : \sprintf(' class="%s"', htmlspecialchars($class, \ENT_QUOTES)),
            htmlspecialchars($this->translator->trans('core.consent.settings'), \ENT_QUOTES),
        );
    }
}
