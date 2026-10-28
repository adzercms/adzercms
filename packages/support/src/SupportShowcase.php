<?php

declare(strict_types=1);

namespace AdzerCMS\Support;

use AdzerCMS\Contracts\Showcase\Showcase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class SupportShowcase implements Showcase
{
    public function __construct(private UrlGeneratorInterface $urls)
    {
    }

    public function section(): array
    {
        return [
            'tickets' => $this->urls->generate('support_tickets', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'new_ticket' => $this->urls->generate('support_ticket_new', [], UrlGeneratorInterface::ABSOLUTE_URL),
        ];
    }
}
