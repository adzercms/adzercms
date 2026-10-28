<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Notice;

use AdzerCMS\Contracts\Setting\Field;
use AdzerCMS\Contracts\Setting\Owner;
use AdzerCMS\Contracts\Setting\Tab;
use AdzerCMS\Contracts\Setting\Type;
use AdzerCMS\Core\Account\ContactKind;
use AdzerCMS\Core\Setting\Brand;
use AdzerCMS\Core\Setting\Settings;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\Translation\TranslatableInterface;

use function Symfony\Component\Translation\t;

final class MailChannel extends ContactChannel implements Owner
{
    public const HOST = 'mail.host';
    public const PORT = 'mail.port';
    public const LOGIN = 'mail.login';
    public const PASSWORD = 'mail.password';
    public const SENDER = 'mail.sender';
    public const ACCOUNT = 'mail.account';
    public const BOUNCE_ADDRESS = 'mail.bounce_address';
    public const BOUNCE_HOST = 'mail.bounce_host';
    public const BOUNCE_PORT = 'mail.bounce_port';
    public const BOUNCE_LOGIN = 'mail.bounce_login';
    public const BOUNCE_PASSWORD = 'mail.bounce_password';
    public const BOUNCE_ACCOUNT = 'mail.bounce_account';

    public function __construct(
        private readonly Settings $settings,
        private readonly MailerInterface $mailer,
        private readonly Brand $brand,
        private readonly Bounces $bounces,
    ) {
    }

    public function settingFields(): array
    {
        $section = t('core.setting.section.mail');
        $bounces = t('core.setting.section.bounces');

        return [
            new Field(self::HOST, t('core.setting.mail_host'), section: $section, tab: Tab::Mail),
            new Field(self::PORT, t('core.setting.mail_port'), section: $section, tab: Tab::Mail),
            new Field(self::LOGIN, t('core.setting.mail_login'), group: self::ACCOUNT, section: $section, tab: Tab::Mail),
            new Field(self::PASSWORD, t('core.setting.mail_password'), Type::Secret, group: self::ACCOUNT, section: $section, tab: Tab::Mail),
            new Field(self::SENDER, t('core.setting.mail_sender'), section: $section, tab: Tab::Mail),
            new Field(self::BOUNCE_ADDRESS, t('core.setting.bounce_address'), section: $bounces, tab: Tab::Mail),
            new Field(self::BOUNCE_HOST, t('core.setting.bounce_host'), section: $bounces, tab: Tab::Mail),
            new Field(self::BOUNCE_PORT, t('core.setting.mail_port'), section: $bounces, tab: Tab::Mail),
            new Field(self::BOUNCE_LOGIN, t('core.setting.mail_login'), group: self::BOUNCE_ACCOUNT, section: $bounces, tab: Tab::Mail),
            new Field(self::BOUNCE_PASSWORD, t('core.setting.mail_password'), Type::Secret, group: self::BOUNCE_ACCOUNT, section: $bounces, tab: Tab::Mail),
        ];
    }

    public function label(): TranslatableInterface
    {
        return t('core.channel.mail');
    }

    public function missing(): TranslatableInterface
    {
        return t('core.channel.missing_mail');
    }

    public function ready(): bool
    {
        return '' !== $this->settings->get(self::HOST) && '' !== $this->settings->get(self::SENDER);
    }

    public function markup(): bool
    {
        return true;
    }

    public function send(string $recipient, Letter $letter, ?int $notice = null): void
    {
        $email = (new Email())
            ->from(new Address($this->settings->get(self::SENDER), $this->brand->title()))
            ->to($recipient)
            ->subject($letter->subject)
            ->text($letter->text)
            ->html($letter->html);
        foreach ($letter->attachments as $attachment) {
            $email->attach($attachment->body, $attachment->name, $attachment->mime);
        }

        $envelope = null;
        $sender = null === $notice ? null : $this->bounces->sender($notice);
        if (null !== $sender) {
            $envelope = Envelope::create($email);
            $envelope->setSender($sender);
        }

        try {
            $this->mailer->send($email, $envelope);
        } catch (TransportExceptionInterface $failure) {
            throw new Undelivered($failure->getMessage(), previous: $failure);
        }
    }

    protected function kind(): ContactKind
    {
        return ContactKind::Email;
    }
}
