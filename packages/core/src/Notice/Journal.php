<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Notice;

use AdzerCMS\Contracts\Setting\Field;
use AdzerCMS\Contracts\Setting\Owner;
use AdzerCMS\Core\Repository\NoticeRepository;
use AdzerCMS\Core\Setting\Settings;

use function Symfony\Component\Clock\now;
use function Symfony\Component\Translation\t;

final class Journal implements Owner
{
    public const KEEP_DAYS = 'notice.keep_days';
    private const DEFAULT_KEEP_DAYS = 180;

    public function __construct(
        private readonly Settings $settings,
        private readonly NoticeRepository $notices,
    ) {
    }

    public function settingFields(): array
    {
        return [new Field(self::KEEP_DAYS, t('core.setting.notice_keep_days'))];
    }

    public function sweep(): int
    {
        $days = max(1, $this->settings->int(self::KEEP_DAYS, self::DEFAULT_KEEP_DAYS));

        return $this->notices->sweep(now()->modify(\sprintf('-%d days', $days)));
    }
}
