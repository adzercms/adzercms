<?php

declare(strict_types=1);

namespace AdzerCMS\Admin\Tests;

use AdzerCMS\Admin\ModuleSources;
use AdzerCMS\Core\Module\Opotopo;
use AdzerCMS\Core\Setting\Brand;
use AdzerCMS\Core\Tests\Accounts;
use AdzerCMS\Core\Tests\Browser;
use AdzerCMS\Core\Tests\SchemaTestCase;
use AdzerCMS\Core\Tests\ServiceLogin;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Translation\TranslatableMessage;

use function Symfony\Component\Translation\t;

final class OpotopoConnectionTest extends SchemaTestCase
{
    use Accounts;
    use Browser;
    use ServiceLogin;

    private const SOURCES = '/admin/settings/'.ModuleSources::PAGE->value;
    private const CLIENT = 'http://localhost/opotopo/client';
    private const CALLBACK = 'http://localhost/admin/opotopo/callback';
    private const AUTHORIZE = Opotopo::API.'/oauth2/authorize';
    private const REFRESH = 'refresh-1';

    protected function setUp(): void
    {
        parent::setUp();
        $this->answerService();
        $this->admin();
    }

    #[Group('ACMS-CORE-1171')]
    public function testClientDocumentDescribesPublicClientOfTheInstanceToAnyone(): void
    {
        $this->client->restart();
        $this->client->request('GET', self::CLIENT);

        self::assertResponseIsSuccessful();
        self::assertSame([
            'client_id' => self::CLIENT,
            'client_name' => self::service(Brand::class)->title(),
            'redirect_uris' => [self::CALLBACK],
            'grant_types' => ['authorization_code', 'refresh_token'],
            'response_types' => ['code'],
            'token_endpoint_auth_method' => 'none',
        ], json_decode((string) $this->client->getResponse()->getContent(), true));
    }

    #[Group('ACMS-US-0004')]
    #[Group('ACMS-CORE-1170')]
    #[Group('ACMS-CORE-1172')]
    #[DataProvider('revocations')]
    public function testOperatorConnectsOpotopoAccountByRowOfSourcesAndDisconnectsItDroppingTokens(MockResponse $revocation, string $kind, TranslatableMessage $outcome): void
    {
        $query = $this->connectButton();
        self::assertSame(
            ['response_type' => 'code', 'client_id' => self::CLIENT, 'redirect_uri' => self::CALLBACK, 'code_challenge_method' => 'S256', 'resource' => Opotopo::RESOURCE],
            array_diff_key($query, array_flip(['state', 'code_challenge'])),
        );

        $this->answers = [self::metadata(), new JsonMockResponse(['access_token' => 'access-1', 'refresh_token' => self::REFRESH, 'expires_in' => 300, 'token_type' => 'Bearer'])];
        $this->client->request('GET', '/admin/opotopo/callback', ['code' => 'c1', 'state' => $query['state'], 'iss' => Opotopo::API]);
        self::assertEquals(t('core.login.connected', ['service' => t('core.opotopo.name')]), $this->flashed('success', self::SOURCES));
        $form = $this->sentForm(2);
        self::assertSame(self::challenge($form['code_verifier']), $query['code_challenge']);
        self::assertSame(
            ['grant_type' => 'authorization_code', 'code' => 'c1', 'redirect_uri' => self::CALLBACK, 'client_id' => self::CLIENT, 'resource' => Opotopo::RESOURCE],
            array_diff_key($form, ['code_verifier' => true]),
        );

        $sources = $this->arrive(self::SOURCES)->filter('#sources');
        self::assertSame(self::phrase('admin.setting.set'), trim(self::cell($sources, Opotopo::API, self::phrase('admin.sources.access'))->text()));
        $disconnect = self::buttons($sources);
        self::assertSame([self::phrase('core.action.disconnect')], $disconnect->each(static fn (Crawler $button): string => trim($button->text())));
        self::assertSame(self::phrase('admin.opotopo.disconnect_confirm'), $disconnect->attr('data-confirm'));

        $this->answers = [self::metadata(), $revocation];
        $this->client->submit($disconnect->form());
        self::assertEquals($outcome, $this->flashed($kind, self::SOURCES));
        self::assertSame(['token' => self::REFRESH, 'token_type_hint' => 'refresh_token', 'client_id' => self::CLIENT], $this->sentForm(4));
        self::assertFalse(self::service(Opotopo::class)->connected());
        self::assertSame([self::phrase('core.action.connect')], self::buttons($this->arrive(self::SOURCES))->each(static fn (Crawler $button): string => trim($button->text())));
    }

    /** @return iterable<string, array{MockResponse, string, TranslatableMessage}> */
    public static function revocations(): iterable
    {
        yield 'revoked' => [new MockResponse(''), 'success', t('admin.opotopo.disconnected')];
        yield 'refused revocation drops tokens too' => [new MockResponse('', ['http_code' => 503]), 'error', t('core.login.declined', ['service' => t('core.opotopo.name'), 'error' => '503'])];
    }

    /** @param array<string, string> $answer */
    #[Group('ACMS-CORE-1170')]
    #[DataProvider('returns')]
    public function testReturnOfForeignIssuerOrStateOrRefusedConsentLeavesOpotopoDisconnectedWithoutTokenRequest(array $answer, TranslatableMessage $refusal): void
    {
        $query = $this->connectButton();

        $this->client->request('GET', '/admin/opotopo/callback', $answer + ['code' => 'c1', 'state' => $query['state'], 'iss' => Opotopo::API]);
        self::assertEquals($refusal, $this->flashed('error', self::SOURCES));
        self::assertCount(1, $this->sent);
        self::assertFalse(self::service(Opotopo::class)->connected());
    }

    /** @return iterable<string, array{array<string, string>, TranslatableMessage}> */
    public static function returns(): iterable
    {
        $service = ['service' => t('core.opotopo.name')];
        yield 'foreign issuer' => [['iss' => 'https://opotopo.example.test'], t('core.login.alien', $service)];
        yield 'foreign state' => [['state' => 'foreign'], t('core.login.expired')];
        yield 'refused consent' => [['error' => 'access_denied', 'error_description' => 'denied'], t('core.login.declined', $service + ['error' => 'denied'])];
    }

    /** @return array<string, string> */
    private function connectButton(): array
    {
        $connect = self::buttons($this->arrive(self::SOURCES));
        self::assertSame([self::phrase('core.action.connect')], $connect->each(static fn (Crawler $button): string => trim($button->text())));
        $this->answers = [self::metadata()];
        $this->client->submit($connect->form());

        return $this->authorized(self::AUTHORIZE);
    }

    private static function buttons(Crawler $page): Crawler
    {
        return $page->filter('#sources tbody tr')->reduce(static fn (Crawler $row): bool => str_contains($row->filter('td')->first()->text(), Opotopo::API))->filter('td.row-actions button');
    }

    private static function metadata(): JsonMockResponse
    {
        return new JsonMockResponse([
            'issuer' => Opotopo::API,
            'authorization_endpoint' => self::AUTHORIZE,
            'token_endpoint' => Opotopo::API.'/oauth2/token',
            'revocation_endpoint' => Opotopo::API.'/oauth2/revoke',
        ]);
    }
}
