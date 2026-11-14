<?php

declare(strict_types=1);

namespace AdzerCMS\Support\Help;

use AdzerCMS\Support\Entity\HelpArticle;
use AdzerCMS\Support\Entity\HelpCategory;
use AdzerCMS\Support\HelpView;
use AdzerCMS\Support\Repository\HelpArticleRepository;
use AdzerCMS\Support\Repository\HelpCategoryRepository;

final readonly class Help
{
    public const WINDOW_LIMIT = 15;
    public const REPLY_LIMIT = 8;
    public const SUBJECT_WORD = 3;

    public function __construct(
        private HelpArticleRepository $articles,
        private HelpCategoryRepository $categories,
        private KnowledgeBase $base,
    ) {
    }

    public function enabled(): bool
    {
        return $this->articles->anyPublished();
    }

    public function window(string $query, string $path): HelpAnswer
    {
        $home = $this->base->home();
        $words = KnowledgeBase::words($query);
        if ([] !== $words) {
            return new HelpAnswer(HelpView::Found, $home, $this->links($this->articles->found($words, self::WINDOW_LIMIT)));
        }

        $prefixes = self::prefixes($path);
        $here = [] === $prefixes ? [] : $this->articles->covering($prefixes);
        if ([] !== $here) {
            return new HelpAnswer(HelpView::Page, $home, $this->links($here));
        }

        return new HelpAnswer(HelpView::Categories, $home, array_map(
            fn (HelpCategory $category): HelpLink => new HelpLink($category->getTitle(), $category->getDescription(), $this->base->category($category)),
            $this->categories->shown(),
        ));
    }

    /** @return list<HelpLink> */
    public function reply(string $query, string $subject): array
    {
        $words = KnowledgeBase::words($query);
        if ([] !== $words) {
            return $this->links($this->articles->found($words, self::REPLY_LIMIT));
        }

        $words = array_values(array_filter(KnowledgeBase::words($subject), static fn (string $word): bool => mb_strlen($word) >= self::SUBJECT_WORD));

        return [] === $words ? [] : $this->links($this->articles->related($words, self::REPLY_LIMIT));
    }

    /**
     * @param list<HelpArticle> $articles
     *
     * @return list<HelpLink>
     */
    private function links(array $articles): array
    {
        return array_map(
            fn (HelpArticle $article): HelpLink => new HelpLink($article->getTitle(), $article->getDescription(), $this->base->url($article), $article->getCategory()->getTitle()),
            $articles,
        );
    }

    /** @return list<string> */
    private static function prefixes(string $path): array
    {
        if ('/' === $path) {
            return [$path];
        }

        $prefixes = [];
        $prefix = '';
        foreach (explode('/', $path) as $segment) {
            if ('' !== $segment) {
                $prefix .= '/'.$segment;
                $prefixes[] = $prefix;
            }
        }

        return $prefixes;
    }
}
