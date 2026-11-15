<?php

declare(strict_types=1);

namespace AdzerCMS\Shop;

use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Core\Ui\Order;
use AdzerCMS\Core\Ui\Table;
use AdzerCMS\Shop\Entity\Category;
use AdzerCMS\Shop\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;

use function Symfony\Component\Translation\t;

final readonly class Categories
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    /** @return list<array{category: Category, products: int}> */
    public function listing(Table $table): array
    {
        $builder = $this->em->createQueryBuilder()
            ->select('c AS category', 'COUNT(p.id) AS products')
            ->from(Category::class, 'c')
            ->leftJoin(Product::class, 'p', 'WITH', 'p.category = c')
            ->groupBy('c.id');
        $table->total($this->count());
        $table->apply($builder);

        return array_map(
            static fn (array $row): array => ['category' => $row['category'], 'products' => (int) $row['products']],
            $builder->addOrderBy('c.id')->setMaxResults($table->size)->setFirstResult($table->offset())->getQuery()->getResult(),
        );
    }

    public function count(): int
    {
        return (int) $this->em->createQueryBuilder()->select('COUNT(c.id)')->from(Category::class, 'c')->getQuery()->getSingleScalarResult();
    }

    /** @return list<Category> */
    public function all(): array
    {
        return $this->em->getRepository(Category::class)->findBy([], ['position' => 'ASC', 'id' => 'ASC']);
    }

    public function save(?Category $category, string $name): Category|Refusal
    {
        $name = trim($name);
        if ('' === $name || mb_strlen($name) > Category::NAME) {
            return new Refusal(t('shop.refusal.category_name', ['limit' => Category::NAME]));
        }

        if (null === $category) {
            $category = new Category($name, $this->last() + 1);
            $this->em->persist($category);
        } else {
            $category->change($name);
        }
        $this->em->flush();

        return $category;
    }

    /** @param list<int> $moved */
    public function arrange(array $moved): void
    {
        Order::place($this->all(), $moved);
        $this->em->flush();
    }

    public function remove(Category $category): ?Refusal
    {
        if (null !== $this->em->getRepository(Product::class)->findOneBy(['category' => $category])) {
            return new Refusal(t('shop.refusal.category_filled', ['name' => $category->getName()]));
        }

        $this->em->remove($category);
        $this->em->flush();

        return null;
    }

    private function last(): int
    {
        return (int) $this->em->createQueryBuilder()->select('MAX(c.position)')->from(Category::class, 'c')->getQuery()->getSingleScalarResult();
    }
}
