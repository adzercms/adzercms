<?php

declare(strict_types=1);

namespace AdzerCMS\Theme\Tests;

use AdzerCMS\Contracts\Setting\Field;
use AdzerCMS\Contracts\Setting\Type;
use AdzerCMS\Core\Account\Role;
use AdzerCMS\Core\Setting\Brand;
use AdzerCMS\Core\Setting\Layout;
use AdzerCMS\Core\Setting\Settings;
use AdzerCMS\Core\Tests\Accounts;
use AdzerCMS\Core\Tests\Browser;
use AdzerCMS\Core\Tests\SchemaTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\AssetMapper\MappedAsset;
use Symfony\Component\DomCrawler\Crawler;

use function Symfony\Component\Translation\t;

final class FrameTest extends SchemaTestCase
{
    use Accounts;
    use Browser;

    private const FRAME = '/fixture/frame';
    private const FONT = '~url\(["\']?([^"\')]+\.woff2)["\']?\)~';

    #[Group('ACMS-UI-0055')]
    public function testStaticAddressCarriesDigestOfItsContent(): void
    {
        $page = $this->frame();
        $published = self::published();

        $addresses = [
            ...$page->filter('link[rel="stylesheet"], link[rel="preload"]')->each(static fn (Crawler $link): string => (string) $link->attr('href')),
            ...array_values(json_decode($page->filter('script[type="importmap"]')->text(), true, flags: \JSON_THROW_ON_ERROR)['imports']),
        ];
        self::assertNotSame([], $addresses);
        foreach ($addresses as $address) {
            $asset = $published[$address] ?? self::fail(\sprintf('%s is not a mapped asset', $address));
            self::assertNotSame($asset->publicPathWithoutDigest, $address);
        }
    }

    #[Group('ACMS-UI-0056')]
    public function testFontIsPreloadedByAddressItsStylesTake(): void
    {
        $page = $this->frame();
        $published = self::published();

        $taken = [];
        foreach ($page->filter('link[rel="stylesheet"]')->each(static fn (Crawler $link): string => (string) $link->attr('href')) as $style) {
            preg_match_all(self::FONT, (string) $published[$style]->content, $fonts);
            foreach ($fonts[1] as $font) {
                $taken[] = \dirname($style).'/'.$font;
            }
        }
        $preloaded = $page->filter('link[rel="preload"][as="font"]')->each(static fn (Crawler $link): string => (string) $link->attr('href'));
        sort($taken);
        sort($preloaded);

        self::assertNotSame([], $taken);
        self::assertSame($taken, $preloaded);
    }

    #[Group('ACMS-UI-0068')]
    public function testFrameWithoutCabinetCarriesNoCabinetLink(): void
    {
        $admin = $this->record('+7 900 111-22-33')->setRole(Role::Admin);
        $user = $this->record('+7 900 000-00-01');
        $this->em->flush();
        $this->client->loginUser($admin);

        $page = $this->arrive(self::FRAME.'?_switch_user='.$user->getUserIdentifier());

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('impersonating', (string) $page->filter('html')->attr('class'));
        self::assertSame([], $page->filter('a')->reduce(static fn (Crawler $link): bool => '' === trim((string) $link->attr('href')))->each(static fn (Crawler $link): string => $link->outerHtml()));
        self::assertCount(0, $page->filter('form.theme-switch'));
        self::assertSame('/', $page->filter('.header-logo')->attr('href'));
    }

    #[Group('ACMS-UI-0064')]
    public function testTopLayoutPutsSectionsUnderPlaceAboveHeader(): void
    {
        $page = $this->frame();
        self::assertStringContainsString('layout-side', (string) $page->filter('html')->attr('class'));

        self::service(Settings::class)->save(new Field(Brand::LAYOUT, t('core.setting.layout'), Type::Choice, choices: ['top' => t('core.layout.top')]), Layout::Top->value);
        $this->em->flush();
        $page = $this->client->request('GET', self::FRAME);

        self::assertStringContainsString('layout-top', (string) $page->filter('html')->attr('class'));
        self::assertSame(['span', 'header'], $page->filter('.panel-layout > *')->slice(0, 2)->each(static fn (Crawler $node): string => $node->nodeName()));
    }

    private function frame(): Crawler
    {
        $this->client->loginUser($this->record('+7 900 000-00-02'));
        $page = $this->client->request('GET', self::FRAME);
        self::assertResponseIsSuccessful();

        return $page;
    }

    /** @return array<string, MappedAsset> */
    private static function published(): array
    {
        $published = [];
        foreach (self::service(AssetMapperInterface::class)->allAssets() as $asset) {
            $published[$asset->publicPath] = $asset;
        }

        return $published;
    }
}
