<?php

declare(strict_types=1);

namespace AdzerCMS\Support;

use AdzerCMS\Contracts\Setting\Field;
use AdzerCMS\Contracts\Setting\Owner;
use AdzerCMS\Core\Setting\Settings;
use AdzerCMS\Core\Ui\Split;
use Symfony\Contracts\Translation\TranslatorInterface;

use function Symfony\Component\Translation\t;

final readonly class SupportSettings implements Owner
{
    public const DEPARTMENTS = 'support.departments';
    public const AUTO_CLOSE_DAYS = 'support.auto_close_days';
    public const ATTACHMENT_MAX_MB = 'support.attachment_max_mb';
    public const ATTACHMENT_TYPES = 'support.attachment_types';
    public const HELP_INDEX = 'support.help_index';
    public const OVERDUE_HOURS = 'support.staff_overdue_hours';

    private const DEFAULT_AUTO_CLOSE_DAYS = 7;
    private const DEFAULT_ATTACHMENT_MAX_MB = 10;
    private const DEFAULT_ATTACHMENT_TYPES = 'jpg, jpeg, png, gif, pdf, txt, log, zip';
    private const DEFAULT_OVERDUE_HOURS = 24;

    public function __construct(
        private Settings $settings,
        private TranslatorInterface $translator,
    ) {
    }

    public function settingFields(): array
    {
        return [
            new Field(self::DEPARTMENTS, t('support.setting.departments')),
            new Field(self::AUTO_CLOSE_DAYS, t('support.setting.auto_close_days')),
            new Field(self::ATTACHMENT_MAX_MB, t('support.setting.attachment_max_mb')),
            new Field(self::ATTACHMENT_TYPES, t('support.setting.attachment_types')),
            new Field(self::HELP_INDEX, t('support.setting.help_index')),
            new Field(self::OVERDUE_HOURS, t('support.setting.overdue_hours')),
        ];
    }

    public function helpIndex(): string
    {
        return trim($this->settings->get(self::HELP_INDEX));
    }

    public function helpEnabled(): bool
    {
        return '' !== $this->helpIndex();
    }

    /** @return list<string> */
    public function departments(): array
    {
        $names = Split::items($this->settings->get(self::DEPARTMENTS));

        return [] === $names ? Split::items($this->translator->trans('support.default_departments')) : $names;
    }

    public function autoCloseDays(): int
    {
        return max(0, $this->settings->int(self::AUTO_CLOSE_DAYS, self::DEFAULT_AUTO_CLOSE_DAYS));
    }

    public function overdueHours(): int
    {
        return max(1, $this->settings->int(self::OVERDUE_HOURS, self::DEFAULT_OVERDUE_HOURS));
    }

    public function attachmentMegabytes(): int
    {
        return max(1, $this->settings->int(self::ATTACHMENT_MAX_MB, self::DEFAULT_ATTACHMENT_MAX_MB));
    }

    public function attachmentLimit(): int
    {
        return $this->attachmentMegabytes() * 1024 * 1024;
    }

    /** @return list<string> */
    public function attachmentTypes(): array
    {
        $types = $this->settings->get(self::ATTACHMENT_TYPES);

        return array_map(strtolower(...), Split::items('' === $types ? self::DEFAULT_ATTACHMENT_TYPES : $types));
    }

    public function attachmentAccept(): string
    {
        return implode(',', array_map(static fn (string $type): string => '.'.$type, $this->attachmentTypes()));
    }
}
