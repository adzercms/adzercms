<?php

declare(strict_types=1);

namespace AdzerCMS\Opotopo;

use Opotopo\SellerApi\Catalog\CatalogSource;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag]
interface Catalog extends CatalogSource
{
    public function name(): string;
}
