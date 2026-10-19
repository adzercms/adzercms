<?php

declare(strict_types=1);

namespace AdzerCMS\Theme\Tests;

use AdzerCMS\Core\Security\CsrfGuard;
use AdzerCMS\Core\Tests\Browser;
use AdzerCMS\Core\Tests\SchemaTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DomCrawler\Crawler;

final class ListOrderTest extends SchemaTestCase
{
    use Browser;

    #[Group('ACMS-UI-0074')]
    public function testOrderedListDragsByHandleAndPostsThroughItsForm(): void
    {
        $page = $this->client->request('GET', '/fixture/list?q=a&sort=name&order=desc');
        $form = $page->filter('form[hx-post]');

        self::assertSame(
            [0, 1, [['7', 'g'], ['8', 'g']], '/fixture/list/order?q=a', true, $form->attr('id')],
            [
                $page->filter('.sortable-header')->count(),
                $page->filter('th.row-handle')->count(),
                $page->filter('tbody td[data-order-id]')->each(static fn (Crawler $handle): array => [(string) $handle->attr('data-order-id'), (string) $handle->attr('data-order-group')]),
                $form->attr('hx-post'),
                '' !== (string) $form->filter('input[name="'.CsrfGuard::FIELD.'"]')->attr('value'),
                $page->filter('tbody')->attr('data-order'),
            ],
        );
    }
}
