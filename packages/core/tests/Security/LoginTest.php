<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Security;

use AdzerCMS\Core\Account\Role;
use AdzerCMS\Core\Account\Status;
use AdzerCMS\Core\Entity\User;
use AdzerCMS\Core\Security\RecordDeleted;
use AdzerCMS\Core\Tests\Accounts;
use AdzerCMS\Core\Tests\Browser;
use AdzerCMS\Core\Tests\SchemaTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\SecurityRequestAttributes;

final class LoginTest extends SchemaTestCase
{
    use Accounts;
    use Browser;

    #[Group('ACMS-CORE-0076')]
    public function testLoginIsEmailPhoneOrModuleIdentifier(): void
    {
        $user = $this->record('+7 900 111-22-33', 'owner@example.com');
        $id = $user->getUserIdentifier();

        foreach (['OWNER@example.com', '8 (900) 111-22-33', 'u'.$id] as $login) {
            $this->signIn($login, self::PASSWORD);
            self::assertResponseRedirects('/');

            $this->client->request('GET', '/fixture/account');
            self::assertSame($id, $this->client->getResponse()->getContent(), $login);

            $this->client->request('GET', '/logout');
        }

        self::assertNotNull($this->reread($user)->getLastLoginAt());
    }

    #[Group('ACMS-CORE-0088')]
    public function testRecordStateIsNamedOnlyAfterPassword(): void
    {
        $stopped = $this->record('+7 900 111-22-33')->setStatus(Status::Stopped);
        $deleted = $this->record('+7 900 444-55-66')->delete();
        $this->em->flush();

        $this->signIn('89001112233', 'wrong-password');
        self::assertInstanceOf(BadCredentialsException::class, $this->failure());
        $this->signIn('nobody@example.com', self::PASSWORD);
        self::assertInstanceOf(BadCredentialsException::class, $this->failure());

        $this->signIn('89001112233', self::PASSWORD);
        self::assertSame('core.account.stopped', $this->failure()->getMessageKey());
        $this->signIn('89004445566', self::PASSWORD);
        self::assertInstanceOf(RecordDeleted::class, $this->failure());

        self::assertSame(Status::Stopped, $this->reread($stopped)->getStatus());
        self::assertTrue($this->reread($deleted)->isDeleted());
    }

    #[Group('ACMS-CORE-0077')]
    #[Group('ACMS-TEST-0064')]
    public function testChangedPasswordStateRoleOrEmailEndsLoginAddedEmailDoesNot(): void
    {
        $user = $this->record('+7 900 111-22-33');
        $this->signIn('89001112233', self::PASSWORD);

        $this->reread($user)->setEmail('added@example.com');
        $this->em->flush();
        $this->client->request('GET', '/fixture/account');
        self::assertResponseIsSuccessful();

        $changes = [
            'password' => static fn (User $user): User => $user->setPassword('other-hash'),
            'state' => static fn (User $user): User => $user->setStatus(Status::Stopped),
            'role' => static fn (User $user): User => $user->setRole(Role::Admin),
            'email' => static fn (User $user): User => $user->setEmail('other@example.com'),
        ];
        $line = 10;
        foreach ($changes as $change => $apply) {
            $user = $this->record('+7 900 000-00-'.++$line, $change.'@example.com');
            $this->signIn($change.'@example.com', self::PASSWORD);

            $apply($this->reread($user));
            $this->em->flush();
            $this->client->request('GET', '/fixture/account');
            self::assertResponseRedirects('/login', message: $change);
        }
    }

    #[Group('ACMS-CORE-0087')]
    public function testClosedPageOfGuestLeadsToLoginAndBack(): void
    {
        $user = $this->record('+7 900 111-22-33');

        $this->client->request('GET', '/fixture/account');
        self::assertResponseRedirects('/login');

        $this->signIn('89001112233', self::PASSWORD);
        self::assertResponseRedirects('http://localhost/fixture/account');

        $this->client->request('GET', '/logout');
        $this->client->request('GET', '/fixture/account');
        $this->client->request('GET', '/fixture/entry/'.$user->getId());
        self::assertResponseRedirects('http://localhost/fixture/account', null, 'the entry of a new record leads back as well');
        $this->client->followRedirect();
        self::assertSame($user->getUserIdentifier(), $this->client->getResponse()->getContent());
    }

    #[Group('ACMS-CORE-0086')]
    public function testAdminEntersServedUserAndAdminSurfaceReturnsHim(): void
    {
        $admin = $this->record('+7 900 111-22-33')->setRole(Role::Admin);
        $other = $this->record('+7 900 444-55-66')->setRole(Role::Admin);
        $stopped = $this->record('+7 900 777-88-99')->setStatus(Status::Stopped);
        $user = $this->record('+7 900 000-00-01');
        $this->em->flush();

        $this->client->loginUser($user);
        $this->client->request('GET', '/fixture/admin');
        self::assertResponseRedirects('/', message: 'a closed surface sends the signed-in person home');

        $this->client->loginUser($admin);

        foreach ([$other, $stopped] as $refused) {
            $this->client->request('GET', '/fixture/admin?_switch_user='.$refused->getUserIdentifier());
            self::assertResponseRedirects('/');
        }

        $this->client->request('GET', '/fixture/account?_switch_user='.$user->getUserIdentifier());
        $this->client->followRedirect();
        self::assertSame($user->getUserIdentifier(), $this->client->getResponse()->getContent());

        $this->client->request('GET', '/fixture/admin');
        self::assertResponseRedirects('/fixture/admin?_switch_user=_exit');
        $this->client->followRedirect();
        $this->client->followRedirect();
        self::assertSame($admin->getUserIdentifier(), $this->client->getResponse()->getContent());
    }

    private function failure(): AuthenticationException
    {
        self::assertResponseRedirects('/login');
        $error = $this->client->getRequest()->getSession()->get(SecurityRequestAttributes::AUTHENTICATION_ERROR);

        return $error instanceof AuthenticationException ? $error : self::fail('login is not refused');
    }
}
