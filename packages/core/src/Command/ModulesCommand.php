<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Command;

use AdzerCMS\Core\Module\Catalog;
use AdzerCMS\Core\Module\Execution;
use AdzerCMS\Core\Module\RequestState;
use AdzerCMS\Core\Repository\ModuleRequestRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsCommand(name: 'adzercms:modules', description: 'Modules: applies requests of the admin panel and snapshots the module catalog of its Composer repositories with verified marks once a day')]
final class ModulesCommand
{
    private const LOCK = 'adzercms.modules';

    public function __construct(
        private readonly Catalog $catalog,
        private readonly Execution $execution,
        private readonly ModuleRequestRepository $queue,
        private readonly EntityManagerInterface $em,
        private readonly LockFactory $locks,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function __invoke(OutputInterface $output): int
    {
        $lock = $this->locks->createLock(self::LOCK);
        if (!$lock->acquire()) {
            return Command::SUCCESS;
        }

        foreach ($this->queue->inState(RequestState::Running) as $interrupted) {
            $interrupted->finish($this->translator->trans('core.module.refusal.interrupted'));
        }
        $this->em->flush();

        $request = $this->queue->next();
        if (null !== $request) {
            $request->start();
            $this->em->flush();
            $request->finish($this->execution->apply($request));
            $this->em->flush();
        }

        $down = $this->catalog->due() ? $this->catalog->refresh() : [];
        foreach ($down as $address) {
            $output->writeln(\sprintf('unavailable: %s', $address));
        }

        return [] === $down ? Command::SUCCESS : Command::FAILURE;
    }
}
