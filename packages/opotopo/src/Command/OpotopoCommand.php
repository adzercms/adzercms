<?php

declare(strict_types=1);

namespace AdzerCMS\Opotopo\Command;

use AdzerCMS\Opotopo\Export;
use AdzerCMS\Opotopo\OpotopoSettings;
use Opotopo\SellerApi\ApiException;
use Psr\Http\Client\ClientExceptionInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Lock\LockFactory;

#[AsCommand(name: 'adzercms:opotopo', description: 'Exports the catalogs of the modules to OPOTOPO as seller listings through the Seller API')]
final class OpotopoCommand
{
    private const LOCK = 'adzercms.opotopo';

    public function __construct(
        private readonly OpotopoSettings $settings,
        private readonly Export $export,
        private readonly LockFactory $locks,
    ) {
    }

    public function __invoke(
        OutputInterface $output,
        #[Option('Send every item, unchanged ones too')] bool $full = false,
        #[Option('Only the catalog of this name')] ?string $catalog = null,
    ): int {
        $config = $this->settings->config();
        if (null === $config) {
            $output->writeln(\sprintf('not configured: %s and %s', OpotopoSettings::URL, OpotopoSettings::KEY));

            return Command::FAILURE;
        }

        if (null !== $catalog && !isset($this->export->named()[$catalog])) {
            $output->writeln(\sprintf('unknown catalog %s, catalogs: %s', $catalog, implode(', ', array_keys($this->export->named())) ?: 'none'));

            return Command::FAILURE;
        }

        $lock = $this->locks->createLock(self::LOCK);
        if (!$lock->acquire()) {
            return Command::SUCCESS;
        }

        try {
            $report = $this->export->run($config, $catalog, $full);
        } catch (ApiException|ClientExceptionInterface $failure) {
            $output->writeln(\sprintf('OPOTOPO unavailable: %s', $failure->getMessage()));

            return Command::FAILURE;
        }

        $output->writeln(\sprintf('created %d, updated %d, unchanged %d, archived %d', $report->created, $report->updated, $report->skipped, $report->archived));
        foreach ($report->failures as $failure) {
            $output->writeln(\sprintf('refused %s %s: %s %s', $failure['catalog'], $failure['sku'], $failure['error'], json_encode($failure['details'], \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR)));
        }

        return Command::SUCCESS;
    }
}
