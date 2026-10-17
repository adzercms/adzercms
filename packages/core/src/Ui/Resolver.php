<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Ui;

use AdzerCMS\Core\Account\Hostname;

class Resolver
{
    /** @return list<string> */
    public function addresses(string $name): array
    {
        $addresses = @gethostbynamel(Hostname::ascii($name));

        return false === $addresses ? [] : $addresses;
    }

    public function name(string $address): ?string
    {
        $name = @gethostbyaddr($address);

        return false === $name || $name === $address ? null : $name;
    }

    /** @return list<array<string, mixed>> */
    public function records(string $name, int $type): array
    {
        $answers = @dns_get_record(Hostname::ascii($name), $type);

        return \is_array($answers) ? array_values(array_filter($answers, is_array(...))) : [];
    }

    /** @return list<string> */
    public function nameservers(string $name): array
    {
        $hosts = [];

        foreach ($this->records($name, \DNS_NS) as $answer) {
            $host = mb_strtolower(rtrim((string) ($answer['target'] ?? ''), '.'));

            if ('' !== $host) {
                $hosts[] = $host;
            }
        }

        sort($hosts);

        return array_values(array_unique($hosts));
    }
}
