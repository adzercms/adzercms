<?php

declare(strict_types=1);

namespace AdzerCMS\Support\Tests;

use AdzerCMS\Support\Entity\HelpArticle;
use AdzerCMS\Support\Entity\HelpArticlePath;
use AdzerCMS\Support\Entity\HelpCategory;

trait KnowledgeRecords
{
    private function category(string $title, string $slug, ?string $description = null): HelpCategory
    {
        $category = new HelpCategory($title, $slug);
        $category->edit($title, $slug, $description);
        $category->place((int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM help_category') + 1);
        $this->em->persist($category);
        $this->em->flush();

        return $category;
    }

    /**
     * @param list<string> $tags
     * @param list<string> $paths
     */
    private function article(HelpCategory $category, string $title, string $slug, bool $published, string $body = '', array $tags = [], ?string $description = null, array $paths = []): HelpArticle
    {
        $article = new HelpArticle($category, $title, $slug, new \DateTimeImmutable('2026-11-01 10:00'));
        $article->edit($category, $title, $slug, $description, $tags, $body, $published, new \DateTimeImmutable('2026-11-02 10:00'));
        $article->place((int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM help_article') + 1);
        $this->em->persist($article);
        foreach ($paths as $path) {
            $this->em->persist(new HelpArticlePath($article, $path));
        }
        $this->em->flush();

        return $article;
    }
}
