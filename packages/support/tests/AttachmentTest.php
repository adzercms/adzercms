<?php

declare(strict_types=1);

namespace AdzerCMS\Support\Tests;

use AdzerCMS\Core\Attachment\Attachments;
use AdzerCMS\Core\Security\CsrfGuard;
use AdzerCMS\Support\Entity\TicketAttachment;
use AdzerCMS\Support\Repository\TicketAttachmentRepository;
use AdzerCMS\Support\SupportSettings;
use PHPUnit\Framework\Attributes\Group;

final class AttachmentTest extends SupportTestCase
{
    #[Group('ACMS-CORE-0029')]
    #[Group('ACMS-SUP-0006')]
    #[Group('ACMS-US-0008')]
    public function testAttachmentReachesOnlyTheSidesOfItsTicket(): void
    {
        $user = $this->member();
        $stranger = $this->member();

        $this->client->loginUser($user);
        $this->open(['subject' => 'Screenshot', 'body' => 'Here is the error'], [$this->picture('shot.png'), $this->scratchFile('fake.png', 'not a picture')]);

        $ticket = $this->ticketOf($user) ?? self::fail('no ticket was opened');
        [$attachment, $fake] = $this->attachmentsOf($this->messagesOf($ticket)[0]) + [null, null];
        self::assertInstanceOf(TicketAttachment::class, $attachment, 'the attachment is not kept');
        self::assertInstanceOf(TicketAttachment::class, $fake, 'the attachment is not kept');
        self::assertSame(['shot.png', 'image/png'], [$attachment->getFile()->getName(), $attachment->getFile()->getMime()]);
        self::assertSame(['fake.png', 'text/plain'], [$fake->getFile()->getName(), $fake->getFile()->getMime()], 'the type follows the content, not the name');
        $address = \sprintf('/support/attachment/%d', $attachment->getId());

        foreach ([[$attachment, 'inline'], [$fake, 'attachment']] as [$file, $disposition]) {
            $this->client->request('GET', \sprintf('/support/attachment/%d', $file->getId()));
            self::assertResponseIsSuccessful();
            self::assertResponseHeaderSame('X-Content-Type-Options', 'nosniff');
            self::assertStringStartsWith($disposition, (string) $this->client->getResponse()->headers->get('Content-Disposition'), 'only pictures and PDF show in the browser');
        }

        $this->client->loginUser($stranger);
        $this->client->request('GET', $address);
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', \sprintf('/support/%d', $ticket->getId()));
        self::assertResponseStatusCodeSame(404);

        $this->client->loginUser($this->staff);
        $this->client->request('GET', $address);
        self::assertResponseIsSuccessful();
    }

    #[Group('ACMS-SUP-0007')]
    #[Group('ACMS-US-0008')]
    public function testAttachmentBeyondTypeOrSizeIsNotStored(): void
    {
        $this->configure([SupportSettings::ATTACHMENT_MAX_MB => '1']);
        $user = $this->member();
        $this->client->loginUser($user);

        foreach ([$this->scratchFile('dump.exe', 'MZ'), $this->scratchFile('big.log', str_repeat('x', 1024 * 1024 + 1))] as $file) {
            $this->open(['subject' => 'Archive', 'body' => 'Logs'], [$file]);

            self::assertSelectorExists('.toast-error');
            self::assertNull($this->ticketOf($user));
        }
    }

    #[Group('ACMS-SUP-0007')]
    #[Group('ACMS-US-0008')]
    public function testDroppedAttachmentLeavesNeitherRecordNorFile(): void
    {
        foreach (['edit', 'drop'] as $action) {
            $user = $this->member();
            $this->client->loginUser($user);
            $this->open(['subject' => 'Screenshot', 'body' => 'Here is the error'], [$this->picture('shot.png')]);

            $message = $this->messagesOf($this->ticketOf($user) ?? self::fail('no ticket was opened'))[0];
            $attachment = $this->attachmentsOf($message)[0] ?? self::fail('the attachment is not kept');
            $id = (int) $attachment->getId();
            $path = self::service(Attachments::class)->path($attachment);
            self::assertFileExists($path);

            $this->client->loginUser($this->staff);
            $this->client->request('POST', \sprintf('/support/message/%d/%s', $message->getId(), $action), [
                CsrfGuard::FIELD => $this->formToken(CsrfGuard::TOKEN_ID),
                'body' => 'Here is the error',
                'dropped' => [(string) $id],
            ]);
            $this->client->followRedirect();

            self::assertNull(self::service(TicketAttachmentRepository::class)->find($id), $action);
            self::assertFileDoesNotExist($path, $action);
        }
    }
}
