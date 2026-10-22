<?php

declare(strict_types=1);

namespace AdzerCMS\Leads\Tests;

use AdzerCMS\Leads\StageGroup;
use AdzerCMS\Support\StaffDigest;
use PHPUnit\Framework\Attributes\Group;

use function Symfony\Component\Clock\now;

final class DigestTest extends LeadsTestCase
{
    #[Group('ACMS-LEAD-0006')]
    #[Group('ACMS-US-0041')]
    public function testOverdueOpenLeadsAreSectionOfDailyStaffDigest(): void
    {
        $due = now('-1 hour');
        $overdue = $this->lead(StageGroup::Work, 'Вера', topic: 'Кран');
        $this->assign($overdue, null, $due);
        $this->assign($this->lead(StageGroup::Fresh, 'Анна'), null, now('+1 hour'));
        $this->assign($this->lead(StageGroup::Done, 'Глеб'), null, $due);

        $digest = self::service(StaffDigest::class)->collect(false);

        self::assertSame(self::phrase('leads.digest.summary', ['count' => 1]), $digest['summary']);
        self::assertSame([self::phrase('leads.digest.line', ['number' => $overdue->getId(), 'name' => 'Вера', 'due' => $due, 'topic' => 'Кран'])], $digest['lines']);
    }
}
