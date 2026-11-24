<?php

declare(strict_types=1);

namespace AdzerCMS\Pages\Repository;

use AdzerCMS\Pages\Entity\Page;
use AdzerCMS\Pages\Entity\PageBlock;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<PageBlock> */
class PageBlockRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PageBlock::class);
    }

    /** @return list<PageBlock> */
    public function of(Page $page): array
    {
        return $this->createQueryBuilder('b')->where('b.page = :page')->setParameter('page', $page)->orderBy('b.position')->addOrderBy('b.id')->getQuery()->getResult();
    }

    /** @return list<array{values: array<string, string>, style: array<string, array<string, array<string, string>>>}> */
    public function held(): array
    {
        return array_values($this->createQueryBuilder('b')->select('b.values', 'b.style')->getQuery()->getArrayResult());
    }

    /** @return array<int, list<string>> */
    public function types(): array
    {
        $types = [];
        foreach ($this->createQueryBuilder('b')->select('IDENTITY(b.page) AS page', 'b.type')->distinct()->getQuery()->getScalarResult() as $row) {
            $types[(int) $row['page']][] = (string) $row['type'];
        }

        return $types;
    }
}
