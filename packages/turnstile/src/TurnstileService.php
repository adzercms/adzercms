<?php

declare(strict_types=1);

namespace AdzerCMS\Turnstile;

use AdzerCMS\Contracts\Captcha\CaptchaService;
use AdzerCMS\Contracts\Captcha\Key;
use AdzerCMS\Contracts\Setting\Guided;

use function Symfony\Component\Translation\t;

final readonly class TurnstileService implements CaptchaService, Guided
{
    public function credentials(): array
    {
        return [Key::Site->field(), Key::Secret->field()];
    }

    public function guide(): array
    {
        return [t('turnstile.guide.widget'), t('turnstile.guide.keys')];
    }

    public function script(): string
    {
        return 'https://challenges.cloudflare.com/turnstile/v0/api.js';
    }

    public function origins(): array
    {
        return ['https://challenges.cloudflare.com'];
    }

    public function verifier(): string
    {
        return 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
    }

    public function widgetClass(): string
    {
        return 'cf-turnstile';
    }

    public function field(): string
    {
        return 'cf-turnstile-response';
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
