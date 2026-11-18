<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Ui;

use AdzerCMS\Contracts\Ui\MenuItem;
use AdzerCMS\Contracts\Ui\SiteLink;
use AdzerCMS\Contracts\Ui\SiteMenu;
use AdzerCMS\Contracts\Ui\SiteSections;
use AdzerCMS\Core\Ui\SiteNav;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Translation\IdentityTranslator;

use function Symfony\Component\Translation\t;

final class SiteNavTest extends TestCase
{
    /** @return iterable<string, array{list<SiteMenu>, list<string>}> */
    public static function menus(): iterable
    {
        yield 'sections by weight without a menu service' => [[], ['Shop /shop', 'Help /help current']];
        yield 'the menu service builds the items from the sections by route' => [[new class implements SiteMenu {
            public function arrange(array $sections): array
            {
                return [new SiteLink('Home', '/'), $sections['help']];
            }
        }], ['Home /', 'Help /help current']];
    }

    /**
     * @param list<SiteMenu> $menus
     * @param list<string>   $shown
     */
    #[Group('ACMS-UI-0168')]
    #[DataProvider('menus')]
    public function testSiteMenuTakesSectionsOfModules(array $menus, array $shown): void
    {
        $sections = new class implements SiteSections {
            public function sections(): array
            {
                return [new MenuItem('help', t('Help'), 200, ['help_article']), new MenuItem('shop', t('Shop'), 100)];
            }
        };
        $routes = new RouteCollection();
        $routes->add('help', new Route('/help'));
        $routes->add('shop', new Route('/shop'));
        $requests = new RequestStack();
        $requests->push(new Request(attributes: ['_route' => 'help_article']));

        $nav = new SiteNav([$sections], $menus, new UrlGenerator($routes, new RequestContext()), $requests, new IdentityTranslator());

        self::assertSame($shown, array_map(static fn (SiteLink $link): string => $link->label.' '.$link->url.($link->current ? ' current' : ''), $nav->items()));
    }
}
