<?php

declare(strict_types=1);

namespace AdzerCMS\Leads;

use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Core\Ui\Order;
use AdzerCMS\Leads\Entity\Stage;
use AdzerCMS\Leads\Repository\LeadRepository;
use AdzerCMS\Leads\Repository\StageRepository;
use Doctrine\ORM\EntityManagerInterface;

use function Symfony\Component\Translation\t;

final readonly class Stages
{
    public function __construct(
        private StageRepository $stages,
        private LeadRepository $leads,
        private EntityManagerInterface $em,
    ) {
    }

    public function add(?StageGroup $group, string $label): Stage|Refusal
    {
        $label = trim($label);
        if (null === $group) {
            return new Refusal(t('leads.refusal.stage_group'));
        }
        if ('' === $label || mb_strlen($label) > Stage::LABEL) {
            return new Refusal(t('leads.refusal.stage_label', ['limit' => Stage::LABEL]));
        }

        $stage = new Stage($group, $label);
        $stage->place(\count($this->stages->ofGroup($group)) + 1);
        $this->em->persist($stage);
        $this->em->flush();

        return $stage;
    }

    public function drop(Stage $stage): ?Refusal
    {
        if ($this->leads->holds($stage)) {
            return new Refusal(t('leads.refusal.stage_held'));
        }
        if (1 === \count($this->stages->ofGroup($stage->getGroup()))) {
            return new Refusal(t('leads.refusal.stage_last'));
        }

        $this->em->remove($stage);
        $this->em->flush();

        return null;
    }

    /** @param list<int> $moved */
    public function arrange(array $moved): void
    {
        Order::place($this->stages->funnel(), $moved, static fn (Stage $stage): StageGroup => $stage->getGroup());
        $this->em->flush();
    }
}
