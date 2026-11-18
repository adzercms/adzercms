<?php

declare(strict_types=1);

namespace AdzerCMS\Pages\Type;

final class Text extends Plain
{
    public function key(): string
    {
        return 'text';
    }

    public function fields(): array
    {
        return [self::title(), self::markup()];
    }
}
