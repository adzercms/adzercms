<?php

declare(strict_types=1);

namespace AdzerCMS\Support\Help;

use AdzerCMS\Contracts\Seo\Page;
use AdzerCMS\Contracts\Seo\Pages;
use AdzerCMS\Support\Controller\KnowledgeBaseController;
use AdzerCMS\Support\Entity\HelpArticle;
use AdzerCMS\Support\Entity\HelpCategory;
use AdzerCMS\Support\Repository\HelpArticleRepository;

use function Symfony\Component\Translation\t;

final readonly class KnowledgePages implements Pages
{
    private const SOURCES = [HelpCategory::class, HelpArticle::class];

    public function __construct(private HelpArticleRepository $articles)
    {
    }

    public function pages(): array
    {
        $articles = $this->articles->contents();
        if ([] === $articles) {
            return [];
        }

        return [
            new Page(KnowledgeBaseController::CONTENTS, t('support.help.title'), sources: self::SOURCES),
            ...array_map(static fn (HelpArticle $article): Page => new Page(
                KnowledgeBaseController::ARTICLE,
                t('support.help.page', ['title' => $article->getTitle()]),
                sources: self::SOURCES,
                parameters: ['category' => $article->getCategory()->getSlug(), 'article' => $article->getSlug()],
            ), $articles),
        ];
    }
}
