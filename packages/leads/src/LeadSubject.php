<?php

declare(strict_types=1);

namespace AdzerCMS\Leads;

use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Leads\Entity\Lead;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\HttpFoundation\ParameterBag;

#[AutoconfigureTag]
interface LeadSubject
{
    public const LISTING = 'admin.leads';

    /** @return array{template: string, context: array<string, mixed>}|null */
    public function fields(ParameterBag $input): ?array;

    public function check(ParameterBag $input): ?Refusal;

    public function attach(Lead $lead, ParameterBag $input): ?string;
}
