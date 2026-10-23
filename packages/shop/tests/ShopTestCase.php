<?php

declare(strict_types=1);

namespace AdzerCMS\Shop\Tests;

use AdzerCMS\Core\Account\Role;
use AdzerCMS\Core\Entity\User;
use AdzerCMS\Core\Tests\Accounts;
use AdzerCMS\Core\Tests\Browser;
use AdzerCMS\Core\Tests\SchemaTestCase;
use AdzerCMS\Shop\Entity\Category;
use AdzerCMS\Shop\Entity\Product;

abstract class ShopTestCase extends SchemaTestCase
{
    use Accounts;
    use Browser;

    protected ?User $owner = null;

    protected function signInOwner(): void
    {
        $this->owner ??= $this->record('79990000001')->setRole(Role::Admin);
        $this->em->flush();
        $this->client->loginUser($this->owner);
    }

    protected function category(string $name, int $position = 1): Category
    {
        $category = new Category($name, $position);
        $this->em->persist($category);
        $this->em->flush();

        return $category;
    }

    /** @param array<string, string> $variants */
    protected function product(Category $category, string $name, ?string $price, array $variants = [], bool $active = true, int $position = 1): Product
    {
        $product = new Product($category, $name);
        $product->change($category, $name, null, $active);
        $product->place($position);
        $product->vary($price, array_map(
            static fn (int|string $label, string $amount): array => ['id' => null, 'name' => (string) $label, 'price' => $amount],
            array_keys($variants),
            array_values($variants),
        ));
        $this->em->persist($product);
        $this->em->flush();

        return $product;
    }
}
