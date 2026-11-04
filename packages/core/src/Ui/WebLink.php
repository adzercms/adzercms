<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Ui;

use AdzerCMS\Core\Account\Hostname;

final class WebLink
{
    private const SCHEMES = ['http', 'https'];
    private const NON_ASCII = '/[^\x00-\x7F]+/';

    public static function valid(string $url): bool
    {
        return false !== filter_var($url, \FILTER_VALIDATE_URL)
            && \in_array(strtolower((string) parse_url($url, \PHP_URL_SCHEME)), self::SCHEMES, true);
    }

    public static function ascii(string $url): string
    {
        $host = parse_url($url, \PHP_URL_HOST);
        $at = \is_string($host) && '' !== $host ? strpos($url, $host) : false;

        if (\is_string($host) && false !== $at) {
            $url = substr_replace($url, Hostname::ascii($host), $at, \strlen($host));
        }

        return (string) preg_replace_callback(self::NON_ASCII, static fn (array $match): string => rawurlencode($match[0]), $url);
    }
}
