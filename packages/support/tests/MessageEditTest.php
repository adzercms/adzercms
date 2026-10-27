<?php

declare(strict_types=1);

namespace AdzerCMS\Support\Tests;

use AdzerCMS\Core\Security\CsrfGuard;
use AdzerCMS\Support\Tickets;
use PHPUnit\Framework\Attributes\Group;

final class MessageEditTest extends SupportTestCase
{
    #[Group('ACMS-SUP-0005')]
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
}
