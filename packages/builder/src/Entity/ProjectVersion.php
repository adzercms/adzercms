<?php

declare(strict_types=1);

namespace AdzerCMS\Builder\Entity;

use AdzerCMS\Builder\BuilderEvent;
use AdzerCMS\Builder\Repository\ProjectVersionRepository;
use AdzerCMS\Core\Moderation\Decision;
use AdzerCMS\Core\Moderation\DecisionNotice;
use AdzerCMS\Core\Moderation\Moderated;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

use function Symfony\Component\Clock\now;

#[ORM\Entity(repositoryClass: ProjectVersionRepository::class)]
#[ORM\Table(name: 'builder_project_versions')]
#[ORM\Index(name: 'idx_builder_project_versions_project', columns: ['project_id', 'decision'])]
class ProjectVersion implements Moderated
{
    public const COVER = 64;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 16, enumType: Decision::class)]
    private Decision $decision = Decision::Pending;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $reason = null;

    /** @var list<array{path: string, title: string, blocks: list<array{type: string, values: array<string, string>, style: array<string, array<string, array<string, string>>>}>}> */
    #[ORM\Column(type: Types::JSON)]
    private array $pages = [];

    #[ORM\Column(length: self::COVER)]
    private string $cover = '';

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $submitted;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Project $project,
        #[ORM\Column(length: Project::NAME)]
        private string $name,
    ) {
        $this->submitted = now();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProject(): Project
    {
        return $this->project;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /** @return list<array{path: string, title: string, blocks: list<array{type: string, values: array<string, string>, style: array<string, array<string, array<string, string>>>}>}> */
    public function getPages(): array
    {
        return $this->pages;
    }

    public function getCover(): string
    {
        return $this->cover;
    }

    public function getSubmitted(): \DateTimeImmutable
    {
        return $this->submitted;
    }

    /** @param list<array{path: string, title: string, blocks: list<array{type: string, values: array<string, string>, style: array<string, array<string, array<string, string>>>}>}> $pages */
    public function snapshot(array $pages, string $cover): void
    {
        $this->pages = $pages;
        $this->cover = $cover;
    }

    public function getDecision(): Decision
    {
        return $this->decision;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function decide(Decision $decision, ?string $reason = null): void
    {
        $this->decision = $decision;
        $this->reason = $reason;
    }

    public function decisionNotice(): DecisionNotice
    {
        return new DecisionNotice($this->project->getOwner(), BuilderEvent::Decision, "builder\0".$this->id, ['name' => $this->name, 'decision' => $this->decision->label()]);
    }
}
