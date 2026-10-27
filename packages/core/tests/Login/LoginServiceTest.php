<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Login;

use AdzerCMS\Contracts\Login\Redirects;
use AdzerCMS\Core\Account\ConsentKind;
use AdzerCMS\Core\Account\Status;
use AdzerCMS\Core\Entity\ConnectedService;
use AdzerCMS\Core\Entity\Consent;
use AdzerCMS\Core\Module\Switches;
use AdzerCMS\Core\Repository\ConnectedServiceRepository;
use AdzerCMS\Core\Repository\ConsentRepository;
use AdzerCMS\Core\Tests\Account\Fixture\OpenPolicy;
use AdzerCMS\Core\Tests\Accounts;
use AdzerCMS\Core\Tests\Configuration;
use AdzerCMS\Core\Tests\Login\Fixture\CatchListener;
use AdzerCMS\Core\Tests\Login\Fixture\Latch\LatchService;
use AdzerCMS\Core\Tests\Login\Fixture\Wicket\WicketService;
use AdzerCMS\Core\Tests\SchemaTestCase;
use AdzerCMS\Core\Tests\ServiceLogin;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

final class LoginServiceTest extends SchemaTestCase
{
    use Accounts;
    use Configuration;
    use ServiceLogin;

    private const CALLBACK = 'http://localhost/login/latch/callback';

    protected function setUp(): void
    {
        parent::setUp();
        $this->answerService();
        $this->configure([LatchService::SETTINGS.Switches::ENABLED => '1', LatchService::SETTINGS.LatchService::CLIENT => 'latch-client']);
    }

    #[Group('ACMS-US-0002')]
    #[Group('ACMS-CORE-0105')]
    #[Group('ACMS-CORE-0107')]
    #[Group('ACMS-CORE-0087')]
    public function testGuestEntersByServiceAndReturnsToClosedPage(): void
    {
        $user = $this->record('+7 900 111-22-33');
        $this->client->request('GET', '/fixture/account');
        self::assertResponseRedirects('/login');

        $query = $this->authorize('latch', LatchService::AUTHORIZE);
        self::assertSame(
            ['response_type' => 'code', 'client_id' => 'latch-client', 'redirect_uri' => self::CALLBACK, 'scope' => 'profile phone', 'code_challenge_method' => 'S256'],
            array_diff_key($query, array_flip(['state', 'code_challenge'])),
        );
        self::assertSame(self::CALLBACK, self::service(Redirects::class)->uri(self::service(LatchService::class)), 'the steps of the service name the address it returns to');

        $this->answers = [new JsonMockResponse(['access_token' => 'token']), new JsonMockResponse(['id' => 'p1', 'phone' => '8 900 111 22 33', 'login' => 'ivan'])];
        $this->client->request('GET', '/login/latch/callback', ['code' => 'c1', 'state' => $query['state']]);
        self::assertResponseRedirects('http://localhost/fixture/account');

        $form = $this->sentForm(0);
        self::assertSame(self::challenge($form['code_verifier']), $query['code_challenge']);
        self::assertSame(['code' => 'c1', 'redirect_uri' => self::CALLBACK, 'client_id' => 'latch-client'], array_diff_key($form, ['code_verifier' => true]));
        self::assertSame(LatchService::PROFILE, $this->sent[1]['url'] ?? null);

        $this->client->request('GET', '/fixture/account');
        self::assertSame($user->getUserIdentifier(), $this->client->getResponse()->getContent());
        self::assertSame('ivan', $this->connection($user->getId())?->getAccount());
        self::assertNotNull($this->reread($user)->getLastLoginAt());
    }

    #[Group('ACMS-US-0002')]
    #[Group('ACMS-CORE-0105')]
    #[Group('ACMS-CORE-0104')]
    public function testServiceWithoutPkceGetsNonceAndItsParameters(): void
    {
        $this->configure([WicketService::SETTINGS.Switches::ENABLED => '1']);

        $query = $this->authorize('wicket', WicketService::AUTHORIZE);
        self::assertArrayNotHasKey('code_challenge', $query);
        self::assertSame('page', $query['display']);
        self::assertNotSame('', $query['nonce']);
        self::assertNotSame($query['state'], $query['nonce']);
    }

    #[Group('ACMS-US-0002')]
    #[Group('ACMS-CORE-0104')]
    public function testServiceDisabledUnknownOrWithoutCredentialsIsUnavailable(): void
    {
        $this->configure([LatchService::SETTINGS.LatchService::CLIENT => '']);

        foreach (['wicket', 'nobody', 'latch'] as $service) {
            $this->client->request('GET', '/login/'.$service);
            self::assertSame('core.login.unavailable', $this->flashed('error', '/login')->getMessage(), $service);
        }
    }

    #[Group('ACMS-US-0002')]
    #[Group('ACMS-CORE-0105')]
    public function testReturnWithAlienStateOrTakenFlightIsRefused(): void
    {
        $this->configure([WicketService::SETTINGS.Switches::ENABLED => '1']);

        $query = $this->authorize('latch', LatchService::AUTHORIZE);
        $this->client->request('GET', '/login/latch/callback', ['code' => 'c1', 'state' => 'forged']);
        self::assertSame('core.login.expired', $this->flashed('error', '/login')->getMessage());
        $this->client->request('GET', '/login/latch/callback', ['code' => 'c1', 'state' => $query['state']]);
        self::assertSame('core.login.expired', $this->flashed('error', '/login')->getMessage(), 'the flight is taken once');

        $query = $this->authorize('wicket', WicketService::AUTHORIZE);
        $this->client->request('GET', '/login/latch/callback', ['code' => 'c1', 'state' => $query['state']]);
        self::assertSame('core.login.expired', $this->flashed('error', '/login')->getMessage(), 'a flight of another service');
        self::assertSame([], $this->sent);
    }

