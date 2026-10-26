<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Confirmation;

use AdzerCMS\Contracts\Setting\Field;
use AdzerCMS\Contracts\Setting\Owner;
use AdzerCMS\Core\Entity\Confirmation;
use AdzerCMS\Core\Entity\User;
use AdzerCMS\Core\Repository\ConfirmationRepository;
use AdzerCMS\Core\Setting\Settings;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;

use function Symfony\Component\Clock\now;
use function Symfony\Component\Translation\t;

final class Confirmations implements Owner
{
    public const PAUSE_MINUTES = 'confirmation.pause_minutes';
    public const EMAIL_DELAY_DAYS = 'confirmation.email_delay_days';
    public const HASHER = 'confirmation';
    private const DEFAULT_PAUSE_MINUTES = 3;
    private const DEFAULT_EMAIL_DELAY_DAYS = 7;
    private const CODE_DIGITS = 6;
    private const TOKEN_BYTES = 32;

    public function __construct(
        private readonly ConfirmationRepository $confirmations,
        private readonly PasswordHasherFactoryInterface $hashers,
        private readonly Settings $settings,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function settingFields(): array
    {
        return [
            new Field(self::PAUSE_MINUTES, t('core.setting.confirmation_pause_minutes')),
            new Field(self::EMAIL_DELAY_DAYS, t('core.setting.confirmation_email_delay_days')),
        ];
    }

    public function emailDelayDays(): int
    {
        return $this->settings->int(self::EMAIL_DELAY_DAYS, self::DEFAULT_EMAIL_DELAY_DAYS);
    }

    /**
     * @phpstan-impure
     *
     * @return array{string, \DateTimeImmutable}
     */
    public function revertLink(User $user, string $prior): array
    {
        $token = bin2hex(random_bytes(self::TOKEN_BYTES));
        $confirmation = new Confirmation($user, $prior, Purpose::Revert, Kind::Link, self::digest($token), $this->emailDelayDays() * 24 * 60);
        $this->em->persist($confirmation);
        $this->em->flush();

        return [$token, $confirmation->getExpiresAt()];
    }

    /** @phpstan-impure */
    public function code(Purpose $purpose, ?User $user, ?string $contact = null): ?string
    {
        $code = str_pad((string) random_int(0, 10 ** self::CODE_DIGITS - 1), self::CODE_DIGITS, '0', \STR_PAD_LEFT);

        return $this->open($purpose, Kind::Code, $user, $contact, $this->hasher()->hash($code)) ? $code : null;
    }

    /** @phpstan-impure */
    public function link(Purpose $purpose, User $user, ?string $contact = null): ?string
    {
        $token = bin2hex(random_bytes(self::TOKEN_BYTES));

        return $this->open($purpose, Kind::Link, $user, $contact, self::digest($token)) ? $token : null;
    }

    /** @phpstan-impure */
    public function matching(Purpose $purpose, User|string $addressee, string $code): ?Confirmation
    {
        $confirmation = $this->confirmations->last($purpose, Kind::Code, $addressee);
        if (null === $confirmation || !$confirmation->usable()) {
            return null;
        }

        $confirmation->attempt();
        $this->em->flush();

        return $this->hasher()->verify($confirmation->getSecretHash(), $code) ? $confirmation : null;
    }

    public function pending(Purpose $purpose, string $token): ?Confirmation
    {
        $confirmation = $this->confirmations->link($purpose, self::digest($token));

        return null !== $confirmation && $confirmation->usable() ? $confirmation : null;
    }

    public function sweep(): int
    {
        return $this->confirmations->sweep(now());
    }

    private function open(Purpose $purpose, Kind $kind, ?User $user, ?string $contact, string $secretHash): bool
    {
        $last = $this->confirmations->last($purpose, $kind, $user ?? (string) $contact);
        $pause = $this->settings->int(self::PAUSE_MINUTES, self::DEFAULT_PAUSE_MINUTES);
        if (null !== $last && $last->getCreatedAt() > now()->modify(\sprintf('-%d minutes', $pause))) {
            return false;
        }

        $this->em->persist(new Confirmation($user, $contact, $purpose, $kind, $secretHash));
        $this->em->flush();

        return true;
    }

    private function hasher(): PasswordHasherInterface
    {
        return $this->hashers->getPasswordHasher(self::HASHER);
    }

    private static function digest(string $token): string
    {
        return hash('sha256', $token);
    }
}
