<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Ui;

use AdzerCMS\Contracts\Refusal;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class Intent
{
    private const BAG = 'intent';

    public function __construct(
        private readonly RequestStack $requests,
        private readonly UrlGeneratorInterface $urls,
    ) {
    }

    public function keep(string $url): void
    {
        $local = Back::local($url);

        if (null !== $local) {
            $this->requests->getSession()->set(self::BAG, $local);
        }
    }

    public function keepHere(): void
    {
        $request = $this->requests->getCurrentRequest();

        if (null !== $request) {
            $this->keep(Back::here($request));
        }
    }

    public function detour(Refusal $refusal, string $back): string
    {
        if (null === $refusal->route) {
            return $back;
        }

        $this->keep($back);

        return $this->urls->generate($refusal->route, $refusal->params);
    }

    public function take(): ?string
    {
        $session = $this->requests->getSession();
        $kept = $session->get(self::BAG);
        $session->remove(self::BAG);

        return \is_string($kept) ? Back::local($kept) : null;
    }
}
