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

    /** @return iterable<string, array{string, string, string}> */
    public static function sets(): iterable
    {
        foreach (['section' => ['headed', '.section-head'], 'page' => ['paged', '.card-head']] as $place => [$list, $title]) {
            yield $place.' records' => [$list, $title, ''];
            yield $place.' empty set' => [$list, $title, '?none=1'];
        }
    }

    #[Group('ACMS-FLOW-0001')]
    #[DataProvider('sets')]
    public function testCreationStaysOnlyInTheTitle(string $list, string $title, string $query): void
    {
        $this->client->loginUser($this->record('+7 900 000-00-03'));
        $page = $this->client->request('GET', '/fixture/list/'.$list.$query);
        $link = \sprintf('a[href="%s"]', ListController::CREATE);

        self::assertSame([1, 1], [$page->filter($title.' '.$link)->count(), $page->filter($link)->count()],
            'the creation action is in the list title only, an empty set does not repeat it');
    }
}
