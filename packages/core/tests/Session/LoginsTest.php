<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Session;

use AdzerCMS\Core\Entity\Login;
use AdzerCMS\Core\Entity\User;
use AdzerCMS\Core\Repository\LoginRepository;
use AdzerCMS\Core\Session\Logins;
use AdzerCMS\Core\Tests\Accounts;
use AdzerCMS\Core\Tests\Browser;
use AdzerCMS\Core\Tests\SchemaTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

use function Symfony\Component\Clock\now;

final class LoginsTest extends SchemaTestCase
{
    use Accounts;
    use Browser;

    private const MOMENT = '2026-10-16 10:00:00';
    private const LOGIN = '89001112233';
    private const LAPTOP = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36';
    private const PHONE = 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Mobile Safari/537.36';

    #[Group('ACMS-CORE-0078')]
    #[Group('ACMS-CORE-0080')]
    #[Group('ACMS-US-0002')]
    public function testLoginIsListedByBrowserSystemAndAddressUntilLogout(): void
    {
        $user = $this->record('+7 900 111-22-33');
        $laptop = $this->browser(self::LAPTOP, '91.78.1.2');
        $this->signIn(self::LOGIN, self::PASSWORD, $laptop);
        $this->signIn(self::LOGIN, self::PASSWORD);

        self::assertCount(2, $this->logins($user));
        $entry = $this->entry($user, $laptop);
        self::assertSame('Chrome 139, Windows 10', $entry->getDevice());
        self::assertSame('91.78.1.2', $entry->getAddress());
        self::assertNull($this->entry($user, $this->client)->getPlace(), 'a private address gives no place');

        $laptop->request('GET', '/logout');

        self::assertSame([Logins::current($this->client->getRequest())], array_map(static fn (Login $login): string => $login->getHandle(), $this->logins($user)));
    }

    #[Group('ACMS-CORE-0079')]
    #[Group('ACMS-US-0002')]
    public function testEndedLoginGoesToLoginFormAndOthersStay(): void
    {
        $user = $this->record('+7 900 111-22-33');
        $phone = $this->browser(self::PHONE, '91.78.1.1');
        $laptop = $this->browser(self::LAPTOP, '91.78.1.2');
        foreach ([$phone, $laptop, $this->client] as $browser) {
            $this->signIn(self::LOGIN, self::PASSWORD, $browser);
        }
        $logins = self::service(Logins::class);

        self::assertTrue($logins->end($user, $this->entry($user, $phone)->getHandle()));
        self::assertFalse($logins->end($this->record('+7 900 444-55-66'), $this->entry($user, $laptop)->getHandle()), 'a login of another user is not ended');
        self::assertTrue($this->visit($phone)->isRedirect('http://localhost/login'), 'an ended login goes to the login form');
        self::assertTrue($this->visit($laptop)->isSuccessful());

        self::assertSame(1, $logins->endOthers($user, $this->client->getRequest()));
        self::assertTrue($this->visit($laptop)->isRedirect('http://localhost/login'));
        self::assertTrue($this->visit($this->client)->isSuccessful());
    }

    #[Group('ACMS-CORE-0077')]
    #[Group('ACMS-CORE-0078')]
    public function testOwnChangeKeepsThisLoginListedAndEndsOthers(): void
    {
        $user = $this->record('+7 900 111-22-33');
        $laptop = $this->browser(self::LAPTOP, '91.78.1.2');
        $this->signIn(self::LOGIN, self::PASSWORD, $laptop);
        $this->signIn(self::LOGIN, self::PASSWORD);

        $this->client->request('POST', '/fixture/account/password');

        self::assertSame([Logins::current($this->client->getRequest())], array_map(static fn (Login $login): string => $login->getHandle(), $this->logins($user)));
        self::assertTrue($this->visit($this->client)->isSuccessful());
        self::assertTrue($this->visit($laptop)->isRedirect('http://localhost/login'));
    }

