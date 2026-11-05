<?php

declare(strict_types=1);

namespace AdzerCMS\Pages\Tests;

use AdzerCMS\Core\Seo\Indexing;
use AdzerCMS\Core\Setting\Brand;
use AdzerCMS\Core\Tests\Configuration;
use AdzerCMS\Core\Tests\SchemaTestCase;
use AdzerCMS\Pages\Entity\Page;
use AdzerCMS\Pages\Entity\PageBlock;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DomCrawler\Crawler;

final class SiteTest extends SchemaTestCase
{
    use Configuration;

    private const BRAND = 'Village network';

    #[Group('ACMS-PAGE-0003')]
    #[Group('ACMS-PAGE-0006')]
    #[Group('ACMS-PAGE-0007')]
    #[Group('ACMS-PAGE-0011')]
    #[Group('ACMS-PAGE-0013')]
    #[Group('ACMS-PAGE-0015')]
    #[Group('ACMS-PAGE-0016')]
    #[Group('ACMS-US-0070')]
    public function testVisitorSeesPublishedPagesOfBlocksInOrderWithMenuAndSchema(): void
    {
        $this->configure([Brand::TITLE => self::BRAND, Indexing::NAME => '1']);
        $home = $this->page('Home', Page::ROOT, published: true, menu: false);
        $this->block($home, 'hero', ['title' => 'Internet for the village', 'text' => "Fast\nStable", 'button' => 'Plans', 'link' => '/tariffs']);
        $this->block($home, 'retired', ['title' => 'Gone module']);
        $this->block($home, 'advantages', ['title' => 'Why us', 'points' => json_encode([['title' => 'Fiber', 'text' => 'To the house']], \JSON_THROW_ON_ERROR)]);
        $this->block($home, 'questions', ['title' => 'Questions', 'pairs' => json_encode([['question' => 'Is there a trial?', 'answer' => 'A **week** free']], \JSON_THROW_ON_ERROR)]);
        $tariffs = $this->page('Tariffs', '/tariffs', published: true, menu: true, label: 'Plans');
        $this->block($tariffs, 'text', ['text' => 'All plans are *monthly*']);
        $this->page('Draft', '/draft', published: false, menu: true);

        $page = $this->client->request('GET', '/');
        self::assertResponseIsSuccessful();
        self::assertSame(['Internet for the village'], $page->filter('h1')->each(static fn (Crawler $node): string => trim($node->text())), 'the title of the first block is the only H1');
        self::assertSame(['Why us', 'Questions'], $page->filter('h2')->each(static fn (Crawler $node): string => trim($node->text())), 'blocks go in order, a block of a type without its module is not shown');
        self::assertSame('/tariffs', $page->filter('.site-hero a.button')->attr('href'));
        self::assertSame(['Plans'], $page->filter('.site-nav a')->each(static fn (Crawler $node): string => trim($node->text())), 'the menu holds published pages marked for it by their label');
        self::assertCount(1, $page->filter('.site-entry'), 'the header leads into the cabinet');
        self::assertSame('<strong>week</strong>', $page->filter('.site-questions .prose strong')->outerHtml());
        self::assertSame(['WebSite', 'Organization', 'FAQPage'], self::schema($page));

        $page = $this->client->request('GET', '/tariffs');
        self::assertSame(['Tariffs'], $page->filter('h1')->each(static fn (Crawler $node): string => trim($node->text())), 'a first block without a title gives H1 to the page title');
        self::assertSame('page', $page->filter('.site-nav a')->attr('aria-current'));
        self::assertSame(['BreadcrumbList'], self::schema($page));

        foreach (['/draft', '/missing'] as $path) {
            $this->client->request('GET', $path);
            self::assertResponseStatusCodeSame(404, $path);
        }

        $sitemap = (string) $this->client->request('GET', '/sitemap.xml')->text();
        self::assertStringContainsString('/tariffs', $sitemap);
        self::assertStringNotContainsString('/draft', $sitemap);

        $this->em->find(Page::class, $home->getId())?->edit('Home', Page::ROOT, null, false, false, null);
        $this->em->flush();
        $this->client->request('GET', '/');
        self::assertResponseRedirects('/cabinet', null, 'without a published home page the root leads to the cabinet');
    }

    private function page(string $title, string $path, bool $published, bool $menu, ?string $label = null): Page
    {
        $page = new Page($title, $path);
        $page->edit($title, $path, null, $published, $menu, $label);
        $this->em->persist($page);
        $this->em->flush();

        return $page;
    }

    /** @param array<string, string> $values */
    private function block(Page $page, string $type, array $values): void
    {
        $block = new PageBlock($page, $type, $values);
        $block->place((int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM page_blocks'));
        $this->em->persist($block);
        $this->em->flush();
    }

    /** @return list<string> */
    private static function schema(Crawler $page): array
    {
        $graph = json_decode($page->filter('script[type="application/ld+json"]')->text(), true, 16, \JSON_THROW_ON_ERROR);

        return array_column($graph['@graph'], '@type');
    }
}
