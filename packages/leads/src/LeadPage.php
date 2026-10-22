<?php

declare(strict_types=1);

namespace AdzerCMS\Leads;

use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Core\Account\ContactKind;
use AdzerCMS\Core\Account\LoginNames;
use AdzerCMS\Core\Entity\User;
use AdzerCMS\Leads\Entity\Lead;
use AdzerCMS\Leads\Repository\LeadEntryRepository;
use AdzerCMS\Leads\Repository\LeadRepository;
use AdzerCMS\Leads\Repository\StageRepository;

final readonly class LeadPage
{
    public function __construct(
        private Subjects $subjects,
        private Handling $handling,
        private LeadRepository $leads,
        private LeadEntryRepository $entries,
        private StageRepository $stages,
        private LeadSettings $settings,
        private LoginNames $logins,
    ) {
    }

    /**
     * @param array<string, Refusal>    $refusals
     * @param array<string, mixed>|null $input
     *
     * @return array<string, mixed>
     */
    public function context(Lead $lead, array $refusals, ?array $input): array
    {
        $entries = $this->entries->feed($lead);
        $authors = [];
        foreach ($entries as $entry) {
            $author = $entry->getAuthor();
            if ($author instanceof User) {
                $authors[(int) $author->getId()] = $author;
            }
        }

        return [
            'lead' => $lead,
            'contacts' => array_filter([[ContactKind::Phone, $lead->getPhone()], [ContactKind::Email, $lead->getEmail()]], static fn (array $contact): bool => null !== $contact[1]),
            'details' => $this->subjects->details($lead),
            'others' => $this->leads->sameClient($lead),
            'entries' => $entries,
            'authors' => $this->logins->ofMany(array_values($authors)),
            'stages' => $this->stages->funnel(),
            'managers' => $this->handling->managers(),
            'reasons' => $this->settings->reasons(),
            'refusals' => $refusals,
            'input' => $input,
        ];
    }
}
