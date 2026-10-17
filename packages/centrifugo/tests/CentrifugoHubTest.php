<?php

declare(strict_types=1);

namespace AdzerCMS\Centrifugo\Tests;

use AdzerCMS\Core\Account\Role;
use AdzerCMS\Core\Account\Status;
use AdzerCMS\Core\Tests\Accounts;
use AdzerCMS\Core\Tests\HttpAnswers;
use AdzerCMS\Core\Tests\SchemaTestCase;
use AdzerCMS\Realtime\Tests\Live;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\Response\MockResponse;

use function Symfony\Component\Clock\now;

final class CentrifugoHubTest extends SchemaTestCase
{
    use Accounts;
    use Live;

    private const HUB = [
        'REALTIME_URL' => 'http://hub.example.org:8006',
        'REALTIME_WS_PATH' => '/realtime/connection/websocket',
        'REALTIME_TOKEN_SECRET' => 'secret-of-hub-tokens-for-the-run-of-tests',
        'REALTIME_API_KEY' => 'publishing-key',
    ];
    private const STAFF = 'staff:localhost';

    /** @var array<string, string> */
    private array $saved = [];

    /** @var list<array{url: string, key: string, channels: list<string>}> */
    private array $published = [];

    private bool $down = false;

    protected function setUp(): void
    {
        foreach (self::HUB as $name => $value) {
            $this->saved[$name] = (string) ($_SERVER[$name] ?? '');
            $_ENV[$name] = $_SERVER[$name] = $value;
        }

        parent::setUp();

        self::service(HttpAnswers::class)->respond(function (string $method, string $url, array $options): MockResponse {
            if ($this->down) {
                throw new TransportException('the hub is silent');
            }
            $body = json_decode((string) $options['body'], true);
            $this->published[] = [
                'url' => $method.' '.$url,
                'key' => 1 === preg_match('/^x-api-key: (.+)$/mi', implode("\n", (array) $options['headers']), $found) ? trim($found[1]) : '',
                'channels' => \is_array($body) ? $body['channels'] : [],
            ];

            return new MockResponse('{}');
        });
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        foreach ($this->saved as $name => $value) {
            $_ENV[$name] = $_SERVER[$name] = $value;
        }
    }

    #[Group('ACMS-UI-0061')]
    #[Group('ACMS-UI-0071')]
    public function testHeadConnectsThroughThePageHostWithTokensSignedForTheInstanceSubject(): void
    {
        $operator = $this->record('79990000021')->setRole(Role::Admin);
        $this->em->flush();
        $subject = $operator->getId().'@localhost';

        $context = $this->head($operator)['context'] ?? self::fail('the head of live blocks is empty');
        self::assertIsArray($context);

        self::assertSame('ws://localhost/realtime/connection/websocket', $context['endpoint']);
        self::assertNotNull(self::service(AssetMapperInterface::class, 'asset_mapper')->getAsset($context['client']), 'the client of the hub is a script of the module');
        self::assertSame($subject, $this->claims($context['token'])->sub, 'the connection token goes to the own record named with the instance');
        self::assertSame(['personal:#'.$subject, self::STAFF], array_column($context['channels'], 'channel'), 'the own channel is the channel of the Centrifugo user');
        $claims = $this->claims($context['channels'][1]['token'] ?? self::fail('the staff channel came without a subscription token'));
        self::assertSame([$subject, self::STAFF], [$claims->sub, $claims->channel]);
    }

    #[Group('ACMS-UI-0059')]
    #[Group('ACMS-UI-0071')]
    public function testChangeIsBroadcastByTheApiKeyAfterCommit(): void
    {
        $user = $this->record('79990000020');
        $this->released();

        $user->setStatus(Status::Stopped);
        $this->em->flush();

        self::assertSame([], $this->published, 'the hub is not called inside an uncommitted transaction');
        $this->terminate();
        self::assertSame([[
            'url' => 'POST http://hub.example.org:8006/api/broadcast',
            'key' => 'publishing-key',
            'channels' => ['personal:#'.$user->getId().'@localhost'],
        ]], $this->published);
    }

    #[Group('ACMS-UI-0059')]
    public function testSilentHubDoesNotBreakTheChange(): void
    {
        $user = $this->record('79990000020');
        $this->released();
        $this->down = true;

        $user->setStatus(Status::Stopped);
        $this->em->flush();
        $this->terminate();

        $this->em->clear();
        self::assertSame(Status::Stopped, $this->reread($user)->getStatus());
    }

    private function claims(mixed $token): \stdClass
    {
        $claims = JWT::decode(\is_string($token) ? $token : self::fail('the token is not a string'), new Key(self::HUB['REALTIME_TOKEN_SECRET'], 'HS256'));
        self::assertGreaterThan(now()->getTimestamp(), $claims->exp, 'the token carries a term');

        return $claims;
    }

    /** @return list<list<string>> */
    private function released(): array
    {
        $this->terminate();
        $channels = array_column($this->published, 'channels');
        $this->published = [];

        return $channels;
    }
}
