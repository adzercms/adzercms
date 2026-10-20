<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Seo;

use AdzerCMS\Core\Entity\Text;
use AdzerCMS\Core\Seo\Indexing;
use AdzerCMS\Core\Tests\Configuration;
use AdzerCMS\Core\Tests\SchemaTestCase;
use PHPUnit\Framework\Attributes\Group;

final class IndexingTest extends SchemaTestCase
{
    use Configuration;

    #[Group('ACMS-SEO-0025')]
    #[Group('ACMS-SEO-0028')]
    public function testClosedInstanceRefusesCrawlers(): void
    {
        $this->client->request('GET', '/robots.txt');
        self::assertSame("User-agent: *\nDisallow: /\n", $this->client->getResponse()->getContent());

        $this->client->request('GET', '/sitemap.xml');
        self::assertResponseStatusCodeSame(404);

        $this->client->request('GET', '/guide/intro');
        self::assertSame('http://localhost/guide noindex', $this->client->getResponse()->getContent());
    }

    #[Group('ACMS-SEO-0024')]
    #[Group('ACMS-SEO-0025')]
    #[Group('ACMS-SEO-0026')]
    #[Group('ACMS-SEO-0027')]
    #[Group('ACMS-SEO-0028')]
    public function testOpenInstanceListsPagesAndClosesServicePaths(): void
    {
        $this->configure([Indexing::NAME => '1']);

        $this->client->request('GET', '/robots.txt');
        $robots = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString("Disallow: /vault/\n", $robots);
        self::assertStringNotContainsString('Disallow: /guide', $robots);
        self::assertStringNotContainsString('Disallow: /shelf/', $robots);
        self::assertStringNotContainsString('Disallow: /setting/', $robots);
        self::assertStringContainsString("Clean-param: utm_source&utm_medium&utm_campaign&utm_content&utm_term&yclid&gclid&fbclid\n\nSitemap: http://localhost/sitemap.xml\n", $robots);

        $this->client->request('GET', '/sitemap.xml');
        self::assertSame(['http://localhost/guide', 'http://localhost/shelf/tales'], $this->sitemap('loc'));
        self::assertSame([], $this->sitemap('lastmod'));

        $this->em->persist(new Text('ru', 'messages', 'fixture.guide', 'Справочник'));
        $this->em->flush();
        $this->client->request('GET', '/sitemap.xml');
        self::assertCount(1, $this->sitemap('lastmod'));

        $this->client->request('GET', '/guide/intro');
        self::assertSame('http://localhost/guide index', $this->client->getResponse()->getContent());
        $this->client->request('GET', '/vault/7');
        self::assertSame('http://localhost/vault/7 index', $this->client->getResponse()->getContent());
        $this->client->request('GET', '/shelf/tales.pdf');
        self::assertSame('http://localhost/shelf/tales index', $this->client->getResponse()->getContent());
        $this->client->request('GET', '/shelf/other.pdf');
        self::assertSame('http://localhost/shelf/other.pdf index', $this->client->getResponse()->getContent());
    }

    /**
     * @phpstan-impure
     *
     * @return list<string>
     */
    private function sitemap(string $element): array
    {
        $xml = new \SimpleXMLElement((string) $this->client->getResponse()->getContent());
        $xml->registerXPathNamespace('s', 'http://www.sitemaps.org/schemas/sitemap/0.9');

        return array_map(strval(...), array_values($xml->xpath('//s:url/s:'.$element) ?: []));
    }
}
