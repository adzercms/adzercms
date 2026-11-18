<?php

declare(strict_types=1);

namespace AdzerCMS\Pages\Tests;

use AdzerCMS\Core\Seo\Indexing;
use AdzerCMS\Core\Setting\Brand;
use AdzerCMS\Core\Site\PublicCache;
use AdzerCMS\Core\Tests\Accounts;
use AdzerCMS\Core\Tests\Configuration;
use AdzerCMS\Core\Tests\SchemaTestCase;
use AdzerCMS\Pages\Entity\MenuEntry;
use AdzerCMS\Pages\Entity\Page;
use AdzerCMS\Pages\Entity\PageBlock;
use AdzerCMS\Pages\Tests\Fixture\Lounge;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DomCrawler\Crawler;

use function Symfony\Component\Clock\now;

final class SiteTest extends SchemaTestCase
{
    use Accounts;
    use Configuration;

    private const BRAND = 'Village network';

    #[Group('ACMS-PAGE-0003')]
    #[Group('ACMS-PAGE-0006')]
    #[Group('ACMS-PAGE-0011')]
    #[Group('ACMS-PAGE-0013')]
    #[Group('ACMS-PAGE-0015')]
    #[Group('ACMS-PAGE-0016')]
    #[Group('ACMS-US-0070')]
    public function testVisitorSeesPublishedPagesOfBlocksInOrderWithSchema(): void
    {
        $this->configure([Brand::TITLE => self::BRAND, Indexing::NAME => '1']);
        $home = $this->page('Home', Page::ROOT, published: true);
        $this->block($home, 'hero', ['title' => 'Internet for the village', 'text' => "Fast\n**Stable**", 'button' => 'Plans', 'link' => '/tariffs']);
        $this->block($home, 'retired', ['title' => 'Gone module']);
        $this->block($home, 'advantages', ['title' => 'Why us', 'points' => json_encode([['title' => 'Fiber', 'text' => 'To the house']], \JSON_THROW_ON_ERROR)]);
        $this->block($home, 'questions', ['title' => 'Questions', 'pairs' => json_encode([['question' => 'Is there a trial?', 'answer' => 'A **week** free']], \JSON_THROW_ON_ERROR)]);
        $tariffs = $this->page('Tariffs', '/tariffs', published: true);
        $this->block($tariffs, 'text', ['text' => 'All plans are *monthly*']);
        $this->page('Draft', '/draft', published: false);

        $page = $this->client->request('GET', '/');
        self::assertResponseIsSuccessful();
        self::assertSame(['Internet for the village'], $page->filter('h1')->each(static fn (Crawler $node): string => trim($node->text())), 'the title of the first block is the only H1');
        self::assertSame(['Why us', 'Questions'], $page->filter('h2')->each(static fn (Crawler $node): string => trim($node->text())), 'blocks go in order, a block of a type without its module is not shown');
        self::assertSame('/tariffs', $page->filter('.site-hero a.button')->attr('href'));
        self::assertSame('<strong>Stable</strong>', $page->filter('.site-hero .site-lead strong')->outerHtml(), 'the first screen text is markup');
        self::assertCount(1, $page->filter('.site-entry'), 'the header leads into the cabinet');
        self::assertSame('<strong>week</strong>', $page->filter('.site-questions .prose strong')->outerHtml());
        self::assertSame(['WebSite', 'Organization', 'FAQPage'], self::schema($page));

        $page = $this->client->request('GET', '/tariffs');
        self::assertSame(['Tariffs'], $page->filter('h1')->each(static fn (Crawler $node): string => trim($node->text())), 'a first block without a title gives H1 to the page title');
        self::assertSame(['BreadcrumbList'], self::schema($page));

        foreach (['/draft', '/missing'] as $path) {
            $this->client->request('GET', $path);
            self::assertResponseStatusCodeSame(404, $path);
        }

        $sitemap = (string) $this->client->request('GET', '/sitemap.xml')->text();
        self::assertStringContainsString('/tariffs', $sitemap);
        self::assertStringNotContainsString('/draft', $sitemap);

        $this->em->find(Page::class, $home->getId())?->edit('Home', Page::ROOT, null, false, null);
        $this->em->flush();
        $this->client->request('GET', '/');
        self::assertResponseRedirects('/cabinet', null, 'without a published home page the root leads to the cabinet');
    }

