<?php

declare(strict_types=1);

namespace AdzerCMS\Support\Help;

use AdzerCMS\Core\Seo\Breadcrumbs;
use AdzerCMS\Core\Setting\Brand;
use AdzerCMS\Support\Controller\KnowledgeBaseController;
use AdzerCMS\Support\Entity\HelpArticle;
use AdzerCMS\Support\Entity\HelpCategory;
use AdzerCMS\Support\Repository\HelpArticleRepository;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @phpstan-import-type Link from Breadcrumbs
 */
final readonly class KnowledgeBase
{
    private const WORD_BREAK = '/[^\p{L}\p{N}]+/u';

    public function __construct(
        private HelpArticleRepository $articles,
        private UrlGeneratorInterface $urls,
        private Breadcrumbs $breadcrumbs,
        private Brand $brand,
        private TranslatorInterface $translator,
    ) {
    }

    /** @return list<string> */
    public static function words(string $text): array
    {
        $parts = preg_split(self::WORD_BREAK, HelpArticle::fold($text), -1, \PREG_SPLIT_NO_EMPTY);

        return false === $parts ? [] : array_values(array_unique($parts));
    }

    /** @return list<array{category: HelpCategory, articles: non-empty-list<HelpArticle>}> */
    public function contents(string $query): array
    {
        $sections = [];
        foreach ($this->articles->contents(self::words($query)) as $article) {
            $category = $article->getCategory();
            $sections[(int) $category->getId()]['category'] = $category;
            $sections[(int) $category->getId()]['articles'][] = $article;
        }

        return array_values($sections);
    }

    public function home(): string
    {
        return $this->urls->generate(KnowledgeBaseController::CONTENTS, [], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    public function category(HelpCategory $category): string
    {
        return $this->home().'#'.$category->getSlug();
    }

    public function url(HelpArticle $article): string
    {
        return $this->urls->generate(KnowledgeBaseController::ARTICLE, [
            'category' => $article->getCategory()->getSlug(),
            'article' => $article->getSlug(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    /** @return non-empty-list<Link> */
    public function trail(?HelpArticle $article): array
    {
        $links = [['name' => $this->translator->trans('support.help.title'), 'url' => $this->home()]];
        if (null !== $article) {
            $links[] = ['name' => $article->getCategory()->getTitle(), 'url' => $this->category($article->getCategory())];
            $links[] = ['name' => $article->getTitle(), 'url' => $this->url($article)];
        }

        return $this->breadcrumbs->trail($links);
    }

    /**
     * @param list<Link> $trail
     *
     * @return list<array<string, mixed>>
     */
    public function schema(?HelpArticle $article, array $trail): array
    {
        $page = null === $article
            ? ['@type' => 'CollectionPage', 'name' => $this->translator->trans('support.help.title'), 'url' => $this->home()]
            : array_filter([
                '@type' => 'TechArticle',
                'headline' => $article->getTitle(),
                'description' => $article->getDescription(),
                'keywords' => implode(', ', $article->getTags()),
                'dateModified' => $article->getUpdatedAt()->format(\DATE_ATOM),
                'mainEntityOfPage' => $this->url($article),
                'publisher' => array_filter(['@type' => 'Organization', 'name' => $this->brand->title(), 'logo' => $this->brand->logo()]),
            ], static fn (mixed $value): bool => null !== $value && '' !== $value);

        return [$page, Breadcrumbs::schema($trail)];
    }
}
