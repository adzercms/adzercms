<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests;

use Symfony\Bridge\Doctrine\Middleware\Debug\DebugDataHolder;

trait Queries
{
    protected function queriesOf(string $url): int
    {
        $holder = self::service(DebugDataHolder::class, 'doctrine.debug_data_holder');
        $holder->reset();

        $this->client->request('GET', $url);
        self::assertResponseIsSuccessful();

        return array_sum(array_map(\count(...), $holder->getData()));
    }

    /** @param callable(int, int): void $fill */
    protected function assertListingKeepsItsQueries(string $url, callable $fill): void
    {
        $fill(1, 3);
        $this->queriesOf($url);
        $short = $this->queriesOf($url);

        $fill(4, 12);

        self::assertSame($short, $this->queriesOf($url), 'the listing takes its rows by one query, not a query per row');
    }
}
