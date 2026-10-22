<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Entity;

use AdzerCMS\Core\Module\ModuleAction;
use AdzerCMS\Core\Module\OperationState;
use AdzerCMS\Core\Repository\ModuleOperationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

use function Symfony\Component\Clock\now;

#[ORM\Entity(repositoryClass: ModuleOperationRepository::class)]
#[ORM\Table(name: 'module_operation')]
#[ORM\Index(name: 'idx_module_operation_state', columns: ['state', 'id'])]
#[ORM\Index(name: 'idx_module_operation_package', columns: ['package', 'state'])]
class ModuleOperation
{
    public const PACKAGE = 190;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 16, enumType: OperationState::class)]
    private OperationState $state = OperationState::Pending;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $filed;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $finished = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $output = null;

    public function __construct(
        #[ORM\Column(length: self::PACKAGE)]
        private string $package,
        #[ORM\Column(length: 16, enumType: ModuleAction::class)]
        private ModuleAction $action,
    ) {
        $this->filed = now();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPackage(): string
    {
        return $this->package;
    }

    public function getAction(): ModuleAction
    {
        return $this->action;
    }

    public function getState(): OperationState
    {
        return $this->state;
    }

    public function getFiled(): \DateTimeImmutable
    {
        return $this->filed;
    }

    public function getFinished(): ?\DateTimeImmutable
    {
        return $this->finished;
    }

    public function getOutput(): ?string
    {
        return $this->output;
    }

    public function start(): void
    {
        $this->state = OperationState::Running;
    }

    public function finish(?string $refusal): void
    {
        $this->state = null === $refusal ? OperationState::Done : OperationState::Failed;
        $this->output = $refusal;
        $this->finished = now();
    }
}
