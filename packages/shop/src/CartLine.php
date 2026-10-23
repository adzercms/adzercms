<?php

declare(strict_types=1);

namespace AdzerCMS\Shop;

use AdzerCMS\Core\Ui\Money;
use AdzerCMS\Shop\Entity\Product;
use AdzerCMS\Shop\Entity\Variant;

final readonly class CartLine
{
    private const GLUE = '.';

    public function __construct(
        public string $key,
        public Product $product,
        public ?Variant $variant,
        public int $quantity,
    ) {
    }

    public static function key(Product $product, ?Variant $variant): string
    {
        return null === $variant ? (string) $product->getId() : $product->getId().self::GLUE.$variant->getId();
    }

    /** @return array{int, ?int} */
    public static function ids(string $key): array
    {
        $ids = explode(self::GLUE, $key, 2);

        return [(int) $ids[0], isset($ids[1]) ? (int) $ids[1] : null];
    }

    public function name(): string
    {
        return null === $this->variant ? $this->product->getName() : $this->product->getName().', '.$this->variant->getName();
    }

    public function price(): string
    {
        return $this->variant?->getPrice() ?? (string) $this->product->getPrice();
    }

    public function sum(): string
    {
        return Money::multiply($this->price(), $this->quantity);
    }
}
