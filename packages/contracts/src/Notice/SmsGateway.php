<?php

declare(strict_types=1);

namespace AdzerCMS\Contracts\Notice;

use AdzerCMS\Contracts\Module\Credentials;
use AdzerCMS\Contracts\Module\Switchable;

interface SmsGateway extends Switchable
{
    /** @throws SmsFailed */
    public function send(Credentials $credentials, string $phone, string $text): void;

    /**
     * @return array<string, string>
     *
     * @throws SmsFailed
     */
    public function contract(Credentials $credentials): array;
}
