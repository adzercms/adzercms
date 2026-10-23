<?php

declare(strict_types=1);

namespace AdzerCMS\Shop;

use Symfony\Contracts\Translation\TranslatableInterface;

final readonly class PaymentChoice
{
    public function __construct(public PaymentMethod $method, public ?string $online, public TranslatableInterface $label)
    {
    }

    public function value(): string
    {
        return null === $this->online ? $this->method->value : $this->method->value.'.'.$this->online;
    }
}
