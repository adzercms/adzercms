<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Ui;

use Symfony\Component\HttpFoundation\Request;

final class Fields
{
    /** @return array<string, string> */
    public static function of(Request $request): array
    {
        $fields = [];

        foreach ($request->request->all() as $name => $value) {
            if (\is_scalar($value)) {
                $fields[(string) $name] = (string) $value;
            }
        }

        return $fields;
    }
}
