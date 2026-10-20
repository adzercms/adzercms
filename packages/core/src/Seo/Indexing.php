<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Seo;

use AdzerCMS\Contracts\Seo\Page;
use AdzerCMS\Contracts\Setting\Field;
use AdzerCMS\Contracts\Setting\Owner;
use AdzerCMS\Contracts\Setting\Type;
use AdzerCMS\Core\Entity\SourceChange;
use AdzerCMS\Core\Setting\Settings;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Twig\Attribute\AsTwigFunction;

use function Symfony\Component\Translation\t;

final readonly class Indexing implements Owner
{
    public const NAME = 'seo.indexing';
    public const ROBOTS = 'robots';
    public const SITEMAP = 'sitemap';

    private const OPEN = ['setting_image', self::ROBOTS, self::SITEMAP];
    private const TRACKING = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'yclid', 'gclid', 'fbclid'];
    private const NAMESPACE = 'http://www.sitemaps.org/schemas/sitemap/0.9';

    public function __construct(
        private EntityManagerInterface $em,
        private PageMap $pages,
        private Settings $settings,
        private RequestStack $requests,
        private RouterInterface $router,
    ) {
    }

    public function settingFields(): array
    {
        return [new Field(self::NAME, t('core.setting.indexing'), Type::Toggle)];
    }

    public function on(): bool
    {
        return $this->settings->on(self::NAME);
    }

    #[AsTwigFunction('page_head')]
    public function head(): Head
    {
        $request = $this->requests->getCurrentRequest() ?? throw new \LogicException('the page head needs a request');
        $route = (string) $request->attributes->get('_route');
        $page = $this->pages->of($route);

        return new Head(
            $page,
            null === $page ? $this->router->generate($route, (array) $request->attributes->get('_route_params'), UrlGeneratorInterface::ABSOLUTE_URL) : $this->pages->url($page),
            $this->on(),
        );
    }

    public function robots(): string
    {
        if (!$this->on()) {
            return "User-agent: *\nDisallow: /\n";
        }

        $lines = ['User-agent: *'];
        foreach ($this->service() as $prefix) {
            $lines[] = 'Disallow: '.$prefix;
        }
        $lines[] = 'Clean-param: '.implode('&', self::TRACKING);
        $lines[] = '';
        $lines[] = 'Sitemap: '.$this->router->generate(self::SITEMAP, referenceType: UrlGeneratorInterface::ABSOLUTE_URL);

        return implode("\n", $lines)."\n";
    }

    public function sitemap(): string
    {
        $xml = new \XMLWriter();
        $xml->openMemory();
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElementNs(null, 'urlset', self::NAMESPACE);
        $pages = $this->pages->all();
        $sources = array_map(fn (Page $page): array => PageMap::sources($this->em, $page), $pages);
        $moments = [];
        foreach ($this->em->getRepository(SourceChange::class)->findBy(['source' => array_values(array_unique(array_merge(...$sources)))]) as $change) {
            $moments[$change->getSource()] = $change->getMoment();
        }
        foreach ($pages as $index => $page) {
            $xml->startElement('url');
            $xml->writeElement('loc', $this->pages->url($page));
            $moment = max([null, ...array_map(static fn (string $source): ?\DateTimeImmutable => $moments[$source] ?? null, $sources[$index])]);
            if (null !== $moment) {
                $xml->writeElement('lastmod', $moment->format(\DATE_ATOM));
            }
            $xml->endElement();
        }
        $xml->endElement();

        return $xml->outputMemory();
    }

    /** @return list<string> */
    private function service(): array
    {
        $open = [...$this->pages->routes(), ...self::OPEN];
        $paths = array_map($this->pages->path(...), $this->pages->all());

        $prefixes = [];
        foreach ($this->router->getRouteCollection() as $name => $route) {
            $prefix = explode('{', $route->getPath(), 2)[0];
            if (!\in_array($name, $open, true) && !array_any($paths, static fn (string $path): bool => str_starts_with($path, $prefix))) {
                $prefixes[] = $prefix;
            }
        }
        sort($prefixes);

        $kept = [];
        foreach ($prefixes as $prefix) {
            if (!array_any($kept, static fn (string $shorter): bool => str_starts_with($prefix, $shorter))) {
                $kept[] = $prefix;
            }
        }

        return $kept;
    }
}
