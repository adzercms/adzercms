<?php

declare(strict_types=1);

namespace AdzerCMS\Support;

use AdzerCMS\Contracts\Text\ValueSection;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class SupportValueSection implements ValueSection
{
    public function __construct(private UrlGeneratorInterface $urls)
    {
    }

    public function values(): array
    {
        return [
            'tickets' => $this->urls->generate('support_tickets', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'new_ticket' => $this->urls->generate('support_ticket_new', [], UrlGeneratorInterface::ABSOLUTE_URL),
        ];
    }
}
