<?php

declare(strict_types=1);

namespace AdzerCMS\SmartCaptcha;

use AdzerCMS\Contracts\Captcha\CaptchaService;
use AdzerCMS\Contracts\Captcha\Key;

final readonly class SmartCaptchaService implements CaptchaService
{
    public function credentials(): array
    {
        return [Key::Site->field(), Key::Secret->field()];
    }

    public function script(): string
    {
        return 'https://smartcaptcha.yandexcloud.net/captcha.js';
    }

    public function origins(): array
    {
        return ['https://smartcaptcha.yandexcloud.net'];
    }

    public function verifier(): string
    {
        return 'https://smartcaptcha.yandexcloud.net/validate';
    }

    public function widgetClass(): string
    {
        return 'smart-captcha';
    }

    public function field(): string
    {
        return 'smart-token';
    }

    public function payload(string $token, string $ip): array
    {
        return ['token' => $token, 'ip' => $ip];
    }

    public function accepted(array $answer): bool
    {
        return 'ok' === ($answer['status'] ?? null);
    }
}
