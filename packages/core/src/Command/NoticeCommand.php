<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Command;

use AdzerCMS\Core\Entity\Notice;
use AdzerCMS\Core\Notice\Channels;
use AdzerCMS\Core\Notice\Notifier;
use AdzerCMS\Core\Notice\Status;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'adzercms:notices', description: 'Notices: sends the ones deferred by quiet hours once the quiet is over')]
final class NoticeCommand
{
    public function __construct(private readonly Notifier $notifier, private readonly Channels $channels)
    {
    }

    public function __invoke(OutputInterface $output): int
    {
        foreach ($this->channels->subscribe() as $bot => $failure) {
            $output->writeln(\sprintf('%s: %s', $bot, $failure));
        }
        $released = $this->notifier->release();
        if ([] !== $released) {
            $failed = \count(array_filter($released, static fn (Notice $notice): bool => Status::Failed === $notice->getStatus()));
            $output->writeln(\sprintf('notices sent: %d, failed: %d', \count($released) - $failed, $failed));
        }

        return Command::SUCCESS;
    }
}
