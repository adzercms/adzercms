<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Command;

use AdzerCMS\Core\Account\ContactKind;
use AdzerCMS\Core\Account\Password;
use AdzerCMS\Core\Account\Role;
use AdzerCMS\Core\Entity\User;
use AdzerCMS\Core\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsCommand(name: 'adzercms:admin', description: 'Administrator role for a record by login; a new record needs a password')]
final class AdminCommand
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly EntityManagerInterface $em,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function __invoke(
        InputInterface $input,
        SymfonyStyle $io,
        #[Argument('Record login: phone or email')] string $login,
        #[Option('Read the password from stdin')] bool $stdin = false,
    ): int {
        $password = $stdin ? rtrim(Body::of($input), "\r\n") : '';
        $refusal = '' === $password ? null : Password::refusal($password);
        if (null !== $refusal) {
            $io->error($refusal->reason->trans($this->translator));

            return Command::INVALID;
        }

        $user = $this->users->byLogin($login) ?? $this->open($login, $password);
        if (null === $user) {
            $io->error(\sprintf('record "%s" does not exist, a new one needs a phone or email login and a password: --stdin', $login));

            return Command::INVALID;
        }

        $user->setRole(Role::Admin);
        if ('' !== $password) {
            $user->setPassword($this->hasher->hashPassword($user, $password));
        }
        $this->em->flush();
        $io->success(\sprintf('%s is an administrator', $login));

        return Command::SUCCESS;
    }

    private function open(string $login, string $password): ?User
    {
        $kind = ContactKind::of($login);
        if ('' === $password || null === $kind->normalize($login)) {
            return null;
        }

        $user = (new User())->setContact($kind, $login);
        $this->em->persist($user);

        return $user;
    }
}
