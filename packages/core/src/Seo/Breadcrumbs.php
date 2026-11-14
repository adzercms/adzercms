<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Seo;

use AdzerCMS\Core\Setting\Brand;
use AdzerCMS\Core\Site\Hosts;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @phpstan-type Link array{name: string, url: string}
 */
final readonly class Breadcrumbs
{
    public function __construct(
        private Brand $brand,
        private Hosts $hosts,
        private UrlGeneratorInterface $urls,
    ) {
    }

    /**
     * @param list<Link> $links
     *
     * @return non-empty-list<Link>
     */
    public function trail(array $links): array
    {
        $current = $this->urls->getContext();

        return [['name' => $this->brand->title(), 'url' => Hosts::url($this->hosts->target(Hosts::SITE, $current) ?? $current, '/')], ...$links];
    }

    /**
     * @param list<Link> $trail
     *
     * @return array<string, mixed>
     */
    public static function schema(array $trail): array
    {
        return [
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_map(
                static fn (array $link, int $index): array => ['@type' => 'ListItem', 'position' => $index + 1, 'name' => $link['name'], 'item' => $link['url']],
                $trail,
                array_keys($trail),
            ),
        ];
    }
}
