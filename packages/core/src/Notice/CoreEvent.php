<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Notice;

use AdzerCMS\Contracts\Notice\Event;

enum CoreEvent: string implements Event
{
    case PasswordReset = 'core.event.password_reset';
    case PasswordIssued = 'core.event.password_issued';
    case Registration = 'core.event.registration';
    case RegistrationKnown = 'core.event.registration_known';
    case ContactConfirm = 'core.event.contact_confirm';
    case ContactRelease = 'core.event.contact_release';
    case MailChanged = 'core.event.mail_changed';
    case MailVerify = 'core.event.mail_verify';
    case AccountDeletion = 'core.event.account_deletion';
    case Message = 'core.event.message';

    public function key(): string
    {
        return $this->value;
    }

    public function template(): string
    {
        return '@Core/notice/'.substr($this->value, \strlen('core.event.'));
    }

    public function awaited(): bool
    {
        return self::Message !== $this && self::PasswordIssued !== $this;
    }
}
