<?php

declare(strict_types=1);

namespace AdzerCMS\Extensions\Command;

use AdzerCMS\Extensions\Metadata;
use AdzerCMS\Extensions\Repository\ExtensionRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'adzercms:extensions', description: 'Refreshes package metadata of the extension catalog from its Composer repositories')]
final class ExtensionsCommand
{
    public function __construct(
        private readonly ExtensionRepository $extensions,
        private readonly Metadata $metadata,
    ) {
    }

    public function __invoke(OutputInterface $output): int
    {
        $lookup = $this->metadata->refresh($this->extensions->ordered());

        $missing = array_keys(array_filter($lookup->packages, static fn (mixed $package): bool => null === $package));
        if ([] !== $missing) {
            $output->writeln(\sprintf('without metadata: %s', implode(', ', $missing)));
        }

        foreach ($lookup->down as $repository) {
            $output->writeln(\sprintf('repository unavailable: %s', $repository));
        }

        return [] === $lookup->down ? Command::SUCCESS : Command::FAILURE;
    }
}
