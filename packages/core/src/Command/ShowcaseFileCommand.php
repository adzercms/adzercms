<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Command;

use AdzerCMS\Contracts\Schedule\Scheduled;
use AdzerCMS\Core\Showcase\Sections;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

#[AsCommand(name: 'adzercms:showcase:file', description: 'Replaces the showcase file named by SHOWCASE_FILE atomically, does nothing without it')]
#[Scheduled('*/10 * * * *')]
final readonly class ShowcaseFileCommand
{
    public const ENV = 'SHOWCASE_FILE';

    public function __construct(
        private Sections $sections,
        #[Autowire(env: self::ENV)] private string $file,
    ) {
    }

    public function __invoke(): int
    {
        if ('' !== $this->file) {
            (new Filesystem())->dumpFile($this->file, $this->sections->json());
        }

        return Command::SUCCESS;
    }
}
