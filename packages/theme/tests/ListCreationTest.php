<?php

declare(strict_types=1);

namespace AdzerCMS\Theme\Tests;

use AdzerCMS\Core\Tests\Accounts;
use AdzerCMS\Core\Tests\Browser;
use AdzerCMS\Core\Tests\SchemaTestCase;
use AdzerCMS\Core\Tests\Ui\Fixture\ListController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

final class ListCreationTest extends SchemaTestCase
{
    use Accounts;
    use Browser;

    /** @return iterable<string, array{string, string, string, int, int}> */
    public static function sets(): iterable
    {
        foreach (['section' => ['headed', '.section-head'], 'page' => ['paged', '.card-head']] as $place => [$list, $title]) {
            yield $place.' records' => [$list, $title, '', 1, 0];
            yield $place.' empty set' => [$list, $title, '?none=1', 1, 1];
            yield $place.' nothing found' => [$list, $title, '?none=1&q=x', 1, 0];
        }
    }

    #[Group('ACMS-FLOW-0001')]
    #[DataProvider('sets')]
    public function testCreationStaysInTheTitleAndRepeatsUnderAnEmptySet(string $list, string $title, string $query, int $heading, int $empty): void
    {
        $this->client->loginUser($this->record('+7 900 000-00-03'));
        $page = $this->client->request('GET', '/fixture/list/'.$list.$query);
        $link = \sprintf('a[href="%s"]', ListController::CREATE);

        self::assertSame([$heading, $empty], [$page->filter($title.' '.$link)->count(), $page->filter('.empty-actions '.$link)->count()]);
    }
}
