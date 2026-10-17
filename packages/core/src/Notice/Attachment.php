<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Notice;

final readonly class Attachment
{
    public function __construct(
        public string $name,
        public string $mime,
        public string $body,
    ) {
    }
}
