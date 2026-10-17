<?php

declare(strict_types=1);

namespace AdzerCMS\YandexId\Tests;

use AdzerCMS\Contracts\Login\Client;
use AdzerCMS\Core\Module\Switches;
use AdzerCMS\Core\Repository\ConnectedServiceRepository;
use AdzerCMS\Core\Tests\Accounts;
use AdzerCMS\Core\Tests\Configuration;
use AdzerCMS\Core\Tests\SchemaTestCase;
use AdzerCMS\Core\Tests\ServiceLogin;
use AdzerCMS\YandexId\YandexIdService;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

final class LoginTest extends SchemaTestCase
{
    use Accounts;
    use Configuration;
    use ServiceLogin;

    private const SETTINGS = 'login.yandexid.';
    private const CLIENT = '4760187d81bc4b7799476b42b5103713';
    private const SECRET = 'a91f0c3e5b7d4e2f8c6a1b9d0e3f5a7c';
    private const CALLBACK = 'http://localhost/login/yandexid/callback';
    private const SCOPE = 'login:info login:email login:default_phone';

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
    #[Group('ACMS-CORE-0104')]
    #[Group('ACMS-CORE-0173')]
    public function testGuestEntersByYandexIdProfileWithPhoneOfRecord(): void
    {
        $user = $this->record('+7 903 765-94-18');

        $query = $this->authorize('yandexid', YandexIdService::AUTHORIZE);
        self::assertSame(
            ['response_type' => 'code', 'client_id' => self::CLIENT, 'redirect_uri' => self::CALLBACK, 'scope' => self::SCOPE, 'code_challenge_method' => 'S256'],
            array_diff_key($query, array_flip(['state', 'code_challenge'])),
        );

        $this->answers = [
            new JsonMockResponse([
                'token_type' => 'bearer', 'access_token' => 'yandex-access-token', 'expires_in' => 31536000,
                'refresh_token' => '1:GN686QVt0mmakDd9:A4pYuW9LGk0_UnlrMIWklkAuJkUWbq27loFekJVmSYrdfzdePBy7:A-2dHOmBxiXgajnD-kYOwQ',
            ]),
            new JsonMockResponse([
                'first_name' => 'Иван', 'last_name' => 'Иванов', 'display_name' => 'ivan', 'emails' => ['test@yandex.ru'], 'default_email' => 'test@yandex.ru',
                'default_phone' => ['id' => 12345678, 'number' => '+79037659418'], 'real_name' => 'Иван Иванов', 'is_avatar_empty' => false,
                'birthday' => '1987-03-12', 'default_avatar_id' => '131652443', 'login' => 'ivan', 'old_social_login' => 'uid-mmzxrnry', 'sex' => 'male',
                'id' => '1000034426', 'client_id' => self::CLIENT, 'psuid' => '1.AAceCw.tbHgw5DtJ9_zeqPrk-Ba2w.qPWSRC5v2t2IaksPJgnge',
            ]),
        ];
        $this->client->request('GET', '/login/yandexid/callback', ['code' => '5xjs4fnp3lw2kd7q', 'state' => $query['state']]);
        self::assertResponseRedirects('/');

        $token = $this->sentForm(0);
        self::assertSame(YandexIdService::TOKEN, $this->sent[0]['url'] ?? null);
        self::assertSame('Basic '.base64_encode(self::CLIENT.':'.self::SECRET), $this->sentHeader(0, 'Authorization'));
        self::assertSame(self::challenge($token['code_verifier']), $query['code_challenge']);
        self::assertSame(['grant_type' => 'authorization_code', 'code' => '5xjs4fnp3lw2kd7q'], array_diff_key($token, ['code_verifier' => true]));
        self::assertSame(YandexIdService::USER_INFO, $this->sent[1]['url'] ?? null);
        self::assertSame('OAuth yandex-access-token', $this->sentHeader(1, 'Authorization'));

        $connected = self::service(ConnectedServiceRepository::class)->findOneBy(['user' => $user->getId(), 'service' => 'yandexid']);
        self::assertSame(['1000034426', 'ivan'], [$connected?->getProfile(), $connected?->getAccount()]);
        $user = $this->reread($user);
        self::assertTrue($user->isNamed());
        self::assertSame('test@yandex.ru', $user->getEmail());
    }

    #[Group('ACMS-US-0002')]
    #[Group('ACMS-CORE-0110')]
    public function testRefusalOfServiceNamesItsReason(): void
    {
        $query = $this->authorize('yandexid', YandexIdService::AUTHORIZE);
        $this->answers = [new JsonMockResponse(['error' => 'invalid_client', 'error_description' => 'Client not found'], ['http_code' => 401])];
        $this->client->request('GET', '/login/yandexid/callback', ['code' => '5xjs4fnp3lw2kd7q', 'state' => $query['state']]);
        $declined = $this->flashed('error', '/login');
        self::assertSame(['core.login.declined', 'Client not found'], [$declined->getMessage(), $declined->getParameters()['error'] ?? null]);

        $query = $this->authorize('yandexid', YandexIdService::AUTHORIZE);
        $this->answers = [
            new JsonMockResponse(['token_type' => 'bearer', 'access_token' => 'AQAAAACy1C6ZAAAAexpired', 'expires_in' => 31536000]),
            new JsonMockResponse(['code' => 'NOT_AUTHORIZED', 'details' => [], 'message' => 'err on user auth: : access denied: blackbox: INVALID: expired_token', 'status' => 'error'], ['http_code' => 401]),
        ];
        $this->client->request('GET', '/login/yandexid/callback', ['code' => '5xjs4fnp3lw2kd7q', 'state' => $query['state']]);
        $declined = $this->flashed('error', '/login');
        self::assertSame(['core.login.declined', '401'], [$declined->getMessage(), $declined->getParameters()['error'] ?? null]);
    }
}
