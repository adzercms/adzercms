<?php

declare(strict_types=1);

namespace AdzerCMS\Support\Tests;

use AdzerCMS\Core\Attachment\Attachments;
use AdzerCMS\Core\Security\CsrfGuard;
use AdzerCMS\Support\Entity\TicketMessage;
use AdzerCMS\Support\Tickets;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestWith;

final class MessageEditTest extends SupportTestCase
{
    #[Group('ACMS-SUP-0005')]
    #[Group('ACMS-SUP-0020')]
    #[Group('ACMS-CORE-0086')]
    #[Group('ACMS-US-0008')]
    public function testStaffEditsAndRemovesMessageWhileUserCannot(): void
    {
        $user = $this->member();
        $ticket = $this->ticket($user, 'No connection');
        $message = $this->messagesOf($ticket)[0];
        $action = \sprintf('/support/message/%d/', $message->getId());

        $this->client->loginUser($user);
        $this->client->request('POST', $action.'edit', [CsrfGuard::FIELD => $this->formToken(CsrfGuard::TOKEN_ID), 'body' => 'forgery']);

        self::assertResponseRedirects('/', null, 'a closed action leads the user to the home page without the edit');
        self::assertSame('First message', $this->messagesOf($ticket)[0]->getBody());

        $this->client->loginUser($this->staff);
        $crawler = $this->client->request('GET', \sprintf('/support/%d', $ticket->getId()));
        $this->client->submit($crawler->filter(\sprintf('form[action="%sedit"]', $action))->form(['body' => 'The internet is gone since morning']));
        $this->client->followRedirect();

        $edited = $this->messagesOf($ticket)[0];
        self::assertSame('The internet is gone since morning', $edited->getBody());
        self::assertNotNull($edited->getEditedAt());
        self::assertSelectorExists('.chat-edited');

        $this->client->loginUser($user);
        $this->client->request('GET', \sprintf('/support/%d', $ticket->getId()));
        self::assertSelectorNotExists('.chat-edited', 'only the operator sees the edit mark');

        $this->client->loginUser($this->staff);
        $crawler = $this->client->request('GET', \sprintf('/support/%d', $ticket->getId()));
        $this->client->submit($crawler->filter(\sprintf('form[action="%sdrop"]', $action))->form());
        $this->client->followRedirect();

        self::assertSame([], $this->messagesOf($ticket));
    }

    #[Group('ACMS-SUP-0002')]
    #[Group('ACMS-US-0008')]
    public function testClosedTicketTakesNeitherEditNorRemoval(): void
    {
        $ticket = $this->ticket($this->member(), 'No connection');
        $message = $this->messagesOf($ticket)[0];
        self::service(Tickets::class)->close($ticket);

        $this->client->loginUser($this->staff);

        foreach (['edit', 'drop'] as $action) {
            $this->client->request('POST', \sprintf('/support/message/%d/%s', $message->getId(), $action), [CsrfGuard::FIELD => $this->formToken(CsrfGuard::TOKEN_ID), 'body' => 'edit']);
            $this->client->followRedirect();

            self::assertSelectorExists('.toast-error');
        }

        self::assertSame('First message', $this->messagesOf($ticket)[0]->getBody());
    }

