<?php

declare(strict_types=1);

namespace AdzerCMS\Leads\Tests\Fixture;

use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Contracts\Ui\Cell;
use AdzerCMS\Contracts\Ui\Column;
use AdzerCMS\Contracts\Ui\Columns;
use AdzerCMS\Leads\Detail;
use AdzerCMS\Leads\Entity\Lead;
use AdzerCMS\Leads\LeadSubject;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\Translation\TranslatableMessage;

final class ItemSubject implements LeadSubject, Columns
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

    public function details(Lead $lead): array
    {
        return array_any($this->attached, static fn (Lead $attached): bool => $attached->getId() === $lead->getId())
            ? [new Detail(new TranslatableMessage(self::LABEL), self::PRICE)]
            : [];
    }

    public function listing(): string
    {
        return self::LISTING;
    }

    public function columns(): array
    {
        return [new Column(self::FIELD, new TranslatableMessage(self::LABEL))];
    }

    public function cells(array $subjects): array
    {
        $cells = [];
        foreach ($this->attached as $lead) {
            if (\in_array($lead->getId(), $subjects, true)) {
                $cells[(int) $lead->getId()] = [self::FIELD => new Cell(self::PRICE)];
            }
        }

        return $cells;
    }

    public function search(string $term): array
    {
        return false === mb_stripos(self::LABEL, $term) ? [] : array_map(static fn (Lead $lead): int => (int) $lead->getId(), $this->attached);
    }
}
