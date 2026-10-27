<?php

declare(strict_types=1);

namespace AdzerCMS\HCaptcha;

use AdzerCMS\Contracts\Captcha\CaptchaService;
use AdzerCMS\Contracts\Captcha\Key;
use AdzerCMS\Contracts\Setting\Guided;

use function Symfony\Component\Translation\t;

final readonly class HCaptchaService implements CaptchaService, Guided
{
    public function credentials(): array
    {
        return [Key::Site->field(), Key::Secret->field()];
    }

    public function guide(): array
    {
        return [t('hcaptcha.guide.site'), t('hcaptcha.guide.secret')];
    }

    public function script(): string
    {
        return 'https://js.hcaptcha.com/1/api.js';
    }

    public function origins(): array
    {
        return ['https://hcaptcha.com', 'https://*.hcaptcha.com'];
    }

    public function verifier(): string
    {
        return 'https://api.hcaptcha.com/siteverify';
    }

    public function widgetClass(): string
    {
        return 'h-captcha';
    }

    public function field(): string
    {
        return 'h-captcha-response';
    }

    public function payload(string $token, string $ip): array
    {
        return ['response' => $token, 'remoteip' => $ip];
    }

    public function accepted(array $answer): bool
    {
        return true === ($answer['success'] ?? null);
    }
}
