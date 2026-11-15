<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Module;

use AdzerCMS\Core\Module\Opotopo;
use AdzerCMS\Core\Module\Sources;
use AdzerCMS\Core\Repository\ModuleSourceRepository;
use AdzerCMS\Core\Tests\HttpAnswers;
use AdzerCMS\Core\Tests\SchemaTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

final class OpotopoTokenTest extends SchemaTestCase
{
    private const CLIENT = 'https://shop.acme.test/opotopo/client';
    private const CALLBACK = 'https://shop.acme.test/admin/opotopo/callback';
    private const TOKEN = Opotopo::API.'/oauth2/token';

    /** @var list<array<string, string>> */
    private array $refreshes = [];

    /** @var list<MockResponse> */
    private array $tokens = [];

    protected function setUp(): void
    {
        parent::setUp();
        self::service(HttpAnswers::class)->respond(function (string $method, string $url, array $options): MockResponse {
            if (self::TOKEN !== $url) {
                return new JsonMockResponse(['issuer' => Opotopo::API, 'authorization_endpoint' => Opotopo::API.'/oauth2/authorize', 'token_endpoint' => self::TOKEN]);
            }
            $form = self::pairs($options['body'] ?? null);
            if ('refresh_token' === ($form['grant_type'] ?? null)) {
                $this->refreshes[] = $form;
            }

            return array_shift($this->tokens) ?? self::fail('unexpected token request');
        });
    }

    #[Group('ACMS-CORE-1173')]
    #[Group('ACMS-CORE-0944')]
    #[DataProvider('lifetimes')]
    public function testConnectionLendsItsAccessRefreshedBeforeComposerToOpotopoHostOverOwnCredentials(int $lifetime, ?MockResponse $refresh, string $bearer, bool $connected): void
    {
        $sources = self::service(Sources::class);
        self::assertNull($sources->add(Opotopo::REPOSITORY));
        $sources->grant(self::service(ModuleSourceRepository::class)->findOneBy(['url' => Opotopo::REPOSITORY]) ?? self::fail('the source is not kept'), '', 'own');
        $opotopo = self::service(Opotopo::class);
        $session = new Session(new MockArraySessionStorage());
        $authorization = $opotopo->authorize($session, self::CLIENT, self::CALLBACK);
        self::assertIsString($authorization);
        $state = self::pairs(parse_url($authorization, \PHP_URL_QUERY))['state'] ?? self::fail('the authorization carries no state');
        $this->tokens = [self::tokens('access-1', 'refresh-1', $lifetime), ...null === $refresh ? [] : [$refresh]];
        self::assertNull($opotopo->connect($session, ['code' => 'c1', 'state' => $state, 'iss' => Opotopo::API], self::CLIENT, self::CALLBACK));

        self::assertSame(['COMPOSER_AUTH' => json_encode(['bearer' => ['api.opotopo.com' => $bearer]], \JSON_UNESCAPED_SLASHES)], $sources->environment());
        self::assertSame(null === $refresh ? [] : [['grant_type' => 'refresh_token', 'refresh_token' => 'refresh-1', 'client_id' => self::CLIENT, 'resource' => Opotopo::RESOURCE]], $this->refreshes);
        self::assertSame($connected, $opotopo->connected());
    }

    /** @return iterable<string, array{int, ?MockResponse, string, bool}> */
    public static function lifetimes(): iterable
    {
        yield 'access outlives a Composer run' => [3600, null, 'access-1', true];
        yield 'expiring access is refreshed' => [600, self::tokens('access-2', 'refresh-2', 3600), 'access-2', true];
        yield 'refused refresh grant drops the connection' => [600, new JsonMockResponse(['error' => 'invalid_grant'], ['http_code' => 400]), 'own', false];
        yield 'silent server leaves the run without the connection' => [600, new MockResponse('', ['http_code' => 503]), 'own', true];
    }

    /** @return array<string, string> */
    private static function pairs(mixed $encoded): array
    {
        parse_str(\is_string($encoded) ? $encoded : '', $decoded);
        $pairs = [];
        foreach ($decoded as $name => $value) {
            if (\is_string($value)) {
                $pairs[(string) $name] = $value;
            }
        }

        return $pairs;
    }

    private static function tokens(string $access, string $refresh, int $lifetime): JsonMockResponse
    {
        return new JsonMockResponse(['access_token' => $access, 'refresh_token' => $refresh, 'expires_in' => $lifetime, 'token_type' => 'Bearer']);
    }
}
