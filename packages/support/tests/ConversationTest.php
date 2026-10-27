<?php

declare(strict_types=1);

namespace AdzerCMS\Support\Tests;

use AdzerCMS\Core\Account\LoginNames;
use AdzerCMS\Core\Security\CsrfGuard;
use AdzerCMS\Support\SupportSettings;
use AdzerCMS\Support\Tests\Fixture\SubjectFixture;
use AdzerCMS\Support\TicketStatus;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DomCrawler\Crawler;

final class ConversationTest extends SupportTestCase
{
    #[Group('ACMS-SUP-0001')]
    #[Group('ACMS-US-0008')]
    public function testUserOpensTicketWithDepartmentSubjectAndMessage(): void
    {
        $user = $this->member();
        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', '/support/new');
        self::assertSame(self::support()->departments(), $crawler->filter('select[name="department"] option')->each(static fn (Crawler $option): string => (string) $option->attr('value')));

        $this->client->submit($crawler->filter('form[enctype="multipart/form-data"]')->form(['subject' => 'No connection', 'body' => 'The internet is gone']));

        $ticket = $this->ticketOf($user) ?? self::fail('no ticket was opened');
        self::assertResponseRedirects(\sprintf('/support/%d', $ticket->getId()));
        self::assertSame('No connection', $ticket->getSubject());
        self::assertSame(self::support()->departments()[0], $ticket->getDepartment());
        self::assertSame(TicketStatus::Open, $ticket->getStatus());

        $messages = $this->messagesOf($ticket);
        self::assertCount(1, $messages);
        self::assertFalse($messages[0]->isFromStaff());
        self::assertSame($user->getId(), $messages[0]->getAuthor()?->getId());
    }

    #[Group('ACMS-SUP-0001')]
    #[Group('ACMS-US-0008')]
    public function testEmptySubjectOrMessageOpensNothing(): void
    {
        $user = $this->member();
        $this->client->loginUser($user);

        foreach ([['subject' => '  ', 'body' => 'text'], ['subject' => 'Subject', 'body' => '   ']] as $fields) {
            $this->open($fields);

            self::assertResponseIsSuccessful();
            self::assertSelectorExists('.toast-error');
            self::assertNull($this->ticketOf($user));
        }
    }

    #[Group('ACMS-SUP-0011')]
    #[Group('ACMS-US-0008')]
    public function testSubjectOfModuleIsCheckedBeforeOpeningAndLinkedForTheViewer(): void
    {
        self::service(SubjectFixture::class)->offered = true;
        $user = $this->member();
        $this->client->loginUser($user);

        self::assertCount(1, $this->client->request('GET', '/support/new')->filter(\sprintf('input[name="%s"]', SubjectFixture::FIELD)), 'the module offers its field');

        $this->open(['subject' => 'Broken item', 'body' => 'It does not start', SubjectFixture::FIELD => 'foreign']);
        self::assertSelectorTextContains('.toast-error', 'Not your item');
        self::assertNull($this->ticketOf($user), 'a subject refused by the module opens nothing');

        $this->open(['subject' => 'Broken item', 'body' => 'It does not start', SubjectFixture::FIELD => SubjectFixture::OWN]);
        $page = \sprintf('/support/%d', ($this->ticketOf($user) ?? self::fail('no ticket was opened'))->getId());

        foreach ([$user, $this->staff] as $viewer) {
            $this->client->loginUser($viewer);
            self::assertSame(\sprintf('/items/%s/%d', SubjectFixture::OWN, $viewer->getId()), $this->client->request('GET', $page)->filter('.ticket-side a.subject')->attr('href'));
        }
    }

    #[Group('ACMS-SUP-0002')]
    #[Group('ACMS-SUP-0009')]
    #[Group('ACMS-US-0008')]
    public function testAnswersMoveTheTicketBetweenSidesUntilItIsClosed(): void
    {
        $user = $this->member('human@example.org');
        $ticket = $this->ticket($user, 'No connection');
        $page = \sprintf('/support/%d', $ticket->getId());

        $this->client->loginUser($this->staff);
        $this->reply($ticket, 'Checking');

        self::assertEmailCount(1);
        self::assertStringContainsString((string) $ticket->getId(), (string) $this->letter()->getSubject());
        self::assertStringContainsString($page.'"', (string) $this->letter()->getHtmlBody(), 'the letter leads to the ticket');
        self::assertSame(TicketStatus::WaitingUser, $this->reread($ticket)->getStatus());

        $this->client->loginUser($user);
        $this->reply($ticket, 'Still broken');

        self::assertSame(TicketStatus::WaitingStaff, $this->reread($ticket)->getStatus());

        $this->client->submit($this->client->request('GET', $page)->filter('form[action$="/close"]')->form());

        self::assertResponseRedirects('/support');
        self::assertSame(TicketStatus::Closed, $this->reread($ticket)->getStatus());

        $this->client->request('POST', $page.'/reply', [CsrfGuard::FIELD => $this->formToken(CsrfGuard::TOKEN_ID), 'body' => 'one more question']);
        $this->client->followRedirect();

        self::assertSelectorExists('.toast-error');
        self::assertCount(3, $this->messagesOf($ticket));
    }

    #[Group('ACMS-SUP-0001')]
    #[Group('ACMS-US-0008')]
    public function testSupportAnswerIsSignedByDepartmentAndSidesAreOpposite(): void
    {
        $user = $this->member();
        $ticket = $this->ticket($user, 'No connection');
        $page = \sprintf('/support/%d', $ticket->getId());

        $this->client->loginUser($this->staff);
        $this->reply($ticket, 'Checking');

        $this->client->loginUser($user);
        $crawler = $this->client->request('GET', $page);

        self::assertSame([self::service(LoginNames::class)->of($user), $ticket->getDepartment()], $crawler->filter('.chat-author')->each(static fn (Crawler $author): string => $author->text()));
        self::assertSame(['own', 'other'], self::sides($crawler));

        $this->client->loginUser($this->staff);

        self::assertSame(['other', 'own'], self::sides($this->client->request('GET', $page)));
    }

    #[Group('ACMS-CORE-0067')]
    #[Group('ACMS-US-0008')]
    public function testStaffSeesCommentAndNotesOfTheUserInTicket(): void
    {
        $user = $this->member()->setComment('Daisy LLC')->setNote("pays by invoice\ncall in the afternoon");
        $this->em->flush();
        $page = \sprintf('/support/%d', $this->ticket($user, 'No connection')->getId());

        $this->client->loginUser($this->staff);
        $side = $this->client->request('GET', $page)->filter('.ticket-side')->text();

        self::assertStringContainsString('Daisy LLC', $side);
        self::assertStringContainsString('call in the afternoon', $side);

        $this->client->loginUser($user);
        $side = $this->client->request('GET', $page)->filter('.ticket-side')->text();

        self::assertStringNotContainsString('Daisy', $side, 'the operator comment does not reach the cabinet');
        self::assertStringNotContainsString('call in the afternoon', $side, 'notes do not reach the cabinet');
    }

    #[Group('ACMS-SUP-0003')]
    #[Group('ACMS-US-0008')]
    public function testDepartmentsOfSeparatorsOnlyReadAsTheDefaultNames(): void
    {
        $default = self::support()->departments();

        $this->configure([SupportSettings::DEPARTMENTS => ' , , ']);

        self::assertSame($default, self::support()->departments());
    }

    /** @return list<string> */
    private static function sides(Crawler $crawler): array
    {
        return $crawler->filter('.chat-message')->each(static fn (Crawler $message): string => trim(str_replace('chat-message', '', (string) $message->attr('class'))));
    }
}
