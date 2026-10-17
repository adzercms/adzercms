<?php

declare(strict_types=1);

namespace AdzerCMS\HCaptcha\Tests;

use AdzerCMS\Contracts\Captcha\Key;
use AdzerCMS\Contracts\Ui\ContentSource;
use AdzerCMS\Core\Captcha\Captcha;
use AdzerCMS\Core\Module\Switches;
use AdzerCMS\Core\Tests\Configuration;
use AdzerCMS\Core\Tests\HttpAnswers;
use AdzerCMS\Core\Tests\SchemaTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class HCaptchaTest extends SchemaTestCase
{
    use Configuration;

    private const SETTINGS = 'captcha.hcaptcha.';
    private const ORIGINS = ['https://hcaptcha.com', 'https://*.hcaptcha.com'];

    #[Group('ACMS-CORE-0028')]
    #[Group('ACMS-CORE-0051')]
    #[Group('ACMS-US-0001')]
    public function testEnabledCaptchaShowsItsWidgetAndOpensItsOrigins(): void
    {
        $this->enable();
        $captcha = self::captcha();

        self::assertSame(['class' => 'h-captcha', 'js' => 'https://js.hcaptcha.com/1/api.js', 'site_key' => 'site'], $captcha->widget());
        self::assertSame(
            [ContentSource::SCRIPT => self::ORIGINS, ContentSource::CONNECT => self::ORIGINS, ContentSource::FRAME => self::ORIGINS, ContentSource::STYLE => self::ORIGINS],
            $captcha->sources(new Request()),
        );
    }

    #[Group('ACMS-CORE-0051')]
    #[Group('ACMS-US-0001')]
    public function testProviderVerdictDecidesTheOutcome(): void
    {
        $this->enable();
        $passed = new JsonMockResponse(['success' => true, 'challenge_ts' => '2026-10-17T02:30:00Z', 'hostname' => 'cabinet.test']);
        self::service(HttpAnswers::class)->respond([$passed, new JsonMockResponse(['success' => false, 'error-codes' => ['invalid-input-response']])]);
        $captcha = self::captcha();
        $answered = new Request(request: ['h-captcha-response' => 'answer'], server: ['REMOTE_ADDR' => '203.0.113.7']);

        self::assertTrue($captcha->passed($answered));
        self::assertSame('https://api.hcaptcha.com/siteverify', $passed->getRequestUrl());
        self::assertSame('secret=secret-key&response=answer&remoteip=203.0.113.7', $passed->getRequestOptions()['body'] ?? null);
        self::assertFalse($captcha->passed($answered));
    }

    private static function captcha(): Captcha
    {
        return new Captcha(self::service(Switches::class), self::service(HttpClientInterface::class, 'http_client'));
    }

    private function enable(): void
    {
        $this->configure([
            self::SETTINGS.Switches::ENABLED => '1',
            self::SETTINGS.Key::Site->value => 'site',
            self::SETTINGS.Key::Secret->value => 'secret-key',
        ]);
    }
}
