<?php

declare(strict_types=1);

namespace AdzerCMS\Support\Entity;

use AdzerCMS\Support\Repository\HelpArticlePathRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: HelpArticlePathRepository::class)]
#[ORM\Table(name: 'help_article_path')]
#[ORM\Index(name: 'idx_help_article_path_path', columns: ['path'])]
class HelpArticlePath
{
    public const PATH = 190;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private HelpArticle $article,
        #[ORM\Column(length: self::PATH)]
        private string $path,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getArticle(): HelpArticle
    {
        return $this->article;
    }

    public function getPath(): string
    {
        return $this->path;
    }
}
