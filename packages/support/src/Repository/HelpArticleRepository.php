<?php

declare(strict_types=1);

namespace AdzerCMS\Support\Repository;

use AdzerCMS\Core\Ui\Filter;
use AdzerCMS\Support\Entity\HelpArticle;
use AdzerCMS\Support\Entity\HelpArticlePath;
use AdzerCMS\Support\Entity\HelpCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<HelpArticle> */
class HelpArticleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HelpArticle::class);
    }

    /** @return list<HelpArticle> */
    public function of(HelpCategory $category): array
    {
        return $this->createQueryBuilder('a')->where('a.category = :category')->setParameter('category', $category)
            ->orderBy('a.position')->addOrderBy('a.id')->getQuery()->getResult();
    }

    /** @return array<int, int> */
    public function counts(): array
    {
        $rows = $this->createQueryBuilder('a')->select('IDENTITY(a.category) AS category', 'COUNT(a.id) AS articles')
            ->groupBy('a.category')->getQuery()->getScalarResult();

        return array_combine(array_map(intval(...), array_column($rows, 'category')), array_map(intval(...), array_column($rows, 'articles')));
    }

    public function published(string $category, string $slug): ?HelpArticle
    {
        $article = $this->shown()->andWhere('c.slug = :category')->andWhere('a.slug = :slug')
            ->setParameter('category', $category)->setParameter('slug', $slug)->getQuery()->getOneOrNullResult();

        return $article instanceof HelpArticle ? $article : null;
    }

    public function anyPublished(?string $category = null): bool
    {
        $query = $this->shown()->select('a.id')->setMaxResults(1);
        if (null !== $category) {
            $query->andWhere('c.slug = :category')->setParameter('category', $category);
        }

        return [] !== $query->getQuery()->getScalarResult();
    }

    /**
     * @param list<string> $words
     *
     * @return list<HelpArticle>
     */
    public function contents(array $words = []): array
    {
        return self::ordered(self::every($this->shown(), $words))->getQuery()->getResult();
    }

    /**
     * @param non-empty-list<string> $words
     *
     * @return list<HelpArticle>
     */
    public function found(array $words, int $limit): array
    {
        $query = self::every($this->shown(), $words)->addSelect(self::hits('a.heading', $words).' AS HIDDEN hits')->orderBy('hits', 'DESC');

        return self::ordered($query)->setMaxResults($limit)->getQuery()->getResult();
    }

    /**
     * @param non-empty-list<string> $words
     *
     * @return list<HelpArticle>
     */
    public function related(array $words, int $limit): array
    {
        $query = self::words($this->shown(), $words);
        $query->andWhere($query->expr()->orX(...array_map(static fn (int $index): string => \sprintf('a.search LIKE :w%d', $index), array_keys($words))))
            ->addSelect(self::hits('a.search', $words).' AS HIDDEN hits')->orderBy('hits', 'DESC');

        return self::ordered($query)->setMaxResults($limit)->getQuery()->getResult();
    }

    /**
     * @param list<string> $paths
     *
     * @return list<HelpArticle>
     */
    public function covering(array $paths): array
    {
        return self::ordered($this->shown()
            ->andWhere(\sprintf('a.id IN (SELECT IDENTITY(p.article) FROM %s p WHERE p.path IN (:paths))', HelpArticlePath::class))
            ->setParameter('paths', $paths))->getQuery()->getResult();
    }

    private function shown(): QueryBuilder
    {
        return $this->createQueryBuilder('a')->addSelect('c')->join('a.category', 'c')->where('a.published = true');
    }

    /** @param list<string> $words */
    private static function every(QueryBuilder $query, array $words): QueryBuilder
    {
        foreach (array_keys($words) as $index) {
            $query->andWhere(\sprintf('a.search LIKE :w%d', $index));
        }

        return self::words($query, $words);
    }

    /** @param list<string> $words */
    private static function words(QueryBuilder $query, array $words): QueryBuilder
    {
        foreach ($words as $index => $word) {
            $query->setParameter('w'.$index, Filter::pattern($word));
        }

        return $query;
    }

    /** @param list<string> $words */
    private static function hits(string $field, array $words): string
    {
        return '('.implode(' + ', array_map(static fn (int $index): string => \sprintf('CASE WHEN %s LIKE :w%d THEN 1 ELSE 0 END', $field, $index), array_keys($words))).')';
    }

    private static function ordered(QueryBuilder $query): QueryBuilder
    {
        return $query->addOrderBy('c.position')->addOrderBy('c.id')->addOrderBy('a.position')->addOrderBy('a.id');
    }
}