    #[Group('ACMS-SUP-0020')]
    #[Group('ACMS-CORE-0086')]
    #[Group('ACMS-US-0008')]
    #[TestWith([false])]
    #[TestWith([true])]
    public function testOwnerReadingLocksSupportAnswer(bool $impersonating): void
    {
        $user = $this->member();
        $ticket = $this->ticket($user, 'No connection');
        self::assertNull(self::service(Tickets::class)->reply($ticket, $this->staff, true, 'Reboot the router'));
        $answer = $this->messagesOf($ticket)[1];
        $page = \sprintf('/support/%d', $ticket->getId());
        $action = \sprintf('/support/message/%d/', $answer->getId());

        if ($impersonating) {
            $this->client->loginUser($this->staff);
            $this->arrive($page.'?_switch_user='.$user->getUserIdentifier());
        } else {
            $this->client->loginUser($user);
            $this->client->request('GET', $page);
        }
        self::assertSelectorTextContains('.chat', 'Reboot the router');

        $this->client->loginUser($this->staff);
        $crawler = $this->client->request('GET', $page);
        self::assertCount($impersonating ? 0 : 1, $crawler->filter(\sprintf('form[action="%sredact"]', $action)), 'a read answer offers redaction instead of edit');

        foreach (['edit', 'drop'] as $act) {
            $this->client->request('POST', $action.$act, [CsrfGuard::FIELD => $this->formToken(CsrfGuard::TOKEN_ID), 'body' => 'Restart the router']);
            $this->client->followRedirect();

            if (!$impersonating) {
                self::assertSelectorExists('.toast-error');
            }
        }

        self::assertSame($impersonating ? [] : ['Reboot the router'], array_map(static fn (TicketMessage $message): string => $message->getBody(), \array_slice($this->messagesOf($ticket), 1)));
    }

    #[Group('ACMS-SUP-0021')]
    #[Group('ACMS-US-0008')]
    #[DataProvider('redactions')]
    public function testStaffRedactsOneFragmentOfReadAnswerInAnyState(bool $read, string $left, ?string $redacted): void
    {
        $user = $this->member();
        $ticket = $this->ticket($user, 'Access');
        $whole = "Your password is hunter2\nKeep it safe";
        self::assertNull(self::service(Tickets::class)->reply($ticket, $this->staff, true, $whole, [$this->scratchFile('keys.txt', 'secret')]));
        $answer = $this->messagesOf($ticket)[1];
        $attachment = $this->attachmentsOf($answer)[0] ?? self::fail('the attachment is not kept');
        $path = self::service(Attachments::class)->path($attachment);
        $page = \sprintf('/support/%d', $ticket->getId());

        if ($read) {
            $this->client->loginUser($user);
            $this->client->request('GET', $page);
        }
        self::service(Tickets::class)->close($ticket);

        $this->client->loginUser($this->staff);
        $this->client->request('POST', \sprintf('/support/message/%d/redact', $answer->getId()), [
            CsrfGuard::FIELD => $this->formToken(CsrfGuard::TOKEN_ID),
            'body' => $left,
            'dropped' => [(string) $attachment->getId()],
        ]);
        $this->client->followRedirect();

        $answer = $this->messagesOf($ticket)[1];
        if (null === $redacted) {
            self::assertSelectorExists('.toast-error');
            self::assertSame([$whole, 1], [$answer->getBody(), \count($this->attachmentsOf($answer))]);

            return;
        }

        self::assertSame([$redacted, 0, 1, null], [$answer->getBody(), \count($this->attachmentsOf($answer)), $answer->getRedactedFiles(), $answer->getEditedAt()]);
        self::assertFileDoesNotExist($path);

        foreach ([$this->staff, $user] as $viewer) {
            $this->client->loginUser($viewer);
            $crawler = $this->client->request('GET', $page);

            self::assertCount(2, $crawler->filter('.chat-redacted'), 'both sides see the marks of the text and the file');
            self::assertStringNotContainsString('hunter2', $crawler->filter('.chat')->text());
        }
    }

    /** @return iterable<string, array{bool, string, ?string}> */
    public static function redactions(): iterable
    {
        yield 'one fragment' => [true, "Your password is \nKeep it safe", "Your password is \u{FFFC}\nKeep it safe"];
        yield 'line breaks of the form' => [true, "Your password is \r\nKeep it safe", "Your password is \u{FFFC}\nKeep it safe"];
        yield 'two fragments' => [true, "Your password is \nKeep it", null];
        yield 'rewording' => [true, "Your password is secret\nKeep it safe", null];
        yield 'unread answer' => [false, "Your password is \nKeep it safe", null];
    }

    #[Group('ACMS-SUP-0021')]
    #[Group('ACMS-US-0008')]
    public function testSidesCannotWriteTheRedactionMark(): void
    {
        $ticket = $this->ticket($this->member(), 'Access', body: "Removed \u{FFFC}by support");

        self::assertSame('Removed by support', $this->messagesOf($ticket)[0]->getBody());
    }
}
