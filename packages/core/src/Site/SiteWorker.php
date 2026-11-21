<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Site;

use AdzerCMS\Contracts\Ui\Fragment;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class SiteWorker implements Fragment
{
    public const ROUTE = 'site_worker';
    public const SCRIPT = '@Core/site/worker.js.twig';

    private const REGISTRATION = '@Core/site/worker.html.twig';

    public function __construct(
        private RequestStack $requests,
        private UrlGeneratorInterface $urls,
    ) {
    }

    public function place(): string
    {
        return self::HEAD;
    }

    public function fragment(?int $subject): ?array
    {
        return Hosts::SITE === $this->requests->getMainRequest()?->attributes->get(Hosts::ROLE)
            ? ['template' => self::REGISTRATION, 'context' => ['worker' => $this->urls->generate(self::ROUTE)]]
            : null;
    }
}
