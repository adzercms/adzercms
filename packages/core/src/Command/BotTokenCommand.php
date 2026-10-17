<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Command;

use AdzerCMS\Contracts\Notice\BotGateway;
use AdzerCMS\Core\Notice\Channels;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'adzercms:bot:token', description: 'Token of an enabled messenger bot module for platform service messages')]
final readonly class BotTokenCommand
{
    public function __construct(private Channels $channels)
    {
    }

    public function __invoke(SymfonyStyle $io, #[Argument('Bot module key')] string $bot): int
    {
        $channel = $this->channels->bot($bot);
        if (null === $channel) {
            $io->getErrorStyle()->error(\sprintf('bot "%s" is not enabled', $bot));

            return Command::FAILURE;
        }
        $io->writeln($channel->credentials->get(BotGateway::TOKEN));

        return Command::SUCCESS;
    }
}
