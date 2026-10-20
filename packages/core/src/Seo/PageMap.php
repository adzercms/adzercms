<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Seo;

use AdzerCMS\Contracts\Seo\Page;
use AdzerCMS\Contracts\Seo\Pages;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class PageMap
{
    /** @param iterable<Pages> $declarations */
    public function __construct(
        #[AutowireIterator(Pages::class)]
        private iterable $declarations,
        private UrlGeneratorInterface $urls,
    ) {
    }

    /** @return list<Page> */
    public function all(): array
    {
        $pages = [];
        foreach ($this->declarations as $declaration) {
            array_push($pages, ...$declaration->pages());
        }

        return $pages;
    }

    public function of(string $route): ?Page
    {
        return array_find($this->all(), static fn (Page $page): bool => $route === $page->route || \in_array($route, $page->routes, true));
    }

    /** @return list<string> */
    public function routes(): array
    {
        return array_merge(...array_map(static fn (Page $page): array => [$page->route, ...$page->routes], $this->all()));
    }

    public function path(Page $page): string
    {
        return $this->urls->generate($page->route);
    }

    public function url(Page $page): string
    {
        return $this->urls->generate($page->route, referenceType: UrlGeneratorInterface::ABSOLUTE_URL);
    }

    /** @return list<string> */
    public static function sources(EntityManagerInterface $em, Page $page): array
    {
        return array_map(static fn (string $source): string => $em->getClassMetadata($source)->getTableName(), $page->sources);
    }
}
