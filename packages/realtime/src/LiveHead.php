<?php

declare(strict_types=1);

namespace AdzerCMS\Realtime;

use AdzerCMS\Contracts\Ui\Fragment;
use AdzerCMS\Core\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

final readonly class LiveHead implements Fragment
{
    public function __construct(
        private Hub $hub,
        private Channels $channels,
        private Security $security,
        private RequestStack $requests,
    ) {
    }

    public function place(): string
    {
        return self::HEAD;
    }

    public function fragment(?int $subject): ?array
    {
        $user = $this->security->getUser();
        $request = $this->requests->getMainRequest();
        $hub = $this->hub->ready();

        if (!$user instanceof User || null === $request || null === $hub) {
            return null;
        }

        $subject = $this->channels->subject($user);

        return ['template' => '@Realtime/_head.html.twig', 'context' => [
            'client' => $hub->client(),
            'endpoint' => $hub->endpoint($request),
            'token' => $hub->connectionToken($subject),
            'channels' => [
                ['channel' => $hub->personal($subject)],
                ...array_map(
                    static fn (string $channel): array => ['channel' => $channel, 'token' => $hub->subscriptionToken($subject, $channel)],
                    $this->channels->granted($user),
                ),
            ],
        ]];
    }
}
