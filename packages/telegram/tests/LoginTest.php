<?php

declare(strict_types=1);

namespace AdzerCMS\Telegram\Tests;

use AdzerCMS\Contracts\Login\Client;
use AdzerCMS\Core\Module\Switches;
use AdzerCMS\Core\Repository\ConnectedServiceRepository;
use AdzerCMS\Core\Tests\Accounts;
use AdzerCMS\Core\Tests\Configuration;
use AdzerCMS\Core\Tests\SchemaTestCase;
use AdzerCMS\Core\Tests\ServiceLogin;
use AdzerCMS\Telegram\TelegramService;
use Firebase\JWT\JWT;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

use function Symfony\Component\Clock\now;

final class LoginTest extends SchemaTestCase
{
    use Accounts;
    use Configuration;
    use ServiceLogin;

    private const SETTINGS = 'login.telegram.';
    private const CLIENT = '123456789';
    private const SECRET = 'tg-secret-7f3a9c1e5b';
    private const CALLBACK = 'http://localhost/login/telegram/callback';
    private const KID = 'oidc-1';
    private const LIVE_KEYS = [
        ['alg' => 'ES256', 'kty' => 'EC', 'x' => 'ahVYrohhX6YA7w0P2gUNSwMFbaabCgBZFkeq9bWdmwU', 'y' => 'Ea8nKJ34VQMA7zv8aYDfzcBhXEjnWQ9C06jVke_eUV0', 'crv' => 'P-256', 'kid' => 'oidc-es256-1', 'use' => 'sig'],
        ['alg' => 'EdDSA', 'crv' => 'Ed25519', 'x' => 'i6BEafXMEe4osXgUTffpKAm6Cn6F2bhqPZoclunTAV4', 'kty' => 'OKP', 'kid' => 'oidc-eddsa-1', 'use' => 'sig'],
        ['alg' => 'ES256K', 'kty' => 'EC', 'x' => 'vsk5i5YJu8H_VPL7DWTgVGXBPrqgkyNmYvfgOrVut38', 'y' => 'Mrg56tBhVeorHPXK1LbTX2jP7rEqOHIatM96HFzVMIU', 'crv' => 'secp256k1', 'kid' => 'oidc-es256k-1', 'use' => 'sig'],
    ];

    private \OpenSSLAsymmetricKey $key;

    protected function setUp(): void
    {
        parent::setUp();
        $this->answerService();
        $this->configure([
            self::SETTINGS.Switches::ENABLED => '1',
            self::SETTINGS.Client::Id->value => self::CLIENT,
            self::SETTINGS.Client::Secret->value => self::SECRET,
        ]);
        $this->key = self::rsa();
    }

    #[Group('ACMS-US-0002')]
    #[Group('ACMS-CORE-0104')]
    #[Group('ACMS-CORE-0109')]
    #[Group('ACMS-CORE-0125')]
    #[Group('ACMS-CORE-0173')]
    public function testGuestEntersByTelegramProfileWithPhoneOfRecord(): void
    {
        $user = $this->record('+7 900 111-22-33');

        $query = $this->authorize('telegram', TelegramService::AUTHORIZE);
        self::assertSame(
            ['response_type' => 'code', 'client_id' => self::CLIENT, 'redirect_uri' => self::CALLBACK, 'scope' => 'openid profile phone '.TelegramService::BOT_SCOPE, 'code_challenge_method' => 'S256'],
            array_diff_key($query, array_flip(['state', 'code_challenge'])),
        );

        $this->answers = [$this->token(self::claims()), $this->keys()];
        $this->client->request('GET', '/login/telegram/callback', ['code' => 'tg-code-1', 'state' => $query['state']]);
        self::assertResponseRedirects('/');

        $token = $this->sentForm(0);
        self::assertSame(TelegramService::TOKEN, $this->sent[0]['url'] ?? null);
        self::assertSame('Basic '.base64_encode(self::CLIENT.':'.self::SECRET), $this->sentHeader(0, 'Authorization'));
        self::assertSame(self::challenge($token['code_verifier']), $query['code_challenge']);
        self::assertSame(
            ['grant_type' => 'authorization_code', 'code' => 'tg-code-1', 'redirect_uri' => self::CALLBACK, 'client_id' => self::CLIENT],
            array_diff_key($token, ['code_verifier' => true]),
        );
        self::assertSame(TelegramService::KEYS, $this->sent[1]['url'] ?? null);

        $connected = self::service(ConnectedServiceRepository::class)->findOneBy(['user' => $user->getId(), 'service' => 'telegram']);
        self::assertSame(['987654321', '@johndoe'], [$connected?->getProfile(), $connected?->getAccount()]);
        self::assertTrue($this->reread($user)->isNamed());
    }

