<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Command;

use AdzerCMS\Core\Kernel;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(name: 'adzercms:cache', description: 'Switches the instance to the cache build of the process, migrating its database first with --migrate, the theme preview to it with --preview; removes builds without processes of every module set with --prune')]
final class CacheCommand
{
    public function __construct(
        #[Autowire(service: 'kernel')]
        private readonly Kernel $kernel,
        #[Autowire(service: 'doctrine_migrations.migrate_command')]
        private readonly Command $migrations,
    ) {
    }

    public function __invoke(
        OutputInterface $output,
        #[Option('Migrate the database before the switch, in the same process')] bool $migrate = false,
        #[Option('Keep the build as the theme preview of the instance')] bool $preview = false,
        #[Option('Remove builds without processes of every module set instead of a switch')] bool $prune = false,
    ): int {
        if ($prune) {
            foreach ([...$this->kernel->currentBuilds(), ...$this->kernel->otherBuilds()] as $builds) {
                foreach ($builds->prune() as $removed) {
                    $output->writeln(\sprintf('build %s removed', $removed));
                }
            }

            return Command::SUCCESS;
        }

        $build = $this->kernel->cacheBuild();
        if (null === $build) {
            $output->writeln('a switch takes a cache build');

            return Command::FAILURE;
        }

        if ($migrate) {
            $input = new ArrayInput(['--allow-no-migration' => true]);
            $input->setInteractive(false);
            $migrated = $this->migrations->run($input, $output);
            if (Command::SUCCESS !== $migrated) {
                return $migrated;
            }
        }

        $this->kernel->builds()->switchTo($build);
        if ($preview) {
            $this->kernel->keepPreview();
        } else {
            $this->kernel->keepSelection();
            $this->kernel->preview()->drop();
        }
        $output->writeln(\sprintf($preview ? 'preview on build %s' : 'instance on build %s', $build));

        return Command::SUCCESS;
    }
}
