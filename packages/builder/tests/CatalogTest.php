<?php

declare(strict_types=1);

namespace AdzerCMS\Builder\Tests;

use AdzerCMS\Builder\ProjectAccess;
use AdzerCMS\Contracts\Seo\Page;
use AdzerCMS\Core\Moderation\Decision;
use AdzerCMS\Core\Seo\PageMap;
use AdzerCMS\Core\Tests\Accounts;
use AdzerCMS\Core\Tests\Browser;
use AdzerCMS\Core\Tests\SchemaTestCase;
use AdzerCMS\Core\Ui\SiteNav;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DomCrawler\Crawler;

final class CatalogTest extends SchemaTestCase
{
    use Accounts;
    use Browser;
    use Templates;

    #[Group('ACMS-BLD-0015')]
    #[Group('ACMS-US-0079')]
    public function testVisitorPicksOpenTemplateWithApprovedVersionNewOnesFirst(): void
    {
        $author = $this->record('79990000002', 'author@example.org');
        $this->version($this->home($author, 'Bakery', ProjectAccess::Open)->getProject(), Decision::Published);
        $this->version($this->home($author, 'Link', ProjectAccess::Link)->getProject(), Decision::Published);
        $this->version($this->home($author, 'Draft', ProjectAccess::Open)->getProject(), Decision::Pending);
        $withdrawn = $this->home($author, 'Withdrawn', ProjectAccess::Open)->getProject();
        $this->version($withdrawn, Decision::Published);
        $this->version($withdrawn, Decision::Withdrawn);
        $fiber = $this->home($author, 'Fiber', ProjectAccess::Open)->getProject();
        $this->version($fiber, Decision::Published);

        $catalog = $this->client->request('GET', '/templates');
        self::assertResponseIsSuccessful();
        self::assertSame([['Fiber', '/templates/'.$fiber->getToken()], ['Bakery', null]], $catalog->filter('[data-template]')->each(static fn (Crawler $card, int $index): array => [trim($card->filter('.offer-title')->text()), 0 === $index ? $card->filter('a')->attr('href') : null]), 'the catalog shows open projects with an approved version, the newest approval first, a card leading to the show');
        self::assertStringNotContainsString('author@example.org', $catalog->filter('.site-main')->html(), 'the catalog does not name the author');
        $this->client->request('GET', (string) $catalog->filter('[data-template] img')->attr('src'));
        self::assertResponseHeaderSame('Content-Type', 'image/webp');

        self::assertArrayHasKey('builder_catalog', self::service(SiteNav::class)->sections(), 'the catalog is a section of the site menu');
        self::assertContains('builder_catalog', array_map(static fn (Page $page): string => $page->route, self::service(PageMap::class)->all()), 'the catalog is a page of the instance');
    }

    #[Group('ACMS-BLD-0024')]
    #[Group('ACMS-UI-0176')]
    #[Group('ACMS-US-0079')]
    public function testCreateSiteOpensWindowOfNewProjectAfterSignInOfGuest(): void
    {
        $this->record('79990000003', 'visitor@example.org');
        $start = fn (): Crawler => $this->client->request('GET', '/templates')->filter('.site-main a[data-record]');

        $guest = $start();
        self::assertSame(['/builder/projects', '/builder/projects'], [$guest->attr('href'), $guest->attr('data-record')], 'without a script the action leads to the projects');
        $this->fragment('/builder/projects');
        self::assertResponseRedirects('/login');
        self::assertCount(1, $this->client->followRedirect()->filter('.modal-header'), 'a guest signs in by the window first');
        $this->client->request('POST', '/login', [
            '_username' => 'visitor@example.org',
            '_password' => self::PASSWORD,
            '_csrf_token' => $this->formToken('authenticate'),
        ], server: ['HTTP_HX-Request' => 'true']);
        self::assertResponseHeaderSame('HX-Redirect', 'http://localhost/builder/projects', 'the sign-in leads to the projects');

        $window = $this->fragment((string) $start()->attr('data-record'));
        self::assertCount(1, $window->filter('.modal-header'));
        self::assertCount(1, $window->filter('form[hx-post="/builder/projects/new"]'), 'a signed in user names the new project in the window');
    }
}
