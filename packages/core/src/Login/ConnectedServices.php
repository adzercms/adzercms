<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Login;

use AdzerCMS\Contracts\Login\LoginService;
use AdzerCMS\Contracts\Login\Profile;
use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Contracts\Ui\CabinetEntry;
use AdzerCMS\Core\Account\ContactKind;
use AdzerCMS\Core\Account\Registration;
use AdzerCMS\Core\Entity\ConnectedService;
use AdzerCMS\Core\Entity\User;
use AdzerCMS\Core\Module\Switches;
use AdzerCMS\Core\Repository\ConnectedServiceRepository;
use AdzerCMS\Core\Repository\UserRepository;
use AdzerCMS\Core\Security\RecordDeleted;
use AdzerCMS\Core\Security\UserChecker;
use AdzerCMS\Core\Ui\Entries;
use Doctrine\ORM\EntityManagerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Security\Core\Exception\AccountStatusException;
use Symfony\Contracts\Translation\TranslatableInterface;

use function Symfony\Component\Translation\t;

final class ConnectedServices
{
    public function __construct(
        private readonly ConnectedServiceRepository $connected,
        private readonly UserRepository $users,
        private readonly Switches $switches,
        private readonly UserChecker $checker,
        private readonly EntityManagerInterface $em,
        private readonly EventDispatcherInterface $events,
        private readonly Entries $entries,
        private readonly Registration $registration,
    ) {
    }

    public function enter(string $service, Profile $profile): User|Refusal
    {
        $known = $this->connected->profile($service, $profile->id);
        $user = $known?->getUser();
        foreach (self::contacts($profile) as [, $contact]) {
            $user ??= $this->users->byLogin($contact);
        }

        if (null === $user && !$this->registration->offered()) {
            return new Refusal(t('core.login.unknown', ['service' => $this->name($service)]));
        }
        $user ??= $this->registration->open(ContactKind::Phone->normalize($profile->phone), ContactKind::Email->normalize($profile->email));
        try {
            $this->checker->checkPostAuth($user);
        } catch (RecordDeleted $deleted) {
            return $this->entries->refusal(t($deleted->getMessageKey(), $deleted->getMessageData(), 'security'), CabinetEntry::Forgot);
        } catch (AccountStatusException $refused) {
            return new Refusal(t($refused->getMessageKey(), $refused->getMessageData(), 'security'));
        }

        return $this->connect($known, $user, $service, $profile) ?? $user;
    }

    public function link(User $user, string $service, Profile $profile): ?Refusal
    {
        $known = $this->connected->profile($service, $profile->id);
        if (null !== $known && $known->getUser() !== $user) {
            return new Refusal(t('core.login.taken', ['service' => $this->name($service)]));
        }

        return $this->connect($known, $user, $service, $profile);
    }

    public function unlink(User $user, string $service): ?Refusal
    {
        $connected = $this->connected->of($user);
        $link = $connected[$service] ?? null;
        if (null === $link) {
            return new Refusal(t('core.login.absent'));
        }

        $ways = array_intersect_key($this->switches->enabled(LoginService::class), $connected);
        unset($ways[$service]);
        if (null === $user->getPassword() && [] === $ways) {
            return $this->entries->refusal(t('core.login.last_way'), CabinetEntry::Security);
        }

        $this->em->remove($link);
        $this->em->flush();

        return null;
    }

    public function name(string $service): TranslatableInterface
    {
        [$module] = $this->switches->enabled(LoginService::class)[$service]
            ?? throw new \LogicException(\sprintf('login service %s is not enabled', $service));

        return $module->name();
    }

    private function connect(?ConnectedService $known, User $user, string $service, Profile $profile): ?Refusal
    {
        if (null === $known) {
            if (isset($this->connected->of($user)[$service])) {
                return new Refusal(t('core.login.other', ['service' => $this->name($service)]));
            }
            $this->em->persist(new ConnectedService($user, $service, $profile->id, $profile->account));
        } else {
            $known->name($profile->account);
        }

        if (!$user->isNamed()) {
            $user->setName($profile->lastName, $profile->firstName, null);
        }
        foreach (self::contacts($profile) as [$kind, $contact]) {
            if (null === $user->getContact($kind) && null === $this->users->byLogin($contact)) {
                $user->setContact($kind, $contact);
            }
        }
        $this->em->flush();
        if (null === $known) {
            $this->events->dispatch(new ServiceConnected($user, $service, $profile));
        }

        return null;
    }

    /** @return list<array{ContactKind, string}> */
    private static function contacts(Profile $profile): array
    {
        $contacts = [];
        foreach ([[ContactKind::Phone, $profile->phone], [ContactKind::Email, $profile->email]] as [$kind, $value]) {
            $contact = $kind->normalize($value);
            if (null !== $contact) {
                $contacts[] = [$kind, $contact];
            }
        }

        return $contacts;
    }
}
