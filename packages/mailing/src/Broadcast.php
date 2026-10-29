<?php

declare(strict_types=1);

namespace AdzerCMS\Mailing;

use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Core\Entity\User;
use AdzerCMS\Core\Notice\Channel;
use AdzerCMS\Core\Notice\Channels;
use AdzerCMS\Core\Notice\Notifier;
use AdzerCMS\Core\Notice\Status;
use AdzerCMS\Core\Repository\UserRepository;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

use function Symfony\Component\Translation\t;

final readonly class Broadcast
{
    public const EVERYONE = 'all';
    public const LIMIT = 536;

    /** @param iterable<Audience> $audiences */
    public function __construct(
        private UserRepository $users,
        private Channels $channels,
        private Notifier $notifier,
        #[AutowireIterator(Audience::class)]
        private iterable $audiences,
    ) {
    }

    /** @return array<string, Audience> */
    public function audiences(): array
    {
        $audiences = [];
        foreach ($this->audiences as $audience) {
            $audiences[$audience->key()] = $audience;
        }

        return $audiences;
    }

    /** @return list<User> */
    public function reachable(string $scope, string $value, ?Channel $channel): array
    {
        $users = $this->users->unarchived();
        $reaching = $this->channels->reachingEach($users, $channel);
        $users = array_values(array_filter($users, static fn (User $user, int $at): bool => [] !== $reaching[$at], \ARRAY_FILTER_USE_BOTH));
        $audience = $this->audiences()[$scope] ?? null;

        return null === $audience ? $users : $audience->select($users, $value);
    }

    /** @return array{sent: int, deferred: int, failed: int}|Refusal */
    public function send(string $body, string $scope, string $value, ?Channel $channel): array|Refusal
    {
        $body = trim($body);
        if ('' === $body) {
            return new Refusal(t('mailing.refusal.empty'));
        }
        if (mb_strlen($body) > self::LIMIT) {
            return new Refusal(t('mailing.refusal.long', ['limit' => self::LIMIT]));
        }

        $counts = ['sent' => 0, 'deferred' => 0, 'failed' => 0];
        $notices = $this->notifier->each($this->reachable($scope, $value, $channel), MailingEvent::Broadcast, ['body' => $body], only: $channel);

        foreach ($notices as $notice) {
            ++$counts[match ($notice->getStatus()) {
                Status::Sent => 'sent',
                Status::Deferred => 'deferred',
                Status::Failed => 'failed',
            }];
        }

        return $counts;
    }
}
