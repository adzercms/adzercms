<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Account;

use AdzerCMS\Core\Account\Hostname;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

final class HostnameTest extends TestCase
{
    /** @return iterable<string, array{string, bool}> */
    public static function names(): iterable
    {
        yield 'a host name with a trailing dot' => ['Node-1.Example.com.', true];
        yield 'an IDN name' => ['сайт.пример.рф', true];
        yield 'a single label' => ['localhost', false];
        yield 'an IPv4 address' => ['192.0.2.1', false];
        yield 'an underscore in a label' => ['my_site.example.com', false];
        yield 'a label of 64 characters' => [str_repeat('a', 64).'.com', false];
        yield 'a name over 253 characters' => [implode('.', array_fill(0, 4, str_repeat('a', 63))).'.com', false];
        yield 'an empty label' => ['example..com', false];
    }

    #[Group('ACMS-CORE-0907')]
    #[DataProvider('names')]
    public function testFqdnIsHostNameOfTwoLabelsAndMoreWithNonNumericTld(string $name, bool $fqdn): void
    {
        self::assertSame($fqdn, Hostname::isFqdn($name));
    }
}
