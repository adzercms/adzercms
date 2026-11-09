<?php

declare(strict_types=1);

namespace AdzerCMS\Leads;

use AdzerCMS\Contracts\Setting\Field;
use AdzerCMS\Contracts\Setting\Owner;
use AdzerCMS\Core\Attachment\Allowance;
use AdzerCMS\Core\Setting\Settings;
use AdzerCMS\Core\Ui\Split;

use function Symfony\Component\Translation\t;

final readonly class LeadSettings implements Owner
{
    public const REASONS = 'leads.refusal_reasons';
    public const ATTACHMENT_MAX_MB = 'leads.attachment_max_mb';
    public const ATTACHMENT_TYPES = 'leads.attachment_types';

    private const NO_ATTACHMENTS = 0;
    private const DEFAULT_ATTACHMENT_TYPES = ['jpg', 'jpeg', 'png', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'zip'];

    public function __construct(private Settings $settings)
    {
    }

    public function settingFields(): array
    {
        return [
            new Field(self::REASONS, t('leads.setting.reasons')),
            new Field(self::ATTACHMENT_MAX_MB, t('leads.setting.attachment_max_mb')),
            new Field(self::ATTACHMENT_TYPES, t('leads.setting.attachment_types')),
        ];
    }

    public function attachments(): ?Allowance
    {
        $megabytes = $this->settings->int(self::ATTACHMENT_MAX_MB, self::NO_ATTACHMENTS);

        return $megabytes <= self::NO_ATTACHMENTS
            ? null
            : Allowance::configured($this->settings, self::ATTACHMENT_MAX_MB, self::ATTACHMENT_TYPES, new Allowance($megabytes, self::DEFAULT_ATTACHMENT_TYPES));
    }

    /** @return list<string> */
    public function reasons(): array
    {
        return Split::lines($this->settings->get(self::REASONS));
    }
}
