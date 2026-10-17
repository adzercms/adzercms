<?php

declare(strict_types=1);

namespace AdzerCMS\SberId\Tests;

use AdzerCMS\Contracts\Login\Client;
use AdzerCMS\Core\Module\Switches;
use AdzerCMS\Core\Repository\ConnectedServiceRepository;
use AdzerCMS\Core\Tests\Accounts;
use AdzerCMS\Core\Tests\Browser;
use AdzerCMS\Core\Tests\Configuration;
use AdzerCMS\Core\Tests\SchemaTestCase;
use AdzerCMS\Core\Tests\ServiceLogin;
use AdzerCMS\SberId\SberIdService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

final class LoginTest extends SchemaTestCase
{
    use Accounts;
    use Browser;
    use Configuration;
    use ServiceLogin;

    private const SETTINGS = 'login.sberid.';
    private const CLIENT = '6f1c2b7e-3d84-4a59-9e10-2c7b5d8a4f36';
    private const SECRET = 'Qm7xT2vN9pL4kR8wZ3yB6cJ1';
    private const CALLBACK = 'http://localhost/login/sberid/callback';

    protected function setUp(): void
    {
        parent::setUp();
        $this->answerService();
        $this->configure([
            self::SETTINGS.Switches::ENABLED => '1',
            self::SETTINGS.Client::Id->value => self::CLIENT,
            self::SETTINGS.Client::Secret->value => self::SECRET,
        ]);
    }

    #[Group('ACMS-US-0002')]
    #[Group('ACMS-CORE-0106')]
    public function testServiceWaitsForItsClientCertificate(): void
    {
        $this->client->request('GET', '/login/sberid');
        self::assertSame('core.login.unavailable', $this->flashed('error', '/login')->getMessage());

        $this->keepContainer(self::SETTINGS.SberIdService::CONTAINER);
        $this->authorize('sberid', SberIdService::AUTHORIZE);
    }

    #[Group('ACMS-US-0002')]
    #[Group('ACMS-CORE-0104')]
    #[Group('ACMS-CORE-0105')]
    #[Group('ACMS-CORE-0106')]
    #[Group('ACMS-CORE-0173')]
    public function testGuestEntersBySberIdProfileWithPhoneOfRecordOverMutualTls(): void
    {
        $this->keepContainer(self::SETTINGS.SberIdService::CONTAINER);
        $user = $this->record('+7 903 111-11-11');

        $query = $this->authorize('sberid', SberIdService::AUTHORIZE);
        self::assertSame(
            ['response_type' => 'code', 'client_id' => self::CLIENT, 'redirect_uri' => self::CALLBACK, 'scope' => 'openid name email mobile', 'client_type' => 'PRIVATE'],
            array_diff_key($query, array_flip(['state', 'nonce'])),
        );

        $this->answers = [
            self::tokens(self::CLIENT, $query['nonce']),
            new JsonMockResponse([
                'sub' => '9f2d5c1a7b3e48d6a0c4e1f7b2d9a5c3e8f1b4d7a2c6e9f0b3d5a8c1e4f7b2d9', 'family_name' => 'Иванов', 'given_name' => 'Иван',
                'middle_name' => 'Иванович', 'birthdate' => '2001-01-01', 'phone_number' => '+7 (903) 1111111', 'email' => 'ivanov@example.ru',
            ]),
            new MockResponse('', ['http_code' => 204]),
        ];
        $this->client->request('GET', '/login/sberid/callback', ['code' => '8C7E2A51-4F3B-9D6E-A0B1-C2D3E4F5A6B7', 'state' => $query['state']]);
        self::assertResponseRedirects('/');

        self::assertSame([SberIdService::TOKEN, SberIdService::USER_INFO, SberIdService::COMPLETED], array_column($this->sent, 'url'));
        self::assertSame(
            ['grant_type' => 'authorization_code', 'code' => '8C7E2A51-4F3B-9D6E-A0B1-C2D3E4F5A6B7', 'client_id' => self::CLIENT, 'client_secret' => self::SECRET, 'redirect_uri' => self::CALLBACK],
            $this->sentForm(0),
        );
        self::assertSame('Bearer access-1', $this->sentHeader(1, 'Authorization'));
        self::assertSame('Bearer access-1', $this->sentHeader(2, 'Authorization'));
        foreach ($this->sent as $call) {
            self::assertStringEndsWith('-cert.pem', \is_string($call['local_cert'] ?? null) ? $call['local_cert'] : '', 'every call of the service goes with the client certificate');
            self::assertStringEndsWith('-key.pem', \is_string($call['local_pk'] ?? null) ? $call['local_pk'] : '');
        }

        $connected = self::service(ConnectedServiceRepository::class)->findOneBy(['user' => $user->getId(), 'service' => 'sberid']);
        self::assertSame('9f2d5c1a7b3e48d6a0c4e1f7b2d9a5c3e8f1b4d7a2c6e9f0b3d5a8c1e4f7b2d9', $connected?->getProfile());
        self::assertSame('ivanov@example.ru', $this->reread($user)->getEmail());
    }

    /** @return iterable<string, array{string, bool, string}> */
    public static function refusals(): iterable
    {
        yield 'token of another application' => ['another-client', true, 'core.login.alien'];
        yield 'answer of another request' => [self::CLIENT, false, 'sberid.foreign'];
        yield 'login without confirmation' => [self::CLIENT, true, 'sberid.unconfirmed'];
    }

    #[DataProvider('refusals')]
    #[Group('ACMS-US-0002')]
    #[Group('ACMS-CORE-0105')]
    #[Group('ACMS-CORE-0106')]
    #[Group('ACMS-CORE-0110')]
    public function testRefusalNamesItsReason(string $audience, bool $ownNonce, string $reason): void
    {
        $this->keepContainer(self::SETTINGS.SberIdService::CONTAINER);
        $query = $this->authorize('sberid', SberIdService::AUTHORIZE);
        $this->answers = [
            self::tokens($audience, $ownNonce ? $query['nonce'] : 'nonce-of-another-request'),
            new JsonMockResponse(['sub' => '4c7e1a9d3b5f', 'phone_number' => '+7 (903) 2222222']),
            new MockResponse('', ['http_code' => 200]),
        ];

        $this->client->request('GET', '/login/sberid/callback', ['code' => '8C7E2A51-4F3B-9D6E-A0B1-C2D3E4F5A6B7', 'state' => $query['state']]);

        self::assertSame($reason, $this->flashed('error', '/login')->getMessage());
    }

    private static function tokens(string $audience, string $nonce): JsonMockResponse
    {
        $encode = static fn (array $part): string => rtrim(strtr(base64_encode(json_encode($part, \JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
        $claims = ['iss' => 'id.sber.ru', 'sub' => '9f2d5c1a7b3e', 'aud' => $audience, 'exp' => 1792195200, 'iat' => 1792195140, 'auth_time' => 1792195140, 'acr' => 'urn:sber:id:loa35', 'nonce' => $nonce];

        return new JsonMockResponse([
            'access_token' => 'access-1', 'token_type' => 'Bearer', 'expires_in' => 60, 'scope' => 'openid name email mobile',
            'id_token' => $encode(['alg' => 'none']).'.'.$encode($claims).'.',
        ]);
    }
}