    #[Group('ACMS-PAGE-0007')]
    #[Group('ACMS-US-0070')]
    #[Group('ACMS-US-0071')]
    public function testMenuHoldsPagesSectionsAndLinksInItsOrder(): void
    {
        $this->page('Home', Page::ROOT, published: true);
        $tariffs = $this->page('Tariffs', '/tariffs', published: true, label: 'Plans');
        $contacts = $this->page('Contacts', '/contacts', published: true);
        $draft = $this->page('Draft', '/draft', published: false);
        $this->menu(
            MenuEntry::ofLink('Plans below', '/#tariffs'),
            MenuEntry::ofPage($tariffs),
            MenuEntry::ofPage($draft),
            MenuEntry::ofSection(Lounge::ROUTE),
            MenuEntry::ofSection('retired_section'),
            MenuEntry::ofPage($contacts),
            MenuEntry::ofLink('Channel', 'https://t.me/village'),
        );

        self::assertSame(
            [
                ['Plans below', '/#tariffs', null, null],
                ['Plans', '/tariffs', null, 'page'],
                [Lounge::LABEL, '/admin/pages', null, null],
                ['Contacts', '/contacts', null, null],
                ['Channel', 'https://t.me/village', '_blank', null],
            ],
            $this->client->request('GET', '/tariffs')->filter('.site-nav a')->each(
                static fn (Crawler $link): array => [trim($link->text()), $link->attr('href'), $link->attr('target'), $link->attr('aria-current')],
            ),
            'items go in the menu order: a page by its menu label or title, a declared section, a link, an https link in a new tab; an unpublished page and an undeclared section stay hidden',
        );
    }

    #[Group('ACMS-PAGE-0021')]
    #[Group('ACMS-US-0070')]
    public function testPagesOfOneSectionAreTabsUnderTheTitle(): void
    {
        $offer = $this->page('Public offer', '/offer', published: true, label: 'Offer');
        $this->page('Appendix 1', '/offer/tariffs', published: true, label: 'Tariffs');
        $this->page('Appendix 2', '/offer/rules', published: true);
        $this->page('Draft appendix', '/offer/draft', published: false);
        $this->page('Offers', '/offers', published: true);
        $tabs = fn (string $path): array => $this->client->request('GET', $path)->filter('.site-title .tabs a')->each(
            static fn (Crawler $tab): array => [trim($tab->text()), (string) $tab->attr('href'), str_contains((string) $tab->attr('class'), 'active')],
        );

        self::assertSame(
            [['Offer', '/offer', false], ['Tariffs', '/offer/tariffs', true], ['Appendix 2', '/offer/rules', false]],
            $tabs('/offer/tariffs'),
            'the section page and its published pages go in the order of the list by the menu label or the title, the open one is current',
        );
        self::assertSame([], $tabs('/offers'), 'a path sharing the start of the section is outside it');

        $this->em->find(Page::class, $offer->getId())?->edit('Public offer', '/offer', null, false, 'Offer');
        $this->em->flush();
        self::assertSame([], $tabs('/offer/tariffs'), 'without the published section page there are no tabs');
    }

