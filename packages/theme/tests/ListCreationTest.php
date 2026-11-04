<?php

declare(strict_types=1);

namespace AdzerCMS\Theme\Tests;

use AdzerCMS\Core\Tests\Browser;
use AdzerCMS\Core\Tests\SchemaTestCase;
use AdzerCMS\Core\Tests\Ui\Fixture\ListController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

final class ListCreationTest extends SchemaTestCase
{
    use Browser;

    /** @return iterable<string, array{string, int, int}> */
    public static function sets(): iterable
    {
        yield 'records' => ['', 1, 0];
        yield 'empty set' => ['?none=1', 0, 1];
        yield 'nothing found' => ['?none=1&q=x', 1, 0];
    }

    #[Group('ACMS-FLOW-0001')]
    #[DataProvider('sets')]
    public function testCreationOfAnEmptySetStandsInPlaceOfItsRows(string $query, int $heading, int $empty): void
    {
        $page = $this->client->request('GET', '/fixture/list/headed'.$query);
        $link = \sprintf('a[href="%s"]', ListController::CREATE);

        self::assertSame([$heading, $empty], [$page->filter('.section-head '.$link)->count(), $page->filter('.empty-actions '.$link)->count()]);
    }
}
