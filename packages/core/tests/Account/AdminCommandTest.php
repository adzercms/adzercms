<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Account;

use AdzerCMS\Core\Account\Role;
use AdzerCMS\Core\Entity\User;
use AdzerCMS\Core\Repository\UserRepository;
use AdzerCMS\Core\Tests\SchemaTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AdminCommandTest extends SchemaTestCase
{
    #[Group('ACMS-CORE-0085')]
    #[Group('ACMS-CORE-0042')]
    #[Group('ACMS-CORE-0099')]
    public function testNewAdminIsOpenedByContactWithPasswordFromStdin(): void
    {
        $command = self::command('adzercms:admin');
        $command->setInputs(['operator-password']);

        self::assertSame(Command::SUCCESS, $command->execute(['login' => ' Operator@Example.COM ', '--stdin' => true]));

        $admin = $this->user('operator@example.com');
        self::assertSame(Role::Admin, $admin->getRole());
        self::assertSame('operator@example.com', $admin->getEmail());
        self::assertTrue(self::service(UserPasswordHasherInterface::class)->isPasswordValid($admin, 'operator-password'));
    }

    #[Group('ACMS-CORE-0085')]
    #[Group('ACMS-CORE-0068')]
    public function testRecordFoundByPhoneInAnyNotationKeepsItsPassword(): void
    {
        $this->em->persist((new User())->setPhone('+7 900 123-45-67')->setPassword('former-hash'));
        $this->em->flush();

        self::assertSame(Command::SUCCESS, self::command('adzercms:admin')->execute(['login' => '8 (900) 123 45 67']));

        $admin = $this->user('79001234567');
        self::assertSame(Role::Admin, $admin->getRole());
        self::assertSame('former-hash', $admin->getPassword());
    }

    #[Group('ACMS-CORE-0085')]
    public function testNewRecordWithoutPasswordOrWithShortOneIsRefused(): void
    {
        $bare = self::command('adzercms:admin');
        self::assertSame(Command::INVALID, $bare->execute(['login' => 'new@example.com']));
        self::assertStringContainsString('record "new@example.com" does not exist', $bare->getDisplay());

        $short = self::command('adzercms:admin');
        $short->setInputs(['short']);
        self::assertSame(Command::INVALID, $short->execute(['login' => 'new@example.com', '--stdin' => true]));
        self::assertStringContainsString('Password is shorter than 8 characters', $short->getDisplay());

        self::assertNull(self::service(UserRepository::class)->byLogin('new@example.com'));
    }

    private function user(string $login): User
    {
        return self::service(UserRepository::class)->byLogin($login) ?? self::fail(\sprintf('record %s does not exist', $login));
    }
}
