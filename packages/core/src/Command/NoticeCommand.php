<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Command;

use AdzerCMS\Core\Entity\Notice;
use AdzerCMS\Core\Notice\Bounces;
use AdzerCMS\Core\Notice\Channels;
use AdzerCMS\Core\Notice\Notifier;
use AdzerCMS\Core\Notice\Status;
use DirectoryTree\ImapEngine\Exceptions\Exception as ImapException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'adzercms:notices', description: 'Notices: sends the ones deferred by quiet hours once the quiet is over, fails the ones whose letters bounced')]
final class NoticeCommand
{
    public function __construct(private readonly Notifier $notifier, private readonly Channels $channels, private readonly Bounces $bounces)
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
        try {
            $bounced = $this->bounces->collect();
            if (0 !== $bounced) {
                $output->writeln(\sprintf('notices bounced: %d', $bounced));
            }
        } catch (ImapException $failure) {
            $output->writeln(\sprintf('bounces: %s', $failure->getMessage()));
        }

        return Command::SUCCESS;
    }
}
