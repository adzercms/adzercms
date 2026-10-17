<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Module\Fixture;

use AdzerCMS\Contracts\Module\Kind;
use Symfony\Contracts\Translation\TranslatableInterface;

use function Symfony\Component\Translation\t;

enum Gateway: string implements Kind
{
    case Payment = 'TEST_GATEWAY';

    public function key(): string
    {
        return 'gateway';
    }

    public function label(): TranslatableInterface
    {
        return t('gateway.kind');
    }

    public function environment(): string
    {
        return $this->value;
    }

    public function single(): bool
    {
        return true;
    }
}
