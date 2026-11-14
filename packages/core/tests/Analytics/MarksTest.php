<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Analytics;

use AdzerCMS\Core\Analytics\Marks;
use AdzerCMS\Core\Tests\Arrivals;
use AdzerCMS\Core\Tests\Configuration;
use AdzerCMS\Core\Tests\SchemaTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

final class MarksTest extends SchemaTestCase
{
    use Arrivals;
    use Configuration;

    private const CLICK = '4504935055255666687';

    #[Group('ACMS-BILL-0141')]
    #[Group('ACMS-BILL-0142')]
    #[Group('ACMS-BILL-0147')]
    public function testMarksOfAddressStayForTheVisitUntilFreshOnes(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $this->land('/login?utm_source=yandex&utm_medium=cpc&utm_campaign=123&utm_term=vpn&yclid='.self::CLICK, $session);
        $this->land('/login', $session);

        self::assertSame(['source' => 'yandex', 'medium' => 'cpc', 'campaign' => '123', 'term' => 'vpn', 'click' => self::CLICK], self::marks(), 'an arrival without marks does not wipe a known source');

        $this->land('/login?utm_source=vk&utm_campaign=555', $session);

        self::assertSame(['source' => 'vk', 'campaign' => '555'], self::marks(), 'fresh marks override the former ones');
    }

    #[Group('ACMS-BILL-0141')]
    #[Group('ACMS-BILL-0142')]
    public function testLatestTransitionWinsBetweenAddressAndSiteCookie(): void
    {
        $this->configure([Marks::COOKIE => self::SITE_COOKIE]);
        $landing = self::siteMarks(['utm_source' => 'yandex', 'utm_campaign' => '708097471', 'utm_term' => 'хостинг', 'yclid' => self::CLICK]);

        $this->land('/register', cookies: $landing);
        self::assertSame(['source' => 'yandex', 'campaign' => '708097471', 'term' => 'хостинг', 'click' => self::CLICK], self::marks(), 'the site cookie brings its marks');

        $session = new Session(new MockArraySessionStorage());
        $this->land('/login?utm_source=vk&utm_campaign=555', $session, $landing);
        $this->land('/register', $session, $landing);
        self::assertSame('vk', self::marks()['source'] ?? null, 'an address after the site visit overrides its marks');

        $this->land('/register', $session, self::siteMarks(['utm_source' => 'lolz.team', 'utm_medium' => 'referral']));
        self::assertSame('lolz.team', self::marks()['source'] ?? null, 'a cookie changed after the address overrides its marks');

        $this->land('/register', cookies: self::siteMarks(['utm_source' => '(direct)', 'utm_medium' => '(none)']));
        self::assertSame([], self::marks(), 'the (direct) mark is an arrival without marks');
    }

    #[Group('ACMS-BILL-0141')]
    public function testSiteArrivalLeavesMarksInSiteCookieInsteadOfSession(): void
    {
        $this->configure([Marks::COOKIE => self::SITE_COOKIE]);

        $this->client->request('GET', '/guide?utm_source=yandex&utm_term=хостинг&yclid='.self::CLICK.'&page=2');

        $cookies = $this->client->getResponse()->headers->getCookies();
        self::assertSame([self::SITE_COOKIE], array_map(static fn (Cookie $cookie): string => $cookie->getName(), $cookies), 'a site arrival opens no session');
        self::assertSame(['utm_source' => 'yandex', 'utm_term' => 'хостинг', 'yclid' => self::CLICK], json_decode((string) $cookies[0]->getValue(), true, flags: \JSON_THROW_ON_ERROR));
    }

    /** @return array<string, string> */
    private static function marks(): array
    {
        return self::service(Marks::class)->current();
    }
}