    #[Group('ACMS-US-0002')]
    #[Group('ACMS-CORE-0110')]
    public function testServiceRefusalNamesItsReason(): void
    {
        $this->answers = [
            new MockResponse('', ['error' => 'host unreachable']),
            new JsonMockResponse(['error' => 'invalid_grant', 'error_description' => 'code expired'], ['http_code' => 400]),
            new JsonMockResponse(['error' => 'invalid_request', 'error_description' => 'invalid device id provided']),
            new MockResponse("\n", ['http_code' => 500, 'response_headers' => ['content-type' => 'text/plain; charset=utf-8']]),
            new JsonMockResponse(['access_token' => 'token']),
            new JsonMockResponse(['phone' => '79001112233']),
        ];

        $this->returned(['code' => 'c1']);
        self::assertSame('core.login.silent', $this->flashed('error', '/login')->getMessage());

        foreach (['code expired', 'invalid device id provided', '500'] as $reason) {
            $this->returned(['code' => 'c1']);
            $declined = $this->flashed('error', '/login');
            self::assertSame(['core.login.declined', $reason], [$declined->getMessage(), $declined->getParameters()['error'] ?? null]);
        }

        $this->returned(['code' => 'c1']);
        self::assertSame('core.login.nameless', $this->flashed('error', '/login')->getMessage());

        $this->returned(['error' => 'access_denied']);
        self::assertSame('core.login.cancelled', $this->flashed('error', '/login')->getMessage());
    }

    #[Group('ACMS-US-0002')]
    public function testProfileWithoutRecordReturnsToLogin(): void
    {
        $this->answers = [new JsonMockResponse(['access_token' => 'token']), new JsonMockResponse(['id' => 'p1', 'phone' => '79001112233'])];

        $this->returned(['code' => 'c1']);
        self::assertSame('core.login.unknown', $this->flashed('error', '/login')->getMessage());
    }

    #[Group('ACMS-US-0001')]
    #[Group('ACMS-CORE-0070')]
    #[Group('ACMS-CORE-0073')]
    public function testProfileWithoutRecordOpensOneWhenRegistrationIsOffered(): void
    {
        self::service(OpenPolicy::class)->registration = true;
        $this->answers = [new JsonMockResponse(['access_token' => 'token']), new JsonMockResponse(['id' => 'p1', 'phone' => '8 900 111 22 33', 'login' => 'ivan'])];

        $this->returned(['code' => 'c1']);
        self::assertResponseRedirects();

        $user = self::service(ConnectedServiceRepository::class)->profile('latch', 'p1')?->getUser() ?? self::fail('the profile opened no record');
        self::assertSame(['79001112233', null, Status::Active], [$user->getPhone(), $user->getPassword(), $user->getStatus()]);
        self::assertSame([ConsentKind::Offer], array_map(
            static fn (Consent $consent): ConsentKind => $consent->getKind(),
            self::service(ConsentRepository::class)->findBy(['user' => $user]),
        ), 'the way to the service takes the offer');

        $this->client->request('GET', '/fixture/account');
        self::assertSame($user->getUserIdentifier(), $this->client->getResponse()->getContent());
    }

    #[Group('ACMS-US-0002')]
    #[Group('ACMS-CORE-0083')]
    public function testSignedInUserConnectsService(): void
    {
        $user = $this->record('+7 900 111-22-33');
        $other = $this->record('+7 900 444-55-66');
        $this->em->persist(new ConnectedService($other, 'latch', 'p2', null));
        $this->em->flush();
        $this->client->loginUser($user);
        $this->answers = [
            new JsonMockResponse(['access_token' => 'token']), new JsonMockResponse(['id' => 'p1', 'login' => 'ivan']),
            new JsonMockResponse(['access_token' => 'token']), new JsonMockResponse(['id' => 'p2']),
        ];

        $this->returned(['code' => 'c1']);
        self::assertSame('core.login.connected', $this->flashed('success', '/')->getMessage());
        self::assertSame('p1', $this->connection($user->getId())?->getProfile());

        $this->em->remove($this->connection($user->getId()) ?? self::fail('the service is not connected'));
        $this->em->flush();
        $this->returned(['code' => 'c1']);
        self::assertSame('core.login.taken', $this->flashed('error', '/')->getMessage());
    }

    public function testModuleListenerTakesTheProfileInsteadOfTheEntry(): void
    {
        $this->answers = [new JsonMockResponse(['access_token' => 'token']), new JsonMockResponse(['id' => CatchListener::PROFILE, 'login' => 'ivan'])];

        $this->returned(['code' => 'c1']);

        self::assertSame('latch '.CatchListener::PROFILE, $this->client->getResponse()->getContent());
        self::assertNull(self::service(ConnectedServiceRepository::class)->findOneBy(['service' => 'latch']));
        $this->client->request('GET', '/fixture/account');
        self::assertResponseRedirects('/login');
    }

    /** @param array<string, string> $query */
    private function returned(array $query): void
    {
        $this->client->request('GET', '/login/latch/callback', $query + ['state' => $this->authorize('latch', LatchService::AUTHORIZE)['state']]);
    }

    /** @phpstan-impure */
    private function connection(?int $user): ?ConnectedService
    {
        return self::service(ConnectedServiceRepository::class)->findOneBy(['user' => $user, 'service' => 'latch']);
    }
}
