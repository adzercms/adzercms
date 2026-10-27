<?php

declare(strict_types=1);

namespace AdzerCMS\Support\Tests;

use AdzerCMS\Support\StaffDigest;
use PHPUnit\Framework\Attributes\Group;

final class DigestTest extends SupportTestCase
{
    #[Group('ACMS-SUP-0012')]
    #[Group('ACMS-US-0010')]
    public function testStaffDigestCarriesWaitingTicketsAndMarksTheNotifiedOnes(): void
    {
        $digest = self::service(StaffDigest::class);
        $user = $this->member();
        $waiting = $this->ticket($user, 'No connection');
        $answered = $this->ticket($this->member(), 'Invoice');
        $this->client->loginUser($this->staff);
        $this->reply($answered, 'Answered');

        $collected = $digest->collect(false);

        self::assertSame([(int) $waiting->getId()], $collected['ids'], 'an answered ticket does not await the operator');
        self::assertStringContainsString('1', $collected['summary']);
        self::assertStringContainsString((string) $user->getId(), $collected['lines'][0]);

        self::assertSame(1, $digest->mark($collected['ids']));
        self::assertSame([], $digest->collect(true)['ids'], 'a notified ticket does not go to the event digest');
        self::assertSame([(int) $waiting->getId()], $digest->collect(false)['ids'], 'the daily digest keeps the ticket until the answer');
    }

    #[Group('ACMS-SUP-0012')]
    #[Group('ACMS-US-0010')]
    public function testStaffDigestMarksOverdueTicketsByTheirWaitingTime(): void
    {
        $now = new \DateTimeImmutable('2026-10-16 12:00');
        $user = $this->member();
        $ticket = $this->ticket($user, 'No connection');
        $ticket->touch($now->modify('-3 days'));
        $this->em->flush();
        $digest = self::service(StaffDigest::class);

        $daily = $digest->collect(false, $now)['lines'][0];
        $event = $digest->collect(true, $now)['lines'][0];

        foreach (['#'.$ticket->getId(), (string) $user->getId(), '72', 'No connection'] as $part) {
            self::assertStringContainsString($part, $daily, 'the daily digest: number, user, waiting time, subject');
        }
        self::assertSame(\sprintf('#%d %d, %s, %s', $ticket->getId(), $user->getId(), $ticket->getDepartment(), 'No connection'), $event, 'the event digest: number, user, department, subject');
    }

    #[Group('ACMS-SUP-0012')]
    #[Group('ACMS-US-0010')]
    public function testStaffDigestCutsLongSectionByTheRestAndIsEmptyWithoutDeeds(): void
    {
        $digest = self::service(StaffDigest::class);
        $opened = 0;

        foreach ([[0, 0, ''], [StaffDigest::SHOWN, StaffDigest::SHOWN, ''], [StaffDigest::SHOWN + 2, StaffDigest::SHOWN + 1, '2']] as [$waiting, $lines, $rest]) {
            for (; $opened < $waiting; ++$opened) {
                $this->ticket($this->member(), \sprintf('Request %d', $opened));
            }

            $collected = $digest->collect(false);

            self::assertCount($lines, $collected['lines'], \sprintf('%d waiting', $waiting));
            self::assertSame(0 === $waiting, '' === $collected['summary'], 'no deeds — no summary');
            if ('' !== $rest) {
                self::assertStringContainsString($rest, (string) end($collected['lines']), 'the last line names the rest');
            }
        }
    }
}
