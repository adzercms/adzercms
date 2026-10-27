<?php

declare(strict_types=1);

namespace AdzerCMS\Support\Tests;

use AdzerCMS\Core\Account\Role;
use AdzerCMS\Core\Entity\User;
use AdzerCMS\Core\Security\CsrfGuard;
use AdzerCMS\Core\Tests\Accounts;
use AdzerCMS\Core\Tests\Browser;
use AdzerCMS\Core\Tests\Configuration;
use AdzerCMS\Core\Tests\Delivery;
use AdzerCMS\Core\Tests\SchemaTestCase;
use AdzerCMS\Support\Entity\Ticket;
use AdzerCMS\Support\Entity\TicketAttachment;
use AdzerCMS\Support\Entity\TicketMessage;
use AdzerCMS\Support\Repository\TicketAttachmentRepository;
use AdzerCMS\Support\Repository\TicketMessageRepository;
use AdzerCMS\Support\Repository\TicketRepository;
use AdzerCMS\Support\SupportSettings;
use AdzerCMS\Support\Tickets;
use Symfony\Component\HttpFoundation\File\UploadedFile;

abstract class SupportTestCase extends SchemaTestCase
{
    use Accounts;
    use Browser;
    use Configuration;
    use Delivery;

    protected User $staff;
    private int $phones = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configure(self::MAIL);
        $this->staff = $this->member()->setRole(Role::Admin);
        $this->em->flush();
    }

    protected function member(?string $email = null): User
    {
        return $this->record(\sprintf('7999%07d', ++$this->phones), $email);
    }

    protected function ticket(User $user, string $subject, ?string $department = null, string $body = 'First message'): Ticket
    {
        $opened = self::service(Tickets::class)->open($user, $subject, $department ?? self::support()->departments()[0], $body);

        return $opened instanceof Ticket ? $opened : self::fail('no ticket was opened');
    }

    /**
     * @param array<string, string> $fields
     * @param list<UploadedFile>    $files
     */
    protected function open(array $fields, array $files = []): void
    {
        $this->client->request(
            'POST',
            '/support/new',
            $fields + ['department' => self::support()->departments()[0], CsrfGuard::FIELD => $this->formToken(CsrfGuard::TOKEN_ID)],
            ['files' => $files],
        );
    }

    protected function reply(Ticket $ticket, string $body): void
    {
        $crawler = $this->client->request('GET', \sprintf('/support/%d', $ticket->getId()));
        $this->client->submit($crawler->filter('form.chat-reply')->form(['body' => $body]));
    }

    protected function ticketOf(User $user): ?Ticket
    {
        return self::service(TicketRepository::class)->findOneBy(['user' => $user]);
    }

    /** @return list<TicketMessage> */
    protected function messagesOf(Ticket $ticket): array
    {
        return self::service(TicketMessageRepository::class)->findBy(['ticket' => $ticket], ['id' => 'ASC']);
    }

    /** @return list<TicketAttachment> */
    protected function attachmentsOf(TicketMessage $message): array
    {
        return self::service(TicketAttachmentRepository::class)->findBy(['message' => $message], ['id' => 'ASC']);
    }

    protected static function support(): SupportSettings
    {
        return self::service(SupportSettings::class);
    }
}
