<?php

declare(strict_types=1);

namespace AdzerCMS\Pages\Repository;

use AdzerCMS\Pages\Entity\Page;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Page> */
class PageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Page::class);
    }

    public function published(string $path): ?Page
    {
        return $this->findOneBy(['path' => $path, 'published' => true]);
    }

    /** @return list<Page> */
    public function site(bool $menu = false): array
    {
        $query = $this->createQueryBuilder('p')->where('p.published = true')->orderBy('p.position')->addOrderBy('p.id');
        if ($menu) {
            $query->andWhere('p.menu = true');
        }

        return $query->getQuery()->getResult();
    }
}
