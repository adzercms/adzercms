<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Ui;

final class WebLink
{
    private const SCHEMES = ['http', 'https'];

    public static function valid(string $url): bool
    {
        return false !== filter_var($url, \FILTER_VALIDATE_URL)
            && \in_array(strtolower((string) parse_url($url, \PHP_URL_SCHEME)), self::SCHEMES, true);
    }
}
