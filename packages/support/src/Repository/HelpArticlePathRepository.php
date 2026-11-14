<?php

declare(strict_types=1);

namespace AdzerCMS\Support\Repository;

use AdzerCMS\Support\Entity\HelpArticle;
use AdzerCMS\Support\Entity\HelpArticlePath;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<HelpArticlePath> */
class HelpArticlePathRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HelpArticlePath::class);
    }

    /** @return list<HelpArticlePath> */
    public function of(HelpArticle $article): array
    {
        return $this->findBy(['article' => $article], ['id' => 'ASC']);
    }
}
