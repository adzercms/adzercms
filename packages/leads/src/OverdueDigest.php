<?php

declare(strict_types=1);

namespace AdzerCMS\Leads;

use AdzerCMS\Leads\Entity\Lead;
use AdzerCMS\Leads\Repository\LeadRepository;
use AdzerCMS\Support\DigestSection;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use function Symfony\Component\Translation\t;

final readonly class OverdueDigest implements DigestSection
{
    public function __construct(
        private LeadRepository $leads,
        private TranslatorInterface $translator,
    ) {
    }

    public function lines(): array
    {
        return array_map(fn (Lead $lead): string => $this->translator->trans('leads.digest.line', [
            'number' => $lead->getId(),
            'name' => $lead->getName() ?? '-',
            'due' => $lead->getDue(),
            'topic' => $lead->getTopic() ?? '-',
        ]), $this->leads->overdue());
    }

    public function summary(int $count): TranslatableInterface
    {
        return t('leads.digest.summary', ['count' => $count]);
    }
}