    #[Group('ACMS-US-0002')]
    #[Group('ACMS-CORE-0109')]
    #[Group('ACMS-CORE-0110')]
    public function testTokenOfAnotherApplicationIssuerKeyOrTimeIsRefused(): void
    {
        $this->record('+7 900 111-22-33');
        $moment = now()->getTimestamp();
        $cases = [
            'core.login.alien' => [['aud' => '111111111'] + self::claims(), $this->key],
            'core.login.alien ' => [['iss' => 'https://oauth.example.org'] + self::claims(), $this->key],
            'core.login.invalid' => [self::claims(), self::rsa()],
            'core.login.invalid ' => [['iat' => $moment - 7200, 'exp' => $moment - 3600] + self::claims(), $this->key],
        ];

        foreach ($cases as $reason => [$claims, $key]) {
            $query = $this->authorize('telegram', TelegramService::AUTHORIZE);
            $this->answers = [$this->token($claims, $key), $this->keys()];
            $this->client->request('GET', '/login/telegram/callback', ['code' => 'tg-code-1', 'state' => $query['state']]);
            self::assertSame(trim($reason), $this->flashed('error', '/login')->getMessage(), $reason);
        }
    }

    #[Group('ACMS-US-0002')]
    #[Group('ACMS-CORE-0110')]
    public function testRefusalInSuccessfulAnswerNamesItsReason(): void
    {
        $query = $this->authorize('telegram', TelegramService::AUTHORIZE);
        $this->answers = [new JsonMockResponse(['error' => 'invalid_grant'])];
        $this->client->request('GET', '/login/telegram/callback', ['code' => 'tg-code-1', 'state' => $query['state']]);
        $declined = $this->flashed('error', '/login');
        self::assertSame(['core.login.declined', 'invalid_grant'], [$declined->getMessage(), $declined->getParameters()['error'] ?? null]);
    }

    /** @return array<string, mixed> */
    private static function claims(): array
    {
        $moment = now()->getTimestamp();

        return [
            'iss' => TelegramService::ISSUER, 'aud' => self::CLIENT, 'sub' => '1234123412341234123', 'iat' => $moment, 'exp' => $moment + 3600,
            'id' => 987654321, 'name' => 'John Doe', 'given_name' => 'John', 'family_name' => 'Doe', 'preferred_username' => 'johndoe',
            'picture' => 'https://cdn4.telesco.pe/file/avatar.jpg', 'phone_number' => '79001112233', 'phone_number_verified' => true,
        ];
    }

    /** @param array<string, mixed> $claims */
    private function token(array $claims, ?\OpenSSLAsymmetricKey $key = null): JsonMockResponse
    {
        return new JsonMockResponse([
            'access_token' => 'tg-access', 'token_type' => 'Bearer', 'expires_in' => 3600,
            'id_token' => JWT::encode($claims, $key ?? $this->key, 'RS256', self::KID),
        ]);
    }

    private function keys(): JsonMockResponse
    {
        $rsa = openssl_pkey_get_details($this->key)['rsa'] ?? self::fail('the test key is not RSA');
        $public = ['alg' => 'RS256', 'e' => self::encode($rsa['e']), 'ext' => true, 'key_ops' => ['verify'], 'kty' => 'RSA', 'n' => self::encode($rsa['n']), 'kid' => self::KID];

        return new JsonMockResponse(['keys' => [$public, ...self::LIVE_KEYS]]);
    }

    private static function rsa(): \OpenSSLAsymmetricKey
    {
        return openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => \OPENSSL_KEYTYPE_RSA]) ?: self::fail('openssl does not create a key');
    }

    private static function encode(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }
}
