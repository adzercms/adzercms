<?php

declare(strict_types=1);

namespace AdzerCMS\Opotopo;

use Opotopo\SellerApi\Catalog\CatalogSource;
use Opotopo\SellerApi\Catalog\ItemRecord;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * @phpstan-type Mapping array{
 *     fields?: array<string, string>,
 *     category?: string,
 *     attributes?: array<string, string>,
 *     images?: array{cover?: string, gallery?: string, second?: string},
 *     model3d?: array{url?: string, orientation?: string},
 *     variants?: list<array{name: string, price: string, files?: string, includes_3d_model?: bool}>,
 * }
 */
#[AutoconfigureTag]
interface Catalog extends CatalogSource
{
    public function name(): string;

    /** @return Mapping */
    public function mapping(): array;

    /** @return iterable<ItemRecord> */
    public function records(): iterable;
}
