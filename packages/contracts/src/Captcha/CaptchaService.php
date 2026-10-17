<?php

declare(strict_types=1);

namespace AdzerCMS\Contracts\Captcha;

use AdzerCMS\Contracts\Module\Switchable;

interface CaptchaService extends Switchable
{
    public function script(): string;

    /** @return list<string> */
    public function origins(): array;

    public function verifier(): string;

    public function widgetClass(): string;

    public function field(): string;

    /** @return array<string, string> */
    public function payload(string $token, string $ip): array;

    /** @param array<mixed> $answer */
    public function accepted(array $answer): bool;
}
