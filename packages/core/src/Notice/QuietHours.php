<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Notice;

use AdzerCMS\Contracts\Setting\Field;
use AdzerCMS\Contracts\Setting\Owner;
use AdzerCMS\Contracts\Setting\Tab;
use AdzerCMS\Contracts\Setting\Type;
use AdzerCMS\Core\Setting\Settings;

use function Symfony\Component\Clock\now;
use function Symfony\Component\Translation\t;

final class QuietHours implements Owner
{
    public const FROM = 'notice.quiet_from';
    public const TO = 'notice.quiet_to';

    public function __construct(private readonly Settings $settings)
    {
    }

    public function settingFields(): array
    {
        return [
            new Field(self::FROM, t('core.setting.notice_quiet_from'), Type::Time, tab: Tab::Mail),
            new Field(self::TO, t('core.setting.notice_quiet_to'), Type::Time, tab: Tab::Mail),
        ];
    }

    public function holds(): bool
    {
        $from = $this->settings->minutes(self::FROM);
        $to = $this->settings->minutes(self::TO);
        if (null === $from || null === $to || $from === $to) {
            return false;
        }

        $moment = now();
        $now = (int) $moment->format('G') * 60 + (int) $moment->format('i');

        return $from < $to ? $now >= $from && $now < $to : $now >= $from || $now < $to;
    }
}
