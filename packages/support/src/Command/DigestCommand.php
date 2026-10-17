<?php

declare(strict_types=1);

namespace AdzerCMS\Support\Command;

use AdzerCMS\Core\Command\Body;
use AdzerCMS\Support\StaffDigest;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'adzercms:digest', description: 'Operator to-do list for the platform staff channel')]
final class DigestCommand
{
    public function __construct(private readonly StaffDigest $digest)
    {
    }

    public function __invoke(
        InputInterface $input,
        OutputInterface $output,
        #[Option('Only tickets not yet announced')] bool $new = false,
        #[Option('Mark the ticket numbers from the request body as announced')] bool $mark = false,
    ): int {
        if ($mark) {
            $output->writeln((string) $this->digest->mark(self::ids(Body::of($input))));

            return Command::SUCCESS;
        }

        $output->writeln(json_encode($this->digest->collect($new), \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR));

        return Command::SUCCESS;
    }

    /** @return list<int> */
    private static function ids(string $body): array
    {
        $decoded = json_decode($body, true);
        $ids = \is_array($decoded) && \is_array($decoded['ids'] ?? null) ? $decoded['ids'] : [];

        return array_values(array_map(intval(...), array_filter($ids, is_numeric(...))));
    }
}
