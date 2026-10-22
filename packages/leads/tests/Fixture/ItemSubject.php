<?php

declare(strict_types=1);

namespace AdzerCMS\Leads\Tests\Fixture;

use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Leads\Entity\Lead;
use AdzerCMS\Leads\LeadSubject;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\Translation\TranslatableMessage;

final class ItemSubject implements LeadSubject
{
    public const FIELD = 'item';
    public const LABEL = 'Item';
    public const PRICE = '1500.00';
    public const SOLD = 'Sold out';

    public bool $sold = false;

    /** @var list<Lead> */
    public array $attached = [];

    public function fields(ParameterBag $input): ?array
    {
        return '' === $input->getString(self::FIELD)
            ? null
            : ['template' => '@Theme/_check.html.twig', 'context' => ['name' => self::FIELD, 'on' => true, 'label' => self::LABEL]];
    }

    public function check(ParameterBag $input): ?Refusal
    {
        return $this->sold && '' !== $input->getString(self::FIELD) ? new Refusal(new TranslatableMessage(self::SOLD)) : null;
    }

    public function attach(Lead $lead, ParameterBag $input): ?string
    {
        if ('' === $input->getString(self::FIELD)) {
            return null;
        }
        $this->attached[] = $lead;

        return self::PRICE;
    }
}
