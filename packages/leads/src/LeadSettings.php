<?php

declare(strict_types=1);

namespace AdzerCMS\Leads;

use AdzerCMS\Contracts\Setting\Field;
use AdzerCMS\Contracts\Setting\Owner;
use AdzerCMS\Core\Setting\Settings;
use AdzerCMS\Core\Ui\Split;

use function Symfony\Component\Translation\t;

final readonly class LeadSettings implements Owner
{
    public const REASONS = 'leads.refusal_reasons';

    public function __construct(private Settings $settings)
    {
    }

    public function settingFields(): array
    {
        return [new Field(self::REASONS, t('leads.setting.reasons'))];
    }

    /** @return list<string> */
    public function reasons(): array
    {
        return Split::lines($this->settings->get(self::REASONS));
    }
}
