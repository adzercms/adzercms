<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Site;

use AdzerCMS\Core\Seo\Indexing;
use AdzerCMS\Core\Tests\Configuration;
use AdzerCMS\Core\Tests\SchemaTestCase;
use AdzerCMS\Core\Tests\SplitHosts;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class HostsTest extends SchemaTestCase
{
    use Configuration;
    use SplitHosts;

    #[Group('ACMS-CORE-1088')]
    #[Group('ACMS-CORE-1089')]
    #[Group('ACMS-CORE-1090')]
    #[Group('ACMS-CORE-1155')]
    public function testRouteAnswersOnHostOfItsRoleAndLinksToTheOtherHostByAbsoluteAddress(): void
    {
        $this->client->request('GET', self::SITE.'/guide');
        self::assertResponseIsSuccessful();
        $this->client->request('GET', self::SITE.'/robots.txt');
        self::assertResponseIsSuccessful();
        $this->client->request('GET', self::CABINET.'/robots.txt');
        self::assertResponseIsSuccessful();
        $this->client->request('GET', self::CABINET.'/vault/7');
        self::assertResponseIsSuccessful();

        $urls = self::service(UrlGeneratorInterface::class);
        self::assertSame(self::SITE.'/guide', $urls->generate('fixture_guide'));
        self::assertSame('/vault/7', $urls->generate('fixture_vault', ['id' => 7]));
        self::assertSame('/robots.txt', $urls->generate(Indexing::ROBOTS));
    }

    /** @return iterable<string, array{string, string, ?string}> */
    public static function strays(): iterable
    {
        yield 'site page asked of the cabinet host' => ['GET', self::CABINET.'/guide/intro?part=2', self::SITE.'/guide/intro?part=2'];
        yield 'cabinet section asked of the site host' => ['HEAD', self::SITE.'/vault/7', self::CABINET.'/vault/7'];
        yield 'address routed on neither host' => ['GET', self::SITE.'/nowhere', null];
        yield 'form sent to the other host' => ['POST', self::CABINET.'/guide', null];
    }

    #[Group('ACMS-CORE-1156')]
    #[DataProvider('strays')]
    public function testAddressUnroutedOnItsHostLeadsToHostRoutingIt(string $method, string $url, ?string $moved): void
    {
        $this->client->request($method, $url);

        if (null === $moved) {
            self::assertResponseStatusCodeSame(404);
        } else {
            self::assertResponseRedirects($moved, 301);
        }
    }

    #[Group('ACMS-CORE-1091')]
    #[Group('ACMS-SEO-0026')]
    public function testOnlySiteHostIsOpenToCrawlers(): void
    {
        $this->configure([Indexing::NAME => '1']);

        $this->client->request('GET', self::CABINET.'/robots.txt');
        self::assertSame("User-agent: *\nDisallow: /\n", $this->client->getResponse()->getContent());
        $this->client->request('GET', self::CABINET.'/sitemap.xml');
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', self::CABINET.'/vault/7');
        self::assertSame(self::CABINET.'/vault/7 noindex', $this->client->getResponse()->getContent());

        $this->client->request('GET', self::SITE.'/robots.txt');
        $robots = (string) $this->client->getResponse()->getContent();
        self::assertStringNotContainsString('Disallow: /vault/', $robots, 'a section of the cabinet host is no path of the site host');
        self::assertStringContainsString('Sitemap: '.self::SITE.'/sitemap.xml', $robots);
        $this->client->request('GET', self::SITE.'/sitemap.xml');
        self::assertStringContainsString('<loc>'.self::SITE.'/guide</loc>', (string) $this->client->getResponse()->getContent());
        $this->client->request('GET', self::SITE.'/guide/intro');
        self::assertSame(self::SITE.'/guide index', $this->client->getResponse()->getContent());
    }
}
