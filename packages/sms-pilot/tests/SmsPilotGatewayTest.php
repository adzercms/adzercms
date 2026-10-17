<?php

declare(strict_types=1);

namespace AdzerCMS\SmsPilot\Tests;

use AdzerCMS\Contracts\Module\Credentials;
use AdzerCMS\Contracts\Notice\SmsFailed;
use AdzerCMS\SmsPilot\SmsPilotGateway;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

final class SmsPilotGatewayTest extends TestCase
{
    private const URL = 'https://smspilot.example.test/api2.php';
    private const CONTRACT = ['id' => '45375', 'email' => 'operator@example.test', 'balance' => '2.54', 'senders' => 'INFORM,SENDER'];

    #[Group('ACMS-CORE-0909')]
    public function testMessageGoesAsOwnRequestSignedByKey(): void
    {
        $answer = new JsonMockResponse(['send' => [['server_id' => '900001']], 'balance' => '100']);

        self::gateway($answer)->send(self::credentials(), '79991234567', 'Code 1234');

        self::assertSame('POST', $answer->getRequestMethod());
        self::assertSame(self::URL, $answer->getRequestUrl());
        self::assertSame(
            ['apikey' => 'pilot-key', 'send' => [['to' => '79991234567', 'text' => 'Code 1234', 'from' => 'VILLAGE']]],
            json_decode(self::body($answer), true),
            'the gateway knows the sender by its key, not by a login with a password',
        );
    }

    #[Group('ACMS-CORE-0132')]
    #[Group('ACMS-CORE-0909')]
    public function testRefusalIsReportedInGatewayWords(): void
    {
        $this->expectExceptionObject(new SmsFailed('the gateway refused: Недостаточно средств'));

        self::gateway(new JsonMockResponse(['error' => ['code' => 122, 'description' => 'Not enough money', 'description_ru' => 'Недостаточно средств']]))
            ->send(self::credentials(), '79991234567', 'Code 1234');
    }

    #[Group('ACMS-CORE-0132')]
    #[Group('ACMS-CORE-0909')]
    public function testFailedStatusIsReportedWithItsCode(): void
    {
        $this->expectExceptionObject(new SmsFailed('the gateway answered 402 {"error":"no package"}'));

        self::gateway(new JsonMockResponse(['error' => 'no package'], ['http_code' => 402]))
            ->send(self::credentials(), '79991234567', 'Code 1234');
    }

    #[Group('ACMS-CORE-0135')]
    public function testContractIsReadWithAndWithoutItsWrapper(): void
    {
        $gateway = self::gateway(
            new JsonMockResponse(self::CONTRACT),
            new JsonMockResponse(['info' => self::CONTRACT]),
            new JsonMockResponse(['send' => [['server_id' => 'msg-1']]]),
        );

        self::assertSame(self::CONTRACT, $gateway->contract(self::credentials()), 'the declared wrapper may be missing');
        self::assertSame(self::CONTRACT, $gateway->contract(self::credentials()));

        $this->expectExceptionObject(new SmsFailed('the gateway gave no contract: {"send":[{"server_id":"msg-1"}]}'));
        $gateway->contract(self::credentials());
    }

    private static function gateway(JsonMockResponse ...$answers): SmsPilotGateway
    {
        return new SmsPilotGateway(new MockHttpClient($answers));
    }

    private static function credentials(): Credentials
    {
        return new Credentials([SmsPilotGateway::URL => self::URL, SmsPilotGateway::KEY => 'pilot-key', SmsPilotGateway::SENDER => 'VILLAGE']);
    }

    private static function body(JsonMockResponse $answer): string
    {
        $body = $answer->getRequestOptions()['body'] ?? null;

        return \is_string($body) ? $body : self::fail('the request carries no body');
    }
}
