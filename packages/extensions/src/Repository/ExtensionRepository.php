<?php

declare(strict_types=1);

namespace AdzerCMS\Extensions\Repository;

use AdzerCMS\Contracts\Module\Category;
use AdzerCMS\Contracts\Module\ExtensionType;
use AdzerCMS\Core\Ui\Filter;
use AdzerCMS\Core\Ui\Table;
use AdzerCMS\Extensions\Entity\Extension;
use AdzerCMS\Extensions\Entity\ExtensionAuthor;
use AdzerCMS\Extensions\ExtensionPrice;
use AdzerCMS\Extensions\ExtensionState;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Extension> */
class ExtensionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Extension::class);
    }

    /** @return list<Extension> */
    public function page(Table $table): array
    {
        $builder = $this->listing($table);
        $table->apply($builder)->total((int) $this->listing($table)->select('COUNT(e.id)')->getQuery()->getSingleScalarResult());

        return $builder
            ->setMaxResults($table->size)
            ->setFirstResult($table->offset())
            ->getQuery()
            ->getResult();
    }

    /** @return list<Extension> */
    public function published(Table $table): array
    {
        return $table->paginate(self::placed($this->catalog($table))->leftJoin('e.categories', 'c')->addSelect('c')->getQuery());
    }

    /** @return list<Extension> */
    public function authored(ExtensionAuthor $author, Table $table): array
    {
        $builder = $this->createQueryBuilder('e')->andWhere('e.author = :author')->setParameter('author', $author);
        $table->apply($builder);

        return $table->paginate($builder->addOrderBy('e.id')->getQuery());
    }

    /** @return list<Extension> */
    public function listed(): array
    {
        return self::placed($this->shown())->getQuery()->getResult();
    }

    /** @return list<Extension> */
    public function ordered(): array
    {
        return self::placed($this->createQueryBuilder('e'))->getQuery()->getResult();
    }

    public function last(): int
    {
        return (int) $this->createQueryBuilder('e')->select('MAX(e.position)')->getQuery()->getSingleScalarResult();
    }

    public function named(string $name): ?Extension
    {
        return $this->findOneBy(['name' => $name]);
    }

    private function listing(Table $table): QueryBuilder
    {
        $builder = $this->createQueryBuilder('e')->leftJoin('e.author', 'a')->leftJoin('a.user', 'u')->addSelect('a', 'u');

        if ('' !== $table->term()) {
            $builder->andWhere(Filter::matching($builder, $table->term(), ['e.name']));
        }

        $state = ExtensionState::tryFrom($table->value('state'));

        return self::typed(null === $state ? $builder : self::stated($builder, $state), $table);
    }

    private function catalog(Table $table): QueryBuilder
    {
        $builder = self::typed($this->shown(), $table);

        $category = Category::tryFrom($table->value('category'));
        if (null !== $category) {
            $builder->innerJoin('e.categories', 'f', Join::WITH, 'f.category = :category')->setParameter('category', $category);
        }

        return match (ExtensionPrice::tryFrom($table->value('price'))) {
            ExtensionPrice::Free => $builder->andWhere('e.price IS NULL'),
            ExtensionPrice::Paid => $builder->andWhere('e.price IS NOT NULL'),
            null => $builder,
        };
    }

    private function shown(): QueryBuilder
    {
        return self::stated($this->createQueryBuilder('e'), ExtensionState::Published);
    }

    private static function stated(QueryBuilder $builder, ExtensionState $state): QueryBuilder
    {
        return $builder->andWhere('e.state = :state')->setParameter('state', $state);
    }

    private static function typed(QueryBuilder $builder, Table $table): QueryBuilder
    {
        $type = ExtensionType::tryFrom($table->value('type'));

        return null === $type ? $builder : $builder->andWhere('e.type = :type')->setParameter('type', $type);
    }

    private static function placed(QueryBuilder $builder): QueryBuilder
    {
        return $builder->orderBy('e.position')->addOrderBy('e.id');
    }
}