    #[Group('ACMS-CORE-1092')]
    #[Group('ACMS-CORE-1093')]
    #[Group('ACMS-US-0070')]
    public function testPageWithoutSessionIsSharedByCachesAndWithSessionIsPrivate(): void
    {
        $home = $this->page('Home', Page::ROOT, published: true);
        $this->block($home, 'hero', ['title' => 'Internet for the village', 'button' => 'Plans', 'link' => '#lead']);

        $this->client->request('GET', '/');
        $headers = $this->client->getResponse()->headers;
        self::assertSame([], $headers->getCookies(), 'a page without a session cookie opens no session');
        self::assertTrue($headers->hasCacheControlDirective('public'));
        self::assertSame(['HX-Request'], $this->client->getResponse()->getVary());
        self::assertSame(
            [PublicCache::MAX_AGE, PublicCache::STALE_WHILE_REVALIDATE, PublicCache::STALE_IF_ERROR],
            array_map(static fn (string $directive): int => (int) $headers->getCacheControlDirective($directive), ['max-age', 'stale-while-revalidate', 'stale-if-error']),
        );

        $this->client->request('GET', '/missing');
        self::assertFalse($this->client->getResponse()->headers->hasCacheControlDirective('public'), 'a refusal is not cached');

        $this->client->loginUser($this->record('79990000010'));
        $this->client->request('GET', '/');
        self::assertTrue($this->client->getResponse()->headers->hasCacheControlDirective('private'), 'a page that opened the session stays private');
    }

    #[Group('ACMS-UI-0152')]
    #[Group('ACMS-UI-0170')]
    #[Group('ACMS-US-0070')]
    #[Group('ACMS-US-0071')]
    public function testFooterCarriesTheOwnerTextAndTheBrandOfItsYears(): void
    {
        $this->configure([Brand::TITLE => self::BRAND]);
        $this->page('Home', Page::ROOT, published: true);
        $year = (int) now()->format('Y');
        $mark = sprintf('© %d %s', $year, self::BRAND);
        $footer = fn (): Crawler => $this->client->request('GET', '/')->filter('.site-footer');

        self::assertSame($mark, trim($footer()->text()), 'without the owner text and the first year the footer is the brand of the year');

        $this->configure([Brand::SINCE => (string) $year]);
        self::assertSame($mark, trim($footer()->text()), 'the first year of the current one is the year alone');

        $this->configure([Brand::SINCE => (string) ($year - 2), Brand::FOOTER => '[Legal information](/offer)']);
        self::assertSame('/offer', $footer()->filter('.prose a')->attr('href'));
        self::assertStringEndsWith(sprintf('© %d–%d %s', $year - 2, $year, self::BRAND), trim($footer()->text()), 'an earlier first year opens the years of the brand');
    }

    #[Group('ACMS-UI-0169')]
    #[Group('ACMS-US-0071')]
    public function testSitePageTitleEndsWithTheEndingOfTheSite(): void
    {
        $this->page('Home', Page::ROOT, published: true);
        $titles = function (): array {
            $page = $this->client->request('GET', '/');

            return [$page->filter('title')->text(), $page->filter('meta[property="og:title"]')->attr('content')];
        };

        self::assertSame(['Home', 'Home'], $titles(), 'without the ending the title is the page title');
        $this->configure([Brand::TITLE_ENDING => '| VILLAGE.NET']);
        self::assertSame(['Home | VILLAGE.NET', 'Home'], $titles(), 'the ending follows the title after a space, the link card keeps the page title');
    }

    #[Group('ACMS-UI-0154')]
    #[Group('ACMS-US-0070')]
    public function testHeaderLeadsToCabinetOfAnotherSiteInNewTab(): void
    {
        $this->configure([Brand::CABINET_URL => 'https://cabinet.example.test/']);
        $this->page('Home', Page::ROOT, published: true);

        $entry = $this->client->request('GET', '/')->filter('.site-entry');
        self::assertSame(['https://cabinet.example.test/', '_blank'], [$entry->attr('href'), $entry->attr('target')]);
    }

    private function page(string $title, string $path, bool $published, ?string $label = null): Page
    {
        $page = new Page($title, $path);
        $page->edit($title, $path, null, $published, $label);
        $this->em->persist($page);
        $this->em->flush();

        return $page;
    }

    private function menu(MenuEntry ...$entries): void
    {
        foreach (array_values($entries) as $position => $entry) {
            $entry->place($position);
            $this->em->persist($entry);
        }
        $this->em->flush();
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
