<?php

declare(strict_types=1);

namespace AdzerCMS\Support\Help;

use AdzerCMS\Contracts\Ui\Fragment;
use AdzerCMS\Support\Repository\HelpArticleRepository;
use Symfony\Component\HttpFoundation\RequestStack;

final readonly class KnowledgeNav implements Fragment
{
    private const PATH = '/help';

    public function __construct(
        private HelpArticleRepository $articles,
        private RequestStack $requests,
    ) {
    }

    public function place(): string
    {
        return self::SITE_NAV;
    }

    public function fragment(?int $subject): ?array
    {
        if (!$this->articles->anyPublished()) {
            return null;
        }
        $current = (string) $this->requests->getMainRequest()?->getPathInfo();

        return ['template' => '@Support/help/_nav.html.twig', 'context' => ['current' => self::PATH === $current || str_starts_with($current, self::PATH.'/')]];
    }
}
