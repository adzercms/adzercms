<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Account;

use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Core\Entity\User;
use AdzerCMS\Core\Repository\UserRepository;

use function Symfony\Component\Translation\t;

final readonly class Contacts
{
    public function __construct(private UserRepository $users)
    {
    }

    /**
     * @param array<string, string> $values
     *
     * @return array<string, ?string>|Refusal
     */
    public static function forms(array $values): array|Refusal
    {
        $contacts = [];

        foreach (ContactKind::cases() as $kind) {
            $value = trim($values[$kind->value] ?? '');
            $contact = '' === $value ? null : $kind->normalize($value);
            if ('' !== $value && null === $contact) {
                return new Refusal(t('core.contact.malformed.'.$kind->value));
            }
            $contacts[$kind->value] = $contact;
        }

        return $contacts;
    }

    /**
     * @param array<string, string> $values
     *
     * @return array<string, ?string>|Refusal
     */
    public function free(array $values, User $user): array|Refusal
    {
        $contacts = self::forms($values);
        if ($contacts instanceof Refusal) {
            return $contacts;
        }

        foreach ($contacts as $kind => $contact) {
            $holder = null === $contact ? null : $this->users->findOneBy([$kind => $contact]);
            if (null !== $holder && $holder !== $user) {
                return new Refusal(t('core.contact.taken.'.$kind, ['id' => $holder->getId()]));
            }
        }

        return $contacts;
    }
}
