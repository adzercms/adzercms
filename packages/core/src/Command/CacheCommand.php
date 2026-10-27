<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Command;

use AdzerCMS\Core\Kernel;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(name: 'adzercms:cache', description: 'Switches the instance to the cache build of the process, the theme preview to it with --preview, and removes builds without processes')]
final class CacheCommand
{
    public function __construct(
        #[Autowire(service: 'kernel')]
        private readonly Kernel $kernel,
    ) {
    }

    public function __invoke(OutputInterface $output, #[Option('Keep the build as the theme preview of the instance')] bool $preview = false): int
    {
        $builds = $this->kernel->builds();
        $build = $this->kernel->cacheBuild();

        if ($preview && null === $build) {
            $output->writeln('a preview takes a cache build');

            return Command::FAILURE;
        }
        if (null !== $build) {
            $builds->switchTo($build);
            if ($preview) {
                $this->kernel->keepPreview();
            } else {
                $this->kernel->keepSelection();
                $this->kernel->preview()->drop();
            }
            $output->writeln(\sprintf($preview ? 'preview on build %s' : 'instance on build %s', $build));
        }

        foreach ($builds->prune() as $removed) {
            $output->writeln(\sprintf('build %s removed', $removed));
        }

        return Command::SUCCESS;
    }
}
