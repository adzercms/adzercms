<?php

declare(strict_types=1);

namespace AdzerCMS\Cabinet\Tests;

use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Core\Account\Role;
use AdzerCMS\Core\Account\Status;
use AdzerCMS\Core\Tests\Account\Fixture\OpenPolicy;
use AdzerCMS\Core\Tests\Accounts;
use AdzerCMS\Core\Tests\Browser;
use AdzerCMS\Core\Tests\Configuration;
use AdzerCMS\Core\Tests\Delivery;
use AdzerCMS\Core\Tests\SchemaTestCase;
use PHPUnit\Framework\Attributes\Group;

use function Symfony\Component\Translation\t;

final class DeletionTest extends SchemaTestCase
{
    use Accounts;
    use Browser;
    use Configuration;
    use Delivery;

    private const MAILBOX = 'abonent@example.org';

    protected function setUp(): void
    {
        parent::setUp();
        $this->configure(self::MAIL);
    }

    #[Group('ACMS-CORE-0073')]
    #[Group('ACMS-US-0002')]
    public function testDeletionLivesOnlyByPermissionOfModule(): void
    {
        $this->client->loginUser($this->record('79990000010', self::MAILBOX));

        foreach ([[false, 404], [true, 302]] as [$offered, $status]) {
            self::service(OpenPolicy::class)->deletion = $offered;

            self::assertCount((int) $offered, $this->client->request('GET', '/profile?tab=security')->filter('form[action="/profile/delete"]'));
            $this->post('/profile/delete');
            self::assertResponseStatusCodeSame($status);
            $this->client->request('GET', '/delete/'.str_repeat('0', 64));
            self::assertResponseStatusCodeSame($status);
        }
    }

    #[Group('ACMS-CORE-0074')]
    #[Group('ACMS-CORE-0075')]
    #[Group('ACMS-CORE-0077')]
    #[Group('ACMS-US-0002')]
    public function testUserDeletesRecordByMailedLinkOnConfirmationPage(): void
    {
        self::service(OpenPolicy::class)->deletion = true;
        self::mockTime('2026-10-17 10:15');
        $user = $this->record('79990000010', self::MAILBOX);
        $this->signIn('79990000010', self::PASSWORD);

        $this->client->submit($this->client->request('GET', '/profile?tab=security')->selectButton(self::phrase('cabinet.deletion.request'))->form());
        self::assertResponseRedirects('/profile?tab=security');
        self::assertSame(self::MAILBOX, $this->letter()->getTo()[0]->getAddress());
        $link = 1 === preg_match('#/delete/[0-9a-f]{64}#', $this->letterText(), $match) ? $match[0] : self::fail('the letter carries no deletion link');

        $page = $this->client->request('GET', $link);
        self::assertFalse($this->reread($user)->isDeleted(), 'opening the link deletes nothing');

        $this->client->submit($page->selectButton(self::phrase('cabinet.deletion.request'))->form());
        self::assertResponseRedirects('/login');
        self::assertSame(self::phrase('cabinet.deletion.done'), self::toast($this->client->followRedirect(), 'success'), 'the deleting browser is signed out');

        $deleted = $this->reread($user);
        self::assertSame(Status::Archived, $deleted->getStatus());
        self::assertSame('2026-10-17 10:15', $deleted->getDeletedAt()?->format('Y-m-d H:i'));
        self::assertSame(['79990000010', self::MAILBOX], [$deleted->getPhone(), $deleted->getEmail()], 'the contacts stay with the record');

        $this->client->request('GET', '/profile');
        self::assertResponseRedirects('/login');
    }

    #[Group('ACMS-CORE-0075')]
    #[Group('ACMS-UI-0029')]
    #[Group('ACMS-US-0002')]
    public function testDeletionIsRefusedOutsideItsConditions(): void
    {
        $policy = self::service(OpenPolicy::class);
        $policy->deletion = true;

        $operator = $this->record('79990000011', 'operator@example.org')->setRole(Role::Admin);
        $this->em->flush();
        $this->client->loginUser($operator);
        self::assertCount(0, $this->client->request('GET', '/profile?tab=security')->filter('form[action="/profile/delete"]'), 'an operator is not offered the deletion');
        $this->post('/profile/delete');
        self::assertSame(self::phrase('core.deletion.operator'), self::toast($this->client->followRedirect(), 'error'));

        $this->client->loginUser($this->record('79990000012'));
        $security = $this->client->request('GET', '/profile?tab=security');
        self::assertSame('/profile', $security->filter('.flash-error a')->attr('href'), 'without mail the reason leads to the contacts');
        self::assertNotNull($security->selectButton(self::phrase('cabinet.deletion.request'))->attr('disabled'));
        $this->post('/profile/delete');
        self::assertResponseRedirects('/profile');

        $user = $this->record('79990000010', self::MAILBOX);
        $this->client->loginUser($user);
        $policy->refused = new Refusal(t('Pay off the debt first'), 'cabinet_account');
        $this->post('/profile/delete');
        self::assertResponseRedirects('/');
        self::assertEmailCount(0, null, 'an unmet condition sends no link');

        $policy->refused = null;
        $this->post('/profile/delete');
        $link = 1 === preg_match('#/delete/[0-9a-f]{64}#', $this->letterText(), $match) ? $match[0] : self::fail('the letter carries no deletion link');
        $page = $this->client->request('GET', $link);
        $policy->refused = new Refusal(t('Pay off the debt first'), 'cabinet_account');
        $page = $this->client->submit($page->selectButton(self::phrase('cabinet.deletion.request'))->form());
        while ($this->client->getResponse()->isRedirection()) {
            $page = $this->client->followRedirect();
        }

        self::assertSame('Pay off the debt first', self::toast($page, 'error'), 'the condition is checked again on confirmation');
        self::assertFalse($this->reread($user)->isDeleted());
    }
}
