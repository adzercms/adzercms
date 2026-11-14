<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Command;

use AdzerCMS\Contracts\Schedule\Scheduled;
use AdzerCMS\Core\Seo\IndexNow;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;

#[AsCommand(name: 'adzercms:indexnow', description: 'IndexNow: announces the indexed pages whose content sources changed since the last announcement')]
#[Scheduled('*/10 * * * *')]
final readonly class IndexNowCommand
{
    public function __construct(private IndexNow $indexNow)
    {
    }

    public function __invoke(): int
    {
        $this->indexNow->announce();

        return Command::SUCCESS;
    }
}
