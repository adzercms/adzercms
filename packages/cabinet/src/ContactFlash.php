<?php

declare(strict_types=1);

namespace AdzerCMS\Cabinet;

use AdzerCMS\Core\Account\ContactOutcome;
use AdzerCMS\Core\Entity\User;
use Symfony\Component\Translation\TranslatableMessage;

use function Symfony\Component\Translation\t;

final class ContactFlash
{
    /** @return array{string, TranslatableMessage} */
    public static function of(ContactOutcome $outcome, string $contact, User $user): array
    {
        return match ($outcome) {
            ContactOutcome::Sent => ['success', t('cabinet.contact.sent', ['contact' => $contact])],
            ContactOutcome::Former => ['success', t('cabinet.contact.former', ['contact' => $user->getEmail()])],
            ContactOutcome::Known => ['error', t('cabinet.contact.taken')],
            ContactOutcome::Malformed => ['error', t('cabinet.contact.malformed')],
            ContactOutcome::Unreachable => ['error', t('cabinet.contact.unreachable')],
            ContactOutcome::TooOften => ['error', t('core.confirmation.too_often')],
            ContactOutcome::Unavailable => ['error', t('cabinet.contact.unsent')],
            ContactOutcome::Owner => throw new \LogicException('the owner of the record is proven by the mail change page'),
        };
    }
}
