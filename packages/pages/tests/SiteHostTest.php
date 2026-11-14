<?php

declare(strict_types=1);

namespace AdzerCMS\Pages\Tests;

use AdzerCMS\Core\Account\Role;
use AdzerCMS\Core\Tests\Accounts;
use AdzerCMS\Core\Tests\Browser;
use AdzerCMS\Core\Tests\SchemaTestCase;
use AdzerCMS\Core\Tests\SplitHosts;
use AdzerCMS\Pages\Entity\Page;
use AdzerCMS\Pages\Repository\PageRepository;
use PHPUnit\Framework\Attributes\Group;

use function Symfony\Component\Translation\t;

final class SiteHostTest extends SchemaTestCase
{
    use Accounts;
    use Browser;
    use SplitHosts;

    private const LIST = '/admin/pages';

    #[Group('ACMS-PAGE-0002')]
    #[Group('ACMS-CORE-1089')]
    #[Group('ACMS-US-0071')]
    public function testPageTakesAddressOfCabinetSectionOnSiteHost(): void
    {
        $this->client->loginUser($this->record('79990000001')->setRole(Role::Admin));
        $this->em->flush();

        $refused = $this->post(self::LIST.'/new', ['title' => 'Pictures', 'path' => '/pages/pictures/hero']);
        self::assertSame(self::phrase(t('pages.refusal.path_route', ['path' => '/pages/pictures/hero'])), trim($refused->filter('.field-refusal')->text()));

        $this->post(self::LIST.'/new', ['title' => 'Profile', 'path' => '/profile']);
        $page = self::service(PageRepository::class)->findOneBy(['path' => '/profile']) ?? self::fail('a section of the cabinet host leaves its address free on the site host');
        $page->edit('Profile', '/profile', null, true, false, null);
        $this->em->flush();

        $visitor = $this->browser('Visitor', '203.0.113.7');
        self::assertSame('Profile', trim($visitor->request('GET', self::SITE.'/profile')->filter('.site-main h1')->text()));
        $visitor->request('GET', self::CABINET.'/profile');
        self::assertSame(self::CABINET.'/login', $visitor->getResponse()->headers->get('Location'), 'the cabinet host keeps its section');
    }

    #[Group('ACMS-UI-0117')]
    #[Group('ACMS-CORE-1155')]
    #[Group('ACMS-US-0070')]
    public function testSiteFrameLeadsToSiteRootAndCabinetOnTheirHosts(): void
    {
        $home = new Page('Home', Page::ROOT);
        $home->edit('Home', Page::ROOT, null, true, false, null);
        $this->em->persist($home);
        $this->em->flush();

        $page = $this->browser('Visitor', '203.0.113.7')->request('GET', self::SITE.'/');
        self::assertSame('/', $page->filter('.header-logo')->attr('href'));
        self::assertSame(self::CABINET.'/login', $page->filter('.site-entry')->attr('href'));

        $this->client->loginUser($this->record('79990000001')->setRole(Role::Admin));
        $this->em->flush();
        $preview = $this->client->request('GET', self::LIST.'/'.$home->getId().'/preview');
        self::assertSame(self::SITE.'/', $preview->filter('.header-logo')->attr('href'));

        $this->client->request('GET', self::CABINET.'/');
        self::assertResponseRedirects('/cabinet', null, 'the root of the cabinet host stays the cabinet');
    }
}
