<?php

declare(strict_types=1);

namespace AdzerCMS\VkId\Tests;

use AdzerCMS\Contracts\Login\Client;
use AdzerCMS\Core\Module\Switches;
use AdzerCMS\Core\Repository\ConnectedServiceRepository;
use AdzerCMS\Core\Tests\Accounts;
use AdzerCMS\Core\Tests\Configuration;
use AdzerCMS\Core\Tests\SchemaTestCase;
use AdzerCMS\Core\Tests\ServiceLogin;
use AdzerCMS\VkId\VkIdService;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

final class LoginTest extends SchemaTestCase
{
    use Accounts;
    use Configuration;
    use ServiceLogin;

    private const SETTINGS = 'login.vkid.';
    private const CLIENT = '54321987';
    private const CALLBACK = 'http://localhost/login/vkid/callback';
    private const SCOPE = 'vkid.personal_info email phone';

    protected function setUp(): void
    {
        parent::setUp();
        $this->answerService();
        $this->configure([self::SETTINGS.Switches::ENABLED => '1', self::SETTINGS.Client::Id->value => self::CLIENT]);
    }

    #[Group('ACMS-US-0002')]
    #[Group('ACMS-CORE-0104')]
    #[Group('ACMS-CORE-0173')]
    public function testGuestEntersByVkIdProfileWithPhoneOfRecord(): void
    {
        $user = $this->record('+7 900 111-22-33');

        $query = $this->authorize('vkid', VkIdService::AUTHORIZE);
        self::assertSame(
            ['response_type' => 'code', 'client_id' => self::CLIENT, 'redirect_uri' => self::CALLBACK, 'scope' => self::SCOPE, 'code_challenge_method' => 'S256'],
            array_diff_key($query, array_flip(['state', 'code_challenge'])),
        );

        $this->answers = [
            new JsonMockResponse([
                'refresh_token' => 'vk2.a.refresh', 'access_token' => 'vk2.a.access', 'id_token' => 'eyJ.id.token', 'token_type' => 'Bearer',
                'expires_in' => 3600, 'user_id' => 1234567, 'state' => $query['state'], 'scope' => self::SCOPE,
            ]),
            new JsonMockResponse(['user' => [
                'user_id' => '1234567', 'first_name' => 'Иван', 'last_name' => 'Петров', 'phone' => '79001112233',
                'avatar' => 'https://sun9-80.userapi.com/s/v1/ig2/avatar.jpg', 'email' => 'ivan@example.org', 'sex' => 2, 'verified' => false, 'birthday' => '01.02.1990',
            ]]),
        ];
        $this->client->request('GET', '/login/vkid/callback', ['code' => 'vk2.a.code', 'device_id' => 'device-1', 'state' => $query['state']]);
        self::assertResponseRedirects('/');

        $token = $this->sentForm(0);
        self::assertSame(VkIdService::TOKEN, $this->sent[0]['url'] ?? null);
        self::assertSame(self::challenge($token['code_verifier']), $query['code_challenge']);
        self::assertSame(
            ['grant_type' => 'authorization_code', 'code' => 'vk2.a.code', 'client_id' => self::CLIENT, 'device_id' => 'device-1', 'redirect_uri' => self::CALLBACK, 'state' => $query['state']],
            array_diff_key($token, ['code_verifier' => true]),
        );
        self::assertSame(VkIdService::USER_INFO, $this->sent[1]['url'] ?? null);
        self::assertSame(['client_id' => self::CLIENT, 'access_token' => 'vk2.a.access'], $this->sentForm(1));

        $connected = self::service(ConnectedServiceRepository::class)->findOneBy(['user' => $user->getId(), 'service' => 'vkid']);
        self::assertSame('1234567', $connected?->getProfile());
        $user = $this->reread($user);
        self::assertTrue($user->isNamed());
        self::assertSame('ivan@example.org', $user->getEmail());
    }

    #[Group('ACMS-US-0002')]
    #[Group('ACMS-CORE-0110')]
    public function testRefusalInSuccessfulAnswerNamesItsReason(): void
    {
        $query = $this->authorize('vkid', VkIdService::AUTHORIZE);
        $this->answers = [new JsonMockResponse(['error' => 'invalid_request', 'error_description' => 'invalid device id provided', 'error_uri' => 'https://vk.cc/cYb3NI', 'state' => $query['state']])];
        $this->client->request('GET', '/login/vkid/callback', ['code' => 'vk2.a.code', 'state' => $query['state']]);
        $declined = $this->flashed('error', '/login');
        self::assertSame(['core.login.declined', 'invalid device id provided'], [$declined->getMessage(), $declined->getParameters()['error'] ?? null]);

        $query = $this->authorize('vkid', VkIdService::AUTHORIZE);
        $this->answers = [
            new JsonMockResponse(['access_token' => 'vk2.a.expired', 'token_type' => 'Bearer', 'expires_in' => 3600, 'user_id' => 1234567, 'state' => $query['state']]),
            new JsonMockResponse(['error' => 'invalid_token', 'error_description' => 'access_token is missing or invalid']),
        ];
        $this->client->request('GET', '/login/vkid/callback', ['code' => 'vk2.a.code', 'device_id' => 'device-1', 'state' => $query['state']]);
        $declined = $this->flashed('error', '/login');
        self::assertSame(['core.login.declined', 'access_token is missing or invalid'], [$declined->getMessage(), $declined->getParameters()['error'] ?? null]);
    }
}
