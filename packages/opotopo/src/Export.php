<?php

declare(strict_types=1);

namespace AdzerCMS\Opotopo;

use Nyholm\Psr7\Factory\Psr17Factory;
use Opotopo\SellerApi\ClientConfig;
use Opotopo\SellerApi\Files\FileUploader;
use Opotopo\SellerApi\SellerApiClient;
use Opotopo\SellerApi\Sync\Synchronizer;
use Opotopo\SellerApi\Sync\SyncReport;
use Symfony\Component\Clock\Clock;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\HttpClient\Psr18Client;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class Export
{
    private const BATCH = 100;
    private const TIMEOUT = 60;
    private const MILLIS = 1000;

    /** @param iterable<Catalog> $catalogs */
    public function __construct(
        private HttpClientInterface $http,
        private ItemRegistry $items,
        private FileRegistry $files,
        #[AutowireIterator(Catalog::class)]
        private iterable $catalogs,
    ) {
    }

    public function run(ClientConfig $config, ?string $catalog, bool $full): SyncReport
    {
        $factory = new Psr17Factory();
        $client = new SellerApiClient(
            new Psr18Client($this->http->withOptions(['timeout' => self::TIMEOUT]), $factory, $factory),
            $factory,
            $factory,
            $config,
            static fn (int $millis) => Clock::get()->sleep($millis / self::MILLIS),
        );

        return (new Synchronizer($client, new FileUploader($client, $this->files, $factory), $this->items, self::BATCH))
            ->run($this->named(), $catalog, $full);
    }

    /** @return array<string, Catalog> */
    public function named(): array
    {
        $named = [];
        foreach ($this->catalogs as $catalog) {
            $named[$catalog->name()] = $catalog;
        }

        return $named;
    }
}
