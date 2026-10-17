<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Notice;

final readonly class Letter
{
    public function __construct(
        public string $subject,
        public string $text,
        public string $html,
        /** @var list<Attachment> */
        public array $attachments = [],
    ) {
    }
}
