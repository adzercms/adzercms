<?php

declare(strict_types=1);

namespace AdzerCMS\Theme\Tests;

use AdzerCMS\Core\Tests\SchemaTestCase;
use AdzerCMS\Core\Ui\Entries;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bridge\Doctrine\Middleware\Debug\DebugDataHolder;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\ErrorHandler\ErrorRenderer\ErrorRendererInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ErrorPageTest extends SchemaTestCase
{
    #[Group('ACMS-UI-0153')]
    #[DataProvider('statuses')]
    public function testErrorPageOutsideDebugIsThemePageOfItsStatus(int $status, bool $site): void
    {
        $stack = self::service(RequestStack::class);
        $holder = self::service(DebugDataHolder::class, 'doctrine.debug_data_holder');
        $stack->push(new Request(attributes: ['showException' => false]));
        $holder->reset();
        try {
            $page = new Crawler(self::service(ErrorRendererInterface::class, 'error_renderer.html')->render(new HttpException($status))->getAsString());
        } finally {
            $stack->pop();
        }

        $title = self::service(TranslatorInterface::class)->trans($site ? 'theme.error.title' : 'theme.error.server', ['code' => $status]);
        self::assertSame($title, $page->filter('h1')->text());
        self::assertSame(self::service(Entries::class)->site(), $page->filter('main a')->attr('href'));
        self::assertCount($site ? 1 : 0, $page->filter('header.site-header'));
        if (!$site) {
            self::assertSame(0, array_sum(array_map(\count(...), $holder->getData())), 'a server error page reads no database');
        }
    }

    /** @return iterable<string, array{int, bool}> */
    public static function statuses(): iterable
    {
        yield 'missing page' => [404, true];
        yield 'server failure' => [500, false];
    }
}
