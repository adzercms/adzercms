<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Schema;

use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Metadata\ExecutedMigrationsList;
use Doctrine\Migrations\Metadata\Storage\MetadataStorage;
use Doctrine\Migrations\Metadata\Storage\TableMetadataStorage;
use Doctrine\Migrations\MigrationsRepository;
use Doctrine\Migrations\Query\Query;
use Doctrine\Migrations\Version\Direction;
use Doctrine\Migrations\Version\ExecutionResult;
use Doctrine\Migrations\Version\Version;

final readonly class MigrationMarks implements MetadataStorage
{
    public function __construct(
        private MetadataStorage $storage,
        private MigrationsRepository $migrations,
    ) {
    }

    public static function of(DependencyFactory $dependencies): self
    {
        return new self(new TableMetadataStorage(
            $dependencies->getConnection(),
            $dependencies->getVersionComparator(),
            $dependencies->getConfiguration()->getMetadataStorageConfiguration(),
            $dependencies->getMigrationRepository(),
        ), $dependencies->getMigrationRepository());
    }

    public function ensureInitialized(): void
    {
        $this->storage->ensureInitialized();

        $available = $this->migrations->getMigrations();
        $versions = [];
        foreach ($available->getItems() as $migration) {
            $versions[MigrationOrder::moment($migration->getVersion())][] = $migration->getVersion();
        }

        $executed = $this->storage->getExecutedMigrations();
        foreach ($executed->getItems() as $mark) {
            $moved = $versions[MigrationOrder::moment($mark->getVersion())] ?? [];
            if ($available->hasMigration($mark->getVersion()) || 1 !== \count($moved)) {
                continue;
            }

            if (!$executed->hasMigration($moved[0])) {
                $this->storage->complete(self::moved($moved[0], $mark->getExecutedAt(), $mark->getExecutionTime()));
            }
            $this->storage->complete(new ExecutionResult($mark->getVersion(), Direction::DOWN));
        }
    }

    public function getExecutedMigrations(): ExecutedMigrationsList
    {
        return $this->storage->getExecutedMigrations();
    }

    public function complete(ExecutionResult $result): void
    {
        $this->storage->complete($result);
    }

    public function reset(): void
    {
        $this->storage->reset();
    }

    /** @return iterable<Query> */
    public function getSql(ExecutionResult $result): iterable
    {
        return $this->storage->getSql($result);
    }

    private static function moved(Version $version, ?\DateTimeImmutable $executedAt, ?float $time): ExecutionResult
    {
        $result = new ExecutionResult($version, Direction::UP, $executedAt);
        if (null !== $time) {
            $result->setTime($time);
        }

        return $result;
    }
}
