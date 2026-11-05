<?php

declare(strict_types=1);

namespace AdzerCMS\Pages\Repository;

use AdzerCMS\Pages\Entity\Page;
use AdzerCMS\Pages\Entity\PagePath;
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

    public function moved(string $path): ?Page
    {
        $page = $this->createQueryBuilder('p')->innerJoin(PagePath::class, 'f', 'WITH', 'f.page = p')
            ->where('f.path = :path')->andWhere('p.published = true')->setParameter('path', $path)
            ->getQuery()->getOneOrNullResult();

        return $page instanceof Page ? $page : null;
    }

    /**
     * @param list<string> $paths
     *
     * @return list<Page>
     */
    public function holding(array $paths): array
    {
        return [] === $paths ? [] : $this->findBy(['path' => $paths]);
    }

    /** @return list<Page> */
    public function ordered(): array
    {
        return $this->createQueryBuilder('p')->orderBy('p.position')->addOrderBy('p.id')->getQuery()->getResult();
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
