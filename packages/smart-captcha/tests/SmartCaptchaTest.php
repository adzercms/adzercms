<?php

declare(strict_types=1);

namespace AdzerCMS\SmartCaptcha\Tests;

use AdzerCMS\Contracts\Captcha\Key;
use AdzerCMS\Contracts\Ui\ContentSource;
use AdzerCMS\Core\Captcha\Captcha;
use AdzerCMS\Core\Module\Switches;
use AdzerCMS\Core\Tests\Configuration;
use AdzerCMS\Core\Tests\HttpAnswers;
use AdzerCMS\Core\Tests\SchemaTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class SmartCaptchaTest extends SchemaTestCase
{
    use Configuration;

    private const SETTINGS = 'captcha.smartcaptcha.';
    private const ORIGIN = 'https://smartcaptcha.yandexcloud.net';

    #[Group('ACMS-CORE-0051')]
    #[Group('ACMS-US-0001')]
    public function testSwitchedOffCaptchaLetsTheFormThrough(): void
    {
        self::assertTrue(self::captcha()->passed(new Request()));
        self::assertSame([], self::captcha()->widget());
        self::assertSame([], self::captcha()->sources(new Request()));
    }

    #[Group('ACMS-CORE-0028')]
    #[Group('ACMS-CORE-0051')]
    #[Group('ACMS-US-0001')]
    public function testEnabledCaptchaShowsItsWidgetAndOpensOnlyItsOrigin(): void
    {
        $this->enable();
        $captcha = self::captcha();

        self::assertSame(['class' => 'smart-captcha', 'js' => self::ORIGIN.'/captcha.js', 'site_key' => 'site'], $captcha->widget());
        self::assertSame(
            [ContentSource::SCRIPT => [self::ORIGIN], ContentSource::CONNECT => [self::ORIGIN], ContentSource::FRAME => [self::ORIGIN], ContentSource::STYLE => [self::ORIGIN]],
            $captcha->sources(new Request()),
        );
        self::assertFalse($captcha->passed(new Request()), 'a form without the answer of the widget does not pass');
    }

    #[Group('ACMS-CORE-0051')]
    #[Group('ACMS-US-0001')]
    public function testProviderVerdictDecidesTheOutcome(): void
    {
        $this->enable();
        $passed = new JsonMockResponse(['status' => 'ok']);
        self::service(HttpAnswers::class)->respond([$passed, new JsonMockResponse(['status' => 'failed'])]);
        $captcha = self::captcha();
        $answered = new Request(request: ['smart-token' => 'answer'], server: ['REMOTE_ADDR' => '203.0.113.7']);

        self::assertTrue($captcha->passed($answered));
        self::assertSame(self::ORIGIN.'/validate', $passed->getRequestUrl());
        self::assertSame('secret=secret-key&token=answer&ip=203.0.113.7', $passed->getRequestOptions()['body'] ?? null);
        self::assertFalse($captcha->passed($answered));
    }

    #[Group('ACMS-CORE-0051')]
    #[Group('ACMS-US-0001')]
    public function testUnreachableProviderDoesNotLetTheFormThrough(): void
    {
        $this->enable();
        self::service(HttpAnswers::class)->respond(new MockResponse('', ['error' => 'network is down']));

        self::assertFalse(self::captcha()->passed(new Request(request: ['smart-token' => 'answer'])));
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
