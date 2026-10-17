<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Command;

use AdzerCMS\Core\Kernel;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\KernelInterface;

#[AsCommand(name: 'adzercms:cache', description: 'Switches the instance to the cache build of the process and removes builds without processes')]
final class CacheCommand
{
    public function __construct(private readonly KernelInterface $kernel)
    {
    }

    public function __invoke(OutputInterface $output): int
    {
        if (!$this->kernel instanceof Kernel) {
            throw new \LogicException('cache builds are run by the engine kernel only');
        }

        $builds = $this->kernel->builds();
        $build = $this->kernel->cacheBuild();

        if (null !== $build) {
            $builds->switchTo($build);
            $output->writeln(sprintf('instance on build %s', $build));
        }

        foreach ($builds->prune() as $removed) {
            $output->writeln(sprintf('build %s removed', $removed));
        }

        return Command::SUCCESS;
    }
}
