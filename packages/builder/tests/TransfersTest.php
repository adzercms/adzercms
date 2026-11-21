<?php

declare(strict_types=1);

namespace AdzerCMS\Builder\Tests;

use AdzerCMS\Builder\Entity\Project;
use AdzerCMS\Builder\Entity\ProjectPage;
use AdzerCMS\Builder\Entity\ProjectTransfer;
use AdzerCMS\Builder\Projects;
use AdzerCMS\Builder\Repository\ProjectTransferRepository;
use AdzerCMS\Builder\TransferOutcome;
use AdzerCMS\Builder\Transfers;
use AdzerCMS\Core\Entity\User;
use AdzerCMS\Core\Tests\Accounts;
use AdzerCMS\Core\Tests\Browser;
use AdzerCMS\Core\Tests\Configuration;
use AdzerCMS\Core\Tests\Delivery;
use AdzerCMS\Core\Tests\SchemaTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Mime\Email;

use function Symfony\Component\Translation\t;

final class TransfersTest extends SchemaTestCase
{
    use Accounts;
    use Browser;
    use Configuration;
    use Delivery;

    private const SECTION = '/builder/projects';

    private User $sender;
    private User $recipient;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        self::mockTime('2026-11-21 10:00');
        $this->configure(self::MAIL);
        $this->sender = $this->record('79990000002', 'sender@example.org');
        $this->recipient = $this->record('79990000003', 'recipient@example.org');
        $this->project = new Project($this->sender, 'Fiber');
        $home = new ProjectPage($this->project, 'Fiber', '/');
        $home->place(1);
        $this->em->persist($this->project);
        $this->em->persist($home);
        $this->em->flush();
        $this->client->disableReboot();
        $this->client->loginUser($this->sender);
    }

    #[Group('ACMS-BLD-0018')]
    #[Group('ACMS-US-0078')]
    public function testOwnerHandsProjectOverAndRecipientAcceptsItInTheCabinet(): void
    {
        $base = self::SECTION.'/'.$this->project->getId();
        $this->post($base.'/transfer', [Transfers::EMAIL => ' Recipient@Example.org ']);
        self::assertResponseHeaderSame('HX-Redirect', $base);
        self::assertSame(['recipient@example.org'], array_map(static fn ($address): string => $address->getAddress(), $this->letter()->getTo()), 'the recipient learns of the transfer');
        self::assertStringContainsString('Fiber', (string) $this->letter()->getSubject());
        self::assertCount(1, $this->client->request('GET', $base)->filter('[data-transfer]'), 'the owner sees the transfer waiting');

        $this->client->loginUser($this->recipient);
        $offers = $this->client->request('GET', self::SECTION)->filter('[data-offer]');
        self::assertCount(1, $offers, 'the recipient finds the transfer in the cabinet');
        $this->post((string) $offers->attr('action'));
        self::assertResponseRedirects($base);
        self::assertSame(['sender@example.org'], array_map(static fn ($address): string => $address->getAddress(), $this->letter()->getTo()), 'the sender learns of the acceptance');
        self::assertStringContainsString(self::phrase(TransferOutcome::Accepted->label()), $this->letterText());

        $this->em->clear();
        self::assertSame($this->recipient->getId(), $this->em->find(Project::class, $this->project->getId())?->getOwner()->getId(), 'the project goes to the recipient');
        self::assertSame([], self::service(ProjectTransferRepository::class)->findAll());
        $this->client->loginUser($this->sender);
        $this->client->request('GET', $base);
        self::assertResponseStatusCodeSame(404, 'the project leaves the sender');
    }

    #[Group('ACMS-BLD-0018')]
    #[Group('ACMS-US-0078')]
    #[DataProvider('recipients')]
    public function testTransferGoesOnlyToAnotherRegisteredUser(string $email, string $reason): void
    {
        $this->post(self::SECTION.'/'.$this->project->getId().'/transfer', [Transfers::EMAIL => $email]);

        self::assertSelectorTextContains('.modal-body', self::phrase(t($reason, ['email' => $email])));
        self::assertSame([], self::service(ProjectTransferRepository::class)->findAll());
    }

    /** @return iterable<string, array{string, string}> */
    public static function recipients(): iterable
    {
        yield 'email nobody registered' => ['nobody@example.org', 'builder.refusal.recipient'];
        yield 'not an email' => ['recipient', 'builder.refusal.recipient'];
        yield 'email of the owner' => ['sender@example.org', 'builder.refusal.recipient_owner'];
    }

    #[Group('ACMS-BLD-0018')]
    #[Group('ACMS-US-0078')]
    public function testSenderCancelsTransferBeforeAcceptance(): void
    {
        $transfer = $this->offer();
        $this->post(self::SECTION.'/'.$this->project->getId().'/transfer/cancel');

        self::assertSame(['recipient@example.org'], array_map(static fn ($address): string => $address->getAddress(), $this->letter()->getTo()), 'the recipient learns of the cancel');
        self::assertStringContainsString(self::phrase(TransferOutcome::Cancelled->label()), $this->letterText());
        $this->client->loginUser($this->recipient);
        self::assertCount(0, $this->client->request('GET', self::SECTION)->filter('[data-offer]'));
        $this->post(self::SECTION.'/transfers/'.$transfer.'/accept');
        self::assertResponseStatusCodeSame(404, 'a cancelled transfer is not accepted');
    }

    #[Group('ACMS-BLD-0018')]
    #[Group('ACMS-US-0078')]
    public function testTransferNotAcceptedInTermLeavesWithNoticeToBoth(): void
    {
        $transfer = $this->offer();
        self::mockTime('2026-11-28 10:01');
        $this->client->loginUser($this->recipient);
        self::assertCount(0, $this->client->request('GET', self::SECTION)->filter('[data-offer]'), 'a transfer past its term is not offered');
        $this->post(self::SECTION.'/transfers/'.$transfer.'/accept');
        self::assertResponseStatusCodeSame(404);

        $console = self::command('adzercms:builder');
        self::assertSame(Command::SUCCESS, $console->execute([]));
        self::assertStringContainsString('transfers expired: 1', $console->getDisplay());
        self::assertSame([], self::service(ProjectTransferRepository::class)->findAll());
        $letters = array_values(array_filter(self::getMailerMessages(), static fn (object $message): bool => $message instanceof Email));
        self::assertSame(['sender@example.org', 'recipient@example.org'], array_map(static fn (Email $letter): string => $letter->getTo()[0]->getAddress(), $letters), 'the sender and the recipient learn of the term');
        self::assertStringContainsString(self::phrase(TransferOutcome::Expired->label()), (string) $letters[0]->getTextBody());
    }

    #[Group('ACMS-BLD-0018')]
    #[Group('ACMS-BLD-0020')]
    #[Group('ACMS-US-0078')]
    public function testAcceptanceOverLimitOfProjectsIsRefused(): void
    {
        $transfer = $this->offer();
        for ($at = 1; $at <= Projects::PROJECTS; ++$at) {
            $this->em->persist(new Project($this->recipient, 'Project '.$at));
        }
        $this->em->flush();

        $this->client->loginUser($this->recipient);
        $this->post(self::SECTION.'/transfers/'.$transfer.'/accept');

        self::assertSame(self::phrase(t('builder.refusal.projects', ['limit' => Projects::PROJECTS])), self::toast($this->client->followRedirect(), 'error'));
        self::assertSame($this->sender->getId(), $this->reread($this->project)->getOwner()->getId());
    }

    private function offer(): int
    {
        $transfer = new ProjectTransfer($this->project, $this->recipient, new \DateTimeImmutable('2026-11-28 10:00'));
        $this->em->persist($transfer);
        $this->em->flush();

        return (int) $transfer->getId();
    }
}
