<?php

declare(strict_types=1);

namespace AdzerCMS\Leads;

use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Core\Account\LoginNames;
use AdzerCMS\Core\Entity\User;
use AdzerCMS\Core\Repository\UserRepository;
use AdzerCMS\Core\Ui\Money;
use AdzerCMS\Leads\Entity\Lead;
use AdzerCMS\Leads\Entity\LeadEntry;
use AdzerCMS\Leads\Repository\StageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Contracts\Translation\TranslatorInterface;

use function Symfony\Component\Translation\t;

final readonly class Handling
{
    public const STAGE = 'stage';
    public const MANAGER = 'manager';
    public const DUE = 'due';
    public const AMOUNT = 'amount';
    public const REASON = 'reason';
    public const NOTE = 'note';
    public const DUE_FORMAT = 'Y-m-d\TH:i';

    public function __construct(
        private StageRepository $stages,
        private UserRepository $users,
        private LoginNames $logins,
        private TranslatorInterface $translator,
        private EntityManagerInterface $em,
    ) {
    }

    /** @return array<int, string> */
    public function managers(): array
    {
        return $this->logins->ofMany($this->users->admins());
    }

    /** @return array<string, Refusal> */
    public function save(Lead $lead, User $author, ParameterBag $input): array
    {
        $stage = $this->stages->find($input->getInt(self::STAGE));
        $manager = $input->getInt(self::MANAGER);
        $chosen = array_find($this->users->admins(), static fn (User $admin): bool => $admin->getId() === $manager);
        $due = trim($input->getString(self::DUE));
        $at = \DateTimeImmutable::createFromFormat('!'.self::DUE_FORMAT, $due);
        $amount = trim($input->getString(self::AMOUNT));
        $reason = trim($input->getString(self::REASON));

        $refusals = array_filter([
            self::STAGE => null === $stage ? new Refusal(t('leads.refusal.stage')) : null,
            self::MANAGER => 0 !== $manager && null === $chosen ? new Refusal(t('leads.refusal.manager')) : null,
            self::DUE => '' === $due || (false !== $at && $at->format(self::DUE_FORMAT) === $due) ? null : new Refusal(t('leads.refusal.due')),
            self::AMOUNT => '' === $amount || null !== Money::parse($amount) ? null : new Refusal(t('leads.refusal.amount')),
            self::REASON => StageGroup::Refused === $stage?->getGroup() && ('' === $reason || mb_strlen($reason) > Lead::REASON)
                ? new Refusal(t('leads.refusal.reason', ['limit' => Lead::REASON]))
                : null,
        ]);
        if ([] !== $refusals || null === $stage) {
            return $refusals;
        }

        $was = $this->shown($lead);
        $lead->handle($stage, $chosen, '' === $due || false === $at ? null : $at, Money::parse($amount), '' === $reason ? null : $reason);
        $changes = [];
        foreach ($this->shown($lead) as $field => $now) {
            if ($was[$field] !== $now) {
                $changes[] = ['field' => $field, 'was' => $was[$field], 'now' => $now];
            }
        }
        if ([] !== $changes) {
            $this->em->persist(new LeadEntry($lead, $author, changes: $changes));
        }
        $this->em->flush();

        return [];
    }

    public function note(Lead $lead, User $author, string $note): ?Refusal
    {
        $note = trim($note);
        if ('' === $note || mb_strlen($note) > LeadEntry::NOTE) {
            return new Refusal(t('leads.refusal.note', ['limit' => LeadEntry::NOTE]));
        }

        $this->em->persist(new LeadEntry($lead, $author, $note));
        $this->em->flush();

        return null;
    }

    /** @return array<string, ?string> */
    private function shown(Lead $lead): array
    {
        $manager = $lead->getManager();
        $due = $lead->getDue();
        $amount = $lead->getAmount();

        return [
            self::STAGE => $lead->getStage()->label()->trans($this->translator),
            self::MANAGER => null === $manager ? null : $this->logins->of($manager),
            self::DUE => null === $due ? null : $this->translator->trans('core.moment.day_time', ['at' => $due]),
            self::AMOUNT => null === $amount ? null : Money::rubles($amount)->trans($this->translator),
            self::REASON => $lead->getReason(),
        ];
    }
}
