<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Notice;

use AdzerCMS\Core\Account\ContactKind;
use AdzerCMS\Core\Repository\NoticeRepository;
use AdzerCMS\Core\Setting\Secrets;
use AdzerCMS\Core\Setting\Settings;
use DirectoryTree\ImapEngine\Collections\MessageCollection;
use DirectoryTree\ImapEngine\Mailbox;
use DirectoryTree\ImapEngine\MailboxInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireServiceClosure;
use Symfony\Component\Mime\Address;
use ZBateson\MailMimeParser\IMessage;

final class Bounces
{
    public const MAILBOX = 'adzercms.bounce_mailbox';
    private const IMAPS_PORT = 993;
    private const CHUNK = 50;
    private const SIGNATURE = 16;
    private const RECIPIENT_HEADERS = ['To', 'Delivered-To', 'X-Original-To', 'Envelope-to'];

    /** @param \Closure(): MailboxInterface $mailbox */
    public function __construct(
        private readonly Settings $settings,
        private readonly Secrets $secrets,
        private readonly NoticeRepository $notices,
        private readonly EntityManagerInterface $em,
        #[AutowireServiceClosure(self::MAILBOX)]
        private readonly \Closure $mailbox,
    ) {
    }

    public static function mailbox(Settings $settings): MailboxInterface
    {
        $port = $settings->int(MailChannel::BOUNCE_PORT, self::IMAPS_PORT);

        return new Mailbox([
            'host' => $settings->get(MailChannel::BOUNCE_HOST),
            'port' => $port,
            'username' => $settings->get(MailChannel::BOUNCE_LOGIN),
            'password' => $settings->get(MailChannel::BOUNCE_PASSWORD),
            'encryption' => self::IMAPS_PORT === $port ? 'ssl' : 'starttls',
        ]);
    }

    public function sender(int $notice): ?Address
    {
        $address = $this->address();

        return null === $address ? null : new Address(\sprintf('%s+%d-%s@%s', $address[0], $notice, $this->signature($notice), $address[1]));
    }

    public function collect(): int
    {
        $address = $this->address();
        if (null === $address) {
            return 0;
        }

        $pattern = \sprintf('/(?<![^\s<,:;"])%s\+(\d+)-([0-9a-f]{%d})@%s(?![\w.-])/i', preg_quote($address[0], '/'), self::SIGNATURE, preg_quote($address[1], '/'));
        $mailbox = ($this->mailbox)();
        $failed = 0;
        try {
            $inbox = $mailbox->inbox();
            $messages = $inbox->messages()->withHeaders()->withBody();
            $messages->chunk(function (MessageCollection $chunk) use ($messages, $pattern, &$failed): void {
                $failures = [];
                $uids = [];
                foreach ($chunk as $message) {
                    $uids[] = $message->uid();
                    $parsed = $message->parse();
                    $notice = $this->notice($parsed, $pattern);
                    $failure = null === $notice ? null : DeliveryStatus::of($parsed)?->failure();
                    if (null !== $notice && null !== $failure) {
                        $failures[$notice] = $failure;
                    }
                }
                $failed += $this->fail($failures);
                $messages->destroy($uids);
            }, self::CHUNK);
            $inbox->expunge();
        } finally {
            $mailbox->disconnect();
        }

        return $failed;
    }

    /** @param array<int, string> $failures */
    private function fail(array $failures): int
    {
        if ([] === $failures) {
            return 0;
        }

        $notices = $this->notices->numbered(array_keys($failures));
        foreach ($notices as $notice) {
            $reason = $failures[(int) $notice->getId()];
            $notice->fail($reason);
            $user = $notice->getUser();
            if (null !== $user && $user->getEmail() === $notice->getRecipient() && !DeliveryStatus::policy($reason)) {
                $user->markUndeliverable($reason);
            }
        }
        $this->em->flush();

        return \count($notices);
    }

    private function notice(IMessage $message, string $pattern): ?int
    {
        foreach (self::RECIPIENT_HEADERS as $name) {
            foreach ($message->getAllHeadersByName($name) as $header) {
                preg_match_all($pattern, $header->getRawValue(), $matches, \PREG_SET_ORDER);
                foreach ($matches as [, $notice, $signature]) {
                    if (hash_equals($this->signature((int) $notice), strtolower($signature))) {
                        return (int) $notice;
                    }
                }
            }
        }

        return null;
    }

    private function signature(int $notice): string
    {
        return substr($this->secrets->sign('notice.'.$notice.'.bounce'), 0, self::SIGNATURE);
    }

    /** @return array{string, string}|null */
    private function address(): ?array
    {
        $address = ContactKind::Email->normalize($this->settings->get(MailChannel::BOUNCE_ADDRESS));
        if (null === $address || '' === $this->settings->get(MailChannel::BOUNCE_HOST) || '' === $this->settings->get(MailChannel::BOUNCE_LOGIN)) {
            return null;
        }
        $at = (int) strrpos($address, '@');

        return [substr($address, 0, $at), substr($address, $at + 1)];
    }
}
