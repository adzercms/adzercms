<?php

declare(strict_types=1);

namespace AdzerCMS\Support;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Contracts\Translation\TranslatableInterface;

#[AutoconfigureTag]
interface DigestSection
{
    /** @return list<string> */
    public function lines(): array;

    public function summary(int $count): TranslatableInterface;
}
