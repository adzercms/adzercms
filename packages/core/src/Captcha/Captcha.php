<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Captcha;

use AdzerCMS\Contracts\Captcha\CaptchaService;
use AdzerCMS\Contracts\Captcha\Key;
use AdzerCMS\Contracts\Ui\ContentSource;
use AdzerCMS\Core\Module\Switches;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class Captcha implements ContentSource
{
    private const TIMEOUT = 10.0;

    public function __construct(private Switches $switches, private HttpClientInterface $http)
    {
    }

    public function sources(Request $request): array
    {
        $active = $this->switches->first(CaptchaService::class);
        if (null === $active) {
            return [];
        }
        $origins = $active[0]->origins();

        return [self::SCRIPT => $origins, self::CONNECT => $origins, self::FRAME => $origins, self::STYLE => $origins];
    }

    /** @return array{class: string, js: string, site_key: string}|array{} */
    public function widget(): array
    {
        $active = $this->switches->first(CaptchaService::class);
        if (null === $active) {
            return [];
        }
        [$service, $credentials] = $active;

        return ['class' => $service->widgetClass(), 'js' => $service->script(), 'site_key' => $credentials->get(Key::Site->value)];
    }

    public function passed(Request $request): bool
    {
        $active = $this->switches->first(CaptchaService::class);
        if (null === $active) {
            return true;
        }
        [$service, $credentials] = $active;
        $token = trim((string) $request->request->get($service->field()));
        if ('' === $token) {
            return false;
        }

        try {
            $answer = $this->http->request('POST', $service->verifier(), [
                'body' => ['secret' => $credentials->get(Key::Secret->value)] + $service->payload($token, (string) $request->getClientIp()),
                'timeout' => self::TIMEOUT,
            ])->toArray(false);
        } catch (ExceptionInterface) {
            return false;
        }

        return $service->accepted($answer);
    }
}
