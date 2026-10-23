<?php

declare(strict_types=1);

namespace AdzerCMS\Shop;

use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Core\Ui\Filter;
use AdzerCMS\Core\Ui\Money;
use AdzerCMS\Core\Ui\Order;
use AdzerCMS\Core\Ui\Table;
use AdzerCMS\Shop\Entity\Category;
use AdzerCMS\Shop\Entity\Photo;
use AdzerCMS\Shop\Entity\Product;
use AdzerCMS\Shop\Entity\Variant;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

use function Symfony\Component\Translation\t;

final readonly class Products
{
    public const CATEGORY = 'category';

    public function __construct(private EntityManagerInterface $em, private Photos $photos)
    {
    }

    /** @return list<Product> */
    public function listing(Table $table): array
    {
        $builder = $this->em->createQueryBuilder()
            ->select('p', 'c', 'v', 'f')
            ->from(Product::class, 'p')
            ->join('p.category', 'c')
            ->leftJoin('p.variants', 'v')
            ->leftJoin('p.photos', 'f');

        $term = $table->term();
        if ('' !== $term) {
            $builder->andWhere(Filter::matching($builder, $term, ['p.name']));
        }
        $category = $table->value(self::CATEGORY);
        if ('' !== $category) {
            $builder->andWhere('c.id = :category')->setParameter('category', (int) $category);
        }

        $table->apply($builder);
        $builder->addOrderBy('p.id')->addOrderBy('v.position')->addOrderBy('f.position');

        return $table->paginate($builder->getQuery());
    }

    /** @return array{products: int, categories: int} */
    public function counts(): array
    {
        return [
            'products' => (int) $this->em->createQueryBuilder()->select('COUNT(p.id)')->from(Product::class, 'p')->getQuery()->getSingleScalarResult(),
            'categories' => (int) $this->em->createQueryBuilder()->select('COUNT(c.id)')->from(Category::class, 'c')->getQuery()->getSingleScalarResult(),
        ];
    }

    /**
     * @param array<array-key, mixed> $variants
     * @param list<UploadedFile>      $uploads
     * @param list<int>               $hidden
     */
    public function save(
        ?Product $product,
        ?Category $category,
        string $name,
        string $description,
        string $price,
        array $variants,
        bool $active,
        array $uploads,
        array $hidden,
    ): Product|Refusal {
        $name = trim($name);
        $description = trim($description);

        if (null === $category) {
            return new Refusal(t('shop.refusal.category'));
        }
        if ('' === $name || mb_strlen($name) > Product::NAME) {
            return new Refusal(t('shop.refusal.name', ['limit' => Product::NAME]));
        }
        $rows = self::rows($variants);
        if ($rows instanceof Refusal) {
            return $rows;
        }
        $single = '' === trim($price) ? null : self::amount($price);
        if (false === $single) {
            return new Refusal(t('shop.refusal.price'));
        }
        if (null === $single && [] === $rows) {
            return new Refusal(t('shop.refusal.unpriced'));
        }
        $refusal = Photos::refusal($uploads);
        if (null !== $refusal) {
            return $refusal;
        }

        $moved = null === $product || $product->getCategory() !== $category;
        if (null === $product) {
            $product = new Product($category, $name);
            $this->em->persist($product);
        }
        if ($moved) {
            $product->place($this->last($category) + 1);
        }
        $product->change($category, $name, '' === $description ? null : $description, $active);
        $product->vary($single, $rows);

        $gone = [];
        foreach ($product->getPhotos() as $photo) {
            if (\in_array($photo->getId(), $hidden, true)) {
                $gone[] = $photo->getFile();
                $product->hide($photo);
            }
        }
        foreach ($uploads as $upload) {
            $product->show($this->photos->store($upload));
        }
        $this->em->flush();
        $this->photos->drop($gone);

        return $product;
    }

    /** @param list<int> $moved */
    public function arrange(array $moved): void
    {
        $first = [] === $moved ? null : $this->em->find(Product::class, $moved[0]);
        if (null === $first) {
            return;
        }

        Order::place($this->em->getRepository(Product::class)->findBy(['category' => $first->getCategory()], ['position' => 'ASC', 'id' => 'ASC']), $moved);
        $this->em->flush();
    }

    public function remove(Product $product): void
    {
        $gone = array_map(static fn (Photo $photo): string => $photo->getFile(), $product->getPhotos());
        $this->em->remove($product);
        $this->em->flush();
        $this->photos->drop($gone);
    }

    /**
     * @param array<array-key, mixed> $variants
     *
     * @return list<array{id: ?int, name: string, price: string}>|Refusal
     */
    private static function rows(array $variants): array|Refusal
    {
        $rows = [];
        foreach ($variants as $variant) {
            $variant = \is_array($variant) ? $variant : [];
            $name = trim(\is_string($variant['name'] ?? null) ? $variant['name'] : '');
            $price = \is_string($variant['price'] ?? null) ? $variant['price'] : '';
            if ('' === $name && '' === trim($price)) {
                continue;
            }

            $amount = self::amount($price);
            if ('' === $name || mb_strlen($name) > Variant::NAME || false === $amount) {
                return new Refusal(t('shop.refusal.variant', ['limit' => Variant::NAME]));
            }
            $id = \is_string($variant['id'] ?? null) && ctype_digit($variant['id']) ? (int) $variant['id'] : null;
            $rows[] = ['id' => $id, 'name' => $name, 'price' => $amount];
        }

        return $rows;
    }

    private static function amount(string $price): string|false
    {
        $amount = Money::parse($price);

        return null !== $amount && Money::isPositive($amount) ? $amount : false;
    }

    private function last(Category $category): int
    {
        return (int) $this->em->createQueryBuilder()
            ->select('MAX(p.position)')
            ->from(Product::class, 'p')
            ->where('p.category = :category')
            ->setParameter('category', $category)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
