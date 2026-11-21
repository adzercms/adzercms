<?php

declare(strict_types=1);

namespace AdzerCMS\Builder;

use AdzerCMS\Builder\Entity\ProjectVersion;
use AdzerCMS\Contracts\Ui\SiteLink;
use AdzerCMS\Core\Ui\SiteNav;
use AdzerCMS\Pages\Site;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class VersionShow
{
    public const PATH = 'path';
    public const TEMPLATE = '@Pages/page.html.twig';

    public function __construct(
        private Site $site,
        private ProjectAlbums $albums,
        private UrlGeneratorInterface $urls,
    ) {
    }

    /**
     * @param array<string, int|string> $parameters
     *
     * @return array{title: string, description: string, heading: ?string, tabs: list<array{label: string, href: string, active: bool}>, blocks: list<array{template: string, key: string, id: string, values: array<string, mixed>, data: array<string, mixed>, level: int}>, style: string, schema: list<array<string, mixed>>}
     */
    public function view(Request $request, ProjectVersion $version, string $path, string $route, array $parameters): array
    {
        $pages = $version->getPages();
        $page = array_find($pages, static fn (array $one): bool => $one['path'] === '/'.$path) ?? throw new NotFoundHttpException();
        SiteNav::replace($request, array_map(fn (array $one): SiteLink => new SiteLink(
            $one['title'],
            $this->urls->generate($route, $parameters + [self::PATH => ltrim($one['path'], '/')]),
            $one === $page,
        ), $pages));
        $blocks = array_map(static fn (int $number, array $block): VersionBlock => new VersionBlock($number + 1, $block['type'], $block['values'], $block['style']), array_keys($page['blocks']), $page['blocks']);

        return $this->site->sheet($page['title'], $blocks, $this->albums->ofVersion($version));
    }
}
