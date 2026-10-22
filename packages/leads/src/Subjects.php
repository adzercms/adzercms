<?php

declare(strict_types=1);

namespace AdzerCMS\Leads;

use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Core\Ui\Money;
use AdzerCMS\Leads\Entity\Lead;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\HttpFoundation\ParameterBag;

final readonly class Subjects
{
    /** @param iterable<LeadSubject> $subjects */
    public function __construct(#[AutowireIterator(LeadSubject::class)] private iterable $subjects)
    {
    }

    /** @return list<array{template: string, context: array<string, mixed>}> */
    public function fields(ParameterBag $input): array
    {
        $fields = [];
        foreach ($this->subjects as $subject) {
            $part = $subject->fields($input);
            if (null !== $part) {
                $fields[] = $part;
            }
        }

        return $fields;
    }

    public function check(ParameterBag $input): ?Refusal
    {
        foreach ($this->subjects as $subject) {
            $refusal = $subject->check($input);
            if (null !== $refusal) {
                return $refusal;
            }
        }

        return null;
    }

    public function attach(Lead $lead, ParameterBag $input): ?string
    {
        $amount = null;
        foreach ($this->subjects as $subject) {
            $part = $subject->attach($lead, $input);
            if (null !== $part) {
                $amount = Money::add($amount ?? '0', $part);
            }
        }

        return $amount;
    }

    /** @return list<Detail> */
    public function details(Lead $lead): array
    {
        $details = [];
        foreach ($this->subjects as $subject) {
            $details = [...$details, ...$subject->details($lead)];
        }

        return $details;
    }
}
