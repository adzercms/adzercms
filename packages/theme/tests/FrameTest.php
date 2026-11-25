<?php

declare(strict_types=1);

namespace AdzerCMS\Theme\Tests;

use AdzerCMS\Contracts\Setting\Field;
use AdzerCMS\Contracts\Setting\Type;
use AdzerCMS\Core\Account\Avatars;
use AdzerCMS\Core\Account\LoginNames;
use AdzerCMS\Core\Setting\Brand;
use AdzerCMS\Core\Setting\Layout;
use AdzerCMS\Core\Setting\Settings;
use AdzerCMS\Core\Tests\Accounts;
use AdzerCMS\Core\Tests\Browser;
use AdzerCMS\Core\Tests\Pictures;
use AdzerCMS\Core\Tests\SchemaTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\AssetMapper\MappedAsset;
use Symfony\Component\DomCrawler\Crawler;

use function Symfony\Component\Translation\t;

final class FrameTest extends SchemaTestCase
{
    use Accounts;
    use Browser;
    use Pictures;

    private const FRAME = '/fixture/frame';
    private const FONT = '~url\(["\']?([^"\')]+\.woff2)["\']?\)~';

    #[Group('ACMS-UI-0055')]
    public function testStaticAddressCarriesDigestOfItsContent(): void
    {
        $page = $this->frame();
        $published = self::published();

        $addresses = [
            ...$page->filter('link[rel="stylesheet"], link[rel="preload"]')->each(static fn (Crawler $link): string => (string) $link->attr('href')),
            ...array_filter(
                array_values(json_decode($page->filter('script[type="importmap"]')->text(), true, flags: \JSON_THROW_ON_ERROR)['imports']),
                static fn (string $address): bool => !str_starts_with($address, 'data:'),
            ),
        ];
        self::assertNotSame([], $addresses);
        foreach ($addresses as $address) {
            $asset = $published[$address] ?? self::fail(\sprintf('%s is not a mapped asset', $address));
            self::assertNotSame($asset->publicPathWithoutDigest, $address);
        }
    }

    #[Group('ACMS-UI-0056')]
    #[Group('ACMS-UI-0171')]
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
        $this->admin('+7 900 111-22-33');
        $user = $this->record('+7 900 000-00-01');

        $page = $this->arrive(self::FRAME.'?_switch_user='.$user->getUserIdentifier());

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('impersonating', (string) $page->filter('html')->attr('class'));
        self::assertSame([], $page->filter('a')->reduce(static fn (Crawler $link): bool => '' === trim((string) $link->attr('href')))->each(static fn (Crawler $link): string => $link->outerHtml()));
        self::assertCount(0, $page->filter('form.theme-switch'));
        self::assertSame('/', $page->filter('.header-logo')->attr('href'));
    }

    /** @return iterable<string, array{?string, ?string, bool, string}> */
    public static function faces(): iterable
    {
        yield 'a photo' => ['ivan', null, true, 'img'];
        yield 'a first name' => ['ivan', 'Petrov', false, 'I'];
        yield 'a last name' => [null, 'petrov', false, 'P'];
        yield 'a login' => [null, null, false, 'U'];
    }

    #[DataProvider('faces')]
    #[Group('ACMS-US-0002')]
    #[Group('ACMS-CORE-1168')]
    #[Group('ACMS-UI-0178')]
    #[Group('ACMS-UI-0179')]
    #[Group('ACMS-UI-0185')]
    public function testHeaderMenuOfRecordOpensByItsAvatar(?string $first, ?string $last, bool $photo, string $face): void
    {
        $user = $this->record('+7 900 000-00-03')->setName($last, $first, null);
        $avatar = $photo ? $this->avatar($user) : null;
        $this->em->flush();
        $this->client->loginUser($user);
        $login = self::service(LoginNames::class)->of($user);

        $menu = $this->client->request('GET', self::FRAME)->filter('.panel-header details.menu.account');

        self::assertCount(1, $menu);
        self::assertSame($login, $menu->filter('summary')->attr('aria-label'), 'the avatar button is named by the login');
        $shown = $menu->filter('summary > .avatar');
        self::assertSame($face, 'img' === $shown->nodeName() ? 'img' : trim($shown->text()));
        if (null !== $avatar) {
            self::assertSame('data:image/webp;base64,'.base64_encode((string) file_get_contents((string) self::service(Avatars::class)->path($avatar))), $shown->attr('src'), 'the page carries the header photo in itself');
        }
        self::assertSame(array_values(array_filter([implode(' ', array_filter([$first, $last])), $login])), $menu->filter('.account-head > *')->each(static fn (Crawler $line): string => trim($line->text())));
        self::assertSame('/logout', $menu->filter('nav > a')->last()->attr('href'));
    }

    #[Group('ACMS-UI-0064')]
    public function testTopLayoutGivesChromeToPlace(): void
    {
        $page = $this->frame();
        self::assertStringContainsString('layout-side', (string) $page->filter('html')->attr('class'));
        self::assertSame('panel-header', $page->filter('.panel-layout > *')->first()->attr('class'));

        self::service(Settings::class)->save(new Field(Brand::LAYOUT, t('core.setting.layout'), Type::Choice, choices: ['top' => t('core.layout.top')]), Layout::Top->value);
        $this->em->flush();
        $page = $this->client->request('GET', self::FRAME);

        self::assertStringContainsString('layout-top', (string) $page->filter('html')->attr('class'));
        self::assertSame('span', $page->filter('.panel-layout > *')->first()->nodeName());
        self::assertCount(0, $page->filter('.panel-header, .panel-sidebar, .bottom-nav'));
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
