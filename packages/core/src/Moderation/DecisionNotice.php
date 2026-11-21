<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Moderation;

use AdzerCMS\Contracts\Notice\Event;
use AdzerCMS\Core\Entity\User;

final readonly class DecisionNotice
{
    /** @param array<string, mixed> $context */
    public function __construct(
        public User $author,
        public Event $event,
        public string $item,
        public array $context = [],
    ) {
    }
}
