<?php

declare(strict_types=1);

namespace AdzerCMS\Theme\Tests;

use AdzerCMS\Core\Security\CsrfGuard;
use AdzerCMS\Core\Tests\Browser;
use AdzerCMS\Core\Tests\SchemaTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DomCrawler\Crawler;

final class ListOrderTest extends SchemaTestCase
{
    use Browser;

    #[Group('ACMS-UI-0074')]
    #[Group('ACMS-UI-0119')]
    #[DataProvider('states')]
    public function testOrderedListDragsByHandleUntilItsColumnSortsIt(string $query, string $link, bool $dragged): void
    {
        $page = $this->client->request('GET', '/fixture/list?'.$query);
        $forms = $page->filter('form[hx-post]');

        self::assertSame(
            [$link, $dragged ? 1 : 0, $dragged ? [['7', 'g'], ['8', 'g']] : [], $dragged ? [['/fixture/list/order?q=a', true]] : [], $forms->each(static fn (Crawler $form): string => (string) $form->attr('id'))],
            [
                $page->filter('.sortable-header a')->attr('href'),
                $page->filter('th.row-handle')->count(),
                $page->filter('tbody td[data-order-id]')->each(static fn (Crawler $handle): array => [(string) $handle->attr('data-order-id'), (string) $handle->attr('data-order-group')]),
                $forms->each(static fn (Crawler $form): array => [(string) $form->attr('hx-post'), '' !== (string) $form->filter('input[name="'.CsrfGuard::FIELD.'"]')->attr('value')]),
                $page->filter('tbody[data-order]')->each(static fn (Crawler $body): string => (string) $body->attr('data-order')),
            ],
        );
    }

    /** @return iterable<string, array{string, string, bool}> */
    public static function states(): iterable
    {
        yield 'manual' => ['q=a', '/fixture/list?q=a&sort=name&order=asc', true];
        yield 'ascending' => ['q=a&sort=name&order=asc', '/fixture/list?q=a&sort=name&order=desc', false];
        yield 'descending' => ['q=a&sort=name&order=desc', '/fixture/list?q=a', false];
    }
}
