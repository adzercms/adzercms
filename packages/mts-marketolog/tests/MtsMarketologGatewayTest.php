<?php

declare(strict_types=1);

namespace AdzerCMS\MtsMarketolog\Tests;

use AdzerCMS\Contracts\Module\Credentials;
use AdzerCMS\Contracts\Notice\SmsFailed;
use AdzerCMS\MtsMarketolog\MtsMarketologGateway;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

final class MtsMarketologGatewayTest extends TestCase
{
    #[Group('ACMS-CORE-1004')]
    #[Group('ACMS-CORE-0910')]
    public function testMessageGoesWithBasicAuthorization(): void
    {
        $answer = new JsonMockResponse(['messages' => [['internal_id' => 'mts-1']]]);

        (new MtsMarketologGateway(new MockHttpClient($answer)))->send(self::credentials(), '79991234567', 'Code 1234');

        self::assertSame(MtsMarketologGateway::API, $answer->getRequestUrl());
        self::assertContains('Authorization: Basic '.base64_encode('operator:пароль'), $answer->getRequestOptions()['headers'] ?? []);
        $body = json_decode((string) ($answer->getRequestOptions()['body'] ?? ''), true);
        self::assertIsArray($body);
        $message = $body['messages'][0] ?? self::fail('the request carries no message');
        self::assertSame('79991234567', $message['to'][0]['msisdn']);
        self::assertSame('VILLAGE', $message['from']['sms_address']);
        self::assertSame('Code 1234', $message['content']['short_text']);
    }

    #[Group('ACMS-CORE-0132')]
    #[Group('ACMS-CORE-0910')]
    public function testAnswerWithoutMessageIdIsRefusal(): void
    {
        $this->expectException(SmsFailed::class);

        (new MtsMarketologGateway(new MockHttpClient(new JsonMockResponse(['messages' => []]))))
            ->send(self::credentials(), '79991234567', 'Code 1234');
    }

    private static function credentials(): Credentials
    {
        return new Credentials([
            MtsMarketologGateway::LOGIN => 'operator',
            MtsMarketologGateway::PASSWORD => 'пароль',
            MtsMarketologGateway::SENDER => 'VILLAGE',
        ]);
    }
}
