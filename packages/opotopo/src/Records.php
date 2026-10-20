<?php

declare(strict_types=1);

namespace AdzerCMS\Opotopo;

use Opotopo\SellerApi\Catalog\ItemRecord;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class Records
{
    public function __construct(
        #[Autowire('%adzercms.files_dir%')]
        private string $root,
    ) {
    }

    /** @param array<string, mixed> $values */
    public function record(string $key, array $values): ItemRecord
    {
        return new Record($key, $values, $this->root);
    }
}
