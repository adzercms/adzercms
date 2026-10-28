<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Notice;

use AdzerCMS\Contracts\Notice\Event;
use AdzerCMS\Core\Account\ContactKind;
use AdzerCMS\Core\Entity\Notice;
use AdzerCMS\Core\Entity\User;
use AdzerCMS\Core\Repository\NoticeRepository;
use AdzerCMS\Core\Setting\Language;
use Doctrine\ORM\EntityManagerInterface;
use Twig\Environment;

use function Symfony\Component\Clock\now;

final class Notifier
{
    public function __construct(
        private readonly Channels $channels,
        private readonly NoticeRepository $notices,
        private readonly QuietHours $quiet,
        private readonly EntityManagerInterface $em,
        private readonly Environment $twig,
        private readonly Language $language,
    ) {
    }

    /** @param array<string, mixed> $context */
    public function notify(User $user, Event $event, array $context = [], ?string $item = null, ?\DateTimeImmutable $window = null, ?ContactKind $besides = null): ?Notice
    {
        $reached = null === $besides ? null : $this->channels->of($besides);
        $reaching = array_values(array_filter($this->channels->reaching($user), static fn (array $pair): bool => $pair[0] !== $reached));

        return $this->reach($user, $reaching, $event, $context, self::idempotency($event, (string) $user->getId(), $item, $window), $event->awaited());
    }

    /**
     * @param array<string, mixed> $context
     * @param list<Attachment>     $attachments
     */
    public function notifyMail(User $user, Event $event, array $context = [], array $attachments = [], ?string $item = null, ?\DateTimeImmutable $window = null): ?Notice
    {
        $reaching = $this->toward(ContactKind::Email, $event->awaited() ? $user->getEmail() : $user->reachableContact(ContactKind::Email));

        return $this->reach($user, $reaching, $event, $context, self::idempotency($event, (string) $user->getId(), $item, $window), $event->awaited(), $attachments);
    }

    /** @param array<string, mixed> $context */
    public function via(User $user, Channel $channel, Event $event, array $context = [], ?string $item = null): ?Notice
    {
        return $this->reach($user, $this->channels->reaching($user, $channel), $event, $context, self::idempotency($event, (string) $user->getId(), $item, null), $event->awaited());
    }

    /**
     * @param list<User>           $users
     * @param array<string, mixed> $context
     *
     * @return list<Notice>
     */
    public function each(array $users, Event $event, array $context, string $item, ?Channel $only = null): array
    {
        $notices = [];
        foreach ($this->channels->reachingEach($users, $only) as $at => $reaching) {
            $notice = $this->reach($users[$at], $reaching, $event, $context, self::idempotency($event, (string) $users[$at]->getId(), $item, null), $event->awaited());
            if (null !== $notice) {
                $notices[] = $notice;
            }
        }

        return $notices;
    }

    public function message(User $user, string $subject, string $text, ?string $item = null): ?Notice
    {
        return $this->notify($user, CoreEvent::Message, ['subject' => $subject, 'text' => $text], $item);
    }

    /** @param array<string, mixed> $context */
    public function contact(string $contact, Event $event, array $context = [], ?User $user = null): ?Notice
    {
        $kind = ContactKind::of($contact);

        return $this->reach($user, $this->toward($kind, $kind->normalize($contact)), $event, $context, null, true);
    }

    /** @param array<string, mixed> $context */
    public function mail(string $address, Event $event, array $context = [], ?string $item = null, ?\DateTimeImmutable $window = null): ?Notice
    {
        $address = ContactKind::Email->normalize($address);

        return null === $address
            ? null
            : $this->reach(null, $this->toward(ContactKind::Email, $address), $event, $context, self::idempotency($event, $address, $item, $window), $event->awaited());
    }

    public function reachable(ContactKind $kind): bool
    {
        return null !== $this->channels->of($kind);
    }

    /** @return list<ContactKind> */
    public function reachableContacts(): array
    {
        return array_values(array_filter(ContactKind::cases(), $this->reachable(...)));
    }

    /** @return list<Notice> */
    public function release(): array
    {
        if ($this->quiet->holds()) {
            return [];
        }

        $deferred = $this->notices->deferred();
        foreach ($deferred as $notice) {
            $channel = $this->channels->ready($notice->getChannel());
            if (null === $channel) {
                $notice->fail(\sprintf('channel %s is not ready', $notice->getChannel()));
            } else {
                $this->send($channel, $notice, $notice->letter());
            }
        }
        $this->em->flush();

        return $deferred;
    }

    /** @return list<array{Channel, string}> */
    private function toward(ContactKind $kind, ?string $recipient): array
    {
        $channel = $this->channels->of($kind);

        return null === $channel || null === $recipient ? [] : [[$channel, $recipient]];
    }

    /**
     * @param list<array{Channel, string}> $reaching
     * @param array<string, mixed>         $context
     * @param list<Attachment>             $attachments
     */
    private function reach(?User $user, array $reaching, Event $event, array $context, ?string $idempotency, bool $awaited, array $attachments = []): ?Notice
    {
        if ([] === $reaching || (null !== $idempotency && $this->notices->settled($idempotency))) {
            return null;
        }

        $quiet = !$awaited && $this->quiet->holds();
        $last = null;
        foreach ($reaching as [$channel, $recipient]) {
            $letter = $this->language->speak($user, fn (): Letter => $this->letter($channel, $event, $context + ['user' => $user], $attachments));
            $last = new Notice($user, $event->key(), $channel->key(), $recipient, $letter->text);
            $this->em->persist($last);
            if ($quiet) {
                $last->defer($letter);
            } else {
                $this->em->flush();
                $this->send($channel, $last, $letter);
            }
            if (Status::Failed !== $last->getStatus()) {
                $last->settle($idempotency);
                break;
            }
        }
        $this->em->flush();

        return $last;
    }

    private function send(Channel $channel, Notice $notice, Letter $letter): void
    {
        try {
            $channel->send($notice->getRecipient(), $letter, $notice->getId());
            $notice->sent();
        } catch (Undelivered $failure) {
            $notice->fail($failure->getMessage());
        }
    }

    /**
     * @param array<string, mixed> $context
     * @param list<Attachment>     $attachments
     */
    private function letter(Channel $channel, Event $event, array $context, array $attachments): Letter
    {
        if (!$channel->markup()) {
            return new Letter('', trim($this->twig->render($event->template().'.txt.twig', $context)), '');
        }

        $template = $this->twig->load($event->template().'.html.twig');

        return new Letter(
            self::plain($template->renderBlock('subject', $context)),
            self::plain($template->renderBlock('text', $context)),
            $template->render($context),
            $attachments,
        );
    }

    private static function plain(string $html): string
    {
        return trim(html_entity_decode($html, \ENT_QUOTES | \ENT_HTML5, 'UTF-8'));
    }

    private static function idempotency(Event $event, string $addressee, ?string $item, ?\DateTimeImmutable $window): ?string
    {
        return CoreEvent::Message === $event && null === $item
            ? null
            : hash('sha256', implode("\0", [$event->key(), $addressee, ($window ?? now())->format('Y-m-d'), $item]));
    }
}
