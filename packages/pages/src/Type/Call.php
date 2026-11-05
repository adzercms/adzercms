<?php

declare(strict_types=1);

namespace AdzerCMS\Pages\Type;

class Call extends Plain
{
    public function key(): string
    {
        return 'call';
    }

    public function fields(): array
    {
        return [self::title(), self::text(), ...Button::fields()];
    }

    public function template(): string
    {
        return '@Pages/blocks/lead.html.twig';
    }
}
