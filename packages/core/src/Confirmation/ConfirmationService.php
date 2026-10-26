<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Confirmation;

use AdzerCMS\Contracts\Ui\CabinetEntry;
use AdzerCMS\Core\Account\ContactConfirmed;
use AdzerCMS\Core\Account\ContactKind;
use AdzerCMS\Core\Account\ContactOutcome;
use AdzerCMS\Core\Account\Registration;
use AdzerCMS\Core\Account\Status;
use AdzerCMS\Core\Entity\Confirmation;
use AdzerCMS\Core\Entity\Notice;
use AdzerCMS\Core\Entity\User;
use AdzerCMS\Core\Notice\CoreEvent;
use AdzerCMS\Core\Notice\Notifier;
use AdzerCMS\Core\Repository\ConfirmationRepository;
use AdzerCMS\Core\Repository\UserRepository;
use AdzerCMS\Core\Ui\Entries;
use Doctrine\ORM\EntityManagerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final readonly class ConfirmationService
{
    public function __construct(
        private UserRepository $users,
        private ConfirmationRepository $confirmations,
        private Confirmations $secrets,
        private Notifier $notifier,
        private Registration $registration,
        private UserPasswordHasherInterface $hasher,
        private Entries $entries,
        private EntityManagerInterface $em,
        private EventDispatcherInterface $events,
    ) {
    }

    public function request(string $login): bool
    {
        $user = $this->users->byLogin($login);
        if (null === $user || (Status::Active !== $user->getStatus() && !$user->isDeleted())) {
            return false;
        }

        $phone = $user->getPhone();
        if (null === $phone || !$this->notifier->reachable(ContactKind::Phone)) {
            return ContactOutcome::Sent === $this->requestLink($user);
        }

        $code = $this->secrets->code(Purpose::Password, $user);

        return null !== $code && self::sent($this->notifier->contact($phone, CoreEvent::PasswordReset, [
            'code' => $code,
            'minutes' => Kind::Code->minutes(),
        ], $user));
    }

    public function requestLink(User $user): ContactOutcome
    {
        return $this->mailLink($user, Purpose::Password, CabinetEntry::PasswordChange, CoreEvent::PasswordReset);
    }

    public function requestDeletion(User $user): ContactOutcome
    {
        return $this->mailLink($user, Purpose::Deletion, CabinetEntry::Deletion, CoreEvent::AccountDeletion);
    }

    public function requestRegistration(string $contact): ContactOutcome
    {
        $kind = ContactKind::of($contact);
        $contact = $kind->normalize($contact);
        if (null === $contact || !$this->notifier->reachable($kind)) {
            return ContactOutcome::Unavailable;
        }

        $code = $this->secrets->code(Purpose::Registration, null, $contact);
        if (null === $code) {
            return ContactOutcome::TooOften;
        }

        $known = $this->byContact($kind, $contact);

        return self::outcome(null === $known
            ? $this->notifier->contact($contact, CoreEvent::Registration, ['code' => $code, 'minutes' => Kind::Code->minutes()])
            : $this->notifier->contact($contact, CoreEvent::RegistrationKnown, [
                'contact' => $contact,
                'url' => $this->url(CabinetEntry::Forgot, ['login' => $contact]),
            ], $known));
    }

    public function requestContact(User $user, string $wanted): ContactOutcome
    {
        $kind = ContactKind::of($wanted);
        $contact = $this->wanted($kind, $wanted);
        if ($contact instanceof ContactOutcome) {
            return $contact;
        }

        return match (true) {
            ContactKind::Phone === $kind => $this->askByCode($user, $contact),
            null !== $user->getEmail() => ContactOutcome::Owner,
            default => $this->askByLink($user, $contact),
        };
    }

    public function askFormer(User $user, string $wanted): ContactOutcome
    {
        $mail = $this->wanted(ContactKind::Email, $wanted);

        return $mail instanceof ContactOutcome ? $mail : $this->askRelease($user, $mail);
    }

    public function askPhone(User $user, string $wanted): ContactOutcome
    {
        $phone = $this->releasePhone($user);
        if (null === $phone) {
            return ContactOutcome::Unavailable;
        }

        $mail = $this->wanted(ContactKind::Email, $wanted);
        if ($mail instanceof ContactOutcome) {
            return $mail;
        }

        $code = $this->secrets->code(Purpose::Release, $user, $mail);

        return null === $code ? ContactOutcome::TooOften : self::outcome($this->notifier->contact($phone, CoreEvent::ContactRelease, [
            'contact' => $mail,
            'code' => $code,
            'minutes' => Kind::Code->minutes(),
        ], $user));
    }

    public function releasePhone(User $user): ?string
    {
        return $this->notifier->reachable(ContactKind::Phone) ? $user->getPhone() : null;
    }

    public function releaseByCode(User $user, string $code): ?ContactOutcome
    {
        $confirmation = $this->secrets->matching(Purpose::Release, $user, $code);

        return null === $confirmation ? null : $this->release($confirmation);
    }

    public function release(Confirmation $confirmation): ContactOutcome
    {
        $contact = (string) $confirmation->getContact();
        if (null !== $this->byContact(ContactKind::of($contact), $contact)) {
            return ContactOutcome::Known;
        }

        $outcome = $this->askByLink($confirmation->requireUser(), $contact);
        if (ContactOutcome::TooOften !== $outcome) {
            $confirmation->use();
            $this->em->flush();
        }

        return $outcome;
    }

    public function completeContactCode(User $user, string $code): bool
    {
        $confirmation = $this->secrets->matching(Purpose::Contact, $user, $code);

        return null !== $confirmation && $this->applyContact($confirmation);
    }

    public function applyContact(Confirmation $confirmation): bool
    {
        $contact = (string) $confirmation->getContact();
        $kind = ContactKind::of($contact);
        if (null !== $this->byContact($kind, $contact)) {
            return false;
        }

        $user = $confirmation->requireUser()->setContact($kind, $contact);
        $confirmation->use();
        $this->em->flush();
        $this->events->dispatch(new ContactConfirmed($user, $kind, $contact));

        return true;
    }

    public function awaited(User $user, Purpose $purpose): ?string
    {
        $confirmation = $this->confirmations->last($purpose, Kind::Code, $user);

        return null !== $confirmation && $confirmation->usable() ? $confirmation->getContact() : null;
    }

    public function cancel(User $user, Purpose $purpose): void
    {
        $confirmation = $this->confirmations->last($purpose, Kind::Code, $user);
        if (null !== $confirmation && $confirmation->usable()) {
            $confirmation->use();
            $this->em->flush();
        }
    }

    public function completeRegistration(string $contact, string $code, string $password): ?User
    {
        $kind = ContactKind::of($contact);
        $contact = $kind->normalize($contact);
        if (null === $contact || null !== $this->byContact($kind, $contact)) {
            return null;
        }

        $confirmation = $this->secrets->matching(Purpose::Registration, $contact, $code);
        if (null === $confirmation) {
            return null;
        }

        $user = $this->registration->open(
            ContactKind::Phone === $kind ? $contact : null,
            ContactKind::Email === $kind ? $contact : null,
        );
        $user->setPassword($this->hasher->hashPassword($user, $password));
        $confirmation->use();
        $this->em->flush();
        $this->events->dispatch(new ContactConfirmed($user, $kind, $contact));

        return $user;
    }

    public function complete(string $login, string $code, string $password): ?User
    {
        $user = $this->users->byLogin($login);
        $confirmation = null === $user ? null : $this->secrets->matching(Purpose::Password, $user, $code);

        return null === $confirmation ? null : $this->completeLink($confirmation, $password);
    }

    public function pending(Purpose $purpose, string $token): ?Confirmation
    {
        return $this->secrets->pending($purpose, $token);
    }

    public function completeLink(Confirmation $confirmation, string $password): User
    {
        $user = $confirmation->requireUser();
        $confirmation->use();
        $user->setPassword($this->hasher->hashPassword($user, $password));
        if ($user->isDeleted()) {
            $user->setStatus(Status::Active);
        }
        $this->em->flush();

        return $user;
    }

    private function mailLink(User $user, Purpose $purpose, CabinetEntry $entry, CoreEvent $event): ContactOutcome
    {
        if (null === $user->getEmail() || !$this->notifier->reachable(ContactKind::Email)) {
            return ContactOutcome::Unavailable;
        }

        $token = $this->secrets->link($purpose, $user);

        return null === $token ? ContactOutcome::TooOften : self::outcome($this->notifier->notifyMail($user, $event, [
            'url' => $this->url($entry, ['token' => $token]),
            'minutes' => Kind::Link->minutes(),
        ]));
    }

    private function askRelease(User $user, string $contact): ContactOutcome
    {
        $token = $this->secrets->link(Purpose::Release, $user, $contact);

        return null === $token ? ContactOutcome::TooOften : self::outcome($this->notifier->notifyMail($user, CoreEvent::ContactRelease, [
            'contact' => $contact,
            'url' => $this->url(CabinetEntry::ContactRelease, ['token' => $token]),
            'minutes' => Kind::Link->minutes(),
        ]), ContactOutcome::Former);
    }

    private function askByCode(User $user, string $contact): ContactOutcome
    {
        $code = $this->secrets->code(Purpose::Contact, $user, $contact);

        return null === $code ? ContactOutcome::TooOften : self::outcome($this->notifier->contact($contact, CoreEvent::ContactConfirm, [
            'code' => $code,
            'minutes' => Kind::Code->minutes(),
        ], $user));
    }

    private function askByLink(User $user, string $contact): ContactOutcome
    {
        $token = $this->secrets->link(Purpose::Contact, $user, $contact);

        return null === $token ? ContactOutcome::TooOften : self::outcome($this->notifier->contact($contact, CoreEvent::ContactConfirm, [
            'url' => $this->url(CabinetEntry::ContactConfirm, ['token' => $token]),
            'minutes' => Kind::Link->minutes(),
        ], $user));
    }

    private function wanted(ContactKind $kind, string $wanted): string|ContactOutcome
    {
        $contact = $kind->normalize($wanted);

        return match (true) {
            null === $contact => ContactOutcome::Malformed,
            !$this->notifier->reachable($kind) => ContactOutcome::Unreachable,
            null !== $this->byContact($kind, $contact) => ContactOutcome::Known,
            default => $contact,
        };
    }

    private function byContact(ContactKind $kind, string $contact): ?User
    {
        return $this->users->findOneBy([ContactKind::Phone === $kind ? 'phone' : 'email' => $contact]);
    }

    /** @param array<string, string> $params */
    private function url(CabinetEntry $entry, array $params): string
    {
        return $this->entries->url($entry, $params, true) ?? throw new \LogicException(\sprintf('no cabinet module serves the entry %s', $entry->value));
    }

    private static function sent(?Notice $notice): bool
    {
        return null !== $notice && null === $notice->getError();
    }

    private static function outcome(?Notice $notice, ContactOutcome $sent = ContactOutcome::Sent): ContactOutcome
    {
        return self::sent($notice) ? $sent : ContactOutcome::Unavailable;
    }
}
