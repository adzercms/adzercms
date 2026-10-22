<?php

declare(strict_types=1);

namespace AdzerCMS\Leads\Entity;

use AdzerCMS\Contracts\Ui\Badged;
use AdzerCMS\Core\Ui\Placed;
use AdzerCMS\Leads\Repository\StageRepository;
use AdzerCMS\Leads\StageGroup;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Contracts\Translation\TranslatableInterface;

use function Symfony\Component\Translation\t;

#[ORM\Entity(repositoryClass: StageRepository::class)]
#[ORM\Table(name: 'lead_stage')]
#[ORM\Index(name: 'idx_lead_stage_group', columns: ['stage_group', 'position'])]
class Stage implements Placed, Badged
{
    public const LABEL = 64;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private int $position = 0;

    public function __construct(
        #[ORM\Column(name: 'stage_group', length: 16, enumType: StageGroup::class)]
        private StageGroup $group,
        #[ORM\Column(length: self::LABEL, nullable: true)]
        private ?string $label = null,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getGroup(): StageGroup
    {
        return $this->group;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function place(int $position): void
    {
        $this->position = $position;
    }

    public function label(): TranslatableInterface
    {
        return null === $this->label ? $this->group->label() : t('leads.stage.label', ['label' => $this->label]);
    }

    public function badge(): string
    {
        return $this->group->badge();
    }
}
