<?php

declare(strict_types=1);

namespace AdzerCMS\Support\Tests;

use AdzerCMS\Core\Seo\Indexing;
use AdzerCMS\Core\Setting\Brand;
use AdzerCMS\Core\Tests\Accounts;
use AdzerCMS\Core\Tests\Browser;
use AdzerCMS\Core\Tests\Configuration;
use AdzerCMS\Core\Tests\SchemaTestCase;
use AdzerCMS\Support\Entity\HelpArticle;
use AdzerCMS\Support\Entity\HelpCategory;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DomCrawler\Crawler;

use function Symfony\Component\Translation\t;

final class KnowledgeSiteTest extends SchemaTestCase
{
    use Accounts;
    use Browser;
    use Configuration;

    private const BRAND = 'Village network';

    #[Group('ACMS-SEO-0031')]
    #[Group('ACMS-SUP-0031')]
    #[Group('ACMS-SUP-0032')]
    #[Group('ACMS-SUP-0033')]
    #[Group('ACMS-SUP-0034')]
    #[Group('ACMS-SUP-0035')]
    #[Group('ACMS-SUP-0036')]
    #[Group('ACMS-US-0075')]
    public function testVisitorFindsPublishedArticlesInContentsAndReadsThem(): void
    {
        $this->configure([Brand::TITLE => self::BRAND, Indexing::NAME => '1']);
        $domains = $this->category('Domains', 'domain', 'Registration and transfer');
        $billing = $this->category('Payment', 'billing');
        $hidden = $this->category('Hidden', 'hidden');
        $this->article($hidden, 'Draft of nothing', 'draft', false);
        $this->client->request('GET', '/help');
        self::assertResponseStatusCodeSame(404, 'a site without published articles has no contents');

        $add = $this->article($domains, 'How to add a domain', 'add', true, 'Open the **domains** section', ['dns'], 'A site on your own name');
        $this->article($billing, 'Пополнение счёта', 'topup', true, 'Card or SBP');
        $this->article($billing, 'Draft', 'draft', false);

        $contents = $this->client->request('GET', '/help');
        self::assertResponseIsSuccessful();
        self::assertSame(['domain' => ['How to add a domain'], 'billing' => ['Пополнение счёта']], self::sections($contents), 'categories and articles go in order, unpublished ones stay hidden');
        self::assertSame('page', $contents->filter('.site-nav a')->last()->attr('aria-current'));
        self::assertSame(['CollectionPage', 'BreadcrumbList'], self::schema($contents));

        self::assertSame(['billing' => ['Пополнение счёта']], self::sections($this->fragment('/help?q='.rawurlencode('СЧЕТА card'))), 'a query folds case and «ё», every word is required');
        self::assertSame(['domain' => ['How to add a domain']], self::sections($this->fragment('/help?q=DOMAINS')), 'the text is searched too');
        $nothing = $this->fragment('/help?q='.rawurlencode('no such thing'));
        self::assertStringEndsWith('/support/new', (string) $nothing->filter('.help-empty a')->attr('href'));

        $this->client->request('GET', '/help/billing');
        self::assertResponseRedirects('/help#billing', 301);
        foreach (['/help/hidden', '/help/missing', '/help/billing/draft', '/help/domain/missing'] as $path) {
            $this->client->request('GET', $path);
            self::assertResponseStatusCodeSame(404, $path);
        }

        $article = $this->client->request('GET', '/help/domain/add');
        self::assertResponseIsSuccessful();
        self::assertSame(['How to add a domain'], $article->filter('h1')->each(static fn (Crawler $node): string => trim($node->text())));
        self::assertSame([self::BRAND, self::phrase(t('support.help.title')), 'Domains', 'How to add a domain'], $article->filter('.site-crumbs > *:not([aria-hidden])')->each(static fn (Crawler $node): string => trim($node->text())));
        self::assertSame('domains', $article->filter('.prose strong')->text());
        self::assertSame(['dns'], $article->filter('.site-tags li')->each(static fn (Crawler $node): string => $node->text()));
        self::assertSame(['TechArticle', 'BreadcrumbList'], self::schema($article));
        self::assertSame($add->getUpdatedAt()->format(\DATE_ATOM), self::graph($article)[0]['dateModified']);

        $sitemap = (string) $this->client->request('GET', '/sitemap.xml')->html();
        self::assertStringContainsString('/help</loc>', $sitemap);
        self::assertStringContainsString('/help/domain/add</loc>', $sitemap);
        self::assertStringNotContainsString('/help/billing/draft', $sitemap);
    }

    private function category(string $title, string $slug, ?string $description = null): HelpCategory
    {
        $category = new HelpCategory($title, $slug);
        $category->edit($title, $slug, $description);
        $category->place((int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM help_category') + 1);
        $this->em->persist($category);
        $this->em->flush();

        return $category;
    }

    /** @param list<string> $tags */
    private function article(HelpCategory $category, string $title, string $slug, bool $published, string $body = '', array $tags = [], ?string $description = null): HelpArticle
    {
        $article = new HelpArticle($category, $title, $slug, new \DateTimeImmutable('2026-11-01 10:00'));
        $article->edit($category, $title, $slug, $description, $tags, $body, $published, new \DateTimeImmutable('2026-11-02 10:00'));
        $article->place((int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM help_article') + 1);
        $this->em->persist($article);
        $this->em->flush();

        return $article;
    }

    /** @return array<string, list<string>> */
    private static function sections(Crawler $page): array
    {
        $sections = [];
        $page->filter('#help-contents section')->each(static function (Crawler $section) use (&$sections): void {
            $sections[(string) $section->attr('id')] = $section->filter('li a')->each(static fn (Crawler $link): string => trim($link->text()));
        });

        return $sections;
    }

    /** @return list<array<string, mixed>> */
    private static function graph(Crawler $page): array
    {
        return json_decode($page->filter('script[type="application/ld+json"]')->text(), true, 16, \JSON_THROW_ON_ERROR)['@graph'];
    }

    /** @return list<string> */
    private static function schema(Crawler $page): array
    {
        return array_column(self::graph($page), '@type');
    }
}