    #[Group('ACMS-CORE-0078')]
    #[Group('ACMS-US-0002')]
    public function testRestampedAndOutdatedLoginsLeaveTheList(): void
    {
        $clock = self::mockTime(self::MOMENT);
        $user = $this->record('+7 900 111-22-33');
        $this->signIn(self::LOGIN, self::PASSWORD, $this->browser(self::LAPTOP, '91.78.1.2'));
        $this->signIn(self::LOGIN, self::PASSWORD);

        $fresh = $this->reread($user);
        $fresh->setPassword(self::service(UserPasswordHasherInterface::class)->hashPassword($fresh, 'new-password'));
        $this->em->flush();
        $this->signIn(self::LOGIN, 'new-password');

        self::assertCount(1, $this->logins($user), 'logins by the former password leave the list');
        self::assertCount(1, self::service(LoginRepository::class)->of($this->reread($user)), 'the list removes them from the registry');

        $clock->sleep(Logins::LIFETIME + Logins::STEP + 1);

        self::assertSame([], $this->logins($user), 'an expired login leaves the list');
    }

    #[Group('ACMS-CORE-0078')]
    public function testSweepRemovesOnlyOutdatedLogins(): void
    {
        $clock = self::mockTime(self::MOMENT);
        $user = $this->record('+7 900 111-22-33');
        $this->signIn(self::LOGIN, self::PASSWORD, $this->browser(self::LAPTOP, '91.78.1.2'));
        $clock->sleep(Logins::LIFETIME + Logins::STEP + 1);
        $this->signIn(self::LOGIN, self::PASSWORD);

        $sweep = self::command('adzercms:sweep');

        self::assertSame(Command::SUCCESS, $sweep->execute([]));
        self::assertStringContainsString('logins removed: 1', $sweep->getDisplay());
        self::assertCount(1, self::service(LoginRepository::class)->of($this->reread($user)));
    }

    #[Group('ACMS-CORE-0082')]
    #[Group('ACMS-US-0002')]
    public function testActivityAfterStepIsMarkedAndRenewsSessionCookie(): void
    {
        $clock = self::mockTime(self::MOMENT);
        $user = $this->record('+7 900 111-22-33');
        $this->signIn(self::LOGIN, self::PASSWORD);
        self::assertGreaterThan(0, $this->sessionCookie()?->getExpiresTime(), 'a login survives closing the browser');
        $options = self::getContainer()->getParameter('session.storage.options');
        self::assertIsArray($options);
        self::assertSame([Logins::LIFETIME, Logins::LIFETIME], [$options['cookie_lifetime'] ?? null, $options['gc_maxlifetime'] ?? null], 'the store keeps a session as long as its cookie');

        $this->visit($this->client);
        self::assertNull($this->sessionCookie(), 'activity within the mark step sends no cookie');

        $clock->sleep(Logins::STEP);
        $this->visit($this->client);

        self::assertSame(now()->getTimestamp() + Logins::LIFETIME, $this->sessionCookie()?->getExpiresTime());
        self::assertSame(now()->getTimestamp(), $this->entry($user, $this->client)->getSeenAt()->getTimestamp());
    }

    /** @return list<Login> */
    private function logins(User $user): array
    {
        return self::service(Logins::class)->of($this->reread($user));
    }

    private function entry(User $user, KernelBrowser $browser): Login
    {
        $handle = Logins::current($browser->getRequest());
        foreach (self::service(LoginRepository::class)->of($this->reread($user)) as $login) {
            if ($login->getHandle() === $handle) {
                return $login;
            }
        }

        return self::fail('the registry has no login of the browser');
    }

    private function visit(KernelBrowser $browser): Response
    {
        $browser->request('GET', '/fixture/account');

        return $browser->getResponse();
    }

    /** @phpstan-impure */
    private function sessionCookie(): ?Cookie
    {
        $name = $this->client->getRequest()->getSession()->getName();
        foreach ($this->client->getResponse()->headers->getCookies() as $cookie) {
            if ($cookie->getName() === $name) {
                return $cookie;
            }
        }

        return null;
    }
}
