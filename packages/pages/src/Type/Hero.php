<?php

declare(strict_types=1);

namespace AdzerCMS\Pages\Type;

class Hero extends Plain
{
    public function key(): string
    {
        return 'hero';
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
